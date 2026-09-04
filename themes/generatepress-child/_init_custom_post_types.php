<?php
defined( 'ABSPATH' ) || exit;

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
	// Posición fija (no la asignada automáticamente por WP) — sin esto, los
	// 6 CPT de acá abajo se numeran dinámicamente a partir de 26 en el orden
	// en que se registran, y jb3-members (add_menu_page con posición fija
	// 30, ver class-admin.php) puede chocar con la posición que le tocó a
	// alguno de ellos, quedando intercalado en el medio de la lista de CPT
	// en vez de después de todos — reportado en vivo 2026-08-31. 26-31 para
	// los 6 CPT, jb3-members pasa a 32 (después de todos).
	'menu_position'			=> 26,
	'menu_icon'				=> 'dashicons-editor-video',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	// Sin archivo nativo: /peliculas/ queda libre para una Página propia
	// (mismo motivo en los otros 5 CPT de acá abajo). Los permalinks de
	// cada película individual (/peliculas/nombre-pelicula/) NO se tocan —
	// esos salen del 'rewrite' de abajo, independiente de has_archive.
	'has_archive'			=> false,
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
	'menu_position'			=> 27,
	'menu_icon'				=> 'dashicons-video-alt2',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	'has_archive'			=> false,
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
	'menu_position'			=> 28,
	'menu_icon'				=> 'dashicons-star-filled',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	'has_archive'			=> false,
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
	'featured_image'		=>  'Miniatura de la Op. Comercial',
	'set_featured_image'	=>  'Establecer miniatura',
	'remove_featured_image'	=>  'Remover miniatura',
	'use_featured_image'	=>  'Usar como miniatura',
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
	'menu_position'			=> 29,
	'menu_icon'				=> 'dashicons-chart-bar',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	'has_archive'			=> false,
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
	'all_items'				=>  'Todos los videos',
	'add_new_item'			=>  'Agregar nuevo video',
	'add_new'				=>  'Agregar nuevo',
	'new_item'				=>  'Nuevo video',
	'edit_item'				=>  'Editar video',
	'update_item'			=>  'Actualizar video',
	'view_item'				=>  'Ver video',
	'view_items'			=>  'Ver videos',
	'search_items'			=>  'Buscar video',
	'not_found'				=>  'No hay videos',
	'not_found_in_trash'	=>  'No hay videos en la papelera',
	'featured_image'		=>  'Miniatura del video',
	'set_featured_image'	=>  'Establecer miniatura',
	'remove_featured_image'	=>  'Remover miniatura',
	'use_featured_image'	=>  'Usar como miniatura',
	'insert_into_item'		=>  'Insertar en el video',
	'uploaded_to_this_item'	=>  'Subida a este video',
	'items_list'			=>  'Lista de videos',
	'items_list_navigation'	=>  'Navegacion de lista de videos',
	'filter_items_list'		=>  'Filtrar lista de videos'
);
$args_videos = array(
	'labels'				=> $labels_videos,
	'public'				=> true,
	'show_in_rest'			=> true,
	'menu_position'			=> 30,
	'menu_icon'				=> 'dashicons-format-video',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	'has_archive'			=> false,
	'rewrite'				=> array( 'slug' => 'videos' ),
);
register_post_type( 'videos', $args_videos );

