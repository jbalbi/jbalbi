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

// ─── FONDO POR PÁGINA: cascada resuelta acá, no en el CSS ──────────────────
// Toda la cascada (override del post → default del tipo de contenido →
// genérico del sitio) se resuelve en PHP y se imprime como UN solo par de
// variables (--jb3-bg-desktop / --jb3-bg-mobile) en <body>. style.css ya no
// decide nada por clase de body — solo consume ese par. Se resuelve acá
// (y no con fallback de var() anidados en CSS) porque "sin fondo" a nivel
// de post necesita poder cortar la cascada, algo que var(--a, var(--b)) no
// puede expresar (siempre hay un fallback).
//
// Grupo de campos "Fondo de página" (Location: Post Type == Page/videos/
// op_comerciales/pagina_canal), campos sueltos:
// - fondo_modo: Select — por_defecto / personalizado / del_canal / sin_fondo
// - fondo_desktop, fondo_mobile: Image (URL) — visibles solo si
//   fondo_modo == personalizado (conditional logic en ACF)
//
// "del_canal": no sube nada en el post — reusa el fondo cargado UNA sola
// vez en el término de "canales" vinculado (campos fondo_desktop/
// fondo_mobile del grupo "Canales", mismo criterio que ya usa el logo
// automático en single-pagina_canal.php: se sube una vez por canal, no en
// cada página). Pensado para "pagina_canal", pero funciona para cualquier
// post que tenga el campo ACF "canal" cargado.
//
// Series/Películas/Prod. Originales quedan afuera de esta cascada por
// completo: usan el hero (ver _init_hero.php) en vez de fondo de página, y
// ni siquiera tienen el grupo "Fondo de página" disponible — sin este
// corte temprano, caerían igual en el fondo genérico del sitio como
// última red de la cascada de abajo.
add_action( 'wp_head', function () {
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}

	if ( is_singular( [ 'series', 'peliculas', 'prod_originales' ] ) ) {
		return;
	}

	$desktop = '';
	$mobile  = '';

	if ( is_singular() ) {
		$modo = get_field( 'fondo_modo' );

		if ( 'sin_fondo' === $modo ) {
			return;
		}

		if ( 'personalizado' === $modo ) {
			$desktop = get_field( 'fondo_desktop' );
			$mobile  = get_field( 'fondo_mobile' );
		}

		if ( 'del_canal' === $modo ) {
			$canal_term_id = get_field( 'canal' );
			if ( $canal_term_id ) {
				$desktop = get_field( 'fondo_desktop', 'canales_' . $canal_term_id );
				$mobile  = get_field( 'fondo_mobile', 'canales_' . $canal_term_id );
			}
		}
	}

	// Default por tipo de contenido — solo existe para videos y op_comerciales.
	if ( ! $desktop && ! $mobile ) {
		$defaults_por_cpt = [
			'videos'         => 'fondo_videos',
			'op_comerciales' => 'fondo_op_comerciales',
		];
		$post_type = is_singular() ? get_post_type() : '';

		if ( isset( $defaults_por_cpt[ $post_type ] ) ) {
			$desktop = get_field( $defaults_por_cpt[ $post_type ] . '_desktop', 'option' );
			$mobile  = get_field( $defaults_por_cpt[ $post_type ] . '_mobile', 'option' );
		}
	}

	// Genérico del sitio — última red, aplica a todo lo que no matcheó arriba
	// (incluidas páginas de archivo, búsqueda, etc.).
	if ( ! $desktop && ! $mobile ) {
		$desktop = get_field( 'fondo_generico_desktop', 'option' );
		$mobile  = get_field( 'fondo_generico_mobile', 'option' );
	}

	if ( ! $desktop && ! $mobile ) {
		return;
	}

	$css = '';
	if ( $desktop ) {
		$css .= '--jb3-bg-desktop:url(' . esc_url( $desktop ) . ');';
	}
	if ( $mobile ) {
		$css .= '--jb3-bg-mobile:url(' . esc_url( $mobile ) . ');';
	}

	printf( '<style>body{%s}</style>', $css );
} );
