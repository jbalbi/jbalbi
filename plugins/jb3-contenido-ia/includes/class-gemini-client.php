<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente de Gemini — proveedor activo hoy (ver JBCIA_PROVIDER en
 * jb3-contenido-ia.php), mismo endpoint clásico que ya usa
 * JB3_AISearch_Client_Gemini (jb3-ai-search): wp_remote_post() a mano, sin
 * SDK, un reintento ante fallo transitorio.
 *
 * A diferencia del cliente de Claude (una sola llamada: la búsqueda web
 * corre del lado del servidor Y la salida estructurada se piden juntas),
 * acá NO se pudo confirmar en vivo si el endpoint clásico de Gemini permite
 * combinar la herramienta `google_search` con `responseSchema` en el MISMO
 * pedido — la documentación de Google mostraba esa combinación solo para un
 * endpoint nuevo en preview ("Interactions API", solo modelos Gemini 3), y
 * las dos pruebas en vivo contra este endpoint clásico devolvieron 429
 * (cuota agotada) antes de poder confirmar cualquiera de los dos caminos.
 *
 * Por eso "Información" queda armada en DOS llamadas separadas (más lento y
 * un poco más caro, pero sobre un camino sin ambigüedad):
 *   1) Búsqueda con grounding, texto libre (sin schema).
 *   2) Extracción a JSON estructurado del texto del paso 1 (sin búsqueda).
 * "Sinopsis" en cambio necesita UNA sola llamada — el resultado que
 * queremos YA es texto libre (la sinopsis reescrita), no hace falta
 * estructurarlo después.
 *
 * PENDIENTE DE VERIFICAR EN VIVO (bloqueado por la cuota 429 del
 * 2026-09-11): confirmar que la sintaxis `'tools' => [['google_search' =>
 * new stdClass()]]` es la correcta para gemini-flash-lite-latest en este
 * endpoint — no se pudo confirmar contra la API real todavía.
 */
class JBCIA_Gemini_Client {

	const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s';
	const MODEL   = 'gemini-flash-lite-latest'; // mismo modelo que ya usa jb3-ai-search — subir a uno más grande si la calidad de redacción no alcanza, una vez que haya presupuesto para eso.

	public static function obtener_sinopsis( string $imdb_id, string $titulo ): ?string {
		$system = 'Sos un redactor de contenido para un sitio de streaming de películas y series. '
			. 'Te dan el ID de IMDb y (si está disponible) el título de un título puntual. '
			. 'Usá la búsqueda web para encontrar información real sobre ESE título — no inventes datos que no puedas confirmar buscando. '
			. 'Con lo que encuentres, escribí una sinopsis ORIGINAL en español, en tus propias palabras — 2 a 4 frases, '
			. 'tono cercano, sin spoilers del final, sin copiar frases textuales de ninguna fuente. '
			. 'Respondé ÚNICAMENTE con la sinopsis final, sin ningún texto adicional antes o después (nada de "Encontré que..." ni saludos).';

		$texto = self::pedir_con_busqueda( $system, self::mensaje_usuario( $imdb_id, $titulo ) );
		$texto = trim( (string) $texto );

		return '' !== $texto ? '<p>' . esc_html( $texto ) . '</p>' : null;
	}

	/** @return array{director?:string,reparto?:string[],anio?:string,duracion?:string,genero?:string[]}|null */
	public static function obtener_informacion( string $imdb_id, string $titulo ): ?array {
		$system_busqueda = 'Sos un investigador de datos de películas y series. '
			. 'Te dan el ID de IMDb y (si está disponible) el título de un título puntual. '
			. 'Usá la búsqueda web para confirmar: director, hasta 5 actores/actrices principales, año de estreno, duración, y género(s). '
			. 'Respondé con esos datos en un texto corto — si no podés confirmar un dato, decilo explícitamente en vez de inventarlo.';

		$texto_crudo = self::pedir_con_busqueda( $system_busqueda, self::mensaje_usuario( $imdb_id, $titulo ) );
		if ( null === $texto_crudo || '' === trim( $texto_crudo ) ) {
			return null;
		}

		$system_extraccion = 'Extraé del texto que te paso, en JSON, los campos: director, reparto (array, hasta 5 nombres), '
			. 'anio, duracion, genero (array). Si un dato no aparece confirmado en el texto, dejalo vacío ("" o []) — nunca inventes.';

		$schema = [
			'type'       => 'OBJECT',
			'properties' => [
				'director' => [ 'type' => 'STRING' ],
				'reparto'  => [ 'type' => 'ARRAY', 'items' => [ 'type' => 'STRING' ] ],
				'anio'     => [ 'type' => 'STRING' ],
				'duracion' => [ 'type' => 'STRING' ],
				'genero'   => [ 'type' => 'ARRAY', 'items' => [ 'type' => 'STRING' ] ],
			],
		];

		return self::pedir_json( $system_extraccion, $texto_crudo, $schema );
	}

