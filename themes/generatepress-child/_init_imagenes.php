<?php
defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// IMÁGENES: TAMAÑOS QUE GENERA WORDPRESS + FORMATO WEBP (2026-10-01)
//
// Todo por código (no en Ajustes → Medios, que vive en la base de datos):
// así queda en git y no depende de lo que tenga cargado cada instalación.
//
// ─── TAMAÑOS ───────────────────────────────────────────────────────────────
// Antes WordPress generaba 6 copias genéricas por cada imagen subida
// (150, 300, 768, 1024, 1536, 2048), sin relación con cómo las usa el
// sitio. Ahora, todas proporcionales (sin recorte, salvo la miniatura del
// admin) y medidas por ANCHO, que es lo que necesita el navegador para
// elegir con srcset:
//
//   nombre        ancho   para qué
//   thumbnail      150    miniaturas cuadradas del admin (recorte, sin cambios)
//   medium         400    posters en celular, grillas chicas
//   medium_large   800    posters en desktop/retina, pantalla completa en celular
//   large         1200    tablets, tarjetas grandes
//   jb3-1920      1920    rotador, hero y fondos en desktop
//   (original)    2560    WordPress achica a 2560 cualquier original más grande
//                         ("-scaled", big_image_size_threshold, default de WP)
//
// Se mantienen los NOMBRES de WordPress (medium/medium_large/large) y se
// cambian sus medidas, en vez de inventar nombres nuevos: el tema y los
// plugins ya piden 'large', 'medium_large' y 'thumbnail' en varios lugares
// (grillas, tarjetas, galería, banner, PDF de Op. Comerciales…) y así todos
// pasan a recibir la medida nueva sin tocar cada plantilla.
// Se dejan de generar '1536x1536' y '2048x2048' (nadie los pide).
//
// Qué medida subir en cada lugar del sitio: ver "MEDIDAS A PEDIRLE AL
// DISEÑADOR" más abajo, en este mismo archivo.
//
// ─── FORMATO ───────────────────────────────────────────────────────────────
// Las copias que genera WordPress se guardan en WebP (25-35% más livianas
// que JPG a la misma calidad, con transparencia como PNG, soportado por
// todos los navegadores actuales). El ORIGINAL subido queda como se subió.
// Confirmado 2026-10-01: Hostinger tiene WebP tanto con ImageMagick como
// con GD (Herramientas → Salud del sitio → Información → Manejo de medios).
// AVIF también está disponible y pesa un poco menos, pero se descartó por
// ahora: es bastante más lento de generar al subir cada imagen.
//
// Las imágenes ya subidas se regeneraron (local, 2026-10-01: 264 imágenes,
// sin errores) partiendo SIEMPRE del original: WordPress crea la versión
// WebP de la imagen principal + las copias, y la ficha del adjunto pasa a
// apuntar al WebP. Los JPG/PNG originales y las copias viejas NO se borran
// (no se rompe ninguna imagen insertada a mano en un contenido). Resultado
// medido: home de 7,3 a 4,3 MB en monitor 1920 y de 8,0 a 4,1 MB en iPhone.
// En PRODUCCIÓN la regeneración hay que correrla aparte (las imágenes de
// allá no son las de local).
// ═══════════════════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════════════════
// REFERENCIA ÚNICA DE IMÁGENES DEL SITIO (pedido 2026-10-01: "en un único
// lugar, no desparramado en cada archivo"). Si se agrega un lugar nuevo
// que muestre imágenes, sumarlo acá.
//
// Reglas generales:
//   - Máximo 2560 px de ancho para cualquier imagen (decisión del usuario).
//     Lo más grande igual se achica solo a 2560 al subir.
//   - Breakpoints: mobile hasta 767px (usa la imagen "mobile" si el lugar
//     tiene una), tablet 768-1023 y desktop 1024+ usan la de desktop.
//   - Las medidas son para pantallas retina/4K: el navegador elige solo la
//     copia más chica que alcance, nadie baja el original de más.
//   - JPG para fotos, PNG para logos/transparencias. El sitio genera el
//     WebP solo; no hace falta subir WebP.
//
// ─── MEDIDAS A PEDIRLE AL DISEÑADOR ────────────────────────────────────────
//
//   LUGAR                        DESKTOP / TABLET        MOBILE (≤767px)
//   Rotador de la home           2560 × 1440 (16:9)      1080 × 1920 (9:16)
//     · desktop: el ~20% inferior es zona de degradé hacia el fondo negro
//       (puede quedar fuera de pantalla en ventanas bajas; ahí va el texto).
//       1920 × 1080 (lo que se venía subiendo) sigue funcionando; solo se ve
//       un poco "blando" en monitores 4K. Se descartó 3840: pesa 2-3 veces
//       más y la mejora casi no se nota.
//     · mobile: lo importante en el ~60% superior; el ~40% inferior,
//       degradé a negro (ahí va logo+texto+botón). Casi todos los celulares
//       son más alargados que 9:16: la imagen queda ~150px más corta que la
//       pantalla y debajo sigue el fondo negro — por eso el degradé es
//       obligatorio (se evaluó pedir 1080 × 2400 para llenar siempre; se
//       eligió 1080 × 1920 con degradé). Las 1000 × 1333 ya subidas siguen
//       funcionando. Sin imagen mobile, en celular se usa la de desktop.
//     · Comportamiento por pantalla (alto, recortes, dónde va el texto):
//       comentario al inicio de _init_home_slider.php.
//   Hero película/serie/P.Orig.  2560 × 720 (32:9)       1080 × 1620 (2:3)
//   Banner (módulo y bloque)     2560 × 720 (32:9)       1080 × 1440 (3:4)
//   Programación                 2560 × 1440 (16:9)      1080 × 2400 (9:20)
//   Fondos (página y del sitio)  2560 × 1440 (16:9)      1080 × 1440 (3:4)
//   Posters (pelis/series/P.O.)  1000 × 1500 (2:3) — una sola versión
//   Thumbs (videos, integr.,     1920 × 1080 (16:9) — una sola versión
//     Op. Comerciales, prensa)     (la grilla los muestra en 16:9 desde
//                                   2026-10-01; antes 3:2 y recortaba)
//   Imagen suelta / img + texto  1440 de ancho (se ve a 720 máx.), alto libre
//   Galería (módulo y bloque)    1920 de ancho (se amplía al hacer clic), alto libre
//   Logos de canal               PNG transparente, 800 de ancho máx.
//   Grid de personas (P.Orig.)   SIN proporción fija, a propósito (decisión
//                                del usuario 2026-10-01): son fichas de
//                                páginas individuales, cada contenido puede
//                                usar fotos verticales, cuadradas o
//                                apaisadas y se muestran completas, sin
//                                recorte (jb3_render_tarjeta: height:auto).
//                                NO forzar un aspect-ratio acá. Única regla:
//                                hasta 1200 de ancho (se ven a ≤600 px).
//                                Si se mezclan proporciones en una misma
//                                grilla, las tarjetas quedan de distinto alto.
//   Vista previa PDF (Op. Com.)  se genera sola desde la 1ª página del PDF
//
// ─── DÓNDE SE USA CADA UNA Y CÓMO SE SIRVE HOY ─────────────────────────────
// (estado al 2026-10-01; "hoy cargado" = medidas reales de lo subido en local)
//
//   LUGAR / ARCHIVO                       PIDE HOY            HOY CARGADO              ESTADO
//   Rotador home                          copia justa         1920×1080 / 1000×1333    OK (srcset,
//     _init_home_slider.php               (srcset)                                       1ª slide prioridad)
//   Posters                               copia justa         693×1024, 708×1024       OK (sizes por
//     _init_grids.php (grillas, "Te         (srcset + sizes)                             pantalla y columnas,
//     puede interesar", módulo de canal,                                                 jb3_grid_sizes())
//     grilla mixta del buscador)
//   Thumbs videos / integraciones /       copia justa         1920×1080, 2400×1350     OK (ídem)
//     Op. Comerciales — _init_grids.php
//     · Medido 2026-10-01: ninguna se ve borrosa en ninguna pantalla. Un
//       poster se ve a 410px (1920), 272 (notebook), 405 reales (tablet) y
//       495 (iPhone): baja 400 en notebook y el original (~693) en el
//       resto. Opción NO aplicada: sumar una copia de 600px ahorraría
//       15-40% por poster en esas pantallas, a cambio de un archivo más por
//       cada imagen subida; con posters en WebP de ~20 KB no se justificó.
//   Hero película/serie/P.Orig.           copia justa         1920×540 / 920×1360      OK (jb3_picture(),
//     _init_hero.php                      (srcset, 100vw)                              prioridad de carga)
//   Fondos de página / del sitio          1920 o original     1920×1080 / 1000×1333    OK (media query:
//     _init_backgrounds.php               según pantalla                               ≤1920 sin retina →
//                                                                                      copia 1920)
//   Programación                          copia justa         2667×1500 / 667×1500     OK (jb3_picture(),
//     blocks/programacion/render.php      (srcset, 100vw)                              lazy)
//   Banner (módulo + bloque de páginas)   copia justa         1920×540 / 1000×1333     OK (jb3_picture(),
//     _init_contenido_pelicula.php        (srcset, ancho                               lazy)
//     (jb3_contenido_render_banner),        del contenido)
//     blocks/banner/render.php
//     · Medido 2026-10-01 (hero Venom, banner Amos del Universo, programación
//       y fondo de Sony Movies): copia correcta en monitor/notebook/tablet,
//       sin deformar, alto reservado correcto mientras carga (width/height
//       también en el <source> mobile). En iPhone el hero mobile (920) y la
//       programación mobile (667) quedan CORTOS (necesita ~1170): no es del
//       código, es lo subido — pedir las medidas mobile de 1080 de arriba.
//     · Fondos: con lo subido hoy (1920) no cambia nada; gana cuando suban
//       de 2560. El de mobile va siempre completo (cubre el alto de la
//       pantalla y necesita más píxeles de los que trae).
//   Imagen suelta / Imagen + texto        copia justa         varias, hasta 4000 ancho OK (jb3_img():
//     _init_contenido_pelicula.php        (720 / media col.)                           sizes fijos)
//   Galería con slider                    copia justa         920×1360, 4000×1848…     OK (sizes en px
//     _init_galeria_slider.php,             + original al                              exactos: alto fijo
//     blocks/galeria-slider/render.php      ampliar                                    420/320/220 × prop.)
//   Grilla de imágenes (bloque)           copia justa         920×1360 (Movie Promo)   OK (jb3_grid_sizes
//     blocks/grilla-imagenes                                                           'posters')
//   Tarjeta suelta (bloque)               copia justa         —                        OK (sizes="auto" +
//     blocks/tarjeta, _init_tarjeta.php                                                respaldo 2 col.)
//   Prod. Originales: personas, prensa    copia justa         1841×1085, 4000×1848…    OK (jb3_grid_sizes
//     _init_contenido_original.php                                                     'grilla')
//   Prod. Originales: grid de videos      miniatura externa   (YouTube/Vimeo)          N/A: no son nuestras
//   Logos de canal (rotador, Nuestras     copia justa         800×(421…1242) PNG→WebP  OK (jb3_sizes_logo:
//     marcas, página de canal, Renta)                                                  px EXACTOS de la
//     _init_home_slider.php, _init_canales_slider.php,                                 caja — ver nota en
//     single-pagina_canal.php, _init_contenido_pelicula.php                            la función)
//     · Verificado 2026-10-01 con una "foto" del tamaño en pantalla de TODAS
//       las imágenes de 8 páginas × 3 pantallas antes y después: ninguna
//       cambió de tamaño (logos incluidos). Ninguna queda corta (blanda) en
//       ninguna pantalla. Ej. Movie Promo (grilla de 8 posters): monitor
//       420 → 86 KB por poster, celular 420 → 296 KB.
//   Vista previa PDF                      copia justa         ~2400×1350               OK (jb3_img(),
//     single-op_comerciales.php, _init_pdf_preview_admin.php                         ancho contenido)
//     · Medido 2026-10-01: iPhone baja la de 1200 (65 KB), monitor la de 1920.
//   Miniaturas del admin                  'thumbnail' (150)   —                        OK
//     _init_admin_columns.php
//
// MAPAS Y BANDERAS DE PAÍSES (uploads/paises/, fuera de la biblioteca de
// medios — los usa el bloque de representantes de jb3-members,
// JB3_Representatives::mapa_img()):
//   · Banderas flag_XX.svg: ~5 KB cada una, sin cambios.
//   · Mapas mapa_XX.svg: eran los de "país en el globo" de Wikipedia, que
//     dibujan los bordes de TODOS los países (Brasil: 12.786 trazos, 9,6 MB)
//     para mostrarse a ~430px; además se descargaban en celular aunque ahí
//     el mapa está oculto. Resuelto 2026-10-01: versiones WebP generadas desde
//     el mismo SVG con Chromium (mapa_XX.webp 1200px + mapa_XX-600.webp, sin
//     diferencia visible comparadas a ojo en retina) — 33 MB → 1,5 MB en
//     total; Argentina 3,2 MB → 30 KB, Brasil 9,6 MB → 35 KB. loading="lazy":
//     en celular ya no se descarga. Los SVG quedan como respaldo (si falta
//     el WebP de un país, se usa el SVG).
//     OJO PRODUCCIÓN: uploads/ no va en git — los 38 .webp hay que subirlos
//     a mano a uploads/paises/. Si se agrega un país nuevo, generar sus dos
//     WebP igual (1200 y 600, fondo transparente, calidad 80).
// ═══════════════════════════════════════════════════════════════════════════

