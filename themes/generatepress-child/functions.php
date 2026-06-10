<?php

add_action('wp_enqueue_scripts', 'generatepress_child_enqueue_styles');
function generatepress_child_enqueue_styles()
{
    wp_enqueue_style(
        'generatepress-style',
        get_template_directory_uri() . '/style.css'
    );

    wp_enqueue_style(
        'generatepress-child-style',
        get_stylesheet_uri(),
        array('generatepress-style'),
        wp_get_theme()->get('Version')
    );
}


// URLs relativas para compatibilidad con túneles y proxies
add_filter('script_loader_src', 'make_relative_url');
add_filter('style_loader_src', 'make_relative_url');
function make_relative_url($url)
{
    return wp_make_link_relative($url);
}


// Cargar tipografía Inter desde Google Fonts
function child_enqueue_fonts()
{
    wp_enqueue_style(
        'google-fonts-inter',
        'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
        array(),
        null
    );
}
add_action('wp_enqueue_scripts', 'child_enqueue_fonts');


// Quitar el menú de comentarios del admin
function quitar_menu_comentarios()
{
    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'quitar_menu_comentarios');


// Desactivar soporte de comentarios en entradas y páginas
function desactivar_comentarios_completamente()
{
    // Quitar soporte en entradas y páginas
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}
add_action('init', 'desactivar_comentarios_completamente', 100);


// Evitar acceso directo a la pantalla de comentarios
function bloquear_pantalla_comentarios()
{
    global $pagenow;
    if ($pagenow === 'edit-comments.php') {
        wp_safe_redirect(admin_url());
        exit;
    }
}
add_action('admin_init', 'bloquear_pantalla_comentarios');




// INCLUDES
require_once('_init_taxonomias.php');           // Creo mis propias taxonomias
require_once('_init_custom_post_types.php');    // Creo mis propios CPTs
