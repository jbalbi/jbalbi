<?php

// ─── ESTILOS ──────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'generatepress_child_enqueue_styles' );
function generatepress_child_enqueue_styles() {
    wp_enqueue_style( 'generatepress-style', get_template_directory_uri() . '/style.css' );
    // filemtime() en vez de wp_get_theme()->get('Version'): la versión del
    // theme es un string fijo (nunca se toca en cada cambio de CSS), así
    // que el navegador podía quedarse sirviendo una copia vieja cacheada
    // después de cualquier edición — con filemtime() el query string
    // cambia solo cada vez que se guarda el archivo, sin depender de
    // acordarse de bumpear nada a mano (mismo criterio que ya usan
    // header-account.js/header-height.js más abajo).
    wp_enqueue_style(
        'generatepress-child-style',
        get_stylesheet_uri(),
        [ 'generatepress-style' ],
        filemtime( get_stylesheet_directory() . '/style.css' )
    );
}

// Tipografía del sitio (var --tipografia-1 en style.css). Para cambiarla:
// actualizar la URL de Google Fonts acá Y el valor de --tipografia-1 en
// style.css — son los dos únicos lugares que hay que tocar.
add_action( 'wp_enqueue_scripts', 'child_enqueue_fonts' );
function child_enqueue_fonts() {
    wp_enqueue_style(
        'google-fonts-tipografia-1',
        'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap',
        [],
        null
    );
}

// El editor de bloques corre en un iframe aparte que, sin esto, NUNCA carga
// el style.css del tema — cualquier bloque que dependa de clases propias
// (.jb3-modulo, .jb3-grid-thumbs, etc., incluidos los ACF Blocks) se ve ahí
// como HTML crudo sin estilo, aunque en el sitio real se vea perfecto.
// add_theme_support('editor-styles') habilita el mecanismo; add_editor_style()
// dice qué hoja de estilos cargar (mismo archivo que ya usa el front-end).
add_action( 'after_setup_theme', function () {
    add_theme_support( 'editor-styles' );
    add_editor_style( 'style.css' );
} );

// ─── COMPATIBILIDAD CON TÚNELES Y PROXIES ─────────────────────────────────────

add_filter( 'script_loader_src', 'make_relative_url' );
add_filter( 'style_loader_src',  'make_relative_url' );
function make_relative_url( $url ) {
    // Solo relativizar URLs del propio sitio. wp_make_link_relative() le saca
    // el esquema+host a CUALQUIER url que reciba — para un asset de terceros
    // (CDN de Swiper, Google Fonts, etc.) eso deja la ruta pidiéndose contra
    // el dominio propio en vez del original, y rompe silenciosamente ese
    // asset (404). Antes de relativizar, comprobamos que sea del mismo host.
    $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
    $url_host  = wp_parse_url( $url, PHP_URL_HOST );
    if ( $url_host && $url_host !== $site_host ) {
        return $url;
    }
    return wp_make_link_relative( $url );
}

// ─── EDITOR: QUITAR PANEL "FORMATO" ───────────────────────────────────────────

add_action( 'after_setup_theme', function () {
    remove_theme_support( 'post-formats' );
}, 20 );

// ─── COMENTARIOS: DESHABILITAR COMPLETAMENTE ──────────────────────────────────

add_action( 'admin_menu', function () {
    remove_menu_page( 'edit-comments.php' );
} );

add_action( 'init', function () {
    foreach ( get_post_types() as $post_type ) {
        if ( post_type_supports( $post_type, 'comments' ) ) {
            remove_post_type_support( $post_type, 'comments' );
            remove_post_type_support( $post_type, 'trackbacks' );
        }
    }
}, 100 );

add_action( 'admin_init', function () {
    global $pagenow;
    if ( $pagenow === 'edit-comments.php' ) {
        wp_safe_redirect( admin_url() );
        exit;
    }
} );

add_filter( 'comments_open',      '__return_false', 20 );
add_filter( 'pings_open',         '__return_false', 20 );
add_filter( 'get_comments_number', '__return_zero',  20 );
add_filter( 'xmlrpc_enabled',     '__return_false' );

