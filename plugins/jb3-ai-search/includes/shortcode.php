<?php
defined( 'ABSPATH' ) || exit;

/**
 * [jb3_buscador_ia] — campo de búsqueda en lenguaje natural + contenedor de
 * resultados. Separado a propósito del buscador nativo del header (ícono de
 * lupa → modal → search.php) — no toca esa parte del sitio.
 *
 * Encola su propio JS/CSS acá mismo (no en un hook aparte) para que solo
 * carguen en las páginas donde el shortcode está presente — mismo criterio
 * que jb3_enqueue_swiper() en el tema.
 */
add_shortcode( 'jb3_buscador_ia', 'jb3_aisearch_shortcode' );

function jb3_aisearch_shortcode(): string {
	$css_path = __DIR__ . '/../assets/css/buscador-ia.css';
	$js_path  = __DIR__ . '/../assets/js/buscador-ia.js';

	wp_enqueue_style( 'jb3-aisearch', JB3_AISEARCH_URL . 'assets/css/buscador-ia.css', [], file_exists( $css_path ) ? filemtime( $css_path ) : false );
	wp_enqueue_script( 'jb3-aisearch', JB3_AISEARCH_URL . 'assets/js/buscador-ia.js', [], file_exists( $js_path ) ? filemtime( $js_path ) : false, true );
	wp_localize_script( 'jb3-aisearch', 'jb3AISearch', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( JB3_AISEARCH_NONCE ),
	] );

	ob_start();
	?>
	<div class="jb3-aisearch">
		<form class="jb3-aisearch__form">
			<input
				type="text"
				class="jb3-aisearch__input"
				placeholder="Ej: series de AXN de 2023, películas de acción..."
				autocomplete="off"
				maxlength="200"
			>
			<button type="submit" class="jb3-aisearch__submit">Buscar</button>
		</form>
		<div class="jb3-aisearch__estado" hidden></div>
		<div class="jb3-aisearch__resultados"></div>
	</div>
	<?php
	return (string) ob_get_clean();
}
