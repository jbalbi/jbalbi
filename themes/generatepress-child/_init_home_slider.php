<?php
defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// ESPECIFICACIÓN DE IMÁGENES PARA DISEÑO (campos "imagen_desktop"/"imagen_mobile"
// del repeater ACF "HOME HEADER SLIDER")
//
// Ambas imágenes se muestran con object-fit:cover dentro de una caja de alto
// fijo (ver .jb3-home-slider-wrap en style.css) — si no respetan la
// proporción de abajo, el recorte automático se come costados o cabezas.
//
// DESKTOP (imagen_desktop):
//   - 1920 × 1080 px (16:9).
//
// MOBILE (imagen_mobile):
//   - 1080 × 2400 px (≈ 9:20, alto/ancho ≈ 2.22) — esa proporción cubre el
//     celular más "alto" real (ej. Samsung Galaxy S22 ≈ 2.22); si es más
//     baja que eso, el recorte automático empieza a comerse los costados en
//     vez de arriba/abajo. Si en duda, mejor pasarse de alto: el excedente
//     se recorta arriba/abajo sin costo, nunca de los costados.
//   - Zona segura: el foco principal (cara, logo, elemento clave) tiene que
//     quedar centrado dentro de los ~1900px del medio del archivo — los
//     ~250px de arriba y los ~250px de abajo pueden perderse según el
//     celular (proporción más "ancha", ej. iPhone SE ≈ 1.78), así que ahí
//     solo debería haber fondo/relleno, nunca lo importante de la foto.
// ═══════════════════════════════════════════════════════════════════════════

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
		return [ 'name' => '', 'logo' => '', 'logo_w' => 0, 'logo_h' => 0 ];
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
		return [ 'name' => '', 'logo' => '', 'logo_w' => 0, 'logo_h' => 0 ];
	}

	$logo_field = function_exists( 'get_field' ) ? get_field( 'logo', 'canales_' . $term->term_id ) : '';
	$logo_url   = is_array( $logo_field ) ? ( $logo_field['url'] ?? '' ) : (string) $logo_field;

	// Proporción (ancho ÷ alto) opcional por canal — campo ACF "logo_proporcion"
	// en el término. Sin ella, la caja del logo usa el tamaño fijo de siempre
	// (--slider-logo-width/height en style.css). Con ella, se recalcula
	// ancho/alto para que el logo ocupe aprox. la MISMA ÁREA que esa caja de
	// referencia en vez de la misma caja — antes, un logo vertical (< 1)
	// quedaba angosto porque solo el ALTO de la caja lo limitaba, mientras uno
	// horizontal (> 1) aprovechaba casi todo el ANCHO; con el área fija en vez
	// del rectángulo fijo, los dos terminan con un peso visual comparable.
	$proporcion = (float) ( function_exists( 'get_field' ) ? get_field( 'logo_proporcion', 'canales_' . $term->term_id ) : 0 );
	$logo_w     = 0;
	$logo_h     = 0;
	if ( $proporcion > 0 ) {
		$area  = 240 * 87; // misma área que la caja de referencia (--slider-logo-width × --slider-logo-height).
		$alto  = sqrt( $area / $proporcion );
		$ancho = $proporcion * $alto;

		// Topes para que una proporción muy extrema (ej. un isotipo casi
		// cuadrado vs. un isologo panorámico) no dispare el tamaño fuera de
		// lo razonable dentro del slide.
		$ancho = max( 80, min( 340, $ancho ) );
		$alto  = max( 48, min( 160, $alto ) );

		$logo_w = (int) round( $ancho );
		$logo_h = (int) round( $alto );
	}

	return [
		'name'   => $term->name,
		'logo'   => $logo_url,
		'logo_w' => $logo_w,
		'logo_h' => $logo_h,
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

	$today          = current_time( 'Ymd' );
	$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';
	$slides         = [];

	while ( have_rows( 'slide', $front_page_id ) ) {
		the_row();

		// Mismo criterio que el módulo de Programación (a propósito AL
		// REVÉS del resto del sitio): sin país elegido, el slide NO se
		// muestra en NINGÚN país — sirve para dejar uno cargándose a medio
		// hacer sin que se publique solo en todos lados mientras tanto.
		$paises_fila = (array) get_sub_field( 'pais' );
		$slugs       = array_map(
			function ( $t ) {
				return is_object( $t ) ? $t->slug : '';
			},
			$paises_fila
		);
		if ( empty( $slugs ) || ! in_array( $pais_visitante, $slugs, true ) ) {
			continue;
		}

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
			'imagen_desktop'    => $imagen_desktop,
			'imagen_mobile'     => get_sub_field( 'imagen_mobile' ),
			'canal_name'        => $canal['name'],
			'canal_logo'        => $canal['logo'],
			'canal_logo_w'      => $canal['logo_w'],
			'canal_logo_h'      => $canal['logo_h'],
			'titulo_del_slider' => get_sub_field( 'titulo_del_slider' ),
			'texto'             => get_sub_field( 'texto' ),
			'texto_boton'       => get_sub_field( 'texto_boton' ),
			'link_url'          => $link_url,
			'link_target'       => $link_target,
			'btn_bg'            => get_sub_field( 'color_del_boton' )       ?: 'var(--color-primary)',
			'btn_bg_hover'      => get_sub_field( 'color_del_boton_hover' ) ?: 'var(--color-secondary)',
			'copyright'         => get_sub_field( 'copyright' ),
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

						<?php if ( $slide['titulo_del_slider'] ) : ?>
							<span class="jb3-home-slider__titulo"><?php echo esc_html( $slide['titulo_del_slider'] ); ?></span>
						<?php endif; ?>

						<div class="jb3-home-slider__overlay">
							<?php if ( $slide['canal_logo'] ) : ?>
								<span class="jb3-home-slider__canal-logo"<?php if ( $slide['canal_logo_w'] && $slide['canal_logo_h'] ) : ?> style="--logo-w:<?php echo (int) $slide['canal_logo_w']; ?>px;--logo-h:<?php echo (int) $slide['canal_logo_h']; ?>px;"<?php endif; ?>>
									<img src="<?php echo esc_url( $slide['canal_logo'] ); ?>" alt="<?php echo esc_attr( $slide['canal_name'] ); ?>">
								</span>
							<?php endif; ?>

							<?php if ( $slide['texto'] ) : ?>
								<div class="jb3-home-slider__texto">
									<?php
									// wp_kses_post(), no crudo: ACF NO aplica wp_kses a los
									// valores de un campo WYSIWYG por su cuenta (a diferencia
									// de post_content, que WordPress sí filtra solo para
									// cuentas sin unfiltered_html) — este era el único WYSIWYG
									// del tema impreso sin sanitizar, corregido en la auditoría
									// de seguridad 2026-08-27, mismo criterio que el resto de
									// los campos WYSIWYG del sitio (mc_bajada, gs_bajada, etc.).
									echo wp_kses_post( $slide['texto'] );
									?>
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
	$html = ob_get_clean();

	// Envuelto en .jb3-hero automáticamente en la portada (pasa por detrás
	// del header — ver style.css) — no depende de que quede guardada una
	// clase a mano en el contenido de la página, que se venía perdiendo
	// cada vez que se reeditaba la Home en el editor de bloques.
	if ( is_front_page() ) {
		$html = '<div class="jb3-hero">' . $html . '</div>';
	}

	return $html;
}
