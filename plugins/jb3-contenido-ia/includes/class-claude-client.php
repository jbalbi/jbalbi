<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente de Claude — mismo criterio que JB3_AISearch_Client_Claude
 * (jb3-ai-search): wp_remote_post() a mano, sin SDK, credenciales del mismo
 * archivo compartido (jbcia_api_key(), jb3-contenido-ia.php).
 *
 * A diferencia de ese cliente (extracción simple de una frase corta →
 * Haiku), acá hace falta BUSCAR información real en la web y después
 * reescribirla con calidad — se usa claude-opus-5 con la herramienta de
 * búsqueda web del lado del servidor (web_search_20260209): Claude busca y
 * sigue razonando dentro del MISMO pedido, sin que este código tenga que
 * armar un loop de tool-use manual (la búsqueda corre en la infraestructura
 * de Anthropic, no acá).
 *
 * tool_choice queda en "auto" a propósito, NUNCA forzado a la herramienta de
 * salida — si se fuerza, Claude no puede elegir llamar primero a la
 * búsqueda. En cambio se le pide explícitamente en el prompt que SIEMPRE
 * termine llamando a la herramienta de salida — no hay garantía dura de que
 * lo haga (a diferencia del tool_choice forzado que usa el buscador de
 * jb3-ai-search), por eso llamar() valida que el bloque esperado esté
 * presente y devuelve null si no, en vez de asumir que sí.
 */
class JBCIA_Claude_Client {

	const API_URL = 'https://api.anthropic.com/v1/messages';
	const MODEL   = 'claude-opus-5';

	public static function obtener_sinopsis( string $imdb_id, string $titulo ): ?string {
		$tool_name = 'entregar_sinopsis';

		$system = 'Sos un redactor de contenido para un sitio de streaming de películas y series. '
			. 'Te dan el ID de IMDb y (si está disponible) el título de una película, serie o producción. '
			. 'Usá la herramienta de búsqueda web para encontrar información real sobre ESE título puntual '
			. '(por ejemplo buscando el ID de IMDb o el título con su año) — no inventes datos que no puedas confirmar buscando. '
			. 'Con lo que encuentres, escribí una sinopsis ORIGINAL en español, en tus propias palabras — 2 a 4 frases, '
			. 'tono cercano, sin spoilers del final. NUNCA copies frases textuales de ninguna fuente que encuentres, '
			. 'reformulá siempre con tu propia redacción. '
			. "Cuando la sinopsis esté lista, llamá SIEMPRE a la herramienta \"{$tool_name}\" con el resultado — nunca respondas en texto libre.";

		$tools = [
			[ 'type' => 'web_search_20260209', 'name' => 'web_search' ],
			[
				'name'         => $tool_name,
				'description'  => 'Entrega la sinopsis final ya reescrita.',
				'input_schema' => [
					'type'       => 'object',
					'properties' => [
						'sinopsis' => [
							'type'        => 'string',
							'description' => 'Sinopsis original en español, 2 a 4 frases, sin spoilers del final.',
						],
					],
					'required'   => [ 'sinopsis' ],
				],
			],
		];

		$resultado = self::llamar( $system, self::mensaje_usuario( $imdb_id, $titulo ), $tools, $tool_name );
		$sinopsis  = trim( (string) ( $resultado['sinopsis'] ?? '' ) );

		return '' !== $sinopsis ? '<p>' . esc_html( $sinopsis ) . '</p>' : null;
	}

