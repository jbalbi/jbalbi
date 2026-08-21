<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tracking de Op. Comerciales: registra en Google Sheets quién ve cada
 * oportunidad ("oportunidades vistas") y quién descarga el PDF
 * ("oportunidades descargadas"), y avisa por email a los representantes
 * del país del usuario cuando descarga.
 *
 * "Vistas" es automático (template_redirect, sin cambios en el theme).
 * "Descargas" necesita que el botón del theme apunte a download_url()
 * en vez del PDF directo — es la única integración que pide el theme,
 * porque un <a href="pdf.pdf" download> nunca pasa por PHP: el navegador
 * baja el archivo directo, no hay forma de loguear el click sin routearlo
 * por acá primero.
 *
 * "Posible bot": heurística, NO una detección certera — escáneres de
 * seguridad de correo (Microsoft Safe Links, Mimecast, Proofpoint,
 * Barracuda, etc.) abren los links de un mail automáticamente para
 * revisarlos antes de que el destinatario real los vea, y eso puede
 * generar una "vista" (o hasta una "descarga") que no es de una persona.
 * No hay forma 100% confiable de filtrar esto del lado del servidor — acá
 * solo se deja el User-Agent crudo + una marca Sí/No contra una lista de
 * firmas conocidas, para que se pueda filtrar/revisar a mano en la
 * planilla. Importante para el paso de Mailchimp (magic link de un solo
 * uso): si un escáner "gasta" el token antes que el usuario real, el
 * usuario se queda sin poder entrar — eso se resuelve en ese paso, esto
 * acá es solo visibilidad sobre vistas/descargas.
 */
class JB3_Op_Comerciales_Tracking {

	const DOWNLOAD_PARAM = 'jb3_descargar_op_comercial';
	const HOJA_VISTAS     = 'oportunidades vistas';
	const HOJA_DESCARGAS  = 'oportunidades descargadas';

	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'track_view' ] );
		add_action( 'init', [ __CLASS__, 'handle_download' ] );
	}

	/** URL que hay que usar en el botón "Descargar PDF" del theme, en vez del PDF directo. */
	public static function download_url( int $post_id ): string {
		return add_query_arg(
			[
				self::DOWNLOAD_PARAM => $post_id,
				'_wpnonce'           => wp_create_nonce( self::DOWNLOAD_PARAM . '_' . $post_id ),
			],
			home_url( '/' )
		);
	}

	// ─── Vistas ───────────────────────────────────────────────────────────────

	public static function track_view() {
		if ( ! is_singular( 'op_comerciales' ) || ! is_user_logged_in() ) {
			return;
		}
		self::log_evento( self::HOJA_VISTAS, get_queried_object_id() );
	}

	// ─── Descargas ────────────────────────────────────────────────────────────

	public static function handle_download() {
		if ( empty( $_GET[ self::DOWNLOAD_PARAM ] ) ) {
			return;
		}

		$post_id = absint( $_GET[ self::DOWNLOAD_PARAM ] );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Tenés que iniciar sesión para descargar este archivo.', 'jb3-members' ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $post_id || ! wp_verify_nonce( $nonce, self::DOWNLOAD_PARAM . '_' . $post_id ) ) {
			wp_die( esc_html__( 'Enlace de descarga inválido o vencido — volvé a la página e intentá de nuevo.', 'jb3-members' ) );
		}

		if ( 'op_comerciales' !== get_post_type( $post_id ) || ! function_exists( 'get_field' ) ) {
			wp_die( esc_html__( 'Contenido no encontrado.', 'jb3-members' ) );
		}

		$pdf     = get_field( 'pdf', $post_id );
		$pdf_url = is_array( $pdf ) ? ( $pdf['url'] ?? '' ) : (string) $pdf;
		if ( ! $pdf_url ) {
			wp_die( esc_html__( 'Este contenido no tiene un PDF cargado.', 'jb3-members' ) );
		}

		// Antes se hacía wp_redirect() directo a $pdf_url — el navegador
		// terminaba mostrando/guardando esa URL real, sin ninguna
		// protección (cualquiera con esa URL podía bajar el archivo para
		// siempre, sin login). Sirviendo el archivo acá mismo, la URL que
		// ve/comparte el usuario sigue siendo siempre la de tracking (atada
		// a su sesión + nonce) — la ubicación real del archivo nunca viaja
		// al navegador.
		$upload_dir = wp_upload_dir();
		$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $pdf_url );
		if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
			wp_die( esc_html__( 'El archivo no se encontró en el servidor.', 'jb3-members' ) );
		}

		self::log_evento( self::HOJA_DESCARGAS, $post_id );
		self::avisar_representantes( $post_id );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $file_path );
		exit;
	}

	// ─── Internals ────────────────────────────────────────────────────────────

	private static function log_evento( string $hoja, int $post_id ): void {
		$sheet_id = get_option( 'jb3_gsheets_id' );
		if ( ! $sheet_id ) {
			return;
		}

		$user      = wp_get_current_user();
		$ip        = self::get_visitor_ip();
		$pais_ip   = function_exists( 'jb3_geo_visitor_country' ) ? strtoupper( jb3_geo_visitor_country() ) : '';
		$countries = function_exists( 'jb3_countries' ) ? jb3_countries() : [];
		$ua        = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Datos propios del usuario y del contenido primero; lo que es más
		// bien diagnóstico (país detectado, IP, User-Agent, posible bot) al
		// final, para no interponerse con lo que realmente importa a simple vista.
		JB3_Google_Sheets::append_row(
			$sheet_id,
			$hoja,
			[
				current_time( 'Y-m-d H:i:s' ),
				$user->display_name,
				$user->user_email,
				get_user_meta( $user->ID, 'jb3_company', true ),
				get_user_meta( $user->ID, 'jb3_phone', true ),
				$countries[ get_user_meta( $user->ID, 'jb3_country', true ) ] ?? get_user_meta( $user->ID, 'jb3_country', true ),
				get_the_title( $post_id ),
				get_permalink( $post_id ),
				$countries[ $pais_ip ] ?? $pais_ip,
				$ip,
				$ua,
				jb3_es_posible_bot( $ua ) ? 'Sí' : 'No',
			],
			[
				'Fecha y hora', 'Nombre', 'Email', 'Empresa', 'Teléfono',
				'País (perfil)', 'Op. Comercial', 'URL',
				'País (detectado por IP)', 'IP', 'User-Agent', 'Posible bot',
			]
		);
	}

	/** País del USUARIO que descarga, no del contenido — para que lo contacte el rep de su zona. */
	private static function avisar_representantes( int $post_id ): void {
		$user_id = get_current_user_id();
		$pais    = get_user_meta( $user_id, 'jb3_country', true );
		if ( ! $pais ) {
			return;
		}

		$reps = JB3_Representatives::get_notifiable_by_country( strtoupper( $pais ) );
		if ( ! $reps ) {
			return;
		}

		JB3_Email::send_op_comercial_descargado( $reps, $user_id, $post_id );
	}

	/**
	 * IP del visitante. geoip_detect2_get_client_ip() (del plugin
	 * geoip-detect, ya activo para el geo-targeting del sitio) resuelve
	 * proxies/CDN correctamente si está disponible; si no, REMOTE_ADDR
	 * directo.
	 */
	private static function get_visitor_ip(): string {
		if ( function_exists( 'geoip_detect2_get_client_ip' ) ) {
			return (string) geoip_detect2_get_client_ip();
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

}