// Medidas de los tamaños nativos, por código. pre_option_* se adelanta a lo
// que haya guardado en la base (Ajustes → Medios), que queda sin efecto.
// Alto 0 = sin límite: el tamaño se define solo por el ancho.
foreach ( array(
	'medium_size_w'       => 400,
	'medium_size_h'       => 0,
	'medium_large_size_w' => 800,
	'medium_large_size_h' => 0,
	'large_size_w'        => 1200,
	'large_size_h'        => 0,
) as $jb3_opcion => $jb3_valor ) {
	add_filter( "pre_option_{$jb3_opcion}", function () use ( $jb3_valor ) {
		return $jb3_valor;
	} );
}
unset( $jb3_opcion, $jb3_valor );

add_action( 'after_setup_theme', function () {
	add_image_size( 'jb3-1920', 1920, 0 );
} );

// Los dos tamaños grandes que agrega WordPress por su cuenta y nadie usa.
add_filter( 'intermediate_image_sizes', function ( $sizes ) {
	return array_values( array_diff( $sizes, array( '1536x1536', '2048x2048' ) ) );
} );

// srcset: por defecto WordPress no ofrece copias de más de 2048px de ancho
// — sin esto, el original de 2560 nunca entraría en la lista y los
// monitores grandes / 4K se quedarían con una copia más chica.
add_filter( 'max_srcset_image_width', function () {
	return 2560;
} );

