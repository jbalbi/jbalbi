<?php
defined( 'ABSPATH' ) || exit;

// ─── [home_slider]: lee el repeater ACF "HOME HEADER SLIDER" de la Home ────
//
// VERSIÓN 1 (en construcción, a propósito acotada para debuggear de a
// partes): solo imagen desktop/mobile responsive + filtro de vigencia por
// fecha_in/fecha_out + flechas prev/next. El resto de los campos (texto,
// botón, colores, canal, copyright) se suman después de confirmar que esto
// se ve bien.
// Normaliza lo que devuelva el campo Taxonomy "canal" (objeto, array de
// objetos, slug, ID o array de cualquiera de esos, según cómo esté
// configurado el campo en ACF — en este slider en particular guarda el
// SLUG del término, ej. "spe-connect") y le suma el logo del término —
// grupo ACF "Canal — Logo" (campo "logo"), ubicado en el término de la
// taxonomía "canales", no en el post/página. Centralizado a propósito: el
// logo se carga una vez por canal, no por cada slide/post que lo use.
function jb3_slide_canal( $canal ) {
	if ( empty( $canal ) ) {
		return [ 'name' => '', 'logo' => '' ];
	}
	if ( is_array( $canal ) ) {
		$canal = reset( $canal );
	}

	if ( is_a( $canal, 'WP_Term' ) ) {
		$term = $canal;
	} elseif ( is_numeric( $canal ) ) {
		$term = get_term( (int) $canal, 'canales' );
	} else {
		$term = get_term_by( 'slug', $canal, 'canales' );
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return [ 'name' => '', 'logo' => '' ];
	}

	$logo_field = function_exists( 'get_field' ) ? get_field( 'logo', 'canales_' . $term->term_id ) : '';
	$logo_url   = is_array( $logo_field ) ? ( $logo_field['url'] ?? '' ) : (string) $logo_field;

	return [
		'name' => $term->name,
		'logo' => $logo_url,
	];
}

