<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente mínimo de Google Sheets API v4 — sin el SDK oficial de Google
 * (google/apiclient necesita Composer, y este plugin no lo usa en ningún
 * otro lado) — mismo criterio de "autohospedado, sin dependencias pesadas"
 * que ya usa el tema con PDF.js/Swiper. Solo lo que hace falta acá: pedir
 * un access token por JWT (flujo de cuenta de servicio) y agregar filas.
 *
 * MUDANZA DE SERVIDOR: copiar el archivo de credenciales a mano (ver
 * wp-content/MUDANZA-SERVIDOR.md).
 *
 * Credenciales: ABSPATH . 'jb3-sc-stats.php' (raíz de WordPress) — a propósito
 * FUERA de wp-content (el repo git es wp-content para adentro), mismo nivel
 * que wp-config.php. Nunca se commitea. Nombre a propósito genérico (no
 * "sheets-credentials" ni nada que mencione al cliente) para no ser un
 * archivo obviamente interesante si alguien escanea el servidor.
 */
class JB3_Google_Sheets {

	const SCOPE           = 'https://www.googleapis.com/auth/spreadsheets';
	const TOKEN_URL        = 'https://oauth2.googleapis.com/token';
	const TOKEN_CACHE_KEY  = 'jb3_gsheets_access_token';

	private static function credentials_path(): string {
		// ABSPATH ya termina en "/" y apunta a la raíz de WordPress (donde
		// vive wp-config.php) — el archivo de credenciales está ahí al
		// lado, NO un nivel arriba (dirname(ABSPATH) se pasa de largo por
		// la barra final).
		return ABSPATH . 'jb3-sc-stats.php';
	}

	private static function credentials(): ?array {
		$path = self::credentials_path();
		if ( ! file_exists( $path ) ) {
			return null;
		}
		$data = include $path;
		return is_array( $data ) ? $data : null;
	}

	// ─── Autenticación (JWT bearer, cuenta de servicio) ───────────────────────

