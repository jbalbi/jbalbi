<?php
defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// ROTADOR DE LA HOME — COMPORTAMIENTO
// (campos "imagen_desktop" / "imagen_mobile" del repeater ACF "HOME HEADER
// SLIDER"). Definido con el usuario el 2026-09-30; el CSS está en style.css,
// sección "SLIDER HOME".
//
// MEDIDAS DE LAS IMÁGENES A PEDIRLE AL DISEÑADOR: en _init_imagenes.php
// (referencia única de imágenes de todo el sitio).
//
// REGLA DE ORO (pedido explícito): es el elemento más importante del sitio
// y la imagen NUNCA se recorta de los costados ni de arriba. Va siempre a
// ancho completo de pantalla, anclada arriba, con el alto que le da su
// propia proporción. Lo ÚNICO que puede quedar afuera es la franja de abajo.
//
// ─── CÓMO SE COMPORTA EN CADA PANTALLA ─────────────────────────────────────
//
//   Celular vertical (hasta 767px de ancho):
//     el rotador ocupa el 100% del alto de la pantalla. Imagen mobile
//     arriba; si queda corta sigue el fondo, si sobra se corta abajo.
//     Texto y botón al pie, sobre la barra violeta fija.
//   Celular apaisado (hasta 767px, horizontal):
//     usa la imagen de DESKTOP (la vertical no entra) + franja de fondo
//     debajo para el texto, que queda más abajo y se llega scrolleando.
//   Tablet (768 a 1023px):
//     imagen de desktop completa + franja de 230px de fondo debajo, donde
//     cae el bloque de texto (si no, tapaba las caras y chocaba con el
//     header: a 768px de ancho la imagen mide solo 432px de alto).
//   Desktop (1024px o más):
//     alto = el de la imagen, con techo = alto de la ventana (para no tener
//     que scrollear de más hasta el contenido que sigue). En ventanas más
//     bajas que 16:9 se pierde solo la franja inferior: ~15% en una
//     notebook de 1366, ~12% en 1920, ~10% en 2560. Texto y botón siempre
//     visibles sobre la barra violeta.
//
// ─── SI UNA IMAGEN NO RESPETA LA PROPORCIÓN ────────────────────────────────
//
// No se rompe ni se recorta: el alto del rotador se calcula a partir de
// las dimensiones REALES de los adjuntos (ver jb3_slide_imagen_ratio() y
// --jb3-slider-ratio más abajo), tomando la más alta entre los slides
// vigentes. Un slide con otra proporción se ve completo, pegado arriba,
// con fondo debajo. Conviene igual que todos los slides compartan medida.
//
// ─── POR QUÉ SE CAMBIÓ (medido con Playwright el 2026-09-30) ───────────────
//
// Antes el rotador tenía alto fijo = pantalla completa y la imagen se
// agrandaba hasta llenarlo (object-fit:cover centrado). Resultado medido:
//   - celular (360/390/430 de ancho): se perdía 35-38% del ANCHO — de las 7
//     personas de la foto de Shark Tank se veían 5;
//   - celular apaisado: se perdía 64% del alto;
//   - desktop: 10-15% repartido entre arriba y abajo;
//   - tablet vertical: el logo del slide se encimaba con el logo del sitio.
// ═══════════════════════════════════════════════════════════════════════════

// ─── [home_slider]: lee el repeater ACF "HOME HEADER SLIDER" de la Home ────
//
// Imagen desktop/mobile responsive + filtro de vigencia por fecha_in/fecha_out
// + flechas prev/next + texto/botón/color + logo de canal + copyright.
// Normaliza lo que devuelva el campo Taxonomy "canal" (objeto, array de
// objetos, slug, ID o array de cualquiera de esos, según cómo esté
// configurado el campo en ACF — en este slider en particular guarda el
// SLUG del término, ej. "spe-connect") y le suma el logo del término —
// grupo ACF "Canal — Logo" (campo "logo"), ubicado en el término de la
// taxonomía "canales", no en el post/página. Centralizado a propósito: el
// logo se carga una vez por canal, no por cada slide/post que lo use.
function jb3_slide_canal( $canal ) {
	if ( empty( $canal ) ) {
		return [ 'name' => '', 'logo' => '', 'logo_id' => 0, 'logo_w' => 0, 'logo_h' => 0 ];
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
		return [ 'name' => '', 'logo' => '', 'logo_id' => 0, 'logo_w' => 0, 'logo_h' => 0 ];
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
		'name'    => $term->name,
		'logo'    => $logo_url,
		'logo_id' => jb3_img_id( $logo_field ), // copia justa del logo: jb3_slide_logo_img().
		'logo_w' => $logo_w,
		'logo_h' => $logo_h,
	];
}

