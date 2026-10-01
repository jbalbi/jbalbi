<?php
defined( 'ABSPATH' ) || exit;

// ─── "CONTENIDO RELACIONADO": automático, mismo canal ───────────────────────
// Distinto de jb3_relacionados_auto() (_init_relacionados.php): ahí la
// relación la carga un editor a mano vía ACF, acá sale sola de la taxonomía
// "etiquetas" (tags libres, ver _init_taxonomias.php) + el/los canal(es) del
// propio contenido — sin curar nada. Un tag puede ser un género (Terror),
// un actor, un tema/franquicia (Spider-Man), o una categoría de paquete
// ("Nuevos Formatos") — el ALCANCE del cruce depende de la familia del post
// actual: Películas/Series/Prod. Originales se cruzan entre sí (esos 3 ya no
// usan el campo ACF de relacionados para esto); cualquier otro tipo con
// etiquetas (por ahora Op. Comerciales) se agrupa SOLO consigo mismo, no se
// mezcla con Videos/Integraciones aunque compartan tag.
//
// UN SOLO MÓDULO aunque el contenido tenga varias etiquetas — antes se
// armaba un módulo por tag ("Más contenido de Acción", "Más contenido de
// Ciencia Ficción"...), pero con 2-3 tags cargados eso se ponía denso, y un
// post que calzaba en más de un tag terminaba repetido en varios módulos.
// Un solo tax_query con TODOS los tags del post (WP_Query hace OR entre los
// terms de una misma cláusula) trae la unión sin duplicados, en una sola
// consulta.
// Llamar después de the_content() en el single-{cpt}.php que corresponda.
//
// post_status explícito en 'publish': la despublicación programada
// (plugin jb3-content-expiration) pasa el post a Borrador solo cuando
// vence — alcanza con pedir 'publish' para que el vencido quede afuera,
// sin lógica de fechas propia acá.
function jb3_etiquetas_auto( int $post_id ): string {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$tags = get_the_terms( $post_id, 'etiquetas' );
	if ( empty( $tags ) || is_wp_error( $tags ) ) {
		return '';
	}

	$canales   = get_the_terms( $post_id, 'canales' );
	$canal_ids = ( $canales && ! is_wp_error( $canales ) ) ? wp_list_pluck( $canales, 'term_id' ) : array();

	$pais = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';

	// Familia "poster" (Películas/Series/Prod.Originales) se cruza entre sí;
	// cualquier otro tipo (Op. Comerciales, etc.) solo consigo mismo.
	$tipos_poster = function_exists( 'jb3_modulo_tipos_poster' )
		? jb3_modulo_tipos_poster()
		: array( 'peliculas', 'series', 'prod_originales' );
	$es_poster = in_array( $post->post_type, $tipos_poster, true );
	$tipos     = $es_poster ? $tipos_poster : array( $post->post_type );

	$tax_query = array(
		// Varios term_id en la misma cláusula = OR entre ellos (matchea
		// cualquiera de los tags del post, no todos a la vez).
		array( 'taxonomy' => 'etiquetas', 'field' => 'term_id', 'terms' => wp_list_pluck( $tags, 'term_id' ) ),
	);

	if ( $canal_ids ) {
		$tax_query[] = array( 'taxonomy' => 'canales', 'field' => 'term_id', 'terms' => $canal_ids );
	}

	// Mismo geo-targeting que el resto del sitio — match estricto: sin país
	// tildado, NO se muestra en ningún país (cambiado 2026-08-14, reforzado a
	// fail-closed 2026-08-26). Cláusulas compartidas — ver
	// jb3_geo_tax_query_pais() en jb3-geo-targeting.php. OJO: la condición es
	// solo function_exists(), NO "$pais &&" — jb3_geo_tax_query_pais('') ya
	// devuelve la cláusula que no matchea nada (fail-closed); si acá se
	// saltara la llamada cuando $pais está vacío, un visitante sin país
	// detectado vería contenido restringido sin filtro alguno (bug real,
	// corregido en la auditoría pre-producción).
	if ( function_exists( 'jb3_geo_tax_query_pais' ) ) {
		$tax_query = array_merge( $tax_query, jb3_geo_tax_query_pais( $pais ) );
	}

	$query = new WP_Query( array(
		'post_type'      => $tipos,
		'post__not_in'   => array( $post_id ),
		'tax_query'      => $tax_query,
		'post_status'    => 'publish',
		// Posters (Películas/Series/Prod. Originales): 6 (2026-10-01; antes 4,
		// y antes 10) — regla del usuario: SIEMPRE filas completas, nunca un
		// poster suelto en la última fila. 6 en una fila en desktop, 4 en
		// notebooks chicas, 6 en tablet (2×3), 4 en celular (2×2) — ver
		// .jb3-relacionados--6 en style.css. Se probó con 8 en una fila: en
		// notebooks los posters quedaban de 116-145px, demasiado chicos.
		// Thumbs (Op. Comerciales): 4, como antes.
		'posts_per_page' => $es_poster ? 6 : 4,
		// Al azar (antes 'date' DESC, pedido 2026-10-01): que no salgan
		// siempre los 4 últimos cargados. ORDER BY RAND() no pesa con el
		// volumen de contenido de este sitio (cientos de posts, no miles).
		// OJO: con la caché de página de LiteSpeed en producción, el HTML
		// queda guardado — la selección cambia cada vez que se regenera la
		// caché de esa página, no en cada visita.
		'orderby'        => 'rand',
		'no_found_rows'  => true,
	) );

	if ( ! $query->posts ) {
		return '';
	}

	// $post->post_type acá solo decide poster-vs-thumb en
	// jb3_relacionados_modulo() — todo lo que trae $query->posts es
	// siempre de la misma familia que $tipos de arriba, da lo mismo cuál
	// tipo puntual se pase.
	return $es_poster
		? jb3_relacionados_modulo( $post->post_type, $query->posts, 'Te puede interesar', 'etiquetas', 6, 'jb3-relacionados--6' )
		: jb3_relacionados_modulo( $post->post_type, $query->posts, 'Te puede interesar', 'etiquetas' );
}
