<?php
/**
 * Plugin Name: JB3 — Vista previa del tema anterior (solo local)
 * Description: Con la cookie jb3_tema=gp, carga GeneratePress + generatepress-child en vez del activo (jb3-child), sin cambiarlo para nadie más. Solo en jbalbi.test.
 *
 * Para comparar el tema propio (jb3 + jb3-child, activo) contra el anterior
 * (GeneratePress + hijo) lado a lado:
 *   - entrar a http://jbalbi.test/?jb3_tema=gp   → deja la cookie y carga GeneratePress
 *   - entrar a http://jbalbi.test/?jb3_tema=0    → la borra (vuelve al activo)
 *
 * Mismo mecanismo que usa el Personalizador de WordPress para previsualizar
 * un tema: los filtros pre_option_stylesheet / pre_option_template. Las
 * opciones del tema (logo, ubicaciones de menú) se leen de
 * theme_mods_generatepress-child, que siguen intactas.
 *
 * País simulado: en local la IP es 127.0.0.1, sin país, y jb3-geo-targeting
 * falla cerrado (404 en todo contenido con país). Con la cookie
 * jb3_pais_local=ar (o cualquier slug de la taxonomía "pais") se toma como
 * el país que detectaría el servidor (GEOIP_COUNTRY_CODE).
 *
 * Inerte fuera de local: SERVER_NAME lo decide la configuración del
 * servidor, no el visitante. Borrar este archivo cuando se decida.
 */

defined( 'ABSPATH' ) || exit;

( function () {
	if ( ! isset( $_SERVER['SERVER_NAME'] ) || 'jbalbi.test' !== $_SERVER['SERVER_NAME'] ) {
		return;
	}
	if ( ! empty( $_COOKIE['jb3_pais_local'] ) ) {
		$_SERVER['GEOIP_COUNTRY_CODE'] = strtoupper( sanitize_key( wp_unslash( $_COOKIE['jb3_pais_local'] ) ) );
	}

	if ( ! is_dir( WP_CONTENT_DIR . '/themes/generatepress-child' ) ) {
		return;
	}

	$pedido = isset( $_GET['jb3_tema'] ) ? sanitize_key( wp_unslash( $_GET['jb3_tema'] ) ) : null;
	if ( null !== $pedido ) {
		$activo = ( 'gp' === $pedido );
		setcookie( 'jb3_tema', $activo ? 'gp' : '', $activo ? 0 : time() - 3600, '/' );
	} else {
		$activo = isset( $_COOKIE['jb3_tema'] ) && 'gp' === $_COOKIE['jb3_tema'];
	}

	if ( $activo ) {
		add_filter( 'pre_option_stylesheet', static fn() => 'generatepress-child' );
		add_filter( 'pre_option_template', static fn() => 'generatepress' );
	}
} )();
