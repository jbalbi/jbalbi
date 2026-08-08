<?php
defined( 'ABSPATH' ) || exit;

// ─── RELACIONADOS: contenido cruzado vía ACF Relationship, automático ───────
// Fuente única de datos: el campo ACF "contenido_relacionado" (Relationship,
// bidireccional — cargar la relación de un lado alcanza, ACF la refleja
// sola del otro). Se agrupa por tipo de contenido y se arma UN MÓDULO POR
// TIPO que tenga algo cargado ("Películas Relacionadas", "Series
// Relacionadas", etc.) — sin esto, cargar 5-6 shortcodes a mano en cada
// película/serie/etc. del catálogo entero es autodestructivo (se olvida,
// queda inconsistente). jb3_relacionados_auto() se llama sola desde cada
// single-{cpt}.php, después de the_content() — cero trabajo por página.
//
// [jb3_sin_relacionados] en el contenido de un post puntual apaga el
// módulo SOLO ahí, por si alguna vez hace falta.
add_shortcode( 'jb3_sin_relacionados', '__return_empty_string' );

// Películas/series/prod. originales son pares entre sí — "Relacionadas" es
// correcto ahí. Videos/op. comerciales/integraciones NO son "relacionados"
// a la película/serie: son contenido que le pertenece (extras, materiales),
// por eso el título no lleva esa palabra — mismo texto que ya usan los
// módulos de la Home ([jb3_modulo titulo="Oportunidades Comerciales"], etc.)
/** Título por defecto de cada módulo, según el tipo de contenido agrupado. */
function jb3_relacionados_titulos_por_tipo(): array {
	return array(
		'peliculas'       => 'Películas Relacionadas',
		'series'          => 'Series Relacionadas',
		'prod_originales' => 'Producciones Originales Relacionadas',
		'op_comerciales'  => 'Oportunidades Comerciales',
		'videos'          => 'Videos',
		'integraciones'   => 'Integraciones de Marca',
		'page'            => 'Páginas Relacionadas',
		'post'            => 'Notas Relacionadas',
	);
}

// Un solo tipo por módulo → una sola familia de grilla (poster o thumb),
// no hace falta separar como en jb3_modulo_render(). $id opcional: por
// defecto uno por tipo, pero jb3_genero_auto() (_init_genero.php) puede
// necesitar varios módulos del MISMO tipo en la misma página (ej. "Más
// películas de Terror" + "Más películas de Acción"), ahí sí hace falta un
// id propio por módulo para no pisarse.
function jb3_relacionados_modulo( string $tipo, array $posts, string $titulo, string $id = '' ): string {
	if ( ! $posts ) {
		return '';
	}

	$id = $id ?: ( 'relacionados-' . $tipo );
	if ( function_exists( 'jb3_subnav_register' ) ) {
		jb3_subnav_register( $titulo, $id );
	}

	$es_poster = function_exists( 'jb3_modulo_tipos_poster' ) && in_array( $tipo, jb3_modulo_tipos_poster(), true );

	ob_start();
	?>
	<section class="jb3-modulo" id="<?php echo esc_attr( $id ); ?>">
		<h2 class="jb3-modulo__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<?php echo $es_poster ? jb3_grid_posters( $posts ) : jb3_grid_thumbs( $posts ); ?>
	</section>
	<?php
	return ob_get_clean();
}

/** Arma todos los módulos de relacionados de un post — llamar después de the_content() en cada single-{cpt}.php. */
function jb3_relacionados_auto( int $post_id ): string {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$post = get_post( $post_id );
	if ( $post && has_shortcode( $post->post_content, 'jb3_sin_relacionados' ) ) {
		return '';
	}

	$relacionados = get_field( 'contenido_relacionado', $post_id );
	if ( empty( $relacionados ) ) {
		return '';
	}

	// Normaliza a IDs sin importar el "Return Format" configurado en ACF
	// para el campo (Post Object devuelve WP_Post, ID devuelve int).
	$ids = array_map(
		function ( $item ) {
			return is_object( $item ) ? $item->ID : (int) $item;
		},
		(array) $relacionados
	);

	// Mismo geo-targeting que el resto del sitio: un relacionado no
	// disponible para el país del visitante no se lista (evita un link que
	// termina en 404).
	if ( function_exists( 'jb3_geo_post_visible' ) ) {
		$ids = array_values( array_filter( $ids, 'jb3_geo_post_visible' ) );
	}

	if ( ! $ids ) {
		return '';
	}

	$posts = get_posts( array(
		'post__in'       => $ids,
		'orderby'        => 'post__in', // Respeta el orden que el editor armó a mano en el campo Relationship.
		'posts_per_page' => -1,
		'post_type'      => 'any',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	) );

	$grupos = array();
	foreach ( $posts as $item ) {
		$grupos[ $item->post_type ][] = $item;
	}

	// Orden fijo de los módulos, no el orden en que se fueron eligiendo en
	// el campo ACF: primero lo que "pertenece" al contenido (Videos/Op.
	// Comerciales/Integraciones), al final lo que es genuinamente
	// relacionado entre pares (Películas/Series/Prod. Originales).
	$orden = array( 'videos', 'op_comerciales', 'integraciones', 'peliculas', 'series', 'prod_originales', 'page', 'post' );

	$titulos = jb3_relacionados_titulos_por_tipo();
	$html    = '';
	foreach ( $orden as $tipo ) {
		if ( empty( $grupos[ $tipo ] ) ) {
			continue;
		}
		$titulo = $titulos[ $tipo ] ?? ( ucfirst( $tipo ) . ' Relacionados' );
		$html  .= jb3_relacionados_modulo( $tipo, $grupos[ $tipo ], $titulo );
		unset( $grupos[ $tipo ] );
	}

	// Cualquier tipo no contemplado arriba (post type nuevo a futuro) igual
	// se muestra, al final, en vez de perderse en silencio.
	foreach ( $grupos as $tipo => $items ) {
		$titulo = $titulos[ $tipo ] ?? ( ucfirst( $tipo ) . ' Relacionados' );
		$html  .= jb3_relacionados_modulo( $tipo, $items, $titulo );
	}

	return $html;
}
