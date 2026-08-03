<?php

// ─── ESTILOS ──────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'generatepress_child_enqueue_styles' );
function generatepress_child_enqueue_styles() {
    wp_enqueue_style( 'generatepress-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style(
        'generatepress-child-style',
        get_stylesheet_uri(),
        [ 'generatepress-style' ],
        wp_get_theme()->get( 'Version' )
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
require_once __DIR__ . '/_shortcode_home_slider.php';   // [home_slider]: repeater ACF "HOME HEADER SLIDER"
