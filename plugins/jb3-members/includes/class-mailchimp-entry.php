<?php
defined( 'ABSPATH' ) || exit;

/**
 * Entrada validada por Mailchimp (?e=*|UNIQID|*) — los links de campaña
 * llevan el "unique_email_id" de Mailchimp por contacto.
 *
 * ALCANCE, a propósito acotado: "e" sirve ÚNICAMENTE para saber a quién
 * mandarle el magic link cuando el visitante NO tiene sesión — nada más.
 * Si YA hay una sesión activa (sea cual sea, venga o no de un link
 * reenviado de otra persona), "e" se ignora por completo: el acceso lo
 * deciden únicamente los permisos normales de la página (el gate de
 * contenido privado, el gate de Op. Comerciales, el geo-targeting por
 * país) — nunca la identidad de quién generó el link. Un usuario logueado
 * con permiso ve el contenido igual aunque haya llegado por el link de
 * otro; un usuario logueado SIN permiso queda bloqueado igual aunque el
 * link fuera el suyo propio. "e" no tiene ni voz ni voto en ninguno de
 * los dos casos, solo en el de "no hay sesión todavía".
 *
 * No hay forma de "leer" el email desde unique_email_id sin la API — es
 * justamente lo que Mailchimp expone para poder personalizar sin mandar
 * el email en texto plano por la URL.
 */
class JB3_Mailchimp_Entry {

	const SIN_MATCH          = 'sin_match';
	const MAGIC_LINK_ENVIADO = 'magic_link_enviado';

	const CACHE_TTL_HIT  = DAY_IN_SECONDS;
	const CACHE_TTL_MISS = HOUR_IN_SECONDS;

	/**
	 * @param string $uniqid El valor crudo de $_GET['e'].
	 * @param string $redirect_to A dónde volver después del magic link (típicamente la página actual).
	 * @return string Una de las constantes de arriba.
	 */
	public static function verificar( string $uniqid, string $redirect_to ): string {
		// Ya hay sesión: "e" no aporta nada, que decida el sistema de
		// permisos normal de la página — ver comentario de clase.
		if ( is_user_logged_in() ) {
			return self::SIN_MATCH;
		}

		$uniqid = trim( $uniqid );
		if ( '' === $uniqid ) {
			return self::SIN_MATCH;
		}

		$mc_email = self::resolver_email( $uniqid );
		if ( ! $mc_email ) {
			return self::SIN_MATCH;
		}

		$wp_user = get_user_by( 'email', $mc_email );
		if ( ! $wp_user ) {
			return self::SIN_MATCH; // Nadie registrado con ese email — que siga el flujo normal (gate genérico).
		}
		if ( ! jb3_is_member( $wp_user->ID ) && ! user_can( $wp_user->ID, 'manage_options' ) ) {
			return self::SIN_MATCH;
		}
		if ( ! jb3_is_approved( $wp_user->ID ) && ! user_can( $wp_user->ID, 'manage_options' ) ) {
			return self::SIN_MATCH; // Registrado pero pendiente de aprobación — no mandar magic link todavía.
		}

		if ( jb3_rate_limited( $mc_email ) ) {
			return self::SIN_MATCH; // Ya se mandaron varios — no seguir insistiendo, cae al gate genérico.
		}

		JB3_Email::send_magic_link( $wp_user->ID, $redirect_to );
		return self::MAGIC_LINK_ENVIADO;
	}

	// ─── Mailchimp lookup ────────────────────────────────────────────────────

	private static function resolver_email( string $uniqid ): ?string {
		$cache_key = 'jb3_mc_uniqid_' . md5( $uniqid );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return '' !== $cached ? $cached : null; // '' cacheado = "ya sabemos que no existe".
		}

		$email = self::consultar_mailchimp( $uniqid );

		set_transient( $cache_key, $email ?? '', $email ? self::CACHE_TTL_HIT : self::CACHE_TTL_MISS );

		return $email;
	}

	private static function consultar_mailchimp( string $uniqid ): ?string {
		if ( ! class_exists( 'JB3_Mailchimp' ) ) {
			return null;
		}

		$api_key = JB3_Mailchimp::api_key();
		$list_id = JB3_Mailchimp::list_id();
		if ( ! $api_key || ! $list_id ) {
			return null;
		}

		$server = JB3_Mailchimp::server();
		$url    = "https://{$server}.api.mailchimp.com/3.0/lists/{$list_id}/members"
			. '?unique_email_id=' . rawurlencode( $uniqid ) . '&count=1&fields=members.email_address';

		$response = wp_remote_get( $url, [
			'headers' => [ 'Authorization' => 'Basic ' . base64_encode( 'jb3:' . $api_key ) ],
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			self::log( 'Error consultando Mailchimp: ' . $response->get_error_message() );
			return null;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['members'][0]['email_address'] ?? null;
	}

	private static function log( string $message ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[JB3 Mailchimp Entry] ' . $message );
		}
	}
}
