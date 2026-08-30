<?php
defined( 'ABSPATH' ) || exit;

/**
 * Endpoint AJAX del chat bubble — mismo criterio de seguridad que
 * class-ajax.php (token → freno de abuso → IA → WP_Query geo-filtrada), pero
 * con dos diferencias: acepta un "historial" de la conversación (para que el
 * modelo pueda interpretar preguntas de seguimiento) y arma la respuesta
 * conversacional acá mismo con PHP, en vez de con una segunda llamada al
 * modelo — ver jb3_aisearch_chat_texto_respuesta() más abajo.
 *
 * Freno por IP: la MISMA función jb3_aisearch_throttle_ok() que ya usa
 * class-ajax.php (mismo transient, misma clave) — a propósito: buscador y
 * chat gastan del mismo recurso compartido (la capa gratuita de Gemini vía
 * jb3_aisearch_extraer_filtros()), así que comparten un solo presupuesto de
 * 8/min por IP en vez de que alguien duplique su cupo abriendo el otro. El
 * freno GLOBAL (300/hora, todo el sitio) ya está adentro de
 * jb3_aisearch_extraer_filtros(), se hereda gratis acá también.
 */
add_action( 'wp_ajax_jb3_ai_chat', 'jb3_aisearch_chat_ajax_handler' );
add_action( 'wp_ajax_nopriv_jb3_ai_chat', 'jb3_aisearch_chat_ajax_handler' );

function jb3_aisearch_chat_ajax_handler() {
	check_ajax_referer( JB3_AISEARCH_NONCE, 'nonce' );

	$pregunta = isset( $_POST['pregunta'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['pregunta'] ) ) ) : '';
	if ( '' === $pregunta ) {
		wp_send_json_error( [ 'mensaje' => 'Escribí algo para preguntar.' ] );
	}
	if ( mb_strlen( $pregunta ) > 200 ) {
		wp_send_json_error( [ 'mensaje' => 'La pregunta es demasiado larga.' ] );
	}

	if ( ! jb3_aisearch_throttle_ok() ) {
		wp_send_json_error( [ 'mensaje' => 'Demasiados mensajes seguidos — esperá un momento y probá de nuevo.' ] );
	}

	$historial = jb3_aisearch_chat_sanitizar_historial( $_POST['historial'] ?? '' );

	try {
		$pregunta_enriquecida = jb3_aisearch_chat_construir_prompt( $historial, $pregunta );
		// El freno GLOBAL (300/hora, todo el sitio) se aplica adentro de esta
		// función sin cambiar su firma — ver jb3-ai-search.php.
		$filtros = jb3_aisearch_extraer_filtros( $pregunta_enriquecida );
		// OJO: acá va $pregunta (la pregunta suelta), NO la enriquecida — si
		// $filtros sale null, JB3_AISearch_Query::buscar() cae a
		// buscar_texto_simple($pregunta) como argumento "s" de WP_Query. Si le
		// pasáramos el string enriquecido, el "Conversación previa: ..." de
		// texto plano ensuciaría esa búsqueda de texto literal.
		$resultados = JB3_AISearch_Query::buscar( $pregunta, $filtros );
	} catch ( \Throwable $e ) {
		jb3_aisearch_debug_log( 'Chat AJAX falló: ' . $e->getMessage() );
		wp_send_json_success( [
			'respuesta' => jb3_aisearch_chat_texto_respuesta( 0, $pregunta, null ),
			'html'      => '',
			'count'     => 0,
		] );
	}

	$count = count( $resultados );
	wp_send_json_success( [
		'respuesta' => jb3_aisearch_chat_texto_respuesta( $count, $pregunta, $filtros['resumen'] ?? null ),
		'html'      => $count ? jb3_aisearch_chat_render_resultados( $resultados ) : '',
		'count'     => $count,
	] );
}

/**
 * Arma el string único que se manda a jb3_aisearch_extraer_filtros() — la
 * firma de esa función (y de todo lo que hay debajo, class-client-gemini.php
 * y class-search.php incluidos) NO se toca: el "contexto de conversación" es
 * pura construcción del string de entrada, nada más. Ver la frase agregada
 * en JB3_AISearch_Schema::instrucciones() que le explica al modelo cómo leer
 * este formato.
 *
 * Limitación conocida, no resuelta acá (mismo criterio que "no hay campo de
 * año" documentado en class-search.php): no existe un campo estructurado de
 * idioma en el contenido. Un seguimiento tipo "¿y en inglés?" no tiene forma
 * de resolverse como filtro real — cae en texto_libre como cualquier palabra
 * suelta y puede no traer nada útil. No se intenta arreglar acá.
 */