// CPT para Integraciones de Marca
$labels_integraciones = array(
	'name'					=>  'Integraciones',
	'singular_name'			=>  'Integración',
	'menu_name'				=>  'Integraciones',
	'name_admin_bar'		=>  'Integraciones',
	'archives'				=>  'Listado de integraciones',
	'attributes'			=>  'Atributos de integraciones',
	'parent_item_colon'		=>  'Integración superior:',
	'all_items'				=>  'Todas las integraciones',
	'add_new_item'			=>  'Agregar nueva integración',
	'add_new'				=>  'Agregar nueva',
	'new_item'				=>  'Nueva integración',
	'edit_item'				=>  'Editar integración',
	'update_item'			=>  'Actualizar integración',
	'view_item'				=>  'Ver integración',
	'view_items'			=>  'Ver integraciones',
	'search_items'			=>  'Buscar integración',
	'not_found'				=>  'No hay integraciones',
	'not_found_in_trash'	=>  'No hay integraciones en la papelera',
	'featured_image'		=>  'Miniatura de la integración',
	'set_featured_image'	=>  'Establecer miniatura',
	'remove_featured_image'	=>  'Remover miniatura',
	'use_featured_image'	=>  'Usar como miniatura',
	'insert_into_item'		=>  'Insertar en la integración',
	'uploaded_to_this_item'	=>  'Subida a esta integración',
	'items_list'			=>  'Lista de integraciones',
	'items_list_navigation'	=>  'Navegacion de lista de integraciones',
	'filter_items_list'		=>  'Filtrar lista de integraciones'
);
$args_integraciones = array(
	'labels'				=> $labels_integraciones,
	'public'				=> true,
	'show_in_rest'			=> true,
	'menu_position'			=> 31,
	'menu_icon'				=> 'dashicons-megaphone',
	'supports'				=> array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'post-formats' ),
	'has_archive'			=> false,
	'rewrite'				=> array( 'slug' => 'integraciones' ),
);
register_post_type( 'integraciones', $args_integraciones );

// CPT para páginas de Canal (AXN, Sony Channel, etc.) — antes eran Páginas
// normales con la plantilla "Canal" (page-canal.php), agrupadas con el
// resto de las Páginas del sitio sin forma de distinguirlas fácil en el
// admin. El vínculo con el término de la taxonomía "canales" (que sigue
// guardando logo/países/redes, sin cambios) sigue siendo el campo ACF
// puntero "canal" — no se pasó a asignación nativa de taxonomía a
// propósito: ese mismo campo se usa también en páginas sueltas que NO son
// la página principal de un canal (ej. una página secundaria de
// contenido), y una asignación nativa solo en este CPT hubiera obligado a
// mantener dos mecanismos de lectura de visibilidad en paralelo.
// Slug interno "pagina_canal" (NO "canales"): coincidir con el slug de la
// taxonomía generaría colisión de query var/rewrite entre taxonomía y CPT.
// Sin 'taxonomies' (ni "canales" ni "pais"): sin "pais" registrada acá, el
// panel nativo de País ni aparece para este CPT — no hace falta repetir el
// hack de _init_taxonomias.php que lo oculta a mano para "page" (ese sigue
// haciendo falta ahí, sin tocar).
$labels_pagina_canal = array(
	'name'					=>  'Canales',
	'singular_name'			=>  'Canal',
	'menu_name'				=>  'Canales',
	'name_admin_bar'		=>  'Canal',
	'archives'				=>  'Listado de canales',
	'attributes'			=>  'Atributos de canal',
	'parent_item_colon'		=>  'Canal superior:',
	'all_items'				=>  'Todos los canales',
	'add_new_item'			=>  'Agregar nuevo canal',
	'add_new'				=>  'Agregar nuevo',
	'new_item'				=>  'Nuevo canal',
	'edit_item'				=>  'Editar canal',
	'update_item'			=>  'Actualizar canal',
	'view_item'				=>  'Ver canal',
	'view_items'			=>  'Ver canales',
	'search_items'			=>  'Buscar canal',
	'not_found'				=>  'No hay canales',
	'not_found_in_trash'	=>  'No hay canales en la papelera',
	'insert_into_item'		=>  'Insertar en el canal',
	'uploaded_to_this_item'	=>  'Subida a este canal',
	'items_list'			=>  'Lista de canales',
	'items_list_navigation'	=>  'Navegacion de lista de canales',
	'filter_items_list'		=>  'Filtrar lista de canales'
);
$args_pagina_canal = array(
	'labels'				=> $labels_pagina_canal,
	'public'				=> true,
	'show_in_rest'			=> true,
	// 33: jb3-members ya ocupa el 32 (ver class-admin.php) — este CPT va
	// después de todos, mismo criterio que la posición fija de los otros 6.
	'menu_position'			=> 33,
	'menu_icon'				=> 'dashicons-networking',
	'supports'				=> array( 'title', 'editor', 'revisions' ),
	'has_archive'			=> false,
	// false (no el 'slug' => 'canal' automático de siempre): la URL de cada
	// canal se mantiene PLANA (/axn/, igual que hoy con la Página vieja),
	// pedido explícito 2026-09-02. WordPress no genera nada solo con esto —
	// la resolución real corre por el hook pre_get_posts de más abajo, que
	// intercepta la URL dinámicamente contra la base (sin lista de slugs a
	// mano, así un canal nuevo cargado desde el editor funciona solo).
	'rewrite'				=> false,
);
register_post_type( 'pagina_canal', $args_pagina_canal );

}
add_action( 'init', 'custom_post_types' );

