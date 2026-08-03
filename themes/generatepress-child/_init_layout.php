<?php
defined( 'ABSPATH' ) || exit;

// ─── LAYOUT FORZADO: SIN SIDEBAR, SIN FOOTER WIDGETS, FULL-WIDTH ──────────────
// El sitio no tiene barra lateral nunca: forzamos 'no-sidebar' sin importar
// lo que diga la configuración de GeneratePress o del post individual.
// Filtro real: generate_get_layout() en generatepress/inc/theme-functions.php.
function jb3_sidebar_layout( $layout ) {
	return 'no-sidebar';
}
add_filter( 'generate_sidebar_layout', 'jb3_sidebar_layout' );

// El footer es 100% personalizado (fuera de GeneratePress): forzamos 0
// columnas de widgets para que GP no renderice su zona de footer widgets.
// Filtro real: generate_get_footer_widgets() devuelve un número de
// columnas (0-5), no un array — se llama 'generate_footer_widgets', no
// 'generate_footer_widget_settings'.
function jb3_footer_widgets( $widgets ) {
	return 0;
}
add_filter( 'generate_footer_widgets', 'jb3_footer_widgets' );

// El contenido siempre ocupa el ancho completo, sin depender del postmeta
// '_generate-full-width-content' que cada página pueda tener guardado (es
// justamente el campo "Contenedor del contenido" que ya no se puede editar
// porque removimos esa metabox). No existe un filtro 'generate_site_layout':
// GP decide el ancho agregando la clase 'full-width-content' al <body> en
// generate_body_classes() (generatepress/inc/markup.php), así que forzamos
// esa clase directamente y sacamos 'contained-content' si estuviera.
function jb3_force_full_width_body_class( $classes ) {
	$classes   = array_diff( $classes, array( 'contained-content' ) );
	$classes[] = 'full-width-content';
	return $classes;
}
add_filter( 'body_class', 'jb3_force_full_width_body_class', 20 );

// El título de contenido (h1.entry-title que imprime GP en
// content.php/content-page.php/content-single.php) nunca se muestra: el
// título va a manejarse desde el hero/plantilla propia, no desde GP.
// Mismo caso que los filtros anteriores: era el checkbox "Título del
// contenido" dentro de "Deshabilitar elementos", en la metabox que
// removimos. Filtro real confirmado en generatepress/inc/general.php
// (generate_disable_title) y theme-functions.php (generate_show_title).
function jb3_disable_content_title( $show_title ) {
	return false;
}
add_filter( 'generate_show_title', 'jb3_disable_content_title' );

// ─── LAYOUT FORZADO: QUITAR METABOX NATIVA REDUNDANTE ─────────────────────────
// Quita el módulo "Diseño de pantalla" de GeneratePress
// (generate_layout_options_meta_box: barra lateral, widgets de pie de
// página y contenedor del contenido) de todos los post types públicos. El
// sitio no usa ninguna de esas opciones por post: el layout está forzado a
// nivel global arriba en este mismo archivo.
//
// ID real confirmado en generatepress/inc/meta-box.php
// (generate_register_layout_meta_box): 'generate_layout_options_meta_box'.
function jb3_remove_native_metaboxes() {
	foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
		remove_meta_box( 'generate_layout_options_meta_box', $post_type, 'side' );
	}
}
add_action( 'add_meta_boxes', 'jb3_remove_native_metaboxes', 999 );