add_action( 'wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu( 'comments' );
} );

add_action( 'template_redirect', function () {
    if ( is_comment_feed() ) {
        wp_safe_redirect( home_url( '/' ), 301 );
        exit;
    }
} );

add_filter( 'bloginfo_url', function ( $output, $property ) {
    return $property === 'comments_rss2_url' ? '' : $output;
}, 10, 2 );

remove_action( 'wp_head', 'feed_links_extra', 3 );

add_filter( 'wp_headers', function ( $headers ) {
    unset( $headers['X-Pingback'] );
    return $headers;
} );

// ─── INCLUDES ─────────────────────────────────────────────────────────────────

require_once __DIR__ . '/_init_taxonomias.php';         // Taxonomías personalizadas
require_once __DIR__ . '/_init_custom_post_types.php';  // Custom Post Types
require_once __DIR__ . '/_init_menu.php';               // Breakpoint del menú hamburguesa
require_once __DIR__ . '/_init_header.php';             // Header custom: preheader, logo+nav, buscador/cuenta/LinkedIn
require_once __DIR__ . '/_init_layout.php';             // Layout forzado: sin sidebar, sin footer widgets, full-width
require_once __DIR__ . '/_init_sliders.php';             // jb3_enqueue_swiper(): loader reutilizable para cualquier slider
require_once __DIR__ . '/_init_home_slider.php';        // [home_slider]: repeater ACF "HOME HEADER SLIDER"
require_once __DIR__ . '/_init_backgrounds.php';        // Fondo por página (ACF) + Options Page "Ajustes del sitio"
require_once __DIR__ . '/_init_hero.php';               // jb3_hero_image(): hero de Series/Películas/Prod. Originales
require_once __DIR__ . '/_init_admin_columns.php';      // Columna de miniatura en los listados de admin
require_once __DIR__ . '/_init_subnav.php';             // [jb3_subnav] / [jb3_subnav_anchor]: motor de navegación dinámica
require_once __DIR__ . '/_init_grids.php';              // jb3_grid_posters() / jb3_grid_thumbs(): grillas reutilizables
require_once __DIR__ . '/_init_modulo.php';             // [jb3_modulo]: consulta canal+país+tipo, título/bajada/grilla + subnav
require_once __DIR__ . '/_init_canales_slider.php';     // [jb3_canales_slider]: "Nuestras Marcas", slider de canales del país
require_once __DIR__ . '/_init_canal_redes.php';        // jb3_canal_redes_sociales_html(): íconos de redes del canal (campo ACF del término)
require_once __DIR__ . '/_init_relacionados.php';       // [jb3_relacionados]: contenido cruzado vía ACF Relationship (bidireccional)
require_once __DIR__ . '/_init_etiquetas.php';          // jb3_etiquetas_auto(): "Contenido Relacionado" automático — mismo tipo+canal, taxonomía "etiquetas"
require_once __DIR__ . '/_init_pdf_viewer.php';          // jb3_pdf_viewer(): visor de PDF propio (PDF.js autohospedado), sin controles nativos ni de extensiones
require_once __DIR__ . '/_init_pdf_preview_admin.php';   // Genera la imagen destacada sola desde la página 1 del PDF (PDF.js en el navegador del editor)
require_once __DIR__ . '/_init_video_modal.php';        // Modal de video (YouTube/Vimeo/archivo propio) para [data-jb3-video]
require_once __DIR__ . '/_init_image_modal.php';        // Modal de imagen (mismo patrón que el de video) para [data-jb3-image]
require_once __DIR__ . '/_init_page_modal.php';          // Modal de página: trae el contenido de una Página real por AJAX, sin header/footer, para [data-jb3-page-modal]
require_once __DIR__ . '/_init_acf_blocks.php';          // Bloque ACF "Módulo de Contenido" (jb3/modulo-contenido): reemplaza a [jb3_modulo] escrito a mano
