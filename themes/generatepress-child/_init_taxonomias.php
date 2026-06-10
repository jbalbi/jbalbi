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



?>