<?php
/**
 * Render del bloque ACF "Galería con Slider" (jb3/galeria-slider).
 * Para filas de imágenes puramente visuales que necesitan carrusel (no
 * grilla fija) — ej. fotos de backstage/grabaciones. Gutenberg no tiene un
 * bloque nativo de carrusel, así que esto sí justifica algo propio (a
 * diferencia de una grilla común, donde ya usamos "Grilla de Imágenes").
 *
 * Carrusel propio (JS vanilla, sin Swiper): cada foto se muestra COMPLETA
 * (ancho automático según su propia proporción, sin recortar — algunas
 * quedan más finas, otras más anchas) y el loop tiene que ser infinito de
 * verdad. Esa combinación (ancho variable + loop infinito) resultó ser un
 * caso límite mal soportado por Swiper (se probaron loop+centrado,
 * loop+sin centrar, rewind — todas con fallas reales verificadas en el
 * navegador: cortes, huecos, o el loop "retrocediendo" en vez de dar la
 * vuelta), así que se resolvió a mano con el truco clásico de tira
 * triplicada (dup+real+dup) + transform, reusando igual las flechas
 * (.swiper-button-prev/next) y las rayitas (.swiper-pagination) que ya
 * existen en el sitio — solo se dejó de usar la librería para el
 * comportamiento, no el estilo visual.
 *
 * Título/Bajada: misma regla simétrica que el resto de los bloques del
 * sitio (Título y Bajada, Grilla de Imágenes) — ver jb3_subnav_id_unico().
 */

defined( 'ABSPATH' ) || exit;

$titulo   = (string) get_field( 'gs_titulo' );
$bajada   = (string) get_field( 'gs_bajada' );
$imagenes = (array) get_field( 'gs_imagenes' );

// CERRADO por defecto: sin ningún país marcado, la galería no se ve para
// nadie — hay que marcar explícitamente los países que sí la pueden ver
// (mismo criterio que "Contenido por País"). get_field() en un campo
// Taxonomy vacío devuelve false, no array vacío, por eso el chequeo
// is_array() en vez de castear directo con (array).
$paises_ids = get_field( 'gs_paises' );
$paises_ids = is_array( $paises_ids ) ? $paises_ids : array();

if ( ! $imagenes ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Galería con Slider: falta cargar al menos una imagen.</em></p>';
	}
	return;
}

if ( $is_preview ) {
	$nombres_pais = array_filter( array_map( function ( $id ) {
		$term = get_term( $id, 'pais' );
		return $term && ! is_wp_error( $term ) ? $term->name : '';
	}, $paises_ids ) );
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>🎞️ Galería con Slider</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Título: <strong><?php echo esc_html( $titulo ?: '(sin título — no entra a la navegación)' ); ?></strong><br>
			Imágenes: <strong><?php echo count( $imagenes ); ?></strong><br>
			Países: <strong><?php echo $nombres_pais ? esc_html( implode( ', ', $nombres_pais ) ) : 'ninguno — NO se ve para nadie todavía'; ?></strong>
		</div>
	</div>
	<?php
	return;
}

$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';
$termino        = $pais_visitante ? get_term_by( 'slug', $pais_visitante, 'pais' ) : false;
$id_visitante   = $termino ? $termino->term_id : 0;

if ( ! $paises_ids || ! in_array( $id_visitante, $paises_ids, true ) ) {
	return;
}

$slides = [];
foreach ( $imagenes as $img ) {
	$src  = is_array( $img ) ? ( $img['sizes']['large'] ?? $img['url'] ?? '' ) : '';
	$full = is_array( $img ) ? ( $img['url'] ?? $src ) : '';
	$alt  = is_array( $img ) ? ( $img['alt'] ?? '' ) : '';
	if ( $src ) {
		$slides[] = [ 'src' => $src, 'full' => $full, 'alt' => $alt ];
	}
}

if ( ! $slides ) {
	return;
}

$id = '';
if ( $titulo && function_exists( 'jb3_subnav_id_unico' ) && function_exists( 'jb3_subnav_register' ) ) {
	$id = jb3_subnav_id_unico( $titulo );
	jb3_subnav_register( $titulo, $id );
}

