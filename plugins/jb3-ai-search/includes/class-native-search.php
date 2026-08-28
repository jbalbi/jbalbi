<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reemplaza la búsqueda nativa del sitio (el ícono de lupa del header →
 * modal → ?s=... → search.php) por la versión con IA, SIN tocar ningún
 * archivo del tema — se engancha a "pre_get_posts" (mismo mecanismo que ya
 * usa jb3-geo-targeting para su propio filtro) y modifica la consulta
 * PRINCIPAL antes de que se ejecute. search.php sigue haciendo exactamente
 * lo mismo que siempre (su loop nativo de have_posts()/the_post()) — solo
 * cambia QUÉ le devuelve la consulta, nunca cómo la muestra.
 *
 * 100% reversible con solo desactivar el plugin: al desactivarlo, este hook
 * deja de estar registrado y WordPress vuelve a su búsqueda de siempre —
 * no queda ningún rastro en el tema. Portable a otro sitio sin tocar nada
 * ahí tampoco, por el mismo motivo.
 *
 * No hace falta agregar el país acá (a diferencia de JB3_AISearch_Query,
 * que sí lo suma a mano): esto corre dentro de is_main_query(), que ya
 * queda cubierto por el filtro "the_posts" de geo-targeting que el sitio
 * aplica a CUALQUIER búsqueda nativa (ver jb3-geo-targeting.php) — sumarlo
 * acá también sería redundante.
 */
add_action( 'pre_get_posts', 'jb3_aisearch_native_pre_get_posts' );

function jb3_aisearch_native_pre_get_posts( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_search() || ! $query->is_main_query() ) {
		return;
	}

	$pregunta = trim( (string) get_search_query() );
	if ( '' === $pregunta ) {
		return;
	}

	// Red de seguridad amplia: esto modifica la búsqueda de TODO el sitio,
	// para TODOS los visitantes — cualquier error acá (JSON del modelo con
	// una forma que no anticipamos, cambio de la API externa, lo que sea)
	// no puede tirar abajo el buscador. Ante cualquier excepción, se actúa
	// exactamente igual que si la API hubiera fallado: no se toca la
	// query, WordPress busca como si el plugin no existiera.
	try {
		$filtros = jb3_aisearch_native_filtros_cacheados( $pregunta );

		// null = la API falló (o nunca hubo clave configurada) — no se toca
		// la query, WordPress corre su búsqueda nativa de siempre. El
		// buscador nunca se rompe por esto, en el peor caso queda como
		// estaba antes de instalar el plugin.
		if ( null === $filtros ) {
			return;
		}

		$args = JB3_AISearch_Query::filtros_a_query_args( $filtros );

		$query->set( 'post_type', $args['post_type'] );
		$query->set( 'tax_query', $args['tax_query'] );
		// Se pisa el "s" original (la pregunta en lenguaje natural completa)
		// por las palabras clave que extrajo el modelo — dejar la frase
		// entera hubiera hecho que WordPress busque ese texto LITERAL en
		// título/contenido, sin encontrar nada aunque el tax_query sí
		// tuviera resultados reales (ej. "series de AXN de 2023" nunca
		// aparece tal cual en ningún título). Si el modelo no extrajo
		// texto_libre, "s" queda vacío — no filtra por texto, deja que
		// tax_query solo decida.
		$query->set( 's', $args['s'] );

		// get_search_query() (usada en search.php para "Resultados de la
		// búsqueda para: X") lee el MISMO "s" que acabamos de pisar arriba
		// — sin este filtro, ese título mostraba las palabras clave de la
		// IA (a veces vacío, ej. "cocina" se resuelve entero como etiqueta
		// y "s" queda '') en vez de lo que el visitante escribió de
		// verdad. Reportado en vivo 2026-08-28.
		add_filter( 'get_search_query', function () use ( $pregunta ) {
			return $pregunta;
		} );

		jb3_aisearch_debug_log( sprintf(
			'[native] pregunta="%s" filtros=%s tax_query=%s post_type=%s',
			$pregunta,
			wp_json_encode( $filtros ),
			wp_json_encode( $args['tax_query'] ),
			wp_json_encode( $args['post_type'] )
		) );
	} catch ( \Throwable $e ) {
		jb3_aisearch_debug_log( '[native] pre_get_posts falló, se usa la búsqueda nativa: ' . $e->getMessage() );
	}
}

/**
 * Cachea el resultado de la extracción por término de búsqueda (10 min) —
 * cada búsqueda nativa puede repetirse por paginación, por gente buscando
 * lo mismo, o por recargas — sin esto cada request gastaría una llamada a
 * la API de nuevo. Cachea también el caso "la API falló" (con un array
 * vacío como marca) para no seguir insistiendo en cada visita mientras
 * dure una caída/cuota agotada.
 */
function jb3_aisearch_native_filtros_cacheados( string $pregunta ): ?array {
	$key      = 'jb3_aisearch_native_' . md5( strtolower( $pregunta ) );
	$cacheado = get_transient( $key );

	if ( false !== $cacheado ) {
		return $cacheado ? $cacheado : null; // [] guardado = "la API falló", se devuelve null.
	}

	$filtros = jb3_aisearch_extraer_filtros( $pregunta );
	set_transient( $key, $filtros ?? [], 10 * MINUTE_IN_SECONDS );
	return $filtros;
}
