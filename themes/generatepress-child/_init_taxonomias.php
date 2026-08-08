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
        // Sin permastruct: la página de cada canal ahora es una Página real
        // de WordPress (plantilla "Canal", ver page-canal.php) vinculada al
        // término por un campo ACF — el término en sí no tiene URL propia.
        'rewrite'           => false,
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
        array( 'peliculas', 'series', 'prod_originales', 'op_comerciales', 'videos', 'integraciones', 'post', 'page' ),
        $args
    );
}

add_action( 'init', 'crear_taxonomia_paises' );

// Tags libres (NO lista cerrada como canales/pais — el editor escribe y
// crea géneros al vuelo, con autocompletado de lo ya usado) para agrupar
// Películas/Series/Prod. Originales por género (Terror, Comedia...). Usada
// por jb3_genero_auto() en _init_genero.php para armar el módulo "Más
// películas de Terror" (mismo tipo + mismo canal que el contenido actual).
function crear_taxonomia_genero() {
    $labels = array(
        'name'          => 'Géneros',
        'singular_name' => 'Género',
        'search_items'  => 'Buscar Géneros',
        'all_items'     => 'Todos los Géneros',
        'edit_item'     => 'Editar Género',
        'update_item'   => 'Actualizar Género',
        'add_new_item'  => 'Agregar Género',
        'new_item_name' => 'Nuevo Género',
        'menu_name'     => 'Géneros',
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => false, // Sin URL propia, solo filtrado interno (mismo criterio que "pais").
    );

    register_taxonomy( 'genero', array( 'peliculas', 'series', 'prod_originales' ), $args );
}

add_action( 'init', 'crear_taxonomia_genero' );

// Oculta el botón "Agregar" de las taxonomías en el editor — los términos se gestionan desde sus menús de admin.
add_action( 'admin_head', function() {
    echo '<style>
        /* Editor clásico */
        #pais-adder, #canales-adder { display: none; }
        /* Editor de bloques (Gutenberg) */
        .editor-post-taxonomies__hierarchical-terms-add { display: none !important; }
    </style>';
} );

// ─── PÁGINAS DE CANAL: ocultar el panel nativo "Países" ─────────────────────
// La restricción por país de un canal vive en el campo ACF "paises" del
// TÉRMINO de "canales" (jb3_geo_page_canal_visible() en jb3-geo-targeting.php
// la lee de ahí) — el panel nativo de la taxonomía "pais" es una restricción
// aparte, directa sobre la Página, que se SUMA (AND) a esa en vez de
// reemplazarla. Usarlo en una página de canal produciría una restricción
// más angosta de lo esperado sin que se note por qué. Se oculta solo para
// páginas ya guardadas con la plantilla "Canal" — el resto de las Páginas
// (que no son de canal) lo conservan, por si alguna necesita restringirse
// por país de forma independiente. En una Página nueva (todavía sin
// guardar con esa plantilla) el panel sigue visible hasta el primer save —
// mismo comportamiento que ya tiene el campo ACF "Canal" del propio template.
add_action( 'enqueue_block_editor_assets', function () {
    $screen = get_current_screen();
    if ( ! $screen || 'page' !== $screen->post_type ) {
        return;
    }

    $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
    if ( ! $post_id || 'page-canal.php' !== get_page_template_slug( $post_id ) ) {
        return;
    }

    wp_add_inline_script(
        'wp-edit-post',
        "wp.domReady( function () { wp.data.dispatch( 'core/edit-post' ).removeEditorPanel( 'taxonomy-panel-pais' ); } );"
    );
} );


?>