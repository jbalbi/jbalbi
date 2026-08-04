<?php
defined( 'ABSPATH' ) || exit;

// ─── OPTIONS PAGE: fondo genérico del sitio ────────────────────────────────
// No existía ninguna Options Page en este proyecto — se registra por código,
// mismo criterio que el resto de la infraestructura del child theme
// (taxonomías, CPT, etc. en _init_*.php). Los campos "fondo_generico_*" se
// cargan a mano en ACF (grupo con Location: Options Page == "Ajustes del sitio").
// Enganchado a "acf/init" (no llamado directo): functions.php carga este
// archivo antes de que WP dispare "init", y acf_add_options_page() llamado
// tan temprano dispara un aviso de WP 6.7+ (textdomain de ACF cargado
// demasiado pronto) — acf/init es el momento que la propia ACF documenta
// para esto.
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}
	acf_add_options_page( [
		'page_title' => 'Ajustes del sitio',
		'menu_title' => 'Ajustes del sitio',
		'menu_slug'  => 'jb3-ajustes-sitio',
		'capability' => 'manage_options',
		'position'   => 80,
		'icon_url'   => 'dashicons-admin-generic',
	] );
} );

// ─── FONDO POR PÁGINA: Páginas, videos, op_comerciales ─────────────────────
// Los CPT con hero (series/peliculas/prod_originales) y la Home tienen su
// propio tratamiento (ver .jb3-hero) — a estos se les imprime un fondo
// detrás del nav vía dos variables CSS (desktop/mobile) en <body>. No hay
// equivalente de <picture>/<source> para background-image, así que la
// responsividad la resuelve una media query en style.css, no PHP.
//
// Fuente del fondo, en orden: el propio de la página (grupo ACF "Fondo de
// página", campos fondo_desktop/fondo_mobile) → si no hay, el genérico de
// la Options Page de arriba (fondo_generico_desktop/fondo_generico_mobile).
add_action( 'wp_head', function () {
	if ( ! is_singular() || is_front_page() ) {
		return;
	}
	if ( in_array( get_post_type(), [ 'series', 'peliculas', 'prod_originales' ], true ) ) {
		return; // esos CPT tienen su propio hero, no fondo de página.
	}
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}

	$desktop = get_field( 'fondo_desktop' ) ?: get_field( 'fondo_generico_desktop', 'option' );
	$mobile  = get_field( 'fondo_mobile' )  ?: get_field( 'fondo_generico_mobile', 'option' );

	if ( ! $desktop && ! $mobile ) {
		return;
	}

	printf(
		'<style>body{%s%s}</style>',
		$desktop ? '--jb3-bg-desktop:url(' . esc_url( $desktop ) . ');' : '',
		$mobile  ? '--jb3-bg-mobile:url(' . esc_url( $mobile ) . ');'  : ''
	);
} );