	private static function mensaje_usuario( string $imdb_id, string $titulo ): string {
		return $titulo
			? "ID de IMDb: {$imdb_id} — Título cargado en el sitio: \"{$titulo}\""
			: "ID de IMDb: {$imdb_id}";
	}

	/** Paso 1: búsqueda con grounding, devuelve texto libre (sin schema — no confirmado si se puede combinar, ver docblock de la clase). */
	private static function pedir_con_busqueda( string $system, string $mensaje ): ?string {
		$body = [
			'system_instruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
			'contents'           => [ [ 'parts' => [ [ 'text' => $mensaje ] ] ] ],
			'tools'              => [ [ 'google_search' => new stdClass() ] ],
		];

		$data = self::pedir( $body );
		return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
	}

	/** Paso 2: extracción a JSON estructurado, sin búsqueda. */
	private static function pedir_json( string $system, string $mensaje, array $schema ): ?array {
		$body = [
			'system_instruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
			'contents'           => [ [ 'parts' => [ [ 'text' => $mensaje ] ] ] ],
			'generationConfig'   => [
				'responseMimeType' => 'application/json',
				'responseSchema'   => $schema,
			],
		];

		$data       = self::pedir( $body );
		$json_texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		if ( '' === $json_texto ) {
			return null;
		}
		$parsed = json_decode( $json_texto, true );
		return is_array( $parsed ) ? $parsed : null;
	}

	/** @return array|null El body decodificado, o null si falló (con un reintento ante error transitorio, mismo criterio que JB3_AISearch_Client_Gemini). */
	private static function pedir( array $body ): ?array {
		$api_key = jbcia_api_key( 'gemini' );
		if ( '' === $api_key ) {
			jbcia_ultimo_error( 'sin_clave' );
			jbcia_debug_log( 'Falta configurar gemini_api_key en jb3-search-cfg.php.' );
			return null;
		}

		$url = sprintf( self::API_URL, self::MODEL, rawurlencode( $api_key ) );

		$respuesta = self::hacer_pedido( $url, $body );
		if ( null === $respuesta ) {
			usleep( 300000 );
			$respuesta = self::hacer_pedido( $url, $body );
		}
		return $respuesta;
	}

	private static function hacer_pedido( string $url, array $body ): ?array {
		$response = wp_remote_post( $url, [
			'timeout' => 30, // la búsqueda con grounding puede tardar más que una extracción simple.
			'headers' => [ 'content-type' => 'application/json' ],
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			jbcia_ultimo_error( 'red' );
			jbcia_debug_log( 'Gemini: error de red — ' . $response->get_error_message() );
			if ( function_exists( 'jb3ia_registrar_uso' ) ) {
				jb3ia_registrar_uso( 'gemini', 'jb3-contenido-ia', false, 'red' );
			}
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			// 429 = cuota agotada (RESOURCE_EXHAUSTED) — distinto de "no encontró nada".
			$motivo = 429 === $code ? 'cuota' : 'error';
			jbcia_ultimo_error( $motivo );
			jbcia_debug_log( 'Gemini: API error ' . $code . ' — ' . wp_remote_retrieve_body( $response ) );
			if ( function_exists( 'jb3ia_registrar_uso' ) ) {
				jb3ia_registrar_uso( 'gemini', 'jb3-contenido-ia', false, $motivo );
			}
			return null;
		}

		// Pedido OK — limpia cualquier motivo de fallo que hubiera quedado del reintento anterior.
		jbcia_ultimo_error( '' );
		if ( function_exists( 'jb3ia_registrar_uso' ) ) {
			jb3ia_registrar_uso( 'gemini', 'jb3-contenido-ia', true );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : null;
	}
}