// Calidad WebP 80 (WordPress usa 86 por defecto; JPG 82). Probado 2026-10-01
// a la misma medida (800px) contra el JPG calidad 82 de antes:
//   poster Peter Rabbit (mucha textura)  JPG 267 KB | WebP 86: 289 (+8%!) | WebP 80: 225 (−16%)
//   poster Venom                         JPG 222 KB | WebP 86: 208 (−6%)  | WebP 80: 164 (−26%)
//   banner Shark Tank                    JPG  39 KB | WebP 86:  30 (−22%) | WebP 80:  23 (−41%)
// Con 86, en imágenes de mucho detalle el WebP pesaba IGUAL o MÁS que el
// JPG. Con 80 pesa menos en todos los casos, y en comparaciones ampliadas
// al doble no se vio diferencia con 86 (en 75 sí se empezaba a suavizar la
// textura fina). El sitio es muy visual: NO bajar de 80 sin comparar a ojo.
// Cambiar este número exige regenerar las imágenes ya subidas.
add_filter( 'wp_editor_set_quality', function ( $calidad, $mime ) {
	return 'image/webp' === $mime ? 80 : $calidad;
}, 10, 2 );

// JPEG/PNG (y HEIC, que WordPress ya convertía a JPEG) → copias en WebP.
add_filter( 'image_editor_output_format', function ( $formatos ) {
	foreach ( (array) $formatos as $origen => $destino ) {
		if ( 'image/jpeg' === $destino ) {
			$formatos[ $origen ] = 'image/webp'; // ej. HEIC → JPEG pasa a HEIC → WebP.
		}
	}
	$formatos['image/jpeg'] = 'image/webp';
	$formatos['image/png']  = 'image/webp';
	return $formatos;
} );

// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES COMPARTIDAS: imagen desktop + mobile que baja la copia justa
// (2026-10-01). Las usan hero, programación, banner y fondos — así cada uno
// solo dice "qué imágenes" y "a qué ancho se ven", sin repetir el armado de
// srcset/sizes. El rotador de la home tiene su propio armado
// (_init_home_slider.php) porque su <picture> tiene reglas propias (solo
// celular vertical, prioridad de la 1ª slide, carga diferida del resto).
// ═══════════════════════════════════════════════════════════════════════════

/**
 * ID del adjunto a partir de lo que devuelva un campo ACF de imagen, sea
 * cual sea su "formato de retorno": array (con 'ID'), ID numérico o URL.
 * Con URL se busca el adjunto por su dirección; si no aparece, 0.
 */
function jb3_img_id( $valor ): int {
	if ( is_array( $valor ) ) {
		return (int) ( $valor['ID'] ?? $valor['id'] ?? 0 );
	}
	if ( is_numeric( $valor ) ) {
		return (int) $valor;
	}
	return is_string( $valor ) && '' !== $valor ? (int) attachment_url_to_postid( $valor ) : 0;
}

/** sizes de algo que va a ancho completo de pantalla (hero, programación). */
function jb3_sizes_ancho_completo(): string {
	return '100vw';
}

/**
 * sizes de algo que va al ancho del CONTENIDO: pantalla menos el margen
 * lateral del sitio (--jb3-modulo-padding-x: 80/40/20 por lado en style.css).
 * Medido: el banner se ve a 1760px en un monitor de 1920.
 */
function jb3_sizes_ancho_contenido(): string {
	return '(max-width: 767px) calc(100vw - 40px), (max-width: 1023px) calc(100vw - 80px), calc(100vw - 160px)';
}

/**
 * <picture> con versión mobile opcional, srcset + sizes en las dos.
 *
 * @param int    $id_desktop Adjunto desktop (obligatorio — sin él devuelve '').
 * @param int    $id_mobile  Adjunto mobile (0 = en mobile se usa la de desktop).
 * @param array  $opciones   sizes          (default ancho completo)
 *                           media_mobile   (default '(max-width: 767px)')
 *                           prioridad      true = es lo primero que se ve
 *                                          (fetchpriority="high"); false = lazy
 *                           alt, class (del <picture>)
 */
function jb3_picture( int $id_desktop, int $id_mobile = 0, array $opciones = array() ): string {
	$src = $id_desktop ? wp_get_attachment_image_url( $id_desktop, 'full' ) : '';
	if ( ! $src ) {
		return '';
	}

	$o = array_merge( array(
		'sizes'        => jb3_sizes_ancho_completo(),
		'media_mobile' => '(max-width: 767px)',
		'prioridad'    => false,
		'alt'          => '',
		'class'        => '',
	), $opciones );

	$srcset_desktop = (string) wp_get_attachment_image_srcset( $id_desktop, 'full' );
	$src_mobile     = $id_mobile ? wp_get_attachment_image_url( $id_mobile, 'full' ) : '';
	$srcset_mobile  = $id_mobile ? (string) wp_get_attachment_image_srcset( $id_mobile, 'full' ) : '';
	$meta           = wp_get_attachment_metadata( $id_desktop );

	// La imagen prioritaria de la página ya está tomada — que WordPress no se
	// la asigne además a otra (ver el mismo criterio en _init_home_slider.php).
	if ( $o['prioridad'] && function_exists( 'wp_high_priority_element_flag' ) ) {
		wp_high_priority_element_flag( false );
	}

	$html = '<picture' . ( $o['class'] ? ' class="' . esc_attr( $o['class'] ) . '"' : '' ) . '>';
	if ( $src_mobile ) {
		// width/height TAMBIÉN en el <source>: la imagen mobile suele tener
		// otra proporción que la de desktop (ej. programación 667×1500 vs
		// 1920×1080). Sin esto el navegador reservaba el alto con la
		// proporción de DESKTOP y en celular la imagen se veía aplastada
		// hasta terminar de cargar (detectado con Playwright 2026-10-01).
		$meta_mobile = wp_get_attachment_metadata( $id_mobile );
		$html       .= '<source media="' . esc_attr( $o['media_mobile'] ) . '" srcset="' . esc_attr( $srcset_mobile ?: $src_mobile ) . '" sizes="' . esc_attr( $o['sizes'] ) . '"'
			. ( ! empty( $meta_mobile['width'] ) ? ' width="' . (int) $meta_mobile['width'] . '" height="' . (int) $meta_mobile['height'] . '"' : '' )
			. '>';
	}
	$html .= '<img src="' . esc_url( $src ) . '"'
		. ( $srcset_desktop ? ' srcset="' . esc_attr( $srcset_desktop ) . '" sizes="' . esc_attr( $o['sizes'] ) . '"' : '' )
		// width/height: el navegador reserva el alto antes de que la imagen
		// llegue — sin esto el contenido de abajo "salta" al cargar.
		. ( ! empty( $meta['width'] ) ? ' width="' . (int) $meta['width'] . '" height="' . (int) $meta['height'] . '"' : '' )
		. ' alt="' . esc_attr( $o['alt'] ) . '" decoding="async" '
		. ( $o['prioridad'] ? 'fetchpriority="high"' : 'loading="lazy"' )
		. '>';
	return $html . '</picture>';
}

