<?php
/**
 * Seguridad básica para cualquier sitio hecho sobre JB3.
 *
 * Vive en el tema padre para que todo sitio nuevo la tenga sin hacer nada.
 * Si un sitio puntual necesita XML-RPC (app móvil de WordPress, Jetpack),
 * su tema hijo puede devolverlo con:
 *   remove_all_filters( 'xmlrpc_enabled' ); remove_all_filters( 'xmlrpc_methods' );
 *
 * Esto NO reemplaza un plugin de seguridad ni la configuración del servidor
 * (HTTPS, permisos de archivos, wp-config.php, backups): es la base mínima.
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;

// XML-RPC: "xmlrpc_enabled" solo apaga los métodos que piden login;
// xmlrpc.php seguía respondiendo el resto (pingbacks incluidos, usados para
// ataques). Sin métodos no queda nada que llamar.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );

// Nombres de usuario: /wp-json/wp/v2/users y /?author=1 → /author/<usuario>/
// le regalan a cualquiera el usuario del administrador (la mitad de un
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
}, 1 ); // Prioridad 1: antes de redirect_canonical (10), que si no manda /?author=1 a /author/<usuario>/ y ya mostró el nombre.

// Los feeds de comentarios (/comments/feed/, /entrada/feed/) mandan a la
// portada — complementa inc/disable-comments.php.
add_action( 'template_redirect', function () {
	if ( is_comment_feed() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

// Cabeceras de seguridad:
//   - X-Frame-Options: el sitio no se puede meter en un <iframe> de otro
//     dominio (clickjacking).
//   - X-Content-Type-Options: el navegador no "adivina" tipos de archivo.
//   - Referrer-Policy: al salir a otro sitio no viaja la URL completa.
//   - Permissions-Policy: ninguna página (ni un script de terceros cargado
//     en ella) puede pedir cámara, micrófono, ubicación, pagos ni USB. No
//     toca autoplay/fullscreen/picture-in-picture (los usan los embeds de
//     YouTube/Vimeo). Si un sitio necesita alguno, cambiarlo con el filtro
//     jb3_permissions_policy desde el hijo.
//   - Strict-Transport-Security (HSTS), solo si la página ya llegó por
//     HTTPS: el navegador recuerda por 1 año que este dominio va SIEMPRE por
//     HTTPS (corta ataques que fuerzan http://). Sin includeSubDomains a
//     propósito: no sabemos qué otros subdominios tiene el dominio de cada
//     sitio. OJO: una vez enviado, volver a http:// no es posible hasta que
//     venza; el sitio tiene que tener HTTPS de forma permanente.
// Función con nombre para que un hijo pueda reemplazarla entera con
// remove_action( 'send_headers', 'jb3_cabeceras_seguridad' ).
function jb3_cabeceras_seguridad() {
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: ' . apply_filters( 'jb3_permissions_policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()' ) );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000' );
	}
}
add_action( 'send_headers', 'jb3_cabeceras_seguridad' );

// Versión de WordPress oculta: le ahorra a un atacante saber de antemano
// qué fallas conocidas probar. Se saca el <meta name="generator"> (y su
// equivalente en los feeds), y en los CSS/JS del core el "?ver=7.1.4" se
// reemplaza por un código derivado de la versión + las claves del sitio:
// sigue cambiando con cada actualización (el navegador baja los archivos
// nuevos), pero no dice qué versión es.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

function jb3_ocultar_version_wp( $src ) {
	static $codigo = null;
	$version = get_bloginfo( 'version' );
	if ( $src && false !== strpos( $src, 'ver=' . $version ) ) {
		if ( null === $codigo ) {
			$codigo = substr( wp_hash( 'jb3-ver-' . $version ), 0, 8 );
		}
		$src = add_query_arg( 'ver', $codigo, $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'jb3_ocultar_version_wp', 9999 );
add_filter( 'script_loader_src', 'jb3_ocultar_version_wp', 9999 );

// Contenido protegido con contraseña (Visibilidad > Protegida con
// contraseña): WordPress solo protege lo que pasa por the_content(); todo lo
// que una plantilla imprime por su cuenta (campos ACF, bloques propios,
// relacionados, PDFs) se mostraba igual sin pedirla. Mientras falte la
// contraseña, se usa password.php (título + formulario, nada más) en vez de
// la plantilla normal — cubre cualquier tipo de contenido, presente o
// futuro, sin depender de que cada plantilla se acuerde de chequearlo. El
// hijo puede tener su propio password.php con el diseño del sitio.
add_filter( 'template_include', function ( $template ) {
	if ( is_singular() && post_password_required() ) {
		$password = locate_template( 'password.php' );
		if ( $password ) {
			return $password;
		}
	}
	return $template;
}, 99 );
