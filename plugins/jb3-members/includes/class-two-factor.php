<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verificación en dos pasos (2FA) para ADMINISTRADORES — y solo para ellos
 * (pedido 2026-10-20). Los miembros siguen entrando solo con el magic link.
 * Documentación completa (cómo funciona, recuperación, qué tocar para
 * cambiarlo): README.md del plugin, sección "Verificación en dos pasos".
 *
 * Segundo factor: código de 6 dígitos de una app de autenticación (Google
 * Authenticator, Microsoft Authenticator, Authy, 1Password…) — estándar
 * TOTP (RFC 6238: HMAC-SHA1, 30 segundos, 6 dígitos), implementado acá sin
 * dependencias (validado con los vectores de prueba del RFC). No sirve un
 * plugin tipo "WP 2FA": esos se enganchan al login con contraseña de
 * wp-login.php, y acá los admins entran por magic link (JB3_Magic_Link), que
 * esos plugins nunca ven.
 *
 * FLUJO
 *   1. El admin abre su magic link (o, con la llave de emergencia del login
 *      con contraseña activa, se loguea con contraseña). En vez de iniciar
 *      sesión, iniciar() guarda un "login pendiente" (transient de 10 min,
 *      referenciado por una cookie HttpOnly — no viaja en la URL) y lo manda
 *      a la página de login con ?jb3_2fa=1.
 *   2. Ahí el shortcode [jb3_login] muestra render_paso():
 *      - sin app configurada: QR para escanear (se arma en el navegador,
 *        public/js/qrcode.min.js — la clave nunca pasa por un servicio
 *        externo) + confirmar con un código;
 *      - con app configurada: pedir el código.
 *   3. procesar() (en init, antes de cualquier salida) valida y recién ahí
 *      inicia la sesión (JB3_Magic_Link::completar_login()), marcándola como
 *      verificada (attach_session_information → 'jb3_2fa').
 *   4. exigir_en_sesiones(): en CADA pedido, una sesión de admin sin esa
 *      marca se cierra (AJAX/API → 401) — cubre las sesiones abiertas antes
 *      de activar esto, y cualquier otro camino de login que no pase por acá.
 *
 * RECUPERACIÓN (celular robado/perdido, o el código borrado de la app). SIN
 * códigos de respaldo, a propósito (decisión 2026-10-20: "nadie los guarda").
 * Tampoco hay recuperación por email: el primer factor YA es el email (magic
 * link), así que "perdí el celular, mandame un link" dejaría a quien robe el
 * email entrar con un solo factor. Siempre se REINICIA la verificación de ese
 * admin (se borra su clave) y en el próximo ingreso configura la app de cero:
 *   a) Otro admin: Usuarios > (el admin) > "Reiniciar verificación en dos
 *      pasos" — reiniciar_de_otro().
 *   b) Desde el hosting (factor distinto del email), solo para ese admin, en
 *      wp-config.php:  define( 'JB3_REINICIAR_2FA', 'su@email.com' );
 *      Actúa UNA vez por cada valor (aplicar_reinicio_wpconfig()); después se
 *      saca la línea.
 *   c) El propio admin, logueado: Perfil > "Volver a configurar" (cambio de
 *      celular con el viejo todavía a mano).
 *   Las tres cierran todas las sesiones abiertas de ese admin (ej. la del
 *   celular robado).
 * Prevención: activar la copia en la nube de Google Authenticator (iniciar
 * sesión con la cuenta de Google en la app) — un celular nuevo recupera los
 * códigos solo.
 *
 * PROTECCIONES: 5 intentos por login pendiente (después, pedir un enlace
 * nuevo); un código ya usado no se puede volver a usar (último paso de
 * tiempo guardado); la clave secreta se guarda cifrada (AES-256-GCM con una
 * llave derivada de las SALT de wp-config.php — un volcado de la base sola
 * no alcanza para generar códigos. OJO: si se cambian las SALT, los admins
 * tienen que volver a configurar la app).
 *
 * APAGADO TOTAL (solo si esta función misma fallara — apaga la verificación
 * para TODOS los admins): en wp-config.php, y sacarlo apenas se resuelva:
 *     define( 'JB3_DESACTIVAR_2FA', true );
 */