// Solo para el CSS de flechas/tipografía de ícono (swiper-bundle.min.css) —
// el comportamiento del carrusel de acá abajo es propio, no usa new Swiper().
jb3_enqueue_swiper();

// static: el script de inicialización se agrega UNA sola vez aunque este
// bloque se use varias veces en la misma página — mismo criterio que
// jb3_pdf_viewer() (_init_pdf_viewer.php).
static $script_encolado = false;
if ( ! $script_encolado ) {
	$script_encolado = true;
	add_action( 'wp_footer', function () {
		?>
		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			document.querySelectorAll( '.jb3-galeria-slider__container' ).forEach( function ( container ) {
				var track  = container.querySelector( '.jb3-galeria-slider__track' );
				var slides = Array.prototype.slice.call( track.children );
				var n      = slides.length / 3; // tira triplicada: dup + real + dup
				var actual = n; // arranca en el primer slide "real" (bloque del medio)
				var animando = false;
				var offsets = [];

				function medir() {
					// Cada slide se centra en el ancho visible del carrusel — con
					// ancho variable por foto, el desplazamiento para centrar
					// cambia según el ancho de CADA slide, así que se calcula uno
					// por uno acá (no es un solo valor fijo para todos).
					var wrapAncho = container.querySelector( '.jb3-galeria-slider__wrap' ).clientWidth;
					offsets = slides.map( function ( s ) {
						return s.offsetLeft - ( wrapAncho - s.offsetWidth ) / 2;
					} );
				}

				function mover( indice, animar ) {
					track.style.transition = animar ? 'transform .5s ease' : 'none';
					track.style.transform  = 'translateX(-' + offsets[ indice ] + 'px)';
				}

				function marcarDot( indiceReal ) {
					container.querySelectorAll( '.swiper-pagination-bullet' ).forEach( function ( d, i ) {
						d.classList.toggle( 'swiper-pagination-bullet-active', i === indiceReal );
					} );
				}

				function ir( indice, animar ) {
					actual = indice;
					mover( actual, animar );
					marcarDot( ( ( actual % n ) + n ) % n );
				}

				// Al llegar a la copia duplicada de cualquiera de los dos lados,
				// esperar a que termine la animación y saltar (sin animar) al
				// slide real equivalente en el bloque del medio — como es la
				// misma foto en la misma posición relativa, el salto es invisible
				// y el carrusel puede seguir infinito hacia cualquier lado.
				track.addEventListener( 'transitionend', function () {
					animando = false;
					// while, no if: un arrastre largo puede saltar más de un
					// bloque de golpe (varias fotos en un solo gesto), así que a
					// veces hace falta corregir más de una vez.
					var corregido = false;
					while ( actual >= n * 2 ) {
						actual -= n;
						corregido = true;
					}
					while ( actual < n ) {
						actual += n;
						corregido = true;
					}
					if ( corregido ) {
						mover( actual, false );
					}
				} );

				function siguiente() {
					if ( animando ) return;
					animando = true;
					ir( actual + 1, true );
				}

				function anterior() {
					if ( animando ) return;
					animando = true;
					ir( actual - 1, true );
				}

				function armarDots() {
					var wrap = container.querySelector( '.swiper-pagination' );
					wrap.innerHTML = '';
					for ( var i = 0; i < n; i++ ) {
						var b = document.createElement( 'span' );
						b.className = 'swiper-pagination-bullet';
						b.addEventListener( 'click', ( function ( idx ) {
							return function () {
								if ( animando ) return;
								animando = true;
								ir( n + idx, true );
							};
						} )( i ) );
						wrap.appendChild( b );
					}
				}

				var intervalo = null;
				function arrancarAutoplay() {
					detenerAutoplay();
					intervalo = setInterval( siguiente, 3500 );
				}
				function detenerAutoplay() {
					if ( intervalo ) {
						clearInterval( intervalo );
						intervalo = null;
					}
				}

				// Pausa el autoplay mientras el mouse está encima O mientras se
				// está arrastrando (touch/mouse) — las dos razones combinadas,
				// para que soltar el dedo sin sacar el mouse de encima no lo
				// reinicie de golpe.
				var hover = false;
				var arrastrando = false;
				function actualizarAutoplay() {
					if ( hover || arrastrando ) {
						detenerAutoplay();
					} else {
						arrancarAutoplay();
					}
				}

				function iniciar() {
					armarDots();
					medir();
					mover( actual, false );
					marcarDot( 0 );

					container.querySelector( '.swiper-button-next' ).addEventListener( 'click', siguiente );
					container.querySelector( '.swiper-button-prev' ).addEventListener( 'click', anterior );

					window.addEventListener( 'resize', function () {
						medir();
						mover( actual, false );
					} );

					container.addEventListener( 'mouseenter', function () { hover = true; actualizarAutoplay(); } );
					container.addEventListener( 'mouseleave', function () { hover = false; actualizarAutoplay(); } );

					// Swipe (touch y mouse, vía Pointer Events): arrastra la tira
					// siguiendo el dedo/cursor en vivo (sin animación, transform
					// directo); al soltar, si pasó el umbral avanza/retrocede un
					// slide entero, si no vuelve a acomodarse en el actual.
					var wrapEl        = container.querySelector( '.jb3-galeria-slider__wrap' );
					var startX        = 0;
					var startOff      = 0;
					var delta         = 0;
					var imgTocada     = null;
					var huboArrastre  = false;

					// setPointerCapture (necesario para que el arrastre siga
					// funcionando aunque el dedo/cursor se salga del carrusel)
					// tiene un efecto colateral: el navegador reasigna el target
					// del "click" resultante al elemento que capturó el puntero
					// (wrapEl), no a la foto realmente tocada — así que la
					// delegación por [data-jb3-image] en document nunca la
					// encuentra. Por eso acá se guarda la imagen real en el
					// momento del pointerdown (ahí el target todavía es
					// correcto) y, si resultó ser un toque real, se abre el
					// modal directamente — sin depender del click nativo.
					wrapEl.addEventListener( 'pointerdown', function ( e ) {
						// Una interacción manual (clic/touch) interrumpe cualquier
						// auto-avance en curso — antes, si el clic caía justo durante
						// esos 0.5s de animación, se descartaba entero y el modal de
						// imagen nunca llegaba a abrirse.
						animando = false;
						arrastrando = true;
						huboArrastre = false;
						actualizarAutoplay();
						delta      = 0;
						startX     = e.clientX;
						startOff   = offsets[ actual ];
						imgTocada  = e.target.closest( 'img[data-jb3-image]' );
						wrapEl.setPointerCapture( e.pointerId );
						track.style.transition = 'none';
					} );

					wrapEl.addEventListener( 'pointermove', function ( e ) {
						if ( ! arrastrando ) return;
						delta = e.clientX - startX;
						// Se marca "hubo arrastre" apenas se cruza un piso chico, en
						// vez de mirar solo el delta final al soltar — en touch, si
						// el navegador llega a interceptar parte del gesto (scroll
						// vertical de la página), el delta final puede quedar chico
						// aunque el dedo sí se movió, y terminaba abriendo la foto
						// en vez de dejar deslizar.
						if ( Math.abs( delta ) > 8 ) {
							huboArrastre = true;
						}
						track.style.transform = 'translateX(-' + ( startOff - delta ) + 'px)';
					} );

					// El click nativo que el navegador dispara igual al soltar
					// (target ya reasignado a wrapEl, ver arriba) no sirve para
					// nada acá — el toque real ya se resolvió a mano en
					// terminarArrastre, así que se lo descarta siempre.
					wrapEl.addEventListener( 'click', function ( e ) {
						e.preventDefault();
						e.stopPropagation();
					}, true );

					function terminarArrastre() {
						if ( ! arrastrando ) return;
						arrastrando = false;
						actualizarAutoplay();

						if ( ! huboArrastre ) {
							// Toque real, sin arrastre — abre la foto.
							if ( imgTocada && window.jb3AbrirImagenModal ) {
								window.jb3AbrirImagenModal( imgTocada.getAttribute( 'data-jb3-image' ) );
							}
							return;
						}

						// Salta directo a la foto más cercana a "hasta dónde se
						// arrastró" (no siempre +1/-1) — un arrastre largo avanza
						// varias fotos de una, en vez de necesitar un gesto por
						// cada foto (mismo criterio en mouse y en touch).
						var objetivo   = offsets[ actual ] - delta;
						var mejorIndice = actual;
						var mejorDistancia = Infinity;
						for ( var i = 0; i < offsets.length; i++ ) {
							var distancia = Math.abs( offsets[ i ] - objetivo );
							if ( distancia < mejorDistancia ) {
								mejorDistancia = distancia;
								mejorIndice = i;
							}
						}
						animando = true;
						ir( mejorIndice, true );
					}
					wrapEl.addEventListener( 'pointerup', terminarArrastre );
					wrapEl.addEventListener( 'pointercancel', terminarArrastre );

					arrancarAutoplay();
				}

				// Los slides usan ancho automático (según el tamaño natural de
				// cada foto, para no recortarlas) — si se mide el ancho antes de
				// que una imagen termine de cargar, queda mal y el carrusel
				// arranca desalineado. Se espera a que TODAS carguen primero.
				var imgs = Array.prototype.slice.call( track.querySelectorAll( 'img' ) );
				var pendientes = imgs.filter( function ( img ) { return ! img.complete; } );
				var yaArranco = false;

				function iniciarUnaVez() {
					if ( yaArranco ) return;
					yaArranco = true;
					iniciar();
				}

				function listo() {
					pendientes.pop();
					if ( ! pendientes.length ) {
						iniciarUnaVez();
					}
				}

				if ( ! pendientes.length ) {
					iniciarUnaVez();
				} else {
					pendientes.forEach( function ( img ) {
						img.addEventListener( 'load', listo );
						img.addEventListener( 'error', listo );
					} );
					// Red de seguridad: si alguna imagen se cuelga sin disparar
					// nunca load NI error (conexión lenta/rara), no se puede
					// quedar esperando para siempre — arranca igual con lo que
					// haya a los 3s, mejor una medición desprolija que un
					// carrusel roto.
					setTimeout( iniciarUnaVez, 3000 );
				}
			} );
		} );
		</script>
		<?php
	} );
}
?>
<section class="jb3-modulo jb3-galeria-slider"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>>
	<?php if ( $titulo ) : ?>
		<h2 class="jb3-modulo__titulo"><?php echo esc_html( $titulo ); ?></h2>
	<?php endif; ?>
	<?php if ( $bajada ) : ?>
		<div class="jb3-modulo__bajada"><?php echo wp_kses_post( $bajada ); ?></div>
	<?php endif; ?>
	<div class="jb3-galeria-slider__container">
		<div class="jb3-galeria-slider__wrap">
			<div class="jb3-galeria-slider__track">
				<?php
				// Triplicado (dup + real + dup) para que el loop infinito
				// funcione en las dos direcciones — ver el script en wp_footer.
				for ( $rep = 0; $rep < 3; $rep++ ) :
					foreach ( $slides as $s ) :
						?>
						<div class="jb3-galeria-slider__slide">
							<?php
							// loading="eager" en LAS TRES copias (no solo en la del
							// medio): el JS reposiciona la tira con transform, así que
							// las copias duplicadas de los costados nunca están en su
							// posición real de layout — el lazy-loading nativo del
							// navegador las mide por posición real, no por dónde las
							// deja el transform, y a veces nunca las llega a cargar (el
							// carrusel se queda esperando ese load() para siempre, ver
							// "pendientes" más abajo). Son pocas fotos por galería, el
							// costo de cargarlas todas de una es mínimo.
							?>
							<img src="<?php echo esc_url( $s['src'] ); ?>" alt="<?php echo esc_attr( $s['alt'] ); ?>" loading="eager" data-jb3-image="<?php echo esc_url( $s['full'] ); ?>">
						</div>
						<?php
					endforeach;
				endfor;
				?>
			</div>
		</div>
		<div class="swiper-button-prev"></div>
		<div class="swiper-button-next"></div>
		<div class="swiper-pagination"></div>
	</div>
</section>
