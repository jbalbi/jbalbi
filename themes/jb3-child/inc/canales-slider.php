<?php
defined( 'ABSPATH' ) || exit;

// ─── [jb3_canales_slider]: slider "Nuestras Marcas" con los canales del país ──
// Mismo patrón de título/bajada generales + override por país que ya usa
// [jb3_modulo] — reutiliza el MISMO shortcode [jb3_modulo_override] y el
// mismo buffer $GLOBALS['jb3_modulo_overrides'] (ver inc/modulo.php), así
// el editor no tiene que aprender una sintaxis nueva:
//
// [jb3_canales_slider titulo="Nuestras Marcas"]
//     [jb3_modulo_override pais="ar" titulo="Nuestras Marcas en Argentina"][/jb3_modulo_override]
// [/jb3_canales_slider]
//
// Solo se listan los canales visibles para el país del visitante
// (jb3_geo_canales_visibles(), mismo criterio que ya filtra [jb3_modulo] y
// los listados nativos) y en el orden curado a mano en Canales (plugin
// jb3-tax-order, aplica solo con que se llame a get_terms() — no hace falta
// ordenar nada acá).
function jb3_canales_slider_render( string $titulo, string $bajada = '' ): string {
	if ( ! $titulo || ! function_exists( 'jb3_geo_canales_visibles' ) ) {
		return '';
	}

	$pais      = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';
	$canal_ids = jb3_geo_canales_visibles( $pais );
	if ( ! $canal_ids ) {
		return '';
	}

	$canales = get_terms( [ 'taxonomy' => 'canales', 'include' => $canal_ids, 'hide_empty' => false ] );
	if ( is_wp_error( $canales ) || empty( $canales ) ) {
		return '';
	}

	$slides = [];
	foreach ( $canales as $canal ) {
		// logo_byn (blanco y negro) es el que corresponde acá por defecto —
		// logo a color queda para el slider de la cabecera
		// (inc/home-slider.php). Si un canal todavía no tiene logo_byn
		// cargado, cae al logo a color antes que dejarlo afuera del slider.
		$logo_byn_field   = get_field( 'logo_byn', 'canales_' . $canal->term_id );
		$logo_color_field = get_field( 'logo', 'canales_' . $canal->term_id );
		$logo_byn_url     = is_array( $logo_byn_field ) ? ( $logo_byn_field['url'] ?? '' ) : (string) $logo_byn_field;
		$logo_color_url   = is_array( $logo_color_field ) ? ( $logo_color_field['url'] ?? '' ) : (string) $logo_color_field;
		$logo_url         = $logo_byn_url ?: $logo_color_url;
		if ( ! $logo_url ) {
			continue;
		}

		$link = jb3_canal_pagina_url( $canal->term_id );

		$slides[] = [
			'nombre' => $canal->name,
			'logo'   => $logo_url,
			'link'   => $link,
			// IDs: copia justa del logo (jb3_canales_slider_logo_img(), abajo).
			'logo_id'       => jb3_img_id( $logo_byn_url ? $logo_byn_field : $logo_color_field ),
			'logo_hover_id' => jb3_img_id( $logo_color_field ),
			// Hover a color: solo tiene sentido si el canal tiene página a
			// donde ir (si no, no hay nada que "invite" a pasar el mouse) Y
			// si el logo mostrado por defecto es el byn (si ya se está
			// mostrando el color por no tener byn, no hay nada que cambiar).
			'logo_hover' => ( $link && $logo_byn_url && $logo_color_url ) ? $logo_color_url : '',
		];
	}

	if ( ! $slides ) {
		return '';
	}

	jb3_enqueue_swiper();

	$id = 'canales-slider';
	if ( function_exists( 'jb3_subnav_register' ) ) {
		jb3_subnav_register( $titulo, $id );
	}

	ob_start();
	?>
	<section class="jb3-canales-slider" id="<?php echo esc_attr( $id ); ?>">
		<h2 class="jb3-canales-slider__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<?php if ( $bajada ) : ?>
			<div class="jb3-canales-slider__bajada"><?php echo wp_kses_post( $bajada ); ?></div>
		<?php endif; ?>

		<div class="jb3-canales-slider__wrap swiper">
			<div class="swiper-wrapper">
				<?php foreach ( $slides as $slide ) :
					$logo_class = 'jb3-canales-slider__logo' . ( $slide['logo_hover'] ? ' jb3-canales-slider__logo--hover' : '' );
					?>
					<div class="swiper-slide jb3-canales-slider__slide">
						<?php if ( $slide['link'] ) : ?>
							<a href="<?php echo esc_url( $slide['link'] ); ?>" class="<?php echo esc_attr( $logo_class ); ?>">
								<?php echo jb3_canales_slider_logo_img( $slide['logo_id'], $slide['logo'], $slide['nombre'], 'default' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php if ( $slide['logo_hover'] ) : ?>
									<?php echo jb3_canales_slider_logo_img( $slide['logo_hover_id'], $slide['logo_hover'], '', 'hover' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							</a>
						<?php else : ?>
							<span class="<?php echo esc_attr( $logo_class ); ?>">
								<?php echo jb3_canales_slider_logo_img( $slide['logo_id'], $slide['logo'], $slide['nombre'], 'default' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php

	// Mismo motivo que en inc/home-slider.php: "load" (no "DOMContentLoaded")
	// para que Swiper mida el ancho real del contenedor ya con el layout
	// resuelto.
	//
	// slidesPerView NUMÉRICO (no 'auto'): son logos de marca — por tema
	// legal no puede verse ninguno cortado a la mitad "espiando" el
	// siguiente. Con un número entero, Swiper reparte el ancho del
	// contenedor exacto entre esa cantidad, siempre completos.
	//
	// cap(n) achica ese número a la cantidad REAL de canales que haya en
	// este momento (varía por país — jb3_geo_canales_visibles() ya filtra
	// eso en PHP) — si un país tiene menos canales que el máximo pensado
	// para ese breakpoint, se muestran todos fijos, más grandes, sin
	// deslizar de más. loop también se apaga solo en ese caso: con loop
	// activo y muy pocos slides de sobra para duplicar, Swiper daba un
	// cálculo inestable (ver commit anterior) — acá directamente no hace
	// falta loop si ya entran todos.
	//
	// Sin flechas (pedido 2026-10-16): avanza solo (autoplay) y se puede
	// arrastrar con el mouse o el dedo — grabCursor muestra la "manito" en
	// desktop para que se note que se puede arrastrar.
	wp_add_inline_script( 'swiper', "
		window.addEventListener( 'load', function () {
			var el = document.querySelector( '.jb3-canales-slider .swiper' );
			if ( ! el ) {
				return;
			}
			var total = el.querySelectorAll( '.swiper-slide' ).length;
			function cap( n ) {
				return Math.min( n, total );
			}
			new Swiper( el, {
				slidesPerView: cap( 3 ),
				spaceBetween: 24,
				loop: total > cap( 3 ),
				autoplay: {
					delay: 4000,
					disableOnInteraction: false,
					pauseOnMouseEnter: true
				},
				grabCursor: true,
				breakpoints: {
					768: {
						slidesPerView: cap( 4 ),
						spaceBetween: 30,
						loop: total > cap( 4 )
					},
					1024: {
						slidesPerView: cap( 6 ),
						spaceBetween: 40,
						loop: total > cap( 6 )
					}
				}
			} );
		} );
	", 'after' );

	return ob_get_clean();
}

// URL de la página de canal (CPT "pagina_canal", campo ACF "canal"
// vinculado al término) — no toda la lista de canales tiene su página
// armada todavía, así que un canal sin página muestra el logo sin link en
// vez de romper el slider entero. Búsqueda en DOS pasos, "pagina_canal"
// primero y "page" solo como fallback (no un solo get_posts() con los dos
// post_type juntos): si algún día vuelve a pasar que una página suelta
// tenga el mismo campo "canal" cargado por error (como pasaba con
// "Películas" antes de corregirla, 2026-09-02) sin querer, el desempate ya
// no depende del orden por fecha de get_posts() — el CPT real gana
// siempre, sin ambigüedad. 'page' se sigue permitiendo como fallback a
// propósito: páginas sueltas que no son la principal de un canal (ej. una
// página secundaria de contenido) pueden tener el mismo campo "canal"
// cargado de forma legítima sin haber migrado al CPT — ver comentario en
// inc/custom-post-types.php sobre por qué ese campo se mantuvo como
// mecanismo único, en vez de duplicarlo con una asignación nativa de
// taxonomía solo para el CPT. Cacheado en memoria: se pide una vez por
// canal por request, no una vez por canal por cada slider que se imprima
// en la misma página.
/**
 * <img> de un logo de "Nuestras marcas" con la copia justa (2026-10-01):
 * caja de alto fijo .jb3-canales-slider__logo — 105/90/75px desktop/tablet/
 * mobile en main.css (si cambia allá, cambiar acá). Ver jb3_sizes_logo(),
 * inc/imagenes.php. eager, como antes: los slides fuera de pantalla del
 * carrusel no se cargarían a tiempo con carga diferida.
 * Sin ID (logo cargado de otra forma), el <img> de siempre.
 *
 * @param string $variante 'default' | 'hover'.
 */
function jb3_canales_slider_logo_img( int $id, string $url, string $alt, string $variante ): string {
	$clase = 'jb3-canales-slider__logo-img jb3-canales-slider__logo-img--' . $variante;
	$attrs = array( 'class' => $clase, 'alt' => $alt, 'loading' => 'eager' );
	if ( 'hover' === $variante ) {
		$attrs['aria-hidden'] = 'true';
	}
	$html = $id ? jb3_img( $id, jb3_sizes_logo( $id, array( 75, 90, 105 ) ), $attrs ) : '';
	return $html ?: '<img class="' . esc_attr( $clase ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . ( 'hover' === $variante ? ' aria-hidden="true"' : '' ) . '>';
}

function jb3_canal_pagina_url( int $canal_term_id ): string {
	static $cache = [];
	if ( array_key_exists( $canal_term_id, $cache ) ) {
		return $cache[ $canal_term_id ];
	}

	foreach ( [ 'pagina_canal', 'page' ] as $post_type ) {
		$pages = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => 'canal',
			'meta_value'     => $canal_term_id,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );
		if ( $pages ) {
			return $cache[ $canal_term_id ] = (string) get_permalink( $pages[0] );
		}
	}

	return $cache[ $canal_term_id ] = '';
}

add_shortcode( 'jb3_canales_slider', function ( $atts, $content = null ) {
	$atts = shortcode_atts( [
		'titulo' => '',
	], $atts );

	if ( ! $atts['titulo'] ) {
		return '';
	}

	$GLOBALS['jb3_modulo_overrides'] = [];
	$bajada = $content ? do_shortcode( $content ) : ''; // Dispara [jb3_modulo_override] como efecto secundario.

	$titulo = $atts['titulo'];
	$pais   = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';

	if ( $pais && isset( $GLOBALS['jb3_modulo_overrides'][ $pais ] ) ) {
		$override = $GLOBALS['jb3_modulo_overrides'][ $pais ];
		if ( $override['titulo'] ) {
			$titulo = $override['titulo'];
		}
		$bajada = $override['bajada'];
	}

	return jb3_canales_slider_render( $titulo, $bajada );
} );