class JB3_Two_Factor {

	const SECRET_META    = 'jb3_2fa_secreto';   // Clave TOTP cifrada.
	const LAST_STEP_META = 'jb3_2fa_ultimo';    // Último paso de tiempo aceptado (anti-reuso).
	const RESET_META     = 'jb3_2fa_reinicio';  // Último valor de JB3_REINICIAR_2FA ya aplicado a este admin.
	const PENDING_COOKIE = 'jb3_2fa_pendiente';
	const PENDING_TTL    = 10 * MINUTE_IN_SECONDS;
	const MAX_INTENTOS   = 5;
	const PERIODO        = 30;
	const DIGITOS        = 6;

	public static function init() {
		add_filter( 'attach_session_information', [ __CLASS__, 'marcar_sesion' ], 10, 2 );
		add_action( 'init', [ __CLASS__, 'exigir_en_sesiones' ], 1 );
		add_action( 'init', [ __CLASS__, 'procesar' ], 6 );
		add_filter( 'authenticate', [ __CLASS__, 'interceptar_login_contrasena' ], 200, 3 );
		add_action( 'show_user_profile', [ __CLASS__, 'render_perfil' ] );
		add_action( 'edit_user_profile', [ __CLASS__, 'render_perfil' ] );
		add_action( 'admin_init', [ __CLASS__, 'procesar_reinicio' ] );
		add_action( 'admin_notices', [ __CLASS__, 'aviso_reinicio' ] );
	}

	public static function activo(): bool {
		return ! ( defined( 'JB3_DESACTIVAR_2FA' ) && JB3_DESACTIVAR_2FA );
	}

	public static function requerido( int $user_id ): bool {
		return self::activo() && user_can( $user_id, 'manage_options' );
	}

	// ─── Paso 1: login pendiente ──────────────────────────────────────────────

	/** En vez de iniciar sesión: guarda el login pendiente y manda al paso del código. */
	public static function iniciar( int $user_id, string $redirect ): void {
		self::aplicar_reinicio_wpconfig( $user_id );

		$token = wp_generate_password( 43, false );
		set_transient( self::clave_pendiente( $token ), [
			'uid'      => $user_id,
			'redirect' => $redirect,
			'intentos' => 0,
		], self::PENDING_TTL );
		self::set_cookie( $token, time() + self::PENDING_TTL );

		wp_safe_redirect( add_query_arg( 'jb3_2fa', '1', self::url_login() ) );
		exit;
	}

