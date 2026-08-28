<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente de Claude (Anthropic) — NO es el que está en uso hoy (ver
 * JB3_AISEARCH_PROVIDER en jb3-ai-search.php, hoy en 'gemini' porque la API
 * de Anthropic no tiene capa gratuita y arrancamos sin presupuesto para
 * esto). Queda listo y probado para el día que se quiera pasar a Claude
 * (por calidad de resultados, o al escalar a producción) — para activarlo
 * alcanza con cambiar esa constante a 'claude' y cargar la clave
 * correspondiente en el archivo de credenciales (ver jb3_aisearch_api_key()).
 *
 * Traduce una pregunta en lenguaje natural a filtros estructurados usando la
 * Messages API de Claude, sin SDK (mismo criterio que JB3_Google_Sheets en
 * jb3-members: wp_remote_post() a mano). Usa "tool use" (la respuesta queda
 * forzada a la estructura del schema) en vez de pedirle al modelo que
 * devuelva JSON en texto libre — mucho más confiable de parsear.
 *
 * El país NUNCA es parte de lo que este cliente le pide al modelo — el
 * geo-targeting se aplica aparte, siempre a partir de jb3_geo_visitor_country()
 * (ver JB3_AISearch_Query), nunca de lo que el usuario escribió.
 */
class JB3_AISearch_Client_Claude {

	const API_URL   = 'https://api.anthropic.com/v1/messages';
	const MODEL     = 'claude-haiku-4-5-20251001'; // tarea de extracción simple, no hace falta un modelo grande.
	const TOOL_NAME = 'extraer_filtros_busqueda';

	/**
	 * @param string $pregunta Texto tal cual lo escribió el usuario.
	 * @return array|null Filtros extraídos, o null si la API falló / no
	 *                     devolvió nada usable (el llamador cae a búsqueda
	 *                     de texto simple en ese caso).
	 */
	public static function extraer_filtros( string $pregunta ): ?array {
		$api_key = jb3_aisearch_api_key( 'claude' );
		if ( '' === $api_key || '' === trim( $pregunta ) ) {
			return null;
		}

		$response = wp_remote_post( self::API_URL, [
			'timeout' => 12,
			'headers' => [
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			],
			'body' => wp_json_encode( [
				'model'       => self::MODEL,
				'max_tokens'  => 1024,
				'tools'       => [ self::tool_schema() ],
				'tool_choice' => [ 'type' => 'tool', 'name' => self::TOOL_NAME ],
				'messages'    => [
					[ 'role' => 'user', 'content' => $pregunta ],
				],
			] ),
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['content'] ) || ! is_array( $body['content'] ) ) {
			return null;
		}

		foreach ( $body['content'] as $block ) {
			if ( is_array( $block ) && ( $block['type'] ?? '' ) === 'tool_use' && is_array( $block['input'] ?? null ) ) {
				return $block['input'];
			}
		}

		return null;
	}

	/** Traduce JB3_AISearch_Schema::campos() al formato "input_schema" (JSON Schema) que espera "tool use". */
	private static function tool_schema(): array {
		$properties = [];
		$required   = [];

		foreach ( JB3_AISearch_Schema::campos() as $campo ) {
			$prop = [ 'description' => $campo['descripcion'] ];

			if ( 'string[]' === $campo['tipo'] ) {
				$prop['type']  = 'array';
				$prop['items'] = [ 'type' => 'string' ];
				if ( ! empty( $campo['enum'] ) ) {
					$prop['items']['enum'] = $campo['enum'];
				}
			} else {
				$prop['type'] = 'integer' === $campo['tipo'] ? 'integer' : 'string';
				if ( ! empty( $campo['enum'] ) ) {
					$prop['enum'] = $campo['enum'];
				}
			}

			$properties[ $campo['nombre'] ] = $prop;
			if ( $campo['requerido'] ?? true ) {
				$required[] = $campo['nombre'];
			}
		}

		return [
			'name'         => self::TOOL_NAME,
			'description'  => JB3_AISearch_Schema::instrucciones(),
			'input_schema' => [
				'type'       => 'object',
				'properties' => $properties,
				'required'   => $required,
			],
		];
	}
}