// ─── URLs PLANAS PARA "pagina_canal" (sin "/canal/" ni "/canales/" adelante) ──
// El CPT se registró con 'rewrite' => false arriba (nada automático) — en
// vez de una lista de slugs a mano (lo que había acá antes, descartado
// 2026-09-02: obligaba a tocar código cada vez que se cargaba un canal
// nuevo desde el editor, algo que un editor de contenido no puede hacer
// solo), esto resuelve la URL plana DE FORMA DINÁMICA, contra la base real.
// Funciona aprovechando la propia regla "atrapa-todo" que trae WordPress
// para cualquier URL de un solo segmento que no matchee ninguna regla más
// específica. OJO, esa regla NO siempre pone el slug en "pagename" — se
// confirmó probando el request real (2026-09-02): con este sitio (varios
// CPT con su propio permastruct antes en la lista) la regla que termina
// matchando es la genérica de "post individual", que pone el slug en
// "name", no en "pagename" — por eso se revisan los dos, cubre cualquiera
// de las dos formas en que WordPress puede resolver un slug suelto.
// Acá se intercepta esa query ANTES de que se ejecute (pre_get_posts,
// todavía a tiempo de cambiarla) y se revisa si en realidad hay un post
// "pagina_canal" publicado con ese slug — si lo hay, la consulta se
// redirige a ese CPT; si no, sigue su curso normal (una Página/Entrada
// real, o 404 genuino si no existe nada). Sin lista que mantener, sin
// flush_rewrite_rules() necesario para que un canal nuevo funcione — el
// editor crea el post, le pone su slug, y ya está online.
add_action( 'pre_get_posts', function ( WP_Query $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$slug = $query->get( 'pagename' ) ?: $query->get( 'name' );
	if ( '' === $slug || str_contains( $slug, '/' ) ) {
		return; // vacío, o con más de un segmento (ej. /pais/algo/) — no es candidato a canal.
	}

	$canal = get_page_by_path( $slug, OBJECT, 'pagina_canal' );
	if ( ! $canal ) {
		return; // no hay ningun canal con ese slug — se deja que WP siga con su resolución normal.
	}

	$query->set( 'post_type', 'pagina_canal' );
	$query->set( 'pagina_canal', $slug );
	$query->set( 'pagename', '' );
	$query->set( 'name', $slug );
} );

// get_permalink()/the_permalink() de un post 'pagina_canal' generarían por
// default algo tipo "?pagina_canal=axn" (al no tener 'rewrite' propio) —
// esto los reescribe a la URL plana real, para que el link que arma
// WordPress coincida con la regla de arriba.
// $leavename: cuando WordPress arma el link de "vista previa" para el
// editor (get_sample_permalink, tanto editor clásico como de bloques) pide
// este link con $leavename=true para dejar el token "%postname%" SIN
// sustituir — así detecta que hay un lugar editable y muestra el control
// de slug. Si acá siempre devolvemos el slug ya resuelto (como antes, sin
// mirar $leavename) WordPress nunca encuentra el token y directamente
// oculta el control de edición del slug en el editor (confirmado 2026-09-02:
// wp-admin/includes/post.php linea ~1582, "Permalinks without a post/page
// name placeholder don't have anything to edit") — la Edición Rápida sigue
// funcionando porque no pasa por get_sample_permalink.
add_filter( 'post_type_link', function ( $link, $post, $leavename ) {
	if ( 'pagina_canal' !== $post->post_type ) {
		return $link;
	}
	return home_url( '/' . ( $leavename ? '%postname%' : $post->post_name ) . '/' );
}, 10, 3 );


?>