/**
 * URL de la copia a usar como imagen de FONDO (CSS no tiene srcset): hasta
 * 1920 de pantalla, la copia de 1920 si existe; más grande, el original
 * (hasta 2560). Ver jb3 fondos en _init_backgrounds.php.
 */
function jb3_img_url( int $id, string $tamano = 'full' ): string {
	return $id ? (string) wp_get_attachment_image_url( $id, $tamano ) : '';
}

/**
 * <img> con srcset + sizes + width/height para un adjunto, para todo lo que
 * no es <picture> desktop/mobile (imagen suelta, galería, tarjetas, logos).
 * Sin copias (srcset vacío) queda el src solo, igual que antes.
 *
 * @param int    $id    Adjunto. 0 = devuelve ''.
 * @param string $sizes Ancho al que se ve (ver jb3_sizes_* / jb3_grid_sizes()).
 *                      Con 'auto' adelante y carga diferida, el navegador mide
 *                      el espacio real; el resto de la lista queda de respaldo
 *                      para los que no soportan auto (Safari, hoy).
 * @param array  $attrs alt, class, loading ('lazy' default | 'eager'),
 *                      tamano (default 'full'), y cualquier atributo extra
 *                      (ej. data-jb3-image) que se imprime tal cual escapado.
 */
function jb3_img( int $id, string $sizes, array $attrs = array() ): string {
	$tamano = $attrs['tamano'] ?? 'full';
	unset( $attrs['tamano'] );
	$src = $id ? wp_get_attachment_image_url( $id, $tamano ) : '';
	if ( ! $src ) {
		return '';
	}
	$attrs  = array_merge( array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ), $attrs );
	$srcset = (string) wp_get_attachment_image_srcset( $id, 'full' );
	$meta   = wp_get_attachment_metadata( $id );

	$html = '<img src="' . esc_url( $src ) . '"';
	if ( $srcset ) {
		$html .= ' srcset="' . esc_attr( $srcset ) . '" sizes="' . esc_attr( $sizes ) . '"';
	}
	if ( ! empty( $meta['width'] ) ) {
		$html .= ' width="' . (int) $meta['width'] . '" height="' . (int) $meta['height'] . '"';
	}
	foreach ( $attrs as $nombre => $valor ) {
		if ( '' === $valor && 'alt' !== $nombre ) {
			continue;
		}
		$html .= ' ' . esc_attr( $nombre ) . '="' . esc_attr( $valor ) . '"';
	}
	return $html . '>';
}

