<?php
defined( 'ABSPATH' ) || exit;

// ─── SEGURIDAD BÁSICA (revisión pre-producción 2026-10-01) ────────────────────
//
// Vive en el tema padre para que cualquier sitio hecho sobre JB3 la tenga
// sin hacer nada. Si un sitio puntual necesita XML-RPC (app móvil de
// WordPress, Jetpack), su tema hijo puede devolverlo con:
//   remove_all_filters( 'xmlrpc_enabled' ); remove_all_filters( 'xmlrpc_methods' );

// XML-RPC: el sitio no lo usa (ni app móvil de WordPress ni Jetpack). Sin
// métodos no queda nada que llamar — "xmlrpc_enabled" solo apaga los que
// piden login, y xmlrpc.php seguía respondiendo el resto (pingbacks
// incluidos, usados para ataques).
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );

// Nombres de usuario: /wp-json/wp/v2/users y /?author=1 → /author/jbalbi/
// le regalaban a cualquiera el usuario del administrador (la mitad de un
// ataque de fuerza bruta al login). Solo para visitantes sin sesión: el
// editor de bloques necesita la lista de usuarios estando logueado.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );
add_action( 'template_redirect', function () {
	if ( is_author() || isset( $_GET['author'] ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 1 ); // Prioridad 1: antes de redirect_canonical (10), que si no manda /?author=1 a /author/jbalbi/ y ya mostró el nombre.

// Los feeds de comentarios (/comments/feed/, /entrada/feed/) mandan a la
// portada — complementa inc/disable-comments.php.
add_action( 'template_redirect', function () {
	if ( is_comment_feed() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

// Cabeceras de seguridad: que el sitio no se pueda meter en un <iframe> de
// otro dominio (clickjacking), que el navegador no "adivine" tipos de
// archivo, y que al salir a otro sitio no viaje la URL completa.
add_action( 'send_headers', function () {
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
} );
