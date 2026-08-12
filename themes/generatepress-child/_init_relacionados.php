<?php
defined( 'ABSPATH' ) || exit;

// ─── RELACIONADOS: contenido cruzado vía ACF Relationship, automático ───────
// Fuente de datos: el campo ACF "contenido_relacionado" (Relationship),
// pero SOLO existe un grupo de campos con ese nombre, ubicado únicamente en
// Op. Comerciales/Videos/Integraciones (ver Custom Fields en el admin) — no
// hay ningún campo equivalente del lado de Películas/Series/Prod.
// Originales/Páginas/Entradas. El campo está marcado como "bidireccional"
// en ACF, pero su bidirectional_target quedó mal configurado (apunta a sí
// mismo, no a un campo del otro lado que ni siquiera existe) — detectado
// 2026-08-11: por eso nunca se reflejó solo como prometía el comentario
// viejo de esta función. En vez de depender de arreglar esa config de ACF,
// se resuelve la relación "de vuelta" con una consulta directa: cualquier
// Op. Comercial/Video/Integración cuyo PROPIO campo contenido_relacionado
// incluya a este post cuenta como relacionado, leyendo el postmeta crudo
// (evita el viaje de ida y vuelta de get_field() por cada post candidato).
//
// Se agrupa por tipo de contenido y se arma UN MÓDULO POR TIPO que tenga
// algo cargado ("Oportunidades Comerciales", "Videos", etc.) — sin esto,
// cargar 5-6 shortcodes a mano en cada película/serie/etc. del catálogo
// entero es autodestructivo (se olvida, queda inconsistente).
// jb3_relacionados_auto() se llama sola desde cada single-{cpt}.php,
// después de the_content() — cero trabajo por página.
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
// defecto uno por tipo, pero jb3_etiquetas_auto() (_init_etiquetas.php) le
// pasa el suyo propio ("etiquetas") para que el ancla de la subnav quede
// prolija en vez del genérico "relacionados-{tipo}".
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

	$ids = array();

	// Lado "propio": lo que este post cargó en su propio campo (hoy solo
	// existe en Op. Comerciales/Videos/Integraciones — ver comentario de
	// arriba). En Películas/Series/Prod. Originales esto siempre viene
	// vacío porque el campo no está ahí, y se completa con la consulta de
	// abajo.
	$relacionados = get_field( 'contenido_relacionado', $post_id );
	if ( ! empty( $relacionados ) ) {
		// Normaliza a IDs sin importar el "Return Format" configurado en
		// ACF para el campo (Post Object devuelve WP_Post, ID devuelve int).
		$ids = array_map(
			function ( $item ) {
				return is_object( $item ) ? $item->ID : (int) $item;
			},
			(array) $relacionados
		);
	}

	// Lado "reflejo": cualquier Op. Comercial/Video/Integración que haya
	// elegido a ESTE post en su propio campo — ver comentario de arriba
	// sobre por qué no se puede depender del "bidireccional" de ACF acá.
	global $wpdb;
	$reflejo_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'contenido_relacionado' AND meta_value LIKE %s",
		'%"' . $wpdb->esc_like( (string) $post_id ) . '"%'
	) );
	$ids = array_merge( $ids, array_map( 'intval', $reflejo_ids ) );

	$ids = array_values( array_unique( $ids ) );
	if ( ! $ids ) {
		return '';
	}

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