/**
 * Ancho en px al que se ve una imagen de ALTO fijo (galería: 420/320/220;
 * logos: cajas de alto fijo), según su proporción, con tope opcional de
 * ancho (la caja del logo). Para armar sizes en px exactos.
 */
function jb3_ancho_por_alto( int $id, float $alto, float $max_ancho = 0 ): int {
	$meta = $id ? wp_get_attachment_metadata( $id ) : false;
	if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return (int) ( $max_ancho ?: $alto );
	}
	$ancho = $alto * $meta['width'] / $meta['height'];
	return (int) ceil( $max_ancho > 0 ? min( $ancho, $max_ancho ) : $ancho );
}

/**
 * sizes de una Tarjeta suelta (bloque "Tarjeta"): se puede arrastrar a
 * cualquier lugar (sola, en Columnas, en una Grilla de N), así que no hay
 * forma de saber de antemano a qué ancho se ve. "auto" = el navegador lo
 * mide él mismo (necesita carga diferida, que jb3_img() pone por defecto);
 * el resto es el respaldo para los que no soportan auto (Safari, hoy):
 * supone al menos 2 columnas en desktop/tablet, que es el uso habitual.
 */
function jb3_sizes_tarjeta_suelta(): string {
	return 'auto, (max-width: 767px) calc(100vw - 40px), (max-width: 1023px) calc((100vw - 120px) / 2), calc((100vw - 200px) / 2)';
}

/** sizes de "Imagen suelta" del módulo de Contenido: tope de 720px en CSS. */
function jb3_sizes_imagen_suelta(): string {
	return '(max-width: 767px) calc(100vw - 40px), (max-width: 1023px) min(calc(100vw - 80px), 720px), 720px';
}

/** sizes de la imagen de "Imagen + texto": mitad del contenido (dos columnas, 40px de gap); en mobile, una sola columna. */
function jb3_sizes_media_columna(): string {
	return '(max-width: 767px) calc(100vw - 40px), (max-width: 1023px) calc((100vw - 120px) / 2), calc((100vw - 200px) / 2)';
}

/**
 * sizes de un LOGO en una caja de alto fijo por breakpoint, en px exactos.
 * OJO: los logos se muestran con width/height:auto + max-width/max-height
 * (tamaño natural limitado por la caja). Con srcset, el "tamaño natural" pasa
 * a ser el valor de sizes — por eso tiene que ser EXACTO (alto de la caja ×
 * proporción, con tope de ancho si la caja lo tiene): más chico achicaría el
 * logo. Verificado con una "foto" de tamaños antes/después (2026-10-01).
 *
 * @param int   $id     Adjunto del logo.
 * @param array $altos  [mobile, tablet, desktop] — alto de la caja en px.
 * @param array $anchos [mobile, tablet, desktop] — tope de ancho (0 = sin tope).
 */
function jb3_sizes_logo( int $id, array $altos, array $anchos = array( 0, 0, 0 ) ): string {
	return '(max-width: 767px) ' . jb3_ancho_por_alto( $id, $altos[0], $anchos[0] ) . 'px, '
		. '(max-width: 1023px) ' . jb3_ancho_por_alto( $id, $altos[1], $anchos[1] ) . 'px, '
		. jb3_ancho_por_alto( $id, $altos[2], $anchos[2] ) . 'px';
}