/**
 * <img> del logo de canal de un slide con la copia justa (2026-10-01, ver
 * jb3_sizes_logo() en _init_imagenes.php). Caja del logo: --logo-w/--logo-h
 * del slide (o 240×87 de --slider-logo-width/height), × --slider-logo-escala
 * en mobile (.65 en style.css — si cambia allá, cambiar acá). El logo de la
 * 1ª slide se ve al entrar: eager; el resto lazy (el script del slider los
 * pasa a eager al terminar de cargar la página, igual que las imágenes).
 */
function jb3_slide_logo_img( array $slide, bool $primera ): string {
	$id    = (int) ( $slide['canal_logo_id'] ?? 0 );
	$ancho = (float) ( $slide['canal_logo_w'] ?: 240 );
	$alto  = (float) ( $slide['canal_logo_h'] ?: 87 );
	$esc   = 0.65;
	$attrs = array( 'alt' => (string) $slide['canal_name'], 'loading' => $primera ? 'eager' : 'lazy' );
	$html  = $id ? jb3_img( $id, jb3_sizes_logo( $id, array( $alto * $esc, $alto, $alto ), array( $ancho * $esc, $ancho, $ancho ) ), $attrs ) : '';
	return $html ?: '<img src="' . esc_url( $slide['canal_logo'] ) . '" alt="' . esc_attr( $slide['canal_name'] ) . '">';
}

/** @return float Alto/ancho del adjunto (ej. 0.5625 para 1920×1080), o 0 si no se conoce. */
function jb3_slide_imagen_ratio( $attachment_id ): float {
	$meta = $attachment_id ? wp_get_attachment_metadata( (int) $attachment_id ) : false;
	if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return 0;
	}
	return $meta['height'] / $meta['width'];
}

/**
 * Lista srcset del adjunto (todas las copias que generó WordPress con su
 * ancho real, ver _init_imagenes.php) para que cada pantalla baje solo la
 * que necesita, en vez del original completo. '' si no hay copias (imagen
 * más chica que el menor tamaño): ahí queda el src solo, como antes.
 */
