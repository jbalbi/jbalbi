<?php
defined( 'ABSPATH' ) || exit;

/**
 * reCAPTCHA v3 (invisible, por puntaje) — SOLO en el formulario de
 * registro (el más expuesto a bots automatizados que crean cuentas en
 * bucle; login/magic-link ya tiene su propio rate-limit por email+IP,
 * decisión explícita del cliente 2026-08-28 de no sumarlo ahí también).
 *
 * MUDANZA DE SERVIDOR: copiar el archivo de credenciales y agregar el
 * dominio nuevo a la clave en google.com/recaptcha/admin (ver
 * wp-content/MUDANZA-SERVIDOR.md).
 *
 * Credenciales: ABSPATH . 'jb3-recaptcha-cfg.php' (raíz de WordPress) — fuera de
 * wp-content (el repo git es wp-content para adentro), mismo criterio que
 * jb3-search-cfg.php (jb3-ai-search) y jb3-sc-stats.php
 * (JB3_Google_Sheets). Nunca se commitea.
 */
class JB3_Recaptcha {

	// Umbral de Google: 1.0 = casi seguro humano, 0.0 = casi seguro bot.
	// 0.5 es el punto medio que la propia documentación de Google sugiere
	// como default razonable — se puede subir si en la práctica sigue
	// entrando spam, o bajar si empieza a rechazar gente real.
	const MIN_SCORE = 0.5;

	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
	}

	private static function credentials(): array {
		$path = ABSPATH . 'jb3-recaptcha-cfg.php';
		if ( ! file_exists( $path ) ) {
			return [];
		}
		$data = include $path;
		return is_array( $data ) ? $data : [];
	}

	public static function site_key(): string {
		return (string) ( self::credentials()['site_key'] ?? '' );
	}

	private static function secret_key(): string {
		return (string) ( self::credentials()['secret_key'] ?? '' );
	}

	// ─── Asset loading ────────────────────────────────────────────────────────
	// Solo en la página con [jb3_register] — el script de Google no tiene
	// sentido cargarlo en ningún otro lado del sitio.
	public static function maybe_enqueue() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'jb3_register' ) ) {
			return;
		}

		$site_key = self::site_key();
		if ( '' === $site_key ) {
			return; // sin credenciales configuradas, no se carga nada (ver verify() más abajo).
		}

		// null de versión: es una URL externa (no un archivo propio con
		// filemtime), no tiene sentido pisarla con un ?ver= nuestro.
		wp_enqueue_script(
			'jb3-recaptcha-api',
			'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ),
			[],
			null,
			true
		);
		wp_enqueue_script(
			'jb3-recaptcha',
			JB3_URL . 'public/js/jb3-recaptcha.js',
			[ 'jb3-recaptcha-api' ],
			jb3_asset_ver( 'public/js/jb3-recaptcha.js' ),
			true
		);
		wp_localize_script( 'jb3-recaptcha', 'jb3Recaptcha', [ 'siteKey' => $site_key ] );
	}

	/**
	 * @param string $token El "g-recaptcha-response" que mandó el form.
	 * @return string '' si pasó la verificación (o si no hay credenciales
	 *                configuradas — un reCAPTCHA mal configurado no puede
	 *                tirar abajo el registro real, mismo criterio de
	 *                degradar sin romper que ya usa jb3-ai-search con
	 *                Gemini), mensaje de error listo para mostrar si no.
	 */
	public static function verify( string $token ): string {
		$secret = self::secret_key();
		if ( '' === $secret ) {
			return '';
		}
		if ( '' === $token ) {
			return __( 'No pudimos verificar que sos una persona real. Recargá la página e intentá de nuevo.', 'jb3-members' );
		}

		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
			'timeout' => 8,
			'body'    => [
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			],
		] );

		// Google inalcanzable/caído: no bloquea. Mismo criterio que arriba
		// — una caída de un servicio externo no puede impedir un registro
		// real de alguien.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['success'] ) || 'register' !== ( $data['action'] ?? '' ) ) {
			return __( 'No pudimos verificar tu solicitud. Probá de nuevo.', 'jb3-members' );
		}
		if ( (float) ( $data['score'] ?? 0 ) < self::MIN_SCORE ) {
			return __( 'Tu registro fue marcado como sospechoso. Si sos una persona real, escribinos.', 'jb3-members' );
		}
		return '';
	}
}
