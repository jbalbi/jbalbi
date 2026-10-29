<?php
/**
 * Desactiva por completo los comentarios, trackbacks y pingbacks.
 *
 * Viene activo en todo sitio hecho sobre JB3. Si un sitio puntual SÍ quiere
 * comentarios, su tema hijo agrega en su functions.php (se carga antes que
 * el del padre, así que el filtro llega a tiempo):
 *   add_filter( 'jb3_desactivar_comentarios', '__return_false' );
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quita el soporte de comentarios y trackbacks de todos los tipos de contenido.
 * Esto también elimina el panel "Comentarios" del editor.
 */
function jb3_disable_comments_post_types() {
	foreach ( get_post_types() as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'init', 'jb3_disable_comments_post_types', 100 );

// Cierra comentarios y pings aunque el contenido antiguo los tenga abiertos
// (bloquea también wp-comments-post.php y la creación por REST).
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );

// Oculta los comentarios que ya existan en la base de datos.
add_filter( 'comments_array', '__return_empty_array', 20 );
add_filter( 'get_comments_number', '__return_zero', 20 );

// Sin feed de comentarios.
add_filter( 'feed_links_show_comments_feed', '__return_false' );

/**
 * Quita "Comentarios" y "Ajustes > Comentarios" del menú del admin.
 */
function jb3_disable_comments_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
	remove_submenu_page( 'options-general.php', 'options-discussion.php' );
}
add_action( 'admin_menu', 'jb3_disable_comments_admin_menu', 999 );

/**
 * Redirige al escritorio si se entra por URL a las pantallas de comentarios.
 */
function jb3_disable_comments_admin_redirect() {
	global $pagenow;

	if ( in_array( $pagenow, array( 'edit-comments.php', 'comment.php', 'options-discussion.php' ), true ) ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'jb3_disable_comments_admin_redirect' );

/**
 * Quita el widget "Comentarios recientes" del escritorio.
 */
function jb3_disable_comments_dashboard() {
	remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}
add_action( 'wp_dashboard_setup', 'jb3_disable_comments_dashboard' );

/**
 * Quita el icono de comentarios de la barra de administración.
 *
 * @param WP_Admin_Bar $admin_bar Barra de administración.
 */
function jb3_disable_comments_admin_bar( $admin_bar ) {
	$admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'jb3_disable_comments_admin_bar', 999 );

/**
 * Quita el widget clásico "Comentarios recientes".
 */
function jb3_disable_comments_widget() {
	unregister_widget( 'WP_Widget_Recent_Comments' );
}
add_action( 'widgets_init', 'jb3_disable_comments_widget', 20 );

/**
 * Elimina los endpoints de comentarios de la API REST.
 *
 * @param array $endpoints Endpoints registrados.
 * @return array
 */
function jb3_disable_comments_rest( $endpoints ) {
	unset( $endpoints['/wp/v2/comments'], $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
	return $endpoints;
}
add_filter( 'rest_endpoints', 'jb3_disable_comments_rest' );

/**
 * Desactiva pingbacks por XML-RPC (además, evita que el sitio se use en ataques de pingback).
 *
 * @param array $methods Métodos XML-RPC.
 * @return array
 */
function jb3_disable_comments_xmlrpc( $methods ) {
	unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
	return $methods;
}
add_filter( 'xmlrpc_methods', 'jb3_disable_comments_xmlrpc' );

/**
 * Quita la cabecera X-Pingback de las respuestas.
 *
 * @param array $headers Cabeceras HTTP.
 * @return array
 */
function jb3_disable_comments_headers( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
}
add_filter( 'wp_headers', 'jb3_disable_comments_headers' );

/**
 * Oculta los bloques de comentarios en el editor.
 */
function jb3_disable_comments_blocks() {
	wp_add_inline_script(
		'wp-blocks',
		"wp.domReady( function () {
			[ 'core/comments', 'core/latest-comments', 'core/post-comments-form' ].forEach( function ( name ) {
				if ( wp.blocks.getBlockType( name ) ) {
					wp.blocks.unregisterBlockType( name );
				}
			} );
		} );"
	);
}
add_action( 'enqueue_block_editor_assets', 'jb3_disable_comments_blocks' );