add_shortcode( 'home_slider', 'jb3_home_slider_shortcode' );
function jb3_home_slider_shortcode() {
	if ( ! function_exists( 'have_rows' ) ) {
		return '';
	}

	$front_page_id = (int) get_option( 'page_on_front' );
	if ( ! $front_page_id || ! have_rows( 'slide', $front_page_id ) ) {
		return '';
	}

	$today  = current_time( 'Ymd' );
	$slides = [];

	while ( have_rows( 'slide', $front_page_id ) ) {
		the_row();

		$fecha_in  = get_sub_field( 'fecha_in' );
		$fecha_out = get_sub_field( 'fecha_out' );

		// fecha_in/fecha_out vacías = sin límite de ese lado (siempre vigente).
		$in_ok  = empty( $fecha_in )  || $today >= $fecha_in;
		$out_ok = empty( $fecha_out ) || $today <= $fecha_out;
		if ( ! $in_ok || ! $out_ok ) {
			continue;
		}

		$imagen_desktop = get_sub_field( 'imagen_desktop' );
		if ( ! $imagen_desktop ) {
			continue; // sin imagen desktop no hay slide que mostrar.
		}

		// Soporta el campo "Link" en cualquiera de sus dos formatos de
		// retorno: "Link Array" (array con url/target/title) o "Link URL"
		// (solo el string de la URL, sin target — con ese formato "abrir en
		// pestaña nueva" no se puede respetar porque ACF ni siquiera lo
		// guarda; para eso el campo tiene que estar en "Link Array").
		$link        = get_sub_field( 'link' );
		$link_url    = is_array( $link ) ? ( $link['url'] ?? '' ) : (string) $link;
		$link_target = is_array( $link ) ? ( $link['target'] ?? '' ) : '';

		$canal = jb3_slide_canal( get_sub_field( 'canal' ) );

		$slides[] = [
			'imagen_desktop' => $imagen_desktop,
			'imagen_mobile'  => get_sub_field( 'imagen_mobile' ),
			'canal_name'     => $canal['name'],
			'canal_logo'     => $canal['logo'],
			'texto'          => get_sub_field( 'texto' ),
			'texto_boton'    => get_sub_field( 'texto_boton' ),
			'link_url'       => $link_url,
			'link_target'    => $link_target,
			'btn_bg'         => get_sub_field( 'color_del_boton' )       ?: 'var(--color-primary)',
			'btn_bg_hover'   => get_sub_field( 'color_del_boton_hover' ) ?: 'var(--color-secondary)',
			'copyright'      => get_sub_field( 'copyright' ),
		];
	}

	if ( empty( $slides ) ) {
		return '';
	}

	jb3_enqueue_swiper();

	// Ojo con esto: Swiper mide el ancho real del contenedor (clientWidth) en
	// el instante exacto de new Swiper(...). Con "DOMContentLoaded" ese
	// instante puede caer antes de que el motor de render termine de
	// resolver el layout con aspect-ratio, mide 0 y el cálculo interno de
	// Swiper explota a un ancho de slide absurdo (ancho/alto gigantes,
	// doble scroll). Con "load" esperamos a que la página esté 100%
	// pintada (imágenes incluidas) antes de medir — sin ambigüedad.
	wp_add_inline_script( 'swiper', "
		window.addEventListener( 'load', function () {
			var el = document.querySelector( '.jb3-home-slider' );
			if ( ! el ) {
				return;
			}
			new Swiper( el, {
				slidesPerView: 1,
				loop: true,
				autoplay: {
					delay: 5000,
					disableOnInteraction: false,
					pauseOnMouseEnter: true
				},
				navigation: {
					nextEl: '.jb3-home-slider .swiper-button-next',
					prevEl: '.jb3-home-slider .swiper-button-prev'
				}
			} );
		} );
	", 'after' );

	ob_start();
	?>
	<div class="jb3-home-slider-wrap">
		<div class="jb3-home-slider swiper">
			<div class="swiper-wrapper">
				<?php foreach ( $slides as $slide ) : ?>
					<div class="swiper-slide">
						<picture class="jb3-home-slider__picture">
							<?php if ( $slide['imagen_mobile'] ) : ?>
								<source media="(max-width: 767px)" srcset="<?php echo esc_url( $slide['imagen_mobile'] ); ?>">
							<?php endif; ?>
							<img src="<?php echo esc_url( $slide['imagen_desktop'] ); ?>" alt="">
						</picture>

						<div class="jb3-home-slider__overlay">
							<?php if ( $slide['canal_logo'] ) : ?>
								<span class="jb3-home-slider__canal-logo">
									<img src="<?php echo esc_url( $slide['canal_logo'] ); ?>" alt="<?php echo esc_attr( $slide['canal_name'] ); ?>">
								</span>
							<?php endif; ?>

							<?php if ( $slide['texto'] ) : ?>
								<div class="jb3-home-slider__texto">
									<?php echo $slide['texto']; // phpcs:ignore WordPress.Security.EscapeOutput -- WYSIWYG ACF, ya sanitizado al guardar. ?>
								</div>
							<?php endif; ?>

							<?php if ( $slide['texto_boton'] && $slide['link_url'] ) : ?>
								<a class="jb3-home-slider__btn"
									href="<?php echo esc_url( $slide['link_url'] ); ?>"
									<?php echo $slide['link_target'] ? 'target="' . esc_attr( $slide['link_target'] ) . '" rel="noopener"' : ''; ?>
									style="--jb3-slide-btn-bg:<?php echo esc_attr( $slide['btn_bg'] ); ?>;--jb3-slide-btn-bg-hover:<?php echo esc_attr( $slide['btn_bg_hover'] ); ?>;">
									<?php echo esc_html( $slide['texto_boton'] ); ?>
								</a>
							<?php endif; ?>

							<?php if ( $slide['copyright'] ) : ?>
								<span class="jb3-home-slider__copyright"><?php echo esc_html( $slide['copyright'] ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="swiper-button-prev"></div>
			<div class="swiper-button-next"></div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