	/** @return array{director?:string,reparto?:string[],anio?:string,duracion?:string,genero?:string[]}|null */
	public static function obtener_informacion( string $imdb_id, string $titulo ): ?array {
		$tool_name = 'entregar_informacion';

		$system = 'Sos un investigador de datos de películas y series para un sitio de streaming. '
			. 'Te dan el ID de IMDb y (si está disponible) el título de un título puntual. '
			. 'Usá la herramienta de búsqueda web para confirmar: director, reparto principal (hasta 5 nombres), '
			. 'año de estreno, duración, y género(s). '
			. 'Dejá vacío (string vacío o array vacío) cualquier dato que no puedas confirmar buscando — NUNCA inventes un valor. '
			. "Cuando termines de buscar, llamá SIEMPRE a la herramienta \"{$tool_name}\" con lo que hayas confirmado — nunca respondas en texto libre.";

		$tools = [
			[ 'type' => 'web_search_20260209', 'name' => 'web_search' ],
			[
				'name'         => $tool_name,
				'description'  => 'Entrega los datos confirmados del título.',
				'input_schema' => [
					'type'       => 'object',
					'properties' => [
						'director' => [ 'type' => 'string', 'description' => 'Nombre del director/a, o vacío si no se confirmó.' ],
						'reparto'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Hasta 5 actores/actrices principales.' ],
						'anio'     => [ 'type' => 'string', 'description' => 'Año de estreno, ej: "2023".' ],
						'duracion' => [ 'type' => 'string', 'description' => 'Duración, ej: "118 min".' ],
						'genero'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Géneros, ej: ["Drama", "Comedia"].' ],
					],
					'required'   => [],
				],
			],
		];

		return self::llamar( $system, self::mensaje_usuario( $imdb_id, $titulo ), $tools, $tool_name );
	}

	private static function mensaje_usuario( string $imdb_id, string $titulo ): string {
		return $titulo
			? "ID de IMDb: {$imdb_id} — Título cargado en el sitio: \"{$titulo}\""
			: "ID de IMDb: {$imdb_id}";
	}

	private static function llamar( string $system, string $user_message, array $tools, string $tool_name ): ?array {
		$api_key = jbcia_api_key( 'claude' );
		if ( '' === $api_key ) {
			jbcia_ultimo_error( 'sin_clave' );
			jbcia_debug_log( 'Falta configurar claude_api_key en jb3-search-cfg.php.' );
			return null;
		}

		$response = wp_remote_post( self::API_URL, [
			// Búsqueda web + razonamiento puede tardar bastante más que una
			// extracción simple — timeout generoso a propósito.
			'timeout' => 90,
			'headers' => [
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'anthropic-beta'    => 'server-side-fallback-2026-07-01',
				'content-type'      => 'application/json',
			],
			'body'    => wp_json_encode( [
				'model'       => self::MODEL,
				'max_tokens'  => 4096,
				'system'      => $system,
				'thinking'    => [ 'type' => 'adaptive' ],
				'tools'       => $tools,
				'tool_choice' => [ 'type' => 'auto' ],
				'fallbacks'   => 'default',
				'messages'    => [
					[ 'role' => 'user', 'content' => $user_message ],
				],
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			jbcia_ultimo_error( 'red' );
			jbcia_debug_log( 'Error de red llamando a Claude: ' . $response->get_error_message() );
			if ( function_exists( 'jb3ia_registrar_uso' ) ) {
				jb3ia_registrar_uso( 'claude', 'jb3-contenido-ia', false, 'red' );
			}
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			// 429 = rate limit; 402/403 con "credit balance" en el body = sin crédito cargado — las dos son "cuota", no "no encontró nada".
			$motivo = 429 === $code ? 'cuota' : 'error';
			jbcia_ultimo_error( $motivo );
			jbcia_debug_log( 'Claude API error ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
			if ( function_exists( 'jb3ia_registrar_uso' ) ) {
				jb3ia_registrar_uso( 'claude', 'jb3-contenido-ia', false, $motivo );
			}
			return null;
		}

		jbcia_ultimo_error( '' );
		if ( function_exists( 'jb3ia_registrar_uso' ) ) {
			jb3ia_registrar_uso( 'claude', 'jb3-contenido-ia', true );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['content'] ) || ! is_array( $body['content'] ) ) {
			jbcia_debug_log( 'Respuesta de Claude sin contenido utilizable.' );
			return null;
		}

		if ( 'refusal' === ( $body['stop_reason'] ?? '' ) ) {
			jbcia_debug_log( 'Claude rechazó el pedido (stop_reason=refusal).' );
			return null;
		}

		foreach ( $body['content'] as $block ) {
			if ( is_array( $block ) && ( $block['type'] ?? '' ) === 'tool_use' && ( $block['name'] ?? '' ) === $tool_name ) {
				return is_array( $block['input'] ?? null ) ? $block['input'] : null;
			}
		}

		jbcia_debug_log( 'La respuesta de Claude no incluyó la herramienta esperada (' . $tool_name . '), stop_reason=' . ( $body['stop_reason'] ?? '?' ) );
		return null;
	}
}