	/**
	 * Access token con caché (los tokens de Google duran 1h) — evita pedir
	 * uno nuevo en cada escritura. wp_cache/transient, no falla si no hay
	 * objeto de caché persistente, transient alcanza.
	 */
	private static function get_access_token() {
		$cached = get_transient( self::TOKEN_CACHE_KEY );
		if ( $cached ) {
			return $cached;
		}

		$creds = self::credentials();
		if ( ! $creds ) {
			self::log( 'No se encontró el archivo de credenciales (' . self::credentials_path() . ').' );
			return false;
		}

		$now = time();
		$header = self::base64url( wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
		$claims = self::base64url( wp_json_encode( [
			'iss'   => $creds['client_email'],
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => $now,
			'exp'   => $now + 3600,
		] ) );

		$unsigned = $header . '.' . $claims;

		$signature = '';
		$ok = openssl_sign( $unsigned, $signature, $creds['private_key'], 'sha256WithRSAEncryption' );
		if ( ! $ok ) {
			self::log( 'openssl_sign() falló — revisar que private_key sea válida.' );
			return false;
		}

		$jwt = $unsigned . '.' . self::base64url( $signature );

		$response = wp_remote_post( self::TOKEN_URL, [
			'body'    => [
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			self::log( 'Error pidiendo access token: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			self::log( 'Respuesta sin access_token: ' . wp_remote_retrieve_body( $response ) );
			return false;
		}

		// Cachea con un margen de 60s respecto al expires_in real, para no
		// usar un token que vence justo en el medio de una request.
		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
		set_transient( self::TOKEN_CACHE_KEY, $body['access_token'], $ttl );

		return $body['access_token'];
	}

	private static function base64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	// ─── API ───────────────────────────────────────────────────────────────────

	/**
	 * Agrega una fila al final de una hoja (tab) de una planilla. Si la hoja
	 * (tab) no existe todavía en la planilla, se crea sola (batchUpdate,
	 * addSheet) — ver ensure_sheet_exists().
	 *
	 * @param string $spreadsheet_id
	 * @param string $sheet_name Nombre de la solapa (ej. "oportunidades vistas").
	 * @param array  $row        Valores de la fila, en orden de columna.
	 * @param array  $headers    Opcional: nombres de columna. Solo se escriben
	 *                           como fila 1 la PRIMERA vez que se crea la hoja
	 *                           — si ya existe, no se tocan (para no pisar
	 *                           encabezados que alguien haya editado a mano).
	 */
	public static function append_row( string $spreadsheet_id, string $sheet_name, array $row, array $headers = [] ): bool {
		$token = self::get_access_token();
		if ( ! $token ) {
			return false;
		}

		if ( ! self::ensure_sheet_exists( $spreadsheet_id, $sheet_name, $token, $headers ) ) {
			return false;
		}

		// RAW, no USER_ENTERED: con USER_ENTERED, Sheets intenta "interpretar"
		// valores que parecen fecha/número y los guarda como su número de
		// serie interno (ej. "2026-08-10 15:11:52" se convertía en
		// "46244.65247") — la celda queda con el formato de texto plano de
		// fábrica, así que se ve el número crudo en vez de la fecha. RAW
		// guarda el texto tal cual se manda, sin reinterpretarlo.
		$range = rawurlencode( $sheet_name ) . '!A:Z';
		$url   = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append"
			. '?valueInputOption=RAW&insertDataOption=INSERT_ROWS';

		$response = wp_remote_post( $url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( [ 'values' => [ array_values( $row ) ] ] ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			self::log( "append_row({$sheet_name}) error: " . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			self::log( "append_row({$sheet_name}) HTTP {$code}: " . wp_remote_retrieve_body( $response ) );
			return false;
		}

		return true;
	}

	/**
	 * Crea la solapa si todavía no existe en la planilla (batchUpdate,
	 * addSheet) — así "oportunidades vistas"/"oportunidades descargadas" se
	 * arman solas la primera vez que hay algo para registrar, sin que el
	 * editor tenga que crearlas a mano de antemano. Si se pasan $headers,
	 * se escriben como fila 1 justo después de crearla.
	 */
	private static function ensure_sheet_exists( string $spreadsheet_id, string $sheet_name, string $token, array $headers = [] ): bool {
		$cache_key = 'jb3_gsheets_tabs_' . md5( $spreadsheet_id );
		$known     = get_transient( $cache_key );

		if ( is_array( $known ) && in_array( $sheet_name, $known, true ) ) {
			return true;
		}

		$meta_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}?fields=sheets.properties.title";
		$response = wp_remote_get( $meta_url, [
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			self::log( 'ensure_sheet_exists() error consultando la planilla: ' . $response->get_error_message() );
			return false;
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$titles = wp_list_pluck( wp_list_pluck( $body['sheets'] ?? [], 'properties' ), 'title' );

		if ( in_array( $sheet_name, $titles, true ) ) {
			set_transient( $cache_key, $titles, HOUR_IN_SECONDS );
			return true;
		}

		// No existe: crearla.
		$batch_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate";
		$create    = wp_remote_post( $batch_url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( [
				'requests' => [
					[ 'addSheet' => [ 'properties' => [ 'title' => $sheet_name ] ] ],
				],
			] ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $create ) ) {
			self::log( 'ensure_sheet_exists() error creando la hoja: ' . $create->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $create );
		if ( $code < 200 || $code >= 300 ) {
			self::log( "ensure_sheet_exists() HTTP {$code} creando '{$sheet_name}': " . wp_remote_retrieve_body( $create ) );
			return false;
		}

		if ( $headers ) {
			self::write_header_row( $spreadsheet_id, $sheet_name, $token, $headers );
		}

		$titles[] = $sheet_name;
		set_transient( $cache_key, $titles, HOUR_IN_SECONDS );
		return true;
	}

	/** Escribe la fila 1 (encabezados) — update, no append: siempre pisa A1:Z1 de una hoja recién creada (vacía). */
	private static function write_header_row( string $spreadsheet_id, string $sheet_name, string $token, array $headers ): void {
		$range = rawurlencode( $sheet_name ) . '!A1:Z1';
		$url   = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}?valueInputOption=USER_ENTERED";

		$response = wp_remote_request( $url, [
			'method'  => 'PUT',
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( [ 'values' => [ array_values( $headers ) ] ] ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			self::log( "write_header_row({$sheet_name}) error: " . $response->get_error_message() );
			return;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			self::log( "write_header_row({$sheet_name}) HTTP {$code}: " . wp_remote_retrieve_body( $response ) );
		}
	}

	private static function log( string $message ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[JB3 Google Sheets] ' . $message );
		}
	}
}
