<?php
defined( 'ABSPATH' ) || exit;

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
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        // Sin permastruct: la página de cada canal es el CPT propio
        // "pagina_canal" (ver inc/custom-post-types.php) vinculado al
        // término por un campo ACF — el término en sí no tiene URL propia.
        'rewrite'           => false,
    );

    // Registrar la taxonomía para los CPTs específicos
    register_taxonomy( 'canales', array( 'peliculas', 'series', 'prod_originales', 'op_comerciales', 'videos', 'integraciones' ), $args );

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
// crea etiquetas al vuelo, con autocompletado de lo ya usado) para agrupar
// contenido por tag — a propósito NO se llama "género": no es solo género
// de película (Terror), también sirve para actor, franquicia (Spider-Man),
// categoría de paquete ("Nuevos Formatos" de Op. Comercial), o cualquier
// otra cosa que relacione contenido. Usada por jb3_etiquetas_auto() en
// inc/etiquetas.php para armar el módulo "Contenido Relacionado" — el
// ALCANCE del cruce (si mezcla tipos entre sí o solo el mismo tipo) lo
// decide esa función según la familia del post actual, no esta lista.
// Renombrada de "genero" a "etiquetas" el 2026-08-11 (ver migración de
// datos: UPDATE wp_term_taxonomy SET taxonomy='etiquetas' WHERE
// taxonomy='genero' — mismos term_id, mismas relaciones, no se perdió
// ningún tag ya cargado).
function crear_taxonomia_etiquetas() {
    $labels = array(
        'name'          => 'Etiquetas',
        'singular_name' => 'Etiqueta',
        'search_items'  => 'Buscar Etiquetas',
        'all_items'     => 'Todas las Etiquetas',
        'edit_item'     => 'Editar Etiqueta',
        'update_item'   => 'Actualizar Etiqueta',
        'add_new_item'  => 'Agregar Etiqueta',
        'new_item_name' => 'Nueva Etiqueta',
        'menu_name'     => 'Etiquetas',
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => false, // Sin URL propia, solo filtrado interno (mismo criterio que "pais").
    );

    register_taxonomy( 'etiquetas', array( 'peliculas', 'series', 'prod_originales', 'op_comerciales' ), $args );
}

add_action( 'init', 'crear_taxonomia_etiquetas' );

// Oculta el botón "Agregar" de las taxonomías en el editor — los términos se gestionan desde sus menús de admin.
add_action( 'admin_head', function() {
    echo '<style>
        /* Editor clásico */
        #pais-adder, #canales-adder { display: none; }
        /* Editor de bloques (Gutenberg) */
        .editor-post-taxonomies__hierarchical-terms-add { display: none !important; }
    </style>';
} );

// ─── CAMPO GALERÍA (ACF): solo agregar/quitar, nada más ────────────────────
// El campo de galería usado en "Las fotos del carrusel" (galeria-slider,
// etc.) trae de fábrica un desplegable de "Bulk actions" (ordenar por
// fecha/título) y un panel lateral con Título/Leyenda/Alt/Descripción que
// se abre al hacer click en una imagen — pedido explícito: solo hacen falta
// los botones de agregar y quitar imágenes, nada de eso. Quitar una imagen
// puntual sigue funcionando igual (la "X" de acf-gallery-remove sobre cada
// miniatura, que no es parte de ninguna de las dos cosas que se sacan acá).
// .acf-gallery-side ya arranca en width:0 (colapsado) — forzarlo siempre a
// 0 evita que el JS de ACF lo expanda al clickear una imagen, sin
// necesidad de tocar el JS del plugin.
add_action( 'admin_head', function() {
    echo '<style>
        .acf-gallery-sort { display: none !important; }
        .acf-gallery-side { width: 0 !important; overflow: hidden !important; }
    </style>';
} );


?>