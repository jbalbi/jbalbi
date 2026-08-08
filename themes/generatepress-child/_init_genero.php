<?php
defined( 'ABSPATH' ) || exit;

// ─── "MÁS CONTENIDO DE ESTE TAG": automático, cruza tipos + mismo canal ─────
// Distinto de jb3_relacionados_auto() (_init_relacionados.php): ahí la
// relación la carga un editor a mano vía ACF, acá sale sola de la taxonomía
// "genero" (tags libres, ver _init_taxonomias.php) + el/los canal(es) del
// propio contenido — sin curar nada. Única forma de cruzar Películas/Series/
// Prod. Originales entre sí (esos 3 tipos ya no usan el campo ACF de
// relacionados) — un tag puede ser un género (Terror) o un tema/franquicia
// (Spider-Man): "Más contenido de Spider-Man" puede traer película + serie
// + prod. original juntas, siempre que compartan el tag Y el canal. Si el
// contenido tiene varios tags, se arma UN MÓDULO POR TAG (no se mezclan).
// Llamar después de the_content() en single-peliculas/series/prod_originales.php.
//
// post_status explícito en 'publish': la despublicación programada
// (plugin jb3-content-expiration) pasa el post a Borrador solo cuando
// vence — alcanza con pedir 'publish' para que el vencido quede afuera,
// sin lógica de fechas propia acá.
function jb3_genero_auto( int $post_id ): string {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$tags = get_the_terms( $post_id, 'genero' );
	if ( empty( $tags ) || is_wp_error( $tags ) ) {
		return '';
	}

	$canales   = get_the_terms( $post_id, 'canales' );
	$canal_ids = ( $canales && ! is_wp_error( $canales ) ) ? wp_list_pluck( $canales, 'term_id' ) : array();

	$pais = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';

	// Los 3 tipos que usan tags — jb3_modulo_tipos_poster() ya es exactamente
	// esta misma lista, se reutiliza en vez de repetirla a mano.
	$tipos = function_exists( 'jb3_modulo_tipos_poster' )
		? jb3_modulo_tipos_poster()
		: array( 'peliculas', 'series', 'prod_originales' );

	$html = '';
	foreach ( $tags as $tag ) {
		$tax_query = array(
			array( 'taxonomy' => 'genero', 'field' => 'term_id', 'terms' => array( $tag->term_id ) ),
		);

		if ( $canal_ids ) {
			$tax_query[] = array( 'taxonomy' => 'canales', 'field' => 'term_id', 'terms' => $canal_ids );
		}

		// Mismo geo-targeting que el resto del sitio (ver jb3_modulo_query()
		// en _init_modulo.php) — fail-open si no hay país detectado.
		if ( $pais ) {
			$tax_query[] = array(
				'relation' => 'OR',
				array( 'taxonomy' => 'pais', 'operator' => 'NOT EXISTS' ),
				array( 'taxonomy' => 'pais', 'field' => 'slug', 'terms' => array( $pais ) ),
			);
			if ( function_exists( 'jb3_geo_canales_visibles' ) ) {
				$tax_query[] = array(
					'relation' => 'OR',
					array( 'taxonomy' => 'canales', 'operator' => 'NOT EXISTS' ),
					array( 'taxonomy' => 'canales', 'field' => 'term_id', 'terms' => jb3_geo_canales_visibles( $pais ) ),
				);
			}
		}

		$query = new WP_Query( array(
			'post_type'      => $tipos,
			'post__not_in'   => array( $post_id ),
			'tax_query'      => $tax_query,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		if ( ! $query->posts ) {
			continue;
		}

		$titulo = 'Más contenido de ' . $tag->name;
		// $post->post_type acá solo decide poster-vs-thumb en
		// jb3_relacionados_modulo() — los 3 tipos que pueden venir mezclados
		// en $query->posts son TODOS de la familia poster, da lo mismo cuál
		// de los tres se pase.
		$html .= jb3_relacionados_modulo( $post->post_type, $query->posts, $titulo, 'genero-' . $tag->term_id );
	}

	return $html;
}
