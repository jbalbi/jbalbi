<?php
defined( 'ABSPATH' ) || exit;

/**
 * Endpoint AJAX del chat bubble — mismo criterio de seguridad que
 * class-ajax.php (token → freno de abuso → IA → WP_Query geo-filtrada), pero
 * con dos diferencias: acepta un "historial" de la conversación (para que el
 * modelo pueda interpretar preguntas de seguimiento) y, desde 2026-08-31,
 * arma la respuesta conversacional con una SEGUNDA llamada a la IA
 * (jb3_aisearch_chat_redactar_respuesta(), jb3-ai-search.php) en vez de solo
 * una plantilla fija — pedido explícito ("que TODAS las conversaciones sean
 * más naturales"). La plantilla (jb3_aisearch_chat_texto_respuesta() más
 * abajo) sigue existiendo como respaldo si esa segunda llamada falla o no
 * hay presupuesto — nunca se rompe la respuesta por eso.
 *
 * Freno por IP: la MISMA función jb3_aisearch_throttle_ok() que ya usa
 * class-ajax.php (mismo transient, misma clave) — a propósito: buscador y
 * chat gastan del mismo recurso compartido (la API paga del proveedor de
 * IA vía jb3_aisearch_extraer_filtros()), así que comparten un solo presupuesto de
 * 8/min por IP en vez de que alguien duplique su cupo abriendo el otro. El
 * freno GLOBAL (300/hora, todo el sitio) ya está adentro de
 * jb3_aisearch_extraer_filtros() Y de jb3_aisearch_chat_redactar_respuesta()
 * — un mensaje de chat con resultados gasta 2 de esas 300 llamadas/hora en
 * vez de 1, se hereda gratis acá también.
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

	$count     = count( $resultados );
	$titulos   = jb3_aisearch_chat_titulos( $resultados );
	$respuesta = jb3_aisearch_chat_redactar_respuesta( $pregunta, $count, $titulos, ! empty( $historial ) )
		?? jb3_aisearch_chat_texto_respuesta( $count, $pregunta, $filtros['resumen'] ?? null );

	wp_send_json_success( [
		'respuesta' => $respuesta,
		'html'      => $count ? jb3_aisearch_chat_render_resultados( $resultados ) : '',
		'count'     => $count,
	] );
}

/**
 * Titulos reales de los primeros resultados encontrados, para pasarle a
 * jb3_aisearch_chat_redactar_respuesta() como unico material que la IA
 * puede nombrar (nunca un titulo fuera de esta lista) - tope bajo (5) a
 * proposito: es solo para que la respuesta pueda mencionar un par como
 * ejemplo, no un listado completo (eso ya lo ve el usuario en las tarjetas
 * de jb3_aisearch_chat_render_resultados(), que muestra hasta 8).
 */
function jb3_aisearch_chat_titulos( array $ids, int $limite = 5 ): array {
	$titulos = [];
	foreach ( array_slice( $ids, 0, $limite ) as $id ) {
		$titulo = get_the_title( $id );
		if ( '' !== $titulo ) {
			$titulos[] = $titulo;
		}
	}
	return $titulos;
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
 * RESPALDO de la respuesta "conversacional": plantilla armada acá con el
 * conteo real de WP_Query. Desde 2026-08-31 la respuesta normal la redacta
 * la IA (jb3_aisearch_chat_redactar_respuesta(), ver comentario de arriba
 * del archivo); esta plantilla se usa solo si esa segunda llamada falla o
 * se agotó el presupuesto por hora — así el chat nunca queda sin respuesta.
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
 *
 * Tono conversacional — pedido explícito 2026-08-31 ("que no suene tan a
 * respuesta de bot", ej. "te puedo recomendar estas películas" en vez de
 * "Encontré 19 resultados de películas de acción:"). Se sacó el número
 * exacto de la frase con 2+ resultados a propósito: jb3_aisearch_chat_render_resultados()
 * muestra como máximo 8 igual, así que decir "19" y listar 8 quedaba
 * inconsistente. "estas opciones de %s" (no "estas %s") también a
 * propósito: "opciones" es el sustantivo que concuerda con "estas"
 * (femenino plural fijo), $resumen queda como complemento con "de" que no
 * necesita concordar en género/número con nada — evita que "estas
 * películas de terror" quedara mal si $resumen viene en otro género/número
 * (ej. "contenido de cocina", masculino singular).
 */
function jb3_aisearch_chat_texto_respuesta( int $count, string $pregunta, ?string $resumen ): string {
	$descripcion = $resumen ? trim( $resumen ) : trim( $pregunta, "¿? \t\n" );

	if ( 0 === $count ) {
		return sprintf( 'No encontré nada de %s por ahora — probá con otra búsqueda.', $descripcion );
	}
	if ( 1 === $count ) {
		return sprintf( 'Encontré esto de %s:', $descripcion );
	}
	return sprintf( 'Te puedo recomendar estas opciones de %s:', $descripcion );
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
