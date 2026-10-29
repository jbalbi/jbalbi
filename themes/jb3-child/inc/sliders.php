<?php
defined( 'ABSPATH' ) || exit;

// ─── SWIPER: loader reutilizable (auto-hospedado) ──────────────────────────
// Cualquier shortcode/template que necesite un slider llama a
// jb3_enqueue_swiper() antes de imprimir su HTML. wp_enqueue_style() y
// wp_enqueue_script() son idempotentes por handle, así que no pasa nada si
// dos sliders distintos en la misma página la llaman cada uno la suya.
//
// Auto-hospedado en assets/vendor/swiper/ (Swiper 11.2.10) en vez de CDN:
// un bloqueo de firewall/extensión al dominio del CDN deja el slider sin
// estilos ni JS y sin ningún error visible — mismo criterio que ya usan acá
// para no depender de dominios de terceros.
function jb3_enqueue_swiper() {
	$css_path = get_stylesheet_directory() . '/assets/vendor/swiper/swiper-bundle.min.css';
	$js_path  = get_stylesheet_directory() . '/assets/vendor/swiper/swiper-bundle.min.js';

	wp_enqueue_style(
		'swiper',
		get_stylesheet_directory_uri() . '/assets/vendor/swiper/swiper-bundle.min.css',
		[],
		file_exists( $css_path ) ? filemtime( $css_path ) : false
	);
	wp_enqueue_script(
		'swiper',
		get_stylesheet_directory_uri() . '/assets/vendor/swiper/swiper-bundle.min.js',
		[],
		file_exists( $js_path ) ? filemtime( $js_path ) : false,
		true
	);
}
