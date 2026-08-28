<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente de Gemini (Google AI Studio) — el que está EN USO hoy (ver
 * JB3_AISEARCH_PROVIDER en jb3-ai-search.php). Se eligió sobre Claude
 * únicamente porque la API de Gemini tiene una capa gratuita real (límite
 * por cantidad de pedidos, no por dinero cargado) y arrancamos el proyecto
 * sin presupuesto para esto — para esta tarea puntual (extraer 5-6 campos
 * de una frase corta) un modelo chico alcanza de sobra, no es una limitación
 * real de calidad. Clave de API: gratis, se genera en aistudio.google.com →
 * "Get API key" (no confundir con una cuenta de Google Cloud/facturación).
 *
 * Mismo contrato que JB3_AISearch_Client_Claude (mismo método, mismo
 * formato de salida) — así JB3_AISearch_Query no necesita saber cuál de
 * los dos está corriendo.
 *
 * Modelo/límites gratuitos: pueden cambiar con el tiempo — confirmar en
 * https://ai.google.dev/gemini-api/docs/pricing antes de asumir que el
 * modelo de acá abajo sigue siendo gratuito.
 */
class JB3_AISearch_Client_Gemini {

	const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s';
	// Probados 2026-08-26: gemini-2.5-flash y gemini-2.0-flash ya no están
	// disponibles (404). gemini-3.6-flash respondía pero tardaba ~20s (trae
	// "razonamiento extendido" activado por defecto, no acepta
	// thinkingBudget:0 para desactivarlo del todo) — demasiado lento para un
	// buscador. gemini-flash-lite-latest responde en ~1-2s, que es lo que
	// hace falta acá. Si esto vuelve a fallar, el propio error 404 de la
	// API suele indicar el nombre del modelo vigente.
	const MODEL   = 'gemini-flash-lite-latest';

	/**
	 * @param string $pregunta Texto tal cual lo escribió el usuario.
	 * @return array|null Filtros extraídos, o null si la API falló / no
	 *                     devolvió nada usable (el llamador cae a búsqueda
	 *                     de texto simple en ese caso).
	 */
	public static function extraer_filtros( string $pregunta ): ?array {
		$api_key = jb3_aisearch_api_key( 'gemini' );
		if ( '' === $api_key || '' === trim( $pregunta ) ) {
			return null;
		}

		$url = sprintf( self::API_URL, self::MODEL, rawurlencode( $api_key ) );

		$response = wp_remote_post( $url, [
			'timeout' => 12,
			'headers' => [ 'content-type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'system_instruction' => [
					'parts' => [ [ 'text' => JB3_AISearch_Schema::instrucciones() ] ],
				],
				'contents' => [
					[ 'parts' => [ [ 'text' => $pregunta ] ] ],
				],
				'generationConfig' => [
					'responseMimeType' => 'application/json',
					'responseSchema'   => self::response_schema(),
				],
			] ),
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$json_texto = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
		if ( '' === $json_texto ) {
			return null;
		}

		$filtros = json_decode( $json_texto, true );
		return is_array( $filtros ) ? $filtros : null;
	}

	/**
	 * Traduce JB3_AISearch_Schema::campos() al formato "responseSchema" de
	 * Gemini — subset de OpenAPI 3.0 con los nombres de tipo en MAYÚSCULA
	 * (OBJECT/STRING/INTEGER/ARRAY), distinto del JSON Schema que usa Claude.
	 */
	private static function response_schema(): array {
		$properties = [];
		$required   = [];

		foreach ( JB3_AISearch_Schema::campos() as $campo ) {
			$prop = [ 'description' => $campo['descripcion'] ];

			// A diferencia de "tool use" en Claude, el responseSchema de
			// Gemini rechaza un string vacío como valor de enum (400:
			// "enum[0]: cannot be empty") — se usaba para representar
			// "sin valor". Se lo saca acá; no hace falta que el enum sea
			// 100% estricto porque class-search.php ya descarta cualquier
			// valor inventado del lado del servidor de todas formas.
			$enum = ! empty( $campo['enum'] ) ? array_values( array_filter( $campo['enum'], 'strlen' ) ) : [];

			if ( 'string[]' === $campo['tipo'] ) {
				$prop['type']  = 'ARRAY';
				$prop['items'] = [ 'type' => 'STRING' ];
				if ( $enum ) {
					$prop['items']['enum'] = $enum;
				}
			} else {
				$prop['type'] = 'integer' === $campo['tipo'] ? 'INTEGER' : 'STRING';
				if ( $enum ) {
					$prop['enum'] = $enum;
				}
			}

			$properties[ $campo['nombre'] ] = $prop;
			if ( $campo['requerido'] ?? true ) {
				$required[] = $campo['nombre'];
			}
		}

		return [
			'type'       => 'OBJECT',
			'properties' => $properties,
			'required'   => $required,
		];
	}
}
