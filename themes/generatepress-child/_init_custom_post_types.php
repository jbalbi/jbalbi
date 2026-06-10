<?php

function custom_post_types() {

// CPT para Películas
$labels_peliculas = array(
	'name'					=>  'Películas',
	'singular_name'			=>  'Película',
	'menu_name'				=>  'Películas',
	'name_admin_bar'		=>  'Películas',
	'archives'				=>  'Listado de películas',
	'attributes'			=>  'Atributos de películas',
	'parent_item_colon'		=>  'Película superior:',
	'all_items'				=>  'Todas las películas',
	'add_new_item'			=>  'Agregar nueva película',
	'add_new'				=>  'Agregar nueva',
	'new_item'				=>  'Nueva película',
	'edit_item'				=>  'Editar película',
	'update_item'			=>  'Actualizar película',
	'view_item'				=>  'Ver película',
	'view_items'			=>  'Ver películas',
	'search_items'			=>  'Buscar película',
	'not_found'				=>  'No hay películas',
	'not_found_in_trash'	=>  'No hay películas en la papelera',
	'featured_image'		=>  'Poster de la película',
	'set_featured_image'	=>  'Establecer poster',
	'remove_featured_image'	=>  'Remover poster',
	'use_featured_image'	=>  'Usar como poster',
	'insert_into_item'		=>  'Insertar en la película',
	'uploaded_to_this_item'	=>  'Subida a esta película',
	'items_list'			=>  'Lista de películas',
	'items_list_navigation'	=>  'Navegacion de lista de películas',
	'filter_items_list'		=>  'Filtrar lista de películas'
);
$args_peliculas = array(
	'labels'				=> $labels_peliculas,
	'public'				=> true,
	'show_in_rest'			=> true,
	'menu_icon'				=> 'dashicons-editor-video',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	//'taxonomies'			=> array( 'category', 'post_tag' ),
	'has_archive'			=> true,
	'rewrite'				=> array( 'slug' => 'peliculas' ),
);
register_post_type( 'peliculas', $args_peliculas );

// CPT para Series
$labels_series = array(
	'name'					=>  'Series',
	'singular_name'			=>  'Serie',
	'menu_name'				=>  'Series',
	'name_admin_bar'		=>  'Series',
	'archives'				=>  'Listado de series',
	'attributes'			=>  'Atributos de series',
	'parent_item_colon'		=>  'Serie superior:',
	'all_items'				=>  'Todas las series',
	'add_new_item'			=>  'Agregar nueva serie',
	'add_new'				=>  'Agregar nueva',
	'new_item'				=>  'Nueva serie',
	'edit_item'				=>  'Editar serie',
	'update_item'			=>  'Actualizar serie',
	'view_item'				=>  'Ver serie',
	'view_items'			=>  'Ver series',
	'search_items'			=>  'Buscar serie',
	'not_found'				=>  'No hay series',
	'not_found_in_trash'	=>  'No hay series en la papelera',
	'featured_image'		=>  'Poster de la serie',
	'set_featured_image'	=>  'Establecer poster',
	'remove_featured_image'	=>  'Remover poster',
	'use_featured_image'	=>  'Usar como poster',
	'insert_into_item'		=>  'Insertar en la serie',
	'uploaded_to_this_item'	=>  'Subida a esta serie',
	'items_list'			=>  'Lista de series',
	'items_list_navigation'	=>  'Navegacion de lista de series',
	'filter_items_list'		=>  'Filtrar lista de series'
);
$args_series = array(
	'labels'				=> $labels_series,
	'public'				=> true,
	'show_in_rest'			=> true,
	'menu_icon'				=> 'dashicons-video-alt2',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	//'taxonomies'			=> array( 'category', 'post_tag' ),
	'has_archive'			=> true,
	'rewrite'				=> array( 'slug' => 'series' ),
);
register_post_type( 'series', $args_series );

// CPT para Prod. Originales
$labels_prod_originales = array(
	'name'					=>  'Prod. originales',
	'singular_name'			=>  'Prod. original',
	'menu_name'				=>  'Prod. originales',
	'name_admin_bar'		=>  'Prod. originales',
	'archives'				=>  'Listado de Prod. originales',
	'attributes'			=>  'Atributos de Prod. originales',
	'parent_item_colon'		=>  'Prod. original superior:',
	'all_items'				=>  'Todas las Prod. originales',
	'add_new_item'			=>  'Agregar nueva Prod. original',
	'add_new'				=>  'Agregar nueva',
	'new_item'				=>  'Nueva Prod. original',
	'edit_item'				=>  'Editar Prod. original',
	'update_item'			=>  'Actualizar Prod. original',
	'view_item'				=>  'Ver Prod. original',
	'view_items'			=>  'Ver Prod. originales',
	'search_items' 			=>  'Buscar Prod. original',
	'not_found'				=>  'No hay Prod. originales',
	'not_found_in_trash'	=>  'No hay Prod. originales en la papelera',
	'featured_image'		=>  'Poster de la Prod. original',
	'set_featured_image'	=>  'Establecer poster',
	'remove_featured_image' =>  'Remover poster',
	'use_featured_image'	=>  'Usar como poster',
	'insert_into_item'		=>  'Insertar en la Prod. original',
	'uploaded_to_this_item'	=>  'Subida a esta Prod. original',
	'items_list'			=>  'Lista de Prod. originales',
	'items_list_navigation'	=>  'Navegacion de lista de Prod. originales',
	'filter_items_list'		=>  'Filtrar lista de Prod. originales'
);
$args_prod_originales = array(
	'labels'				=> $labels_prod_originales,
	'public'				=> true,
	'show_in_rest'			=> true,
	'menu_icon'				=> 'dashicons-star-filled',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	//'taxonomies'			=> array( 'category', 'post_tag' ),
	'has_archive'			=> true,
	'rewrite' 				=> array( 'slug' => 'prod-originales' ),
);
register_post_type( 'prod_originales', $args_prod_originales );

// CPT para Op. Comerciales
$labels_op_comerciales = array(
	'name'					=>  'Op. Comerciales',
	'singular_name'			=>  'Op. Comercial',
	'menu_name'				=>  'Op. Comerciales',
	'name_admin_bar'		=>  'Op. Comerciales',
	'archives'				=>  'Listado de Op. Comerciales',
	'attributes'			=>  'Atributos de Op. Comerciales',
	'parent_item_colon'		=>  'Op. Comercial superior:',
	'all_items'				=>  'Todas las Op. Comerciales',
	'add_new_item'			=>  'Agregar nueva Op. Comercial',
	'add_new'				=>  'Agregar nueva',
	'new_item'				=>  'Nueva Op. Comercial',
	'edit_item'				=>  'Editar Op. Comercial',
	'update_item'			=>  'Actualizar Op. Comercial',
	'view_item'				=>  'Ver Op. Comercial',
	'view_items'			=>  'Ver Op. Comerciales',
	'search_items'			=>  'Buscar Op. Comercial',
	'not_found'				=>  'No hay Op. Comerciales',
	'not_found_in_trash'	=>  'No hay Op. Comerciales en la papelera',
	'featured_image'		=>  'Poster de la Op. Comercial',
	'set_featured_image'	=>  'Establecer poster',
	'remove_featured_image'	=>  'Remover poster',
	'use_featured_image'	=>  'Usar como poster',
	'insert_into_item'		=>  'Insertar en la Op. Comercial',
	'uploaded_to_this_item'	=>  'Subida a esta Op. Comercial',
	'items_list'			=>  'Lista de Op. Comerciales',
	'items_list_navigation'	=>  'Navegacion de lista de Op. Comerciales',
	'filter_items_list'		=>  'Filtrar lista de Op. Comerciales'
);
$args_op_comerciales = array(
	'labels'				=> $labels_op_comerciales,
	'public'				=> true,
	'show_in_rest'			=> true,
	'menu_icon'				=> 'dashicons-chart-bar',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	//'taxonomies'			=> array( 'category', 'post_tag' ),
	'has_archive'			=> true,
	'rewrite'				=> array( 'slug' => 'op-comerciales' ),
);
register_post_type( 'op_comerciales', $args_op_comerciales );

// CPT para Videos
$labels_videos = array(
	'name'					=>  'Videos',
	'singular_name'			=>  'Video',
	'menu_name'				=>  'Videos',
	'name_admin_bar'		=>  'Videos',
	'archives'				=>  'Listado de videos',
	'attributes'			=>  'Atributos de videos',
	'parent_item_colon'		=>  'Video superior:',
	'all_items'				=>  'Todas las videos',
	'add_new_item'			=>  'Agregar nueva video',
	'add_new'				=>  'Agregar nueva',
	'new_item'				=>  'Nueva video',
	'edit_item'				=>  'Editar video',
	'update_item'			=>  'Actualizar video',
	'view_item'				=>  'Ver video',
	'view_items'			=>  'Ver videos',
	'search_items'			=>  'Buscar video',
	'not_found'				=>  'No hay videos',
	'not_found_in_trash'	=>  'No hay videos en la papelera',
	'featured_image'		=>  'Poster de la video',
	'set_featured_image'	=>  'Establecer poster',
	'remove_featured_image'	=>  'Remover poster',
	'use_featured_image'	=>  'Usar como poster',
	'insert_into_item'		=>  'Insertar en la video',
	'uploaded_to_this_item'	=>  'Subida a esta video',
	'items_list'			=>  'Lista de videos',
	'items_list_navigation'	=>  'Navegacion de lista de videos',
	'filter_items_list'		=>  'Filtrar lista de videos'
);
$args_videos = array(
	'labels'				=> $labels_videos,
	'public'				=> true,
	'show_in_rest'			=> true,
	'menu_icon'				=> 'dashicons-format-video',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	//'taxonomies'			=> array( 'category', 'post_tag' ),
	'has_archive'			=> true,
	'rewrite'				=> array( 'slug' => 'videos' ),
);
register_post_type( 'videos', $args_videos );

}
add_action( 'init', 'custom_post_types' );


?>