function jb3_aisearch_chat_construir_prompt( array $historial, string $pregunta ): string {
	if ( ! $historial ) {
		return $pregunta; // primer mensaje: comportamiento idéntico al buscador de hoy.
	}

	$lineas = [ 'Conversación previa:' ];
	foreach ( $historial as $turno ) {
		$lineas[] = 'Usuario preguntó: "' . $turno['pregunta'] . '"';
		if ( '' !== $turno['respuesta'] ) {
			$lineas[] = 'Se le respondió: "' . $turno['respuesta'] . '"';
		}
	}
	$lineas[] = '';
	$lineas[] = 'Pregunta actual: "' . $pregunta . '"';

	return implode( "\n", $lineas );
}

/**
 * Decodifica y sanea lo que mandó el cliente — chat.js ya limita a
 * JB3_AISEARCH_CHAT_HISTORIAL_MAX intercambios antes de mandarlos, pero un
 * POST directo a admin-ajax.php no pasa por el JS, así que este tope se
 * vuelve a aplicar acá server-side (mismo criterio que el mb_strlen(200) de
 * $_POST['pregunta'] arriba).
 */
function jb3_aisearch_chat_sanitizar_historial( $raw ): array {
	$data = json_decode( is_string( $raw ) ? wp_unslash( $raw ) : '', true );
	if ( ! is_array( $data ) ) {
		return [];
	}

	$limpio = [];
	foreach ( array_slice( $data, -JB3_AISEARCH_CHAT_HISTORIAL_MAX ) as $turno ) {
		if ( ! is_array( $turno ) || empty( $turno['pregunta'] ) ) {
			continue;
		}
		$limpio[] = [
			'pregunta'  => mb_substr( sanitize_text_field( (string) $turno['pregunta'] ), 0, 200 ),
			'respuesta' => mb_substr( sanitize_text_field( (string) ( $turno['respuesta'] ?? '' ) ), 0, 200 ),
		];
	}
	return $limpio;
}

/**
 * La respuesta "conversacional" es SIEMPRE una plantilla armada acá con el
 * conteo real de WP_Query — nunca una segunda llamada al modelo generando
 * prosa (cero riesgo de alucinación, cero costo extra de API por mensaje).
 * El rol del LLM en este plugin no cambia: sigue siendo únicamente
 * "pregunta → filtros estructurados".
 *
 * $resumen (campo "resumen" del schema, ver class-schema.php — agregado
 * 2026-08-30) es una frase corta que arma el MISMO llamado que ya extrae los
 * filtros, sin costo extra: reemplaza repetir la pregunta del usuario tal
 * cual entre comillas (sonaba robótico — "No encontré nada para 'y alguna
 * pelicula con tom hanks?'", reportado en vivo) por algo más natural ("No
 * encontré películas con Tom Hanks"). $pregunta queda como respaldo si el
 * modelo no pudo resumirla (campo opcional) o si $filtros salió null — en
 * ese caso se le saca el signo de pregunta final para que la plantilla
 * ("No encontré ¿...?") no quede con puntuación cruzada.
 */
function jb3_aisearch_chat_texto_respuesta( int $count, string $pregunta, ?string $resumen ): string {
	$descripcion = $resumen ? trim( $resumen ) : trim( $pregunta, "¿? \t\n" );

	if ( $count > 0 ) {
		return sprintf(
			'Encontré %d resultado%s de %s:',
			$count,
			1 === $count ? '' : 's',
			$descripcion
		);
	}
	return sprintf( 'No encontré %s — probá reformular la pregunta.', $descripcion );
}

/**
 * Lista compacta (thumbnail chica + título + link) para el panel angosto del
 * chat (~380px) — jb3_grid_mixto() (tema, _init_grids.php) está pensado para
 * un layout ancho tipo masonry, no entra acá. Presentación específica del
 * chat, por eso vive en el plugin y no en el tema.
 */
function jb3_aisearch_chat_render_resultados( array $ids ): string {
	$html = '';
	// Tope visual a 8 ítems aunque $ids tenga hasta 24 (ver
	// JB3_AISearch_Query::buscar()) — la lista angosta del chat no es el
	// lugar para mostrar 24 resultados; el conteo real ya quedó dicho en
	// jb3_aisearch_chat_texto_respuesta().
	foreach ( array_slice( $ids, 0, 8 ) as $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			continue;
		}
		$html .= '<a class="jb3-ai-chat__resultado" href="' . esc_url( get_permalink( $post ) ) . '">'
			. '<span class="jb3-ai-chat__resultado-media">' . ( has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, 'thumbnail' ) : '' ) . '</span>'
			. '<span class="jb3-ai-chat__resultado-titulo">' . esc_html( get_the_title( $post ) ) . '</span>'
			. '</a>';
	}
	return $html ? '<div class="jb3-ai-chat__resultados">' . $html . '</div>' : '';
}
