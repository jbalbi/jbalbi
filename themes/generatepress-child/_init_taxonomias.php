<?php


function crear_taxonomia_canales() {
    // Etiquetas de la taxonomía
    $labels = array(
        'name'              => 'Canales',
        'singular_name'     => 'Canal',
        'search_items'      => 'Buscar Canales',
        'all_items'         => 'Todos los Canales',
        'parent_item'       => 'Canal Padre',
        'parent_item_colon' => 'Canal Padre:',
        'edit_item'         => 'Editar Canal',
        'update_item'       => 'Actualizar Canal',
        'add_new_item'      => 'Agregar Nuevo Canal',
        'new_item_name'     => 'Nuevo Nombre del Canal',
        'menu_name'         => 'Canales',
    );

    // Argumentos de la taxonomía
    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        //'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,        
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'canales' ),
    );

    // Registrar la taxonomía para los CPTs específicos
    register_taxonomy( 'canales', array( 'peliculas', 'series', 'prod_originales', 'op_comerciales', 'videos' ), $args );
   
}

add_action( 'init', 'crear_taxonomia_canales' );


function crear_taxonomia_paises() {
    $labels = array(
        'name'          => 'Países',
        'singular_name' => 'País',
        'search_items'  => 'Buscar Países',
        'all_items'     => 'Todos los Países',
        'edit_item'     => 'Editar País',
        'update_item'   => 'Actualizar País',
        'add_new_item'  => 'Agregar País',
        'new_item_name' => 'Nuevo País',
        'menu_name'     => 'Países',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => false, // Sin URLs /pais/argentina/ — solo filtrado interno
    );

    register_taxonomy(
        'pais',
        array( 'peliculas', 'series', 'prod_originales', 'op_comerciales', 'videos', 'post', 'page' ),
        $args
    );
}

add_action( 'init', 'crear_taxonomia_paises' );

// Oculta el botón "Agregar" de las taxonomías en el editor — los términos se gestionan desde sus menús de admin.
add_action( 'admin_head', function() {
    echo '<style>
        /* Editor clásico */
        #pais-adder, #canales-adder { display: none; }
        /* Editor de bloques (Gutenberg) */
        .editor-post-taxonomies__hierarchical-terms-add { display: none !important; }
    </style>';
} );


?>