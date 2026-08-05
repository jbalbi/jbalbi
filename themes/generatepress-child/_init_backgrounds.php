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

// ─── FONDO POR DEFECTO: genérico + por tipo de contenido ───────────────────
// Se imprimen SIEMPRE, en toda página (no dependen de cuál se está viendo)
// como variables CSS en <body>. El CSS (style.css, sección "FONDO POR
// PÁGINA") es quien decide cuál usar en cada caso, según la clase que
// WordPress ya le agrega solo al <body> (.single-videos,
// .single-op_comerciales, etc. — no hace falta agregar nada a mano en los
// templates), con cascada hacia el genérico si ese tipo de contenido no
// tiene uno propio cargado. Los valores salen del grupo de campos "Fondos
// del sitio" en la Options Page "Ajustes del sitio" de arriba.
//
// El fondo PERSONALIZADO por contenido individual (heredado/personalizado/
// sin fondo, campo a nivel de cada post/página) es un paso aparte, todavía
// no armado — cuando se sume, agrega su propia variable acá con prioridad
// más alta en la cascada de style.css.
add_action( 'wp_head', function () {
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}

	$vars = [
		'--jb3-bg-generico-desktop'       => get_field( 'fondo_generico_desktop', 'option' ),
		'--jb3-bg-generico-mobile'        => get_field( 'fondo_generico_mobile', 'option' ),
		'--jb3-bg-videos-desktop'         => get_field( 'fondo_videos_desktop', 'option' ),
		'--jb3-bg-videos-mobile'          => get_field( 'fondo_videos_mobile', 'option' ),
		'--jb3-bg-op_comerciales-desktop' => get_field( 'fondo_op_comerciales_desktop', 'option' ),
		'--jb3-bg-op_comerciales-mobile'  => get_field( 'fondo_op_comerciales_mobile', 'option' ),
	];

	$css = '';
	foreach ( $vars as $name => $url ) {
		if ( $url ) {
			$css .= $name . ':url(' . esc_url( $url ) . ');';
		}
	}

	if ( $css ) {
		printf( '<style>body{%s}</style>', $css );
	}
} );