function jb3_slide_srcset( int $attachment_id ): string {
	if ( ! $attachment_id ) {
		return '';
	}
	return (string) wp_get_attachment_image_srcset( $attachment_id, 'full' );
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

	// Proporción (alto/ancho) más alta entre los slides vigentes, por
	// breakpoint — es la que define el alto del rotador (ver
	// .jb3-home-slider-wrap en style.css), así TODOS los slides entran
	// completos, sin recorte.
	$ratio_desktop = 0;
	$ratio_mobile  = 0;

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

		// get_sub_field( ..., false ) = valor crudo (ID del adjunto), sin
		// importar el "formato de retorno" del campo (hoy URL). Sin imagen
		// mobile, en mobile se muestra la de desktop (ver <picture> abajo).
		$id_desktop    = (int) get_sub_field( 'imagen_desktop', false );
		$id_mobile     = (int) get_sub_field( 'imagen_mobile', false );
		$r_desktop     = jb3_slide_imagen_ratio( $id_desktop );
		$r_mobile      = jb3_slide_imagen_ratio( $id_mobile ) ?: $r_desktop;
		$ratio_desktop = max( $ratio_desktop, $r_desktop );
		$ratio_mobile  = max( $ratio_mobile, $r_mobile );

		$slides[] = [
			'imagen_desktop'    => $imagen_desktop,
			'imagen_mobile'     => get_sub_field( 'imagen_mobile' ),
			'srcset_desktop'    => jb3_slide_srcset( $id_desktop ),
			'srcset_mobile'     => jb3_slide_srcset( $id_mobile ),
			'canal_name'        => $canal['name'],
			'canal_logo'        => $canal['logo'],
			'canal_logo_w'      => $canal['logo_w'],
			'canal_logo_h'      => $canal['logo_h'],
			'canal_logo_id'     => $canal['logo_id'],
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
	//
	// Imágenes de los slides 2 en adelante: llegan con loading="lazy" (ver el
	// <img> más abajo) para que no compitan con la primera, que es la que se
	// ve al entrar. Recién acá, con la página ya cargada, se les pasa a
	// "eager" para que se bajen en segundo plano ANTES de que el autoplay
	// las muestre — si se las dejara lazy, el navegador las pediría recién
	// al entrar en pantalla y podían aparecer un instante en negro.
	wp_add_inline_script( 'swiper', "
		window.addEventListener( 'load', function () {
			var el = document.querySelector( '.jb3-home-slider' );
			if ( ! el ) {
				return;
			}
			el.querySelectorAll( 'img[loading=\"lazy\"]' ).forEach( function ( img ) {
				img.loading = 'eager';
			} );
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

	// Sin dimensiones conocidas (adjunto sin metadata) no se imprime la
	// variable y style.css cae a la proporción de las medidas pedidas al
	// diseñador (16:9 desktop, 9:16 mobile — ver la especificación arriba).
	$wrap_style = '';
	if ( $ratio_desktop ) {
		$wrap_style .= '--jb3-slider-ratio:' . round( $ratio_desktop, 5 ) . ';';
	}
	if ( $ratio_mobile ) {
		$wrap_style .= '--jb3-slider-ratio-mobile:' . round( $ratio_mobile, 5 ) . ';';
	}

	// La 1ª slide ya lleva fetchpriority="high" (ver el <img> más abajo).
	// WordPress no lo sabe — este <img> no pasa por sus funciones — y le
	// ponía la misma prioridad al primer poster de la página, que le
	// competía a la imagen que se ve al entrar. Con esto le avisamos que el
	// elemento prioritario de la página ya está tomado.
	if ( function_exists( 'wp_high_priority_element_flag' ) ) {
		wp_high_priority_element_flag( false );
	}

	ob_start();
	?>
	<div class="jb3-home-slider-wrap"<?php echo $wrap_style ? ' style="' . esc_attr( $wrap_style ) . '"' : ''; ?>>
		<div class="jb3-home-slider swiper">
			<div class="swiper-wrapper">
				<?php foreach ( $slides as $i => $slide ) : ?>
					<div class="swiper-slide">
						<?php
						// srcset + sizes="100vw" (el rotador va siempre a ancho
						// completo): el navegador elige la copia según el ancho de
						// pantalla Y la densidad (un iPhone de 390px @3x pide ~1170),
						// en vez de bajar siempre el original. Sin copias (srcset
						// vacío) queda solo el src, como antes.
						// Primera slide: fetchpriority="high" — es lo primero que se
						// ve, que se pida antes que cualquier otra imagen de la página.
						// El resto: lazy (ver el script de arriba).
						?>
						<picture class="jb3-home-slider__picture">
							<?php if ( $slide['imagen_mobile'] ) : ?>
								<?php // Mismo media query que --jb3-slider-ratio-mobile en style.css — tienen que coincidir. ?>
								<source media="(max-width: 767px) and (orientation: portrait)" srcset="<?php echo esc_attr( $slide['srcset_mobile'] ?: $slide['imagen_mobile'] ); ?>" sizes="100vw">
							<?php endif; ?>
							<img src="<?php echo esc_url( $slide['imagen_desktop'] ); ?>"<?php if ( $slide['srcset_desktop'] ) : ?> srcset="<?php echo esc_attr( $slide['srcset_desktop'] ); ?>" sizes="100vw"<?php endif; ?> alt="" decoding="async" <?php echo 0 === $i ? 'fetchpriority="high"' : 'loading="lazy"'; ?>>
						</picture>

						<?php if ( $slide['titulo_del_slider'] ) : ?>
							<span class="jb3-home-slider__titulo"><?php echo esc_html( $slide['titulo_del_slider'] ); ?></span>
						<?php endif; ?>

						<div class="jb3-home-slider__overlay">
							<?php if ( $slide['canal_logo'] ) : ?>
								<span class="jb3-home-slider__canal-logo"<?php if ( $slide['canal_logo_w'] && $slide['canal_logo_h'] ) : ?> style="--logo-w:<?php echo (int) $slide['canal_logo_w']; ?>px;--logo-h:<?php echo (int) $slide['canal_logo_h']; ?>px;"<?php endif; ?>>
									<?php echo jb3_slide_logo_img( $slide, 0 === $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo. ?>
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
