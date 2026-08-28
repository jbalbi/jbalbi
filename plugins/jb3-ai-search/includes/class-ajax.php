<?php
defined( 'ABSPATH' ) || exit;

/**
 * Endpoint AJAX público (con y sin sesión — es un buscador de cara al
 * público) que orquesta: token de seguridad → freno de abuso → Claude →
 * WP_Query geo-filtrada → HTML de resultados (jb3_grid_thumbs(), la misma
 * función que ya usa search.php para resultados de tipos mixtos).
 */
add_action( 'wp_ajax_jb3_ai_search', 'jb3_aisearch_ajax_handler' );
add_action( 'wp_ajax_nopriv_jb3_ai_search', 'jb3_aisearch_ajax_handler' );

function jb3_aisearch_ajax_handler() {
	check_ajax_referer( JB3_AISEARCH_NONCE, 'nonce' );

	$pregunta = isset( $_POST['pregunta'] ) ? sanitize_text_field( wp_unslash( $_POST['pregunta'] ) ) : '';
	$pregunta = trim( $pregunta );
	if ( '' === $pregunta ) {
		wp_send_json_error( [ 'mensaje' => 'Escribí algo para buscar.' ] );
	}

	// Una pregunta en lenguaje natural no necesita ser larga — cortamos acá
	// (antes de gastar una llamada a la API) para no darle a nadie una vía
	// de mandar payloads de varios KB que inflen el costo/tokens de cada
	// pedido (un POST directo a admin-ajax.php no pasa por el maxlength del
	// <input>, ver shortcode.php).
	if ( mb_strlen( $pregunta ) > 200 ) {
		wp_send_json_error( [ 'mensaje' => 'La búsqueda es demasiado larga.' ] );
	}

	// El freno global (todo el sitio, no por IP) se aplica adentro de
	// jb3_aisearch_extraer_filtros() — ver jb3-ai-search.php.
	if ( ! jb3_aisearch_throttle_ok() ) {
		wp_send_json_error( [ 'mensaje' => 'Demasiadas búsquedas seguidas — esperá un momento y probá de nuevo.' ] );
	}

	// Red de seguridad: cualquier error inesperado en la extracción/consulta
	// (ver el mismo criterio en class-native-search.php) degrada a "sin
	// resultados" en vez de un 500 — este endpoint es público y de cara al
	// usuario, no puede devolver una respuesta rota.
	try {
		$filtros    = jb3_aisearch_extraer_filtros( $pregunta );
		$resultados = JB3_AISearch_Query::buscar( $pregunta, $filtros );
	} catch ( \Throwable $e ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[jb3-ai-search] AJAX falló: ' . $e->getMessage() );
		}
		wp_send_json_success( [ 'html' => '', 'count' => 0 ] );
	}

	if ( ! $resultados ) {
		wp_send_json_success( [ 'html' => '', 'count' => 0 ] );
	}

	wp_send_json_success( [
		'html'  => function_exists( 'jb3_grid_mixto' ) ? jb3_grid_mixto( $resultados ) : '',
		'count' => count( $resultados ),
	] );
}

/**
 * Freno simple por IP (transient, ventana de 1 minuto) antes de gastar una
 * llamada a la API paga — no es un rate-limit fino, solo corta el caso obvio
 * de alguien mandando búsquedas en bucle.
 */
function jb3_aisearch_throttle_ok(): bool {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'desconocida';
	$key = JB3_AISEARCH_THROTTLE_KEY . md5( $ip );

	$conteo = (int) get_transient( $key );
	if ( $conteo >= JB3_AISEARCH_THROTTLE_MAX ) {
		return false;
	}

	set_transient( $key, $conteo + 1, MINUTE_IN_SECONDS );
	return true;
}
