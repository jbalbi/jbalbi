<?php
/**
 * Plugin Name: JB3 — Dueño del sitio
 * Description: Solo el dueño (login jbalbi) puede instalar/activar/editar plugins y temas, tocar ACF o escribir código en el contenido. Los demás administradores administran todo el contenido, pero no pueden llegar al código del sitio ni a la cuenta del dueño.
 *
 * Pedido del usuario (2026-10-29): va a darle rol Administrador a otras
 * personas y no quiere que se lleven el tema hijo. Un Administrador común de
 * WordPress puede instalar un plugin (ej. un "administrador de archivos") y
 * descargar cualquier archivo del servidor — eso es lo que se corta acá.
 *
 * Es un mu-plugin a propósito: no aparece en Plugins y NO se puede desactivar
 * desde el panel — solo se saca borrando el archivo (deploy / hosting).
 * Funciona igual con cualquier tema activo (JB3 o GeneratePress).
 *
 * Qué NO puede hacer un administrador que no sea el dueño:
 *   1. Plugins, temas y actualizaciones: instalar, subir, activar,
 *      desactivar, borrar, editar ni actualizar plugins/temas/WordPress.
 *      (El editor de archivos ya está apagado con DISALLOW_FILE_EDIT.)
 *   2. La cuenta del dueño: editarla (correo/contraseña), borrarla ni
 *      cambiarle el rol — si pudiera, le cambiaba el correo y se quedaba con
 *      el sitio.
 *   3. Código en el contenido (unfiltered_html): sin esto, podría pegar un
 *      <script> en una página que, al abrirla el dueño logueado, use SUS
 *      permisos para instalar un plugin. Sin unfiltered_html, WordPress
 *      filtra el contenido al guardar (kses) — y como los campos ACF no pasan
 *      por ese filtro, se filtran acá también (punto 5).
 *   4. ACF (menú "ACF": grupos de campos, exportar): las definiciones de los
 *      campos son parte del tema. Las páginas de opciones ("Ajustes del
 *      sitio") siguen visibles.
 *   5. Ajustes críticos: dirección del sitio, correo del administrador,
 *      registro abierto y rol por defecto de los usuarios nuevos.
 *
 * LÍMITE: esto protege el PANEL. Quien tenga acceso al hosting (panel, FTP,
 * SSH) o al repositorio de GitHub puede descargar todo — esas credenciales
 * tienen que ser solo del dueño.
 *
 * MUDANZA / PRODUCCIÓN: el dueño se identifica por su login (no por ID, que
 * puede cambiar entre bases). Si en algún servidor no existe un usuario con
 * este login, NADIE tiene estos permisos (falla cerrado) — se arregla
 * cambiando la constante por deploy.
 */

defined( 'ABSPATH' ) || exit;

const JB3_PROPIETARIO_LOGIN = 'jbalbi';

/** ID del dueño en ESTA base (0 si no existe). */
function jb3_propietario_id(): int {
	static $id = null;
	if ( null === $id ) {
		$usuario = get_user_by( 'login', JB3_PROPIETARIO_LOGIN );
		$id      = $usuario ? (int) $usuario->ID : 0;
	}
	return $id;
}

function jb3_es_propietario( $usuario = null ): bool {
	$usuario = $usuario ?: wp_get_current_user();
	return $usuario instanceof WP_User && $usuario->exists() && JB3_PROPIETARIO_LOGIN === $usuario->user_login;
}

// 1 y 3: permisos que solo tiene el dueño.
add_filter( 'user_has_cap', function ( $allcaps, $caps, $args, $user ) {
	if ( jb3_es_propietario( $user ) ) {
		return $allcaps;
	}
	$solo_dueno = array(
		'install_plugins', 'upload_plugins', 'activate_plugins', 'deactivate_plugins', 'delete_plugins', 'update_plugins', 'edit_plugins', 'resume_plugins',
		'install_themes', 'upload_themes', 'switch_themes', 'delete_themes', 'update_themes', 'edit_themes', 'resume_themes',
		'update_core', 'edit_files', 'unfiltered_html', 'unfiltered_upload',
	);
	foreach ( $solo_dueno as $cap ) {
		unset( $allcaps[ $cap ] );
	}
	return $allcaps;
}, 999, 4 );

// 2: la cuenta del dueño solo la toca el dueño.
add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
	if ( in_array( $cap, array( 'edit_user', 'delete_user', 'promote_user', 'remove_user' ), true )
		&& isset( $args[0] ) && (int) $args[0] === jb3_propietario_id()
		&& (int) $user_id !== jb3_propietario_id() ) {
		return array( 'do_not_allow' );
	}
	return $caps;
}, 999, 4 );

// 3 (campos ACF): sin unfiltered_html, el texto de cualquier campo ACF se
// filtra con wp_kses_post() al guardar, igual que WordPress hace con el
// contenido — saca <script>, on*="", javascript:, etc. y deja el HTML normal
// (párrafos, links, imágenes, data-*). Los valores ya guardados no cambian.
add_filter( 'acf/update_value', function ( $value, $post_id, $field ) {
	if ( is_string( $value ) && ! current_user_can( 'unfiltered_html' ) ) {
		return wp_kses_post( $value );
	}
	return $value;
}, 999, 3 );

// 4: menú de ACF solo para el dueño.
add_filter( 'acf/settings/show_admin', function ( $mostrar ) {
	return $mostrar && jb3_es_propietario();
} );

// 5: ajustes críticos — si alguien que no es el dueño intenta cambiarlos,
// se conserva el valor anterior.
foreach ( array( 'siteurl', 'home', 'admin_email', 'new_admin_email', 'users_can_register', 'default_role' ) as $jb3_opcion ) {
	add_filter( 'pre_update_option_' . $jb3_opcion, function ( $nuevo, $viejo ) {
		return ( is_user_logged_in() && ! jb3_es_propietario() ) ? $viejo : $nuevo;
	}, 999, 2 );
}
unset( $jb3_opcion );
