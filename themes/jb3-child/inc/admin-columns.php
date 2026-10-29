<?php
defined( 'ABSPATH' ) || exit;

// ─── COLUMNA DE MINIATURA EN LOS LISTADOS DE ADMIN ─────────────────────────
// Miniatura de la Imagen Destacada como primera columna en los listados
// ("Todas las películas", etc.) de los CPT que la usan — para identificar
// cada fila a simple vista sin tener que abrir cada una.
add_action( 'init', function () {
	foreach ( [ 'peliculas', 'series', 'prod_originales', 'op_comerciales', 'videos', 'integraciones' ] as $post_type ) {
		add_filter( "manage_{$post_type}_posts_columns", 'jb3_add_thumb_column' );
		add_action( "manage_{$post_type}_posts_custom_column", 'jb3_render_thumb_column', 10, 2 );
	}
} );

// Sin esto la columna queda tan ancha como el resto (Título, Canales, etc.),
// dejando un montón de aire vacío entre la miniatura y el título.
add_action( 'admin_head', function () {
	echo '<style>.column-jb3_thumb{width:80px;}</style>';
} );

function jb3_add_thumb_column( $columns ) {
	$new = [];
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['jb3_thumb'] = 'Miniatura';
		}
		$new[ $key ] = $label;
	}
	return $new;
}

// Películas/Series/Prod. Originales usan la Imagen Destacada como poster
// (vertical) — el resto (Op. Comerciales/Videos/Integraciones) la usa como
// thumb de listado (horizontal). Mismo campo nativo en los dos casos, solo
// cambia la proporción con la que se recorta acá en el admin.
const JB3_POSTER_POST_TYPES = [ 'peliculas', 'series', 'prod_originales' ];

function jb3_render_thumb_column( $column, $post_id ) {
	if ( 'jb3_thumb' !== $column ) {
		return;
	}

	if ( ! has_post_thumbnail( $post_id ) ) {
		echo '&mdash;';
		return;
	}

	$vertical = in_array( get_post_type( $post_id ), JB3_POSTER_POST_TYPES, true );
	$size     = $vertical ? [ 50, 75 ] : [ 60, 40 ];
	$style    = sprintf( 'width:%dpx;height:%dpx;object-fit:cover;display:block;margin-right:10px;', $size[0], $size[1] );

	echo get_the_post_thumbnail( $post_id, $size, [ 'style' => $style ] );
}