	/** Login con contraseña (solo con la llave de emergencia JB3_PERMITIR_LOGIN_CONTRASENA): también pide el código. */
	public static function interceptar_login_contrasena( $user, $username, $password ) {
		if ( ! ( $user instanceof WP_User ) || ! self::requerido( $user->ID ) ) {
			return $user;
		}
		$redirect = wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ?? '' ), admin_url() );
		self::iniciar( $user->ID, $redirect );
		return $user; // No se llega: iniciar() redirige.
	}

	// ─── Paso 2: pantalla del código (shortcode [jb3_login] con ?jb3_2fa=1) ───

	public static function render_paso(): string {
		$p = self::pendiente();
		if ( ! $p ) {
			return '<div class="jb3-notice jb3-error">' . esc_html__( 'La verificación venció o ya no es válida. Pedí un enlace de acceso nuevo.', 'jb3-members' ) . '</div>';
		}

		$configurando = ! get_user_meta( $p['uid'], self::SECRET_META, true );
		if ( $configurando && empty( $p['secreto_nuevo'] ) ) {
			// Clave nueva para esta configuración — se guarda en el usuario
			// recién cuando confirma un código válido.
			$p['secreto_nuevo'] = self::generar_secreto();
			self::guardar_pendiente( $p );
		}

		ob_start();
		?>
		<div class="jb3-2fa">
			<?php if ( isset( $_GET['jb3_2fa_error'] ) ) : ?>
				<div class="jb3-notice jb3-error" role="alert"><?php esc_html_e( 'El código no es correcto. Probá de nuevo con el que muestra la app ahora.', 'jb3-members' ); ?></div>
			<?php endif; ?>

			<?php if ( $configurando ) : ?>
				<?php $uri = self::uri_otpauth( $p['uid'], $p['secreto_nuevo'] ); ?>
				<h2 class="jb3-2fa__titulo"><?php esc_html_e( 'Configurá la verificación en dos pasos', 'jb3-members' ); ?></h2>
				<ol class="jb3-2fa__pasos">
					<li><?php esc_html_e( 'Instalá en tu celular una app de autenticación: Google Authenticator, Microsoft Authenticator o similar. Recomendado: en Google Authenticator, iniciá sesión con tu cuenta de Google para que los códigos queden respaldados si cambiás de celular.', 'jb3-members' ); ?></li>
					<li><?php esc_html_e( 'En la app, agregá una cuenta escaneando este código QR:', 'jb3-members' ); ?></li>
				</ol>
				<div class="jb3-2fa__qr" data-jb3-2fa-qr="<?php echo esc_attr( $uri ); ?>"></div>
				<p class="jb3-2fa__manual"><?php esc_html_e( '¿No podés escanear? Cargá esta clave a mano:', 'jb3-members' ); ?> <code><?php echo esc_html( trim( chunk_split( $p['secreto_nuevo'], 4, ' ' ) ) ); ?></code></p>
				<p><?php esc_html_e( 'Después, escribí el código de 6 dígitos que muestra la app:', 'jb3-members' ); ?></p>
			<?php else : ?>
				<h2 class="jb3-2fa__titulo"><?php esc_html_e( 'Verificación en dos pasos', 'jb3-members' ); ?></h2>
				<p><?php esc_html_e( 'Escribí el código de 6 dígitos que muestra tu app de autenticación.', 'jb3-members' ); ?></p>
			<?php endif; ?>

			<form method="post" class="jb3-form" autocomplete="off">
				<?php wp_nonce_field( 'jb3_2fa', 'jb3_2fa_nonce' ); ?>
				<div class="jb3-field">
					<label for="jb3_2fa_codigo"><?php esc_html_e( 'Código', 'jb3-members' ); ?></label>
					<input type="text" id="jb3_2fa_codigo" name="jb3_2fa_codigo" required autofocus
						inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456">
				</div>
				<button type="submit" class="jb3-btn"><?php esc_html_e( 'Verificar', 'jb3-members' ); ?></button>
			</form>

			<?php if ( ! $configurando ) : ?>
				<p class="jb3-2fa__ayuda"><?php esc_html_e( '¿Cambiaste de celular o borraste el código de la app? Pedile a otro administrador que reinicie tu verificación en dos pasos (Usuarios > tu usuario), o a quien administra el hosting.', 'jb3-members' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		if ( $configurando ) {
			wp_enqueue_script( 'jb3-qrcode', JB3_URL . 'public/js/qrcode.min.js', [], jb3_asset_ver( 'public/js/qrcode.min.js' ), true );
			wp_add_inline_script( 'jb3-qrcode', "
				document.querySelectorAll( '[data-jb3-2fa-qr]' ).forEach( function ( el ) {
					var qr = qrcode( 0, 'M' );
					qr.addData( el.getAttribute( 'data-jb3-2fa-qr' ) );
					qr.make();
					el.innerHTML = qr.createSvgTag( { cellSize: 5, margin: 4, scalable: true } );
				} );
			" );
		}
		return ob_get_clean();
	}

	// ─── Paso 3: validar el código (init, antes de cualquier salida) ─────────

	public static function procesar(): void {
		if ( ! empty( $_GET['jb3_2fa'] ) ) {
			// Pantalla atada a una cookie propia: nunca a la caché de página.
			nocache_headers();
			do_action( 'litespeed_control_set_nocache', 'verificación en dos pasos' );
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['jb3_2fa_codigo'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jb3_2fa_nonce'] ?? '' ) ), 'jb3_2fa' ) ) {
			return;
		}

		$p = self::pendiente();
		if ( ! $p ) {
			self::ir_al_paso( false );
		}
		$uid = (int) $p['uid'];

		$p['intentos']++;
		if ( $p['intentos'] > self::MAX_INTENTOS ) {
			self::descartar_pendiente();
			wp_safe_redirect( add_query_arg( 'jb3_error', '2fa_bloqueado', self::url_login() ) );
			exit;
		}
		self::guardar_pendiente( $p );

		$codigo = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['jb3_2fa_codigo'] ) ) );

		// Configurando: el código tiene que salir de la clave nueva. Recién
		// ahí se guarda la clave en el usuario.
		if ( ! empty( $p['secreto_nuevo'] ) && ! get_user_meta( $uid, self::SECRET_META, true ) ) {
			$paso = self::verificar_totp( $p['secreto_nuevo'], $codigo, 0 );
			if ( ! $paso ) {
				self::ir_al_paso( true );
			}
			update_user_meta( $uid, self::SECRET_META, self::cifrar( $p['secreto_nuevo'] ) );
			update_user_meta( $uid, self::LAST_STEP_META, $paso );
			self::finalizar( $p );
		}

		// Ya configurado: código de la app.
		$secreto = self::descifrar( (string) get_user_meta( $uid, self::SECRET_META, true ) );
		$paso    = $secreto ? self::verificar_totp( $secreto, $codigo, (int) get_user_meta( $uid, self::LAST_STEP_META, true ) ) : 0;
		if ( ! $paso ) {
			self::ir_al_paso( true );
		}
		update_user_meta( $uid, self::LAST_STEP_META, $paso );
		self::finalizar( $p );
	}

	private static function finalizar( array $p ): void {
		self::descartar_pendiente();
		$user = get_userdata( (int) $p['uid'] );
		if ( ! $user ) {
			wp_safe_redirect( self::url_login() );
			exit;
		}
		$GLOBALS['jb3_2fa_verificado'] = true; // marcar_sesion() lo lee al crear la sesión.
		JB3_Magic_Link::completar_login( $user, (string) $p['redirect'] );
	}

	private static function ir_al_paso( bool $con_error ): void {
		$url = add_query_arg( 'jb3_2fa', '1', self::url_login() );
		wp_safe_redirect( $con_error ? add_query_arg( 'jb3_2fa_error', '1', $url ) : $url );
		exit;
	}

	// ─── Sesiones: solo las verificadas sobreviven ───────────────────────────

	/** Marca la sesión que se está creando como verificada (ver finalizar()). */
	public static function marcar_sesion( array $info, $user_id ): array {
		if ( ! empty( $GLOBALS['jb3_2fa_verificado'] ) ) {
			$info['jb3_2fa'] = true;
		}
		return $info;
	}

	public static function sesion_actual_verificada(): bool {
		$sesion = WP_Session_Tokens::get_instance( get_current_user_id() )->get( wp_get_session_token() );
		return ! empty( $sesion['jb3_2fa'] );
	}

	/** Admin logueado con una sesión sin verificar (ej. abierta antes de activar esto): se cierra. */
	public static function exigir_en_sesiones(): void {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ! is_user_logged_in() || ! self::requerido( get_current_user_id() ) ) {
			return;
		}
		if ( self::sesion_actual_verificada() ) {
			return;
		}

		WP_Session_Tokens::get_instance( get_current_user_id() )->destroy( wp_get_session_token() );
		wp_clear_auth_cookie();
		wp_set_current_user( 0 );

		// En init todavía no está definido REST_REQUEST: la API se reconoce por la URL.
		$es_api = str_contains( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/wp-json/' ) || isset( $_GET['rest_route'] );
		if ( wp_doing_ajax() || $es_api ) {
			wp_die( esc_html__( 'Tu sesión necesita la verificación en dos pasos. Volvé a ingresar.', 'jb3-members' ), '', [ 'response' => 401 ] );
		}
		wp_safe_redirect( add_query_arg( 'jb3_error', '2fa_requerido', self::url_login() ) );
		exit;
	}

	// ─── Reinicio (recuperación sin códigos de respaldo) ─────────────────────

	/** Borra la clave del admin: en su próximo ingreso configura la app de cero (QR nuevo). */
	public static function reiniciar( int $user_id ): void {
		delete_user_meta( $user_id, self::SECRET_META );
		delete_user_meta( $user_id, self::LAST_STEP_META );
	}

	/**
	 * Reinicio desde el hosting: define( 'JB3_REINICIAR_2FA', 'admin@email' )
	 * en wp-config.php. Se aplica al ingresar ese admin, UNA vez por cada
	 * valor (queda anotado en RESET_META): si la línea queda olvidada, no lo
	 * vuelve a reiniciar en cada ingreso. Cierra todas sus sesiones abiertas.
	 */
	private static function aplicar_reinicio_wpconfig( int $user_id ): void {
		if ( ! defined( 'JB3_REINICIAR_2FA' ) || ! is_string( JB3_REINICIAR_2FA ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || 0 !== strcasecmp( trim( JB3_REINICIAR_2FA ), $user->user_email ) ) {
			return;
		}
		$marca = hash( 'sha256', strtolower( trim( JB3_REINICIAR_2FA ) ) . '|' . wp_salt( 'auth' ) );
		if ( get_user_meta( $user_id, self::RESET_META, true ) === $marca ) {
			return;
		}
		self::reiniciar( $user_id );
		WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		update_user_meta( $user_id, self::RESET_META, $marca );
	}

	// ─── Perfil: estado + reiniciar (el propio admin, u otro admin) ──────────

	public static function render_perfil( WP_User $user ): void {
		if ( ! self::requerido( $user->ID ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$propio      = get_current_user_id() === $user->ID;
		$configurado = (bool) get_user_meta( $user->ID, self::SECRET_META, true );
		$url         = wp_nonce_url( add_query_arg( [ 'jb3_2fa_reiniciar' => $user->ID ], self::url_perfil( $user->ID ) ), 'jb3_2fa_reiniciar_' . $user->ID );
		$confirmar   = $propio
			? __( 'La próxima vez que ingreses vas a tener que escanear un QR nuevo con la app, y se cierran tus otras sesiones abiertas. ¿Continuar?', 'jb3-members' )
			: __( 'Este administrador va a tener que escanear un QR nuevo la próxima vez que ingrese, y se cierran todas sus sesiones abiertas. ¿Continuar?', 'jb3-members' );
		?>
		<h2><?php esc_html_e( 'Verificación en dos pasos', 'jb3-members' ); ?></h2>
		<table class="form-table" role="presentation"><tr>
			<th><?php esc_html_e( 'Estado', 'jb3-members' ); ?></th>
			<td>
				<?php if ( $configurado ) : ?>
					<p><?php esc_html_e( 'Activada con una app de autenticación.', 'jb3-members' ); ?></p>
					<p>
						<a class="button" href="<?php echo esc_url( $url ); ?>" onclick="return confirm('<?php echo esc_js( $confirmar ); ?>');">
							<?php echo $propio ? esc_html__( 'Volver a configurar (celular nuevo)', 'jb3-members' ) : esc_html__( 'Reiniciar verificación en dos pasos', 'jb3-members' ); ?>
						</a>
					</p>
					<?php if ( ! $propio ) : ?>
						<p class="description"><?php esc_html_e( 'Para cuando este administrador perdió o cambió el celular, o borró el código de la app.', 'jb3-members' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Sin configurar: se configura en el próximo ingreso.', 'jb3-members' ); ?></p>
				<?php endif; ?>
			</td>
		</tr></table>
		<?php
	}

	public static function procesar_reinicio(): void {
		if ( empty( $_GET['jb3_2fa_reiniciar'] ) ) {
			return;
		}
		$user_id = absint( $_GET['jb3_2fa_reiniciar'] );
		check_admin_referer( 'jb3_2fa_reiniciar_' . $user_id );
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) || ! self::requerido( $user_id ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'jb3-members' ), '', [ 'response' => 403 ] );
		}

		self::reiniciar( $user_id );
		if ( get_current_user_id() === $user_id ) {
			wp_destroy_other_sessions(); // El propio admin: sigue en esta sesión, se cierran las demás.
		} else {
			WP_Session_Tokens::get_instance( $user_id )->destroy_all(); // Otro admin: ej. la sesión del celular robado.
		}
		// Vuelve al perfil de ese usuario con un aviso (user-edit.php necesita
		// user_id — sin él, WordPress muestra "ID de usuario no válido").
		wp_safe_redirect( add_query_arg( 'jb3_2fa_reiniciado', '1', self::url_perfil( $user_id ) ) );
		exit;
	}

	/** Aviso después de reiniciar (ver procesar_reinicio()). */
	public static function aviso_reinicio(): void {
		if ( empty( $_GET['jb3_2fa_reiniciado'] ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Verificación en dos pasos reiniciada: en el próximo ingreso se configura la app de nuevo con un QR. Se cerraron las sesiones abiertas que correspondía.', 'jb3-members' ) . '</p></div>';
	}

	/** Perfil del usuario en el admin: el propio (profile.php) o el de otro (user-edit.php?user_id=). */
	private static function url_perfil( int $user_id ): string {
		return get_current_user_id() === $user_id
			? admin_url( 'profile.php' )
			: add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) );
	}

	// ─── TOTP (RFC 6238) ──────────────────────────────────────────────────────

	private static function generar_secreto(): string {
		return self::base32_encode( random_bytes( 20 ) ); // 160 bits, lo recomendado por el RFC.
	}

	/** Paso de tiempo del código si es válido (ventana de ±30 s) y posterior a $ultimo_paso; 0 si no. */
	private static function verificar_totp( string $secreto, string $codigo, int $ultimo_paso ): int {
		if ( ! preg_match( '/^\d{' . self::DIGITOS . '}$/', $codigo ) ) {
			return 0;
		}
		$clave = self::base32_decode( $secreto );
		$ahora = (int) floor( time() / self::PERIODO );
		foreach ( [ 0, -1, 1 ] as $desvio ) {
			$paso = $ahora + $desvio;
			if ( $paso > $ultimo_paso && hash_equals( self::hotp( $clave, $paso ), $codigo ) ) {
				return $paso;
			}
		}
		return 0;
	}

	private static function hotp( string $clave, int $contador ): string {
		$hash   = hash_hmac( 'sha1', pack( 'J', $contador ), $clave, true );
		$offset = ord( $hash[19] ) & 0x0f;
		$valor  = ( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) | ( ord( $hash[ $offset + 1 ] ) << 16 ) | ( ord( $hash[ $offset + 2 ] ) << 8 ) | ord( $hash[ $offset + 3 ] );
		return str_pad( (string) ( $valor % ( 10 ** self::DIGITOS ) ), self::DIGITOS, '0', STR_PAD_LEFT );
	}

	/** Contenido del QR: el formato que entienden todas las apps de autenticación. */
	private static function uri_otpauth( int $user_id, string $secreto ): string {
		$user   = get_userdata( $user_id );
		$emisor = 'Conectados';
		return 'otpauth://totp/' . rawurlencode( $emisor . ':' . $user->user_email )
			. '?secret=' . $secreto . '&issuer=' . rawurlencode( $emisor ) . '&digits=' . self::DIGITOS . '&period=' . self::PERIODO;
	}

	private static function base32_encode( string $bytes ): string {
		$alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$bits     = '';
		foreach ( str_split( $bytes ) as $c ) {
			$bits .= str_pad( decbin( ord( $c ) ), 8, '0', STR_PAD_LEFT );
		}
		$salida = '';
		foreach ( str_split( $bits, 5 ) as $grupo ) {
			$salida .= $alfabeto[ bindec( str_pad( $grupo, 5, '0' ) ) ];
		}
		return $salida;
	}

	private static function base32_decode( string $texto ): string {
		$alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$bits     = '';
		foreach ( str_split( strtoupper( preg_replace( '/[^A-Za-z2-7]/', '', $texto ) ) ) as $c ) {
			$bits .= str_pad( decbin( strpos( $alfabeto, $c ) ), 5, '0', STR_PAD_LEFT );
		}
		$salida = '';
		foreach ( str_split( $bits, 8 ) as $byte ) {
			if ( 8 === strlen( $byte ) ) {
				$salida .= chr( bindec( $byte ) );
			}
		}
		return $salida;
	}

	// ─── Clave secreta cifrada ────────────────────────────────────────────────

	private static function llave(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . '|jb3_2fa', true );
	}

	private static function cifrar( string $texto ): string {
		$iv      = random_bytes( 12 );
		$tag     = '';
		$cifrado = openssl_encrypt( $texto, 'aes-256-gcm', self::llave(), OPENSSL_RAW_DATA, $iv, $tag );
		return base64_encode( $iv . $tag . $cifrado );
	}

	private static function descifrar( string $guardado ): string {
		$datos = base64_decode( $guardado, true );
		if ( ! $datos || strlen( $datos ) < 29 ) {
			return '';
		}
		$texto = openssl_decrypt( substr( $datos, 28 ), 'aes-256-gcm', self::llave(), OPENSSL_RAW_DATA, substr( $datos, 0, 12 ), substr( $datos, 12, 16 ) );
		return false === $texto ? '' : $texto;
	}

	// ─── Login pendiente (transient + cookie HttpOnly) ───────────────────────

	private static function clave_pendiente( string $token ): string {
		return 'jb3_2fa_p_' . hash( 'sha256', $token );
	}

	private static function token_cookie(): string {
		return sanitize_text_field( wp_unslash( $_COOKIE[ self::PENDING_COOKIE ] ?? '' ) );
	}

	private static function pendiente(): ?array {
		$token = self::token_cookie();
		$p     = '' !== $token ? get_transient( self::clave_pendiente( $token ) ) : false;
		return is_array( $p ) ? $p : null;
	}

	private static function guardar_pendiente( array $p ): void {
		$token = self::token_cookie();
		if ( '' !== $token ) {
			set_transient( self::clave_pendiente( $token ), $p, self::PENDING_TTL );
		}
	}

	private static function descartar_pendiente(): void {
		$token = self::token_cookie();
		if ( '' !== $token ) {
			delete_transient( self::clave_pendiente( $token ) );
		}
		self::set_cookie( '', time() - HOUR_IN_SECONDS );
	}

	private static function set_cookie( string $valor, int $vence ): void {
		setcookie( self::PENDING_COOKIE, $valor, [
			'expires'  => $vence,
			'path'     => COOKIEPATH ?: '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		] );
	}

	private static function url_login(): string {
		$page_id = (int) get_option( 'jb3_login_page_id' );
		return ( $page_id ? get_permalink( $page_id ) : '' ) ?: home_url( '/login/' );
	}
}
