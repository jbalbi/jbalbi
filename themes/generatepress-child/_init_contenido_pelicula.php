<?php
defined( 'ABSPATH' ) || exit;

// ─── CONTENIDO ESTRUCTURADO DE PELÍCULA/SERIE (prototipo, 2026-09-03) ──────
// Alternativa al editor de bloques libre para Películas/Series: un campo
// ACF Flexible Content ("Contenido de la película/serie", grupo creado por
// script — group_jb3_contenido_pelicula) con un layout por tipo de
// contenido (Sinopsis, Información, Trailer, Película completa, Especial,
// Galería, Imagen suelta, Banner, Integraciones, Contenido libre,
// Renta/Alquiler). El editor arma la página
// agregando/reordenando esos layouts por drag-and-drop, en el orden que
// quiera — misma libertad de orden que un builder, pero cada bloque sigue
// siendo un campo estructurado (no HTML libre).
//
// AGRUPACIÓN (conversación 2026-09-03): Trailer/Película completa/Especial/
// Galería se pueden repetir tantas veces como haga falta (ej. 2 trailers) —
// jb3_contenido_agrupar() junta las filas CONSECUTIVAS del mismo tipo para
// que el título de sección salga una sola vez, no una por fila. Sinopsis +
// Información se agrupan entre sí (aunque son layouts distintos) para
// quedar en 2 columnas cuando las dos están cargadas. "Imagen suelta" e
// "Integraciones" NUNCA se agrupan — cada fila es su propia sección, así el
// editor puede intercalar una entre dos trailers sin que se mezclen.
//
// jb3_render_contenido_pelicula() se imprime ANTES de the_content() en
// single-peliculas.php/single-series.php — el editor de bloques sigue
// disponible DEBAJO para el caso puntual que pida algo que ningún layout
// de acá cubra.
//
// "Relacionados" NO es un layout de este campo a propósito: sigue siendo
// 100% automático al pie de la página (jb3_relacionados_auto(), ya
// enganchado en el template) — el editor nunca lo elige.
//
// Subnav (pedido 2026-09-04): cada sección con título (Sinopsis,
// Información, Trailer, Ver completo, Especiales, Galería, Renta) se anota
// SOLA en la subnav dinámica del sitio vía jb3_contenido_subnav_id() más
// abajo — mismo motor que ya usan [jb3_modulo] y "Galería con Slider"
// (_init_subnav.php). El editor no agrega nada a mano ni elige qué entra:
// la barra siempre refleja exactamente lo que la película/serie tiene
// cargado en ese momento. single-peliculas.php/single-series.php ya
// imprimen `jb3_subnav_render( jb3_subnav_items() )` ANTES del artículo —
// como jb3_render_contenido_pelicula() corre dentro del mismo buffer de
// salida que arma ese template antes de esa línea, los registros llegan a
// tiempo sin tocar la plantilla para nada.
function jb3_render_contenido_pelicula( int $post_id ): string {
	if ( ! function_exists( 'have_rows' ) || ! have_rows( 'contenido', $post_id ) ) {
		return '';
	}

	$filas = [];
	while ( have_rows( 'contenido', $post_id ) ) {
		the_row();
		$filas[] = [
			'layout' => get_row_layout(),
			// get_row( true ): formateado, keyeado por NOMBRE de campo (no
			// por field key) y con los valores ya resueltos (ej. el campo
			// Imagen devuelve el array completo, no el ID crudo) — el
			// default get_row() sin argumento es "raw" y rompe todo esto.
			'data'   => get_row( true ),
		];
	}

	ob_start();
	?>
	<div class="jb3-contenido-pelicula">
		<?php foreach ( jb3_contenido_agrupar( $filas ) as $grupo ) : ?>
			<?php
			switch ( $grupo['tipo'] ) {
				case 'texto_par':
					jb3_contenido_render_texto_par( $grupo['filas'] );
					break;
				case 'trailer':
					jb3_contenido_render_videos( 'Trailer', $grupo['filas'] );
					break;
				case 'pelicula_completa':
					jb3_contenido_render_videos( 'Ver completo', $grupo['filas'] );
					break;
				case 'especial':
					jb3_contenido_render_videos( 'Especiales', $grupo['filas'], 'Especial' );
					break;
				case 'galeria':
					jb3_contenido_render_galeria( $grupo['filas'][0] );
					break;
				case 'imagen':
					jb3_contenido_render_imagen( $grupo['filas'][0] );
					break;
				case 'banner':
					jb3_contenido_render_banner( $grupo['filas'][0] );
					break;
				case 'integraciones':
					$fila = $grupo['filas'][0];
					jb3_contenido_render_integraciones( $post_id, (string) ( $fila['data']['titulo'] ?? '' ), (int) ( $fila['data']['limite'] ?? 0 ) );
					break;
				case 'contenido_libre':
					jb3_contenido_render_libre( $grupo['filas'][0] );
					break;
				case 'renta':
					jb3_contenido_render_renta( $grupo['filas'][0] );
					break;
			}
			?>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

// ─── Agrupación de filas consecutivas del mismo tipo ────────────────────────
// "texto_par": sinopsis + informacion comparten grupo aunque sean layouts
// distintos, tope de 2 filas (una de cada una) — un 3er texto consecutivo
// arranca grupo nuevo en vez de intentar una fila de 3 columnas.
// "imagen"/"banner"/"integraciones"/"contenido_libre"/"renta"/"galeria"
// nunca agrupan: cada fila es siempre su propio grupo, para poder intercalarlas
// libremente entre cualquier otro bloque. "galeria" en particular usa el
// carrusel compartido (jb3_render_galeria_slider(), _init_galeria_slider.php)
// — cada fila ya es un slider completo con su propio título, agruparlas no
// tendría sentido (a diferencia de Trailer/Especial, que sí comparten un
// único título de sección).
function jb3_contenido_agrupar( array $filas ): array {
	$agrupables = [ 'trailer', 'pelicula_completa', 'especial' ];
	$grupos     = [];
	$actual     = null;

	foreach ( $filas as $fila ) {
		$layout     = $fila['layout'];
		$es_texto   = in_array( $layout, [ 'sinopsis', 'informacion' ], true );
		$tipo_grupo = $es_texto ? 'texto_par' : $layout;
		$se_agrupa  = $es_texto || in_array( $layout, $agrupables, true );

		$topo_texto_lleno = $es_texto && $actual && count( $actual['filas'] ) >= 2;

		if ( $se_agrupa && $actual && $actual['tipo'] === $tipo_grupo && ! $topo_texto_lleno ) {
			$actual['filas'][] = $fila;
			continue;
		}

		if ( $actual ) {
			$grupos[] = $actual;
		}
		$actual = [ 'tipo' => $tipo_grupo, 'filas' => [ $fila ] ];
	}
	if ( $actual ) {
		$grupos[] = $actual;
	}
	return $grupos;
}

// Registra $titulo en la subnav dinámica (_init_subnav.php) y devuelve el id
// para usar como anchor — mismo patrón que ya usa [jb3_modulo] y el bloque
// "Galería con Slider" (jb3_subnav_id_unico() + jb3_subnav_register()).
// Pedido 2026-09-04: la subnav de Películas/Series tiene que reflejar SOLA
// lo que el editor cargó, sin que tenga que agregar [jb3_subnav_anchor] a
// mano en cada sección — cada función de render de acá abajo llama a esto
// antes de imprimir su <section>, así el link "#id" siempre apunta a la
// sección real. function_exists(): por las dudas si este archivo se
// llegara a cargar sin _init_subnav.php, no debería pasar nunca en este
// tema pero así no rompe si pasa.
function jb3_contenido_subnav_id( string $titulo ): string {
	if ( ! $titulo || ! function_exists( 'jb3_subnav_id_unico' ) || ! function_exists( 'jb3_subnav_register' ) ) {
		return '';
	}
	$id = jb3_subnav_id_unico( $titulo );
	jb3_subnav_register( $titulo, $id );
	return $id;
}

// ─── Grupo: Sinopsis + Información ──────────────────────────────────────────
// 2 columnas si las dos están cargadas; ancho completo si solo hay una. Dos
// entradas de subnav separadas (no una sola "Sinopsis / Información"): cada
// una puede faltar independientemente de la otra.
function jb3_contenido_render_texto_par( array $filas ) {
	$sinopsis    = '';
	$informacion = '';
	foreach ( $filas as $fila ) {
		if ( 'sinopsis' === $fila['layout'] ) {
			$sinopsis = $fila['data']['texto'] ?? '';
		} elseif ( 'informacion' === $fila['layout'] ) {
			$informacion = $fila['data']['texto'] ?? '';
		}
	}
	if ( ! $sinopsis && ! $informacion ) {
		return; // Layouts agregados pero sin completar — no imprime secciones vacías.
	}
	$dos_columnas = $sinopsis && $informacion;
	?>
	<div class="jb3-contenido-fila<?php echo $dos_columnas ? ' jb3-contenido-fila--dos-columnas' : ''; ?>">
		<?php if ( $sinopsis ) : ?>
			<section id="<?php echo esc_attr( jb3_contenido_subnav_id( 'Sinopsis' ) ); ?>" class="jb3-contenido-bloque jb3-contenido-bloque--texto">
				<h2 class="jb3-contenido-bloque__titulo">Sinopsis</h2>
				<div class="jb3-contenido-bloque__texto"><?php echo wp_kses_post( $sinopsis ); ?></div>
			</section>
		<?php endif; ?>
		<?php if ( $informacion ) : ?>
			<section id="<?php echo esc_attr( jb3_contenido_subnav_id( 'Información' ) ); ?>" class="jb3-contenido-bloque jb3-contenido-bloque--texto">
				<h2 class="jb3-contenido-bloque__titulo">Información</h2>
				<div class="jb3-contenido-bloque__texto"><?php echo wp_kses_post( $informacion ); ?></div>
			</section>
		<?php endif; ?>
	</div>
	<?php
}

// Miniatura de YouTube a partir de la URL — mismo regex que ya usa el JS del
// modal (_init_video_modal.php, parseVideo()) para reconocer un link de
// YouTube, portado a PHP. img.youtube.com/vi/{ID}/hqdefault.jpg existe
// SIEMPRE que el video exista (a diferencia de maxresdefault.jpg, que no
// todos los videos tienen en alta resolución) — sin llamar a ninguna API,
// es un patrón de URL fijo de YouTube.
function jb3_contenido_youtube_thumb( string $url ): ?string {
	if ( preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{6,})/', $url, $match ) ) {
		return 'https://img.youtube.com/vi/' . $match[1] . '/hqdefault.jpg';
	}
	return null;
}

// Miniatura de Vimeo — a diferencia de YouTube, Vimeo NO tiene un patrón de
// URL fijo para la miniatura, hace falta pedirla a su API oEmbed (pedido
// 2026-09-03). Cacheada en un transient 1 día: sin esto, cada carga de la
// página de la película le pegaría a la API de Vimeo por cada video — igual
// se cachea el resultado VACÍO ('no encontrado') para no reintentar la
// llamada en cada visita si el video no tiene miniatura o la URL es
// inválida (mismo criterio que otros caches de API externas del sitio,
// ej. JB3_Mailchimp::get_cached_interests()).
function jb3_contenido_vimeo_thumb( string $url ): ?string {
	if ( ! preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $url, $match ) ) {
		return null;
	}

	$cache_key = 'jb3_vimeo_thumb_' . $match[1];
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached ?: null;
	}

	$thumb    = '';
	$response = wp_remote_get( 'https://vimeo.com/api/oembed.json?url=' . rawurlencode( $url ), [ 'timeout' => 5 ] );
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$thumb = $body['thumbnail_url'] ?? '';
	}

	set_transient( $cache_key, $thumb, DAY_IN_SECONDS );
	return $thumb ?: null;
}

// Punto único que usa jb3_contenido_render_videos() — prueba YouTube
// primero (no pega a ninguna API, es gratis) y recién si no matchea prueba
// Vimeo (si tampoco matchea, ni siquiera intenta la llamada HTTP). Un
// archivo propio no tiene forma de sacar un frame sin procesarlo del lado
// del servidor — ahí vuelve null y el bloque se ve negro con el ícono de
// play, como antes.
function jb3_contenido_video_thumb( string $url ): ?string {
	return jb3_contenido_youtube_thumb( $url ) ?? jb3_contenido_vimeo_thumb( $url );
}

// ─── Grupo: Trailer / Película completa / Especial ─────────────────────────
// $video_url va tal cual al modal de video ya existente (_init_video_modal.php,
// data-jb3-video) — mismo campo/comportamiento que Videos/Op. Comerciales:
// el JS del modal detecta solo si es YouTube, Vimeo o un archivo propio.
// $titulo_item_default: "Especial" (los que no completaron el título propio
// muestran ese texto genérico en vez de quedar sin etiqueta).
function jb3_contenido_render_videos( string $titulo_grupo, array $filas, string $titulo_item_default = '' ) {
	$items = [];
	foreach ( $filas as $fila ) {
		$url = $fila['data']['video_url'] ?? '';
		if ( ! $url ) {
			continue;
		}
		$items[] = [
			'titulo'    => ( $fila['data']['titulo'] ?? '' ) ?: $titulo_item_default,
			'video_url' => $url,
			'thumb'     => jb3_contenido_video_thumb( $url ),
		];
	}
	if ( ! $items ) {
		return;
	}
	?>
	<section id="<?php echo esc_attr( jb3_contenido_subnav_id( $titulo_grupo ) ); ?>" class="jb3-contenido-bloque jb3-contenido-bloque--video">
		<h2 class="jb3-contenido-bloque__titulo"><?php echo esc_html( $titulo_grupo ); ?></h2>
		<div class="jb3-contenido-videos">
			<?php foreach ( $items as $item ) : ?>
				<div class="jb3-contenido-videos__item">
					<a
						class="jb3-contenido-video"
						href="<?php echo esc_url( $item['video_url'] ); ?>"
						data-jb3-video="<?php echo esc_url( $item['video_url'] ); ?>"
						<?php if ( $item['thumb'] ) : ?>
							style="background-image:url(<?php echo esc_url( $item['thumb'] ); ?>);"
						<?php endif; ?>
					>
						<span class="jb3-contenido-video__play" aria-hidden="true"></span>
					</a>
					<?php if ( $item['titulo'] ) : ?>
						<p class="jb3-contenido-videos__item-titulo"><?php echo esc_html( $item['titulo'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

// ─── Fila individual: Galería ────────────────────────────────────────────────
// Usa el MISMO carrusel que el bloque "Galería con Slider" del sitio
// (jb3_render_galeria_slider(), _init_galeria_slider.php — extraído de ese
// bloque 2026-09-03 justo para esto, no hay dos implementaciones del
// carrusel). Nunca se agrupa (ver jb3_contenido_agrupar): cada fila ya es
// un slider completo con su propio título, así que dos galerías seguidas
// simplemente salen como dos carruseles uno debajo del otro, cada uno con
// su propio nombre — no hace falta compartir un título de sección como sí
// pasa con Trailer/Especial.
function jb3_contenido_render_galeria( array $fila ) {
	$imagenes = $fila['data']['imagenes'] ?? [];
	if ( ! $imagenes || ! is_array( $imagenes ) ) {
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

	$titulo = $fila['data']['titulo'] ?: 'Galería';
	jb3_render_galeria_slider( $slides, $titulo, '', jb3_contenido_subnav_id( $titulo ) );
}

// ─── Fila individual: Imagen suelta ─────────────────────────────────────────
// Nunca se agrupa (ver jb3_contenido_agrupar) — pensada justo para poder
// intercalar una imagen entre dos bloques de otro tipo, sin arrastrar el
// editor de bloques libre para algo tan chico.
function jb3_contenido_render_imagen( array $fila ) {
	$img = $fila['data']['imagen'] ?? null;
	if ( ! $img || ! is_array( $img ) ) {
		return;
	}
	$epigrafe = $fila['data']['epigrafe'] ?? '';
	?>
	<figure class="jb3-contenido-bloque jb3-contenido-bloque--imagen">
		<img
			src="<?php echo esc_url( $img['sizes']['large'] ?? $img['url'] ); ?>"
			alt="<?php echo esc_attr( $img['alt'] ?? '' ); ?>"
			loading="lazy"
		>
		<?php if ( $epigrafe ) : ?>
			<figcaption class="jb3-contenido-bloque__epigrafe"><?php echo esc_html( $epigrafe ); ?></figcaption>
		<?php endif; ?>
	</figure>
	<?php
}

// ─── Fila individual: Banner ─────────────────────────────────────────────────
// Nunca se agrupa (ver jb3_contenido_agrupar) — mismo criterio que Imagen
// suelta, pero con imagen mobile propia (opcional, cae a la de desktop si
// no está cargada) más link y el mismo gate de país+fechas que Renta
// (fail-closed: sin país tildado, no se muestra en ningún lado —
// jb3_contenido_pais_permitido() y jb3_contenido_fecha_vigente(), ambas
// definidas más abajo/arriba en este archivo y ya compartidas con Renta).
function jb3_contenido_render_banner( array $fila ) {
	$data = $fila['data'];

	if ( ! jb3_contenido_fecha_vigente( (string) ( $data['fecha_in'] ?? '' ), (string) ( $data['fecha_out'] ?? '' ) ) ) {
		return;
	}

	$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';
	if ( ! jb3_contenido_pais_permitido( (array) ( $data['paises'] ?? [] ), $pais_visitante ) ) {
		return;
	}

	$desktop = $data['imagen_desktop'] ?? null;
	$mobile  = $data['imagen_mobile'] ?? null;
	$link    = $data['link'] ?? '';
	if ( ! is_array( $desktop ) || ! $link ) {
		return; // Layout agregado pero sin imagen de desktop o sin link — no imprime un banner roto.
	}

	$mobile_url = is_array( $mobile ) ? ( $mobile['url'] ?? '' ) : '';
	?>
	<a class="jb3-contenido-bloque jb3-contenido-bloque--banner" href="<?php echo esc_url( $link ); ?>">
		<picture>
			<?php if ( $mobile_url ) : ?>
				<source media="(max-width: 767px)" srcset="<?php echo esc_url( $mobile_url ); ?>">
			<?php endif; ?>
			<img
				src="<?php echo esc_url( $desktop['url'] ); ?>"
				alt="<?php echo esc_attr( $desktop['alt'] ?? '' ); ?>"
				loading="lazy"
			>
		</picture>
	</a>
	<?php
}

// ─── Fila individual: Integraciones ─────────────────────────────────────────
// Reusa el shortcode [jb3_modulo] que ya existe (_init_modulo.php) en vez de
// reimplementar nada — el canal se saca solo de la taxonomía "canales" del
// propio post (Películas/Series ya la tienen, ver _init_taxonomias.php), no
// hace falta que el editor lo tipee de nuevo acá.
function jb3_contenido_render_integraciones( int $post_id, string $titulo, int $limite ) {
	$canal_slugs = [];
	foreach ( wp_get_post_terms( $post_id, 'canales' ) as $term ) {
		$canal_slugs[] = $term->slug;
	}

	$atts = [
		'tipo'   => 'integraciones',
		'titulo' => $titulo ?: 'Integraciones',
		'limite' => $limite > 0 ? (string) $limite : '5',
	];
	if ( $canal_slugs ) {
		$atts['canal'] = implode( ',', $canal_slugs );
	}

	$shortcode = '[jb3_modulo';
	foreach ( $atts as $key => $value ) {
		$shortcode .= ' ' . $key . '="' . esc_attr( $value ) . '"';
	}
	$shortcode .= ']';

	echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_modulo ya escapa todo lo suyo.
}

// ─── Fila individual: Contenido libre ───────────────────────────────────────
// Válvula de escape para lo que no entra en ningún layout fijo (un banner,
// un shortcode como [jb3_modulo ...], texto simple) — nunca se agrupa
// (conversación 2026-09-03), así se puede intercalar entre cualquier par de
// bloques. Sin título de sección: es contenido crudo, no una sección con
// nombre fijo como Sinopsis/Trailer.
//
// Campo tipo 'textarea', NO 'wysiwyg' (cambiado 2026-09-03): el WYSIWYG
// adentro de un layout de Flexible Content tiene un bug conocido de ACF/
// TinyMCE — la solapa Visual (a veces ninguna de las dos) no inicializa
// bien en filas agregadas dinámicamente con el "+" del editor, dejando el
// campo sin poder escribir nada. Un textarea plano no depende de TinyMCE,
// cero riesgo de este bug — a cambio, wpautop() acá arma los párrafos a
// mano (el textarea no lo hace solo como sí hacía el wysiwyg).
//
// do_shortcode() para que un shortcode pegado en el texto se renderice de
// verdad en vez de quedar como texto literal — sin wp_kses_post() a
// propósito: el editor que carga esto ya es de confianza (mismo nivel que
// the_content()), y kses podría cortar atributos/clases que el shortcode
// necesita (ej. data-jb3-video).
function jb3_contenido_render_libre( array $fila ) {
	$texto = $fila['data']['texto'] ?? '';
	if ( ! $texto ) {
		return;
	}
	// Mismo orden que usa 'the_content' en el core: wpautop() arma los <p>,
	// shortcode_unautop() deshace el <p> que le puso alrededor a un
	// shortcode que estaba solo en su propia línea (si no, un shortcode de
	// bloque como [jb3_modulo] queda envuelto en un <p> — HTML inválido,
	// bloques adentro de un párrafo), recién ahí do_shortcode() lo expande.
	$html = do_shortcode( shortcode_unautop( wpautop( $texto ) ) );
	echo '<div class="jb3-contenido-bloque jb3-contenido-bloque--libre">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// ─── Fila individual: Renta / Alquiler ──────────────────────────────────────
// Lista fija de plataformas (pedido explícito 2026-09-03, a diferencia del
// título que sí se dejó abierto por país) — UN SOLO lugar en código que
// alimenta tanto las choices del checkbox del campo ACF (ver script que lo
// creó) como el repeater de logos de Ajustes del sitio. Agregar una
// plataforma nueva más adelante implica tocar acá Y reimportar el campo
// ACF con la choice nueva — no hay forma de que quede 100% sin tocar código,
// a diferencia del título por país que si es 100% configurable sin deploy.
function jb3_contenido_plataformas_renta(): array {
	return [
		'google_play'  => 'Google Play',
		'apple_tv'     => 'Apple TV',
		'amazon_prime' => 'Amazon Prime Video',
		'claro_video'  => 'Claro Video',
		'movistar_tv'  => 'Movistar TV',
		'flow'         => 'Flow',
		'nuevo_siglo'  => 'Nuevo Siglo',
	];
}

// Logos cargados UNA sola vez en Ajustes del sitio → Plataformas de
// streaming (repeater slug+nombre+logo, sembrado por script) — no en cada
// película, mismo criterio que el logo del canal. Cacheado en memoria: se
// puede pedir varias veces en la misma request si hay más de un módulo de
// Renta en la página.
function jb3_contenido_plataforma_logo( string $slug ): string {
	static $cache = null;
	if ( null === $cache ) {
		$cache = [];
		if ( function_exists( 'have_rows' ) && have_rows( 'plataformas_streaming_logos', 'option' ) ) {
			while ( have_rows( 'plataformas_streaming_logos', 'option' ) ) {
				the_row();
				$fila_slug = get_sub_field( 'slug' );
				$logo      = get_sub_field( 'logo' );
				if ( $fila_slug && is_array( $logo ) && ! empty( $logo['url'] ) ) {
					$cache[ $fila_slug ] = $logo['url'];
				}
			}
		}
	}
	return $cache[ $slug ] ?? '';
}

// Título por país: busca en "titulos_por_pais" un match con el país del
// visitante; sin match, cae al "titulo_defecto". Recibe el país del
// visitante ya resuelto (no lo calcula acá) para poder testear la función
// sola sin depender de jb3_geo_visitor_country().
function jb3_contenido_renta_titulo( array $data, string $pais_visitante ): string {
	foreach ( (array) ( $data['titulos_por_pais'] ?? [] ) as $fila ) {
		$term_id = $fila['pais'] ?? null;
		if ( ! $term_id ) {
			continue;
		}
		$term = get_term( $term_id, 'pais' );
		if ( $term && ! is_wp_error( $term ) && $term->slug === $pais_visitante ) {
			return $fila['titulo'] ?: $data['titulo_defecto'];
		}
	}
	return $data['titulo_defecto'] ?: 'Renta';
}

// $term_ids: valores crudos del campo taxonomy "paises" de UNA fila de
// plataforma (array de IDs de término, o vacío). Fail-closed: sin ningún
// país tildado, esta fila puntual no le aplica a nadie — mismo criterio que
// jb3_geo_canal_paises_permitidos() en jb3-geo-targeting.php (mismo
// patrón: IDs de término → slugs → in_array contra el país detectado).
function jb3_contenido_pais_permitido( array $term_ids, string $pais_visitante ): bool {
	if ( ! $pais_visitante || ! $term_ids ) {
		return false;
	}
	$slugs = get_terms( [
		'taxonomy'   => 'pais',
		'include'    => array_map( 'intval', $term_ids ),
		'fields'     => 'slugs',
		'hide_empty' => false,
	] );
	return ! is_wp_error( $slugs ) && in_array( $pais_visitante, $slugs, true );
}

// $fecha_in/$fecha_out: strings 'Ymd' o '' (return_format del date_picker
// de ACF) — cualquiera de las dos vacía = sin límite de ese lado. Reusado
// por Renta y Banner, mismo criterio los dos.
function jb3_contenido_fecha_vigente( string $fecha_in, string $fecha_out ): bool {
	$hoy = current_time( 'Ymd' );
	if ( $fecha_in && $hoy < $fecha_in ) {
		return false;
	}
	if ( $fecha_out && $hoy > $fecha_out ) {
		return false;
	}
	return true;
}

// Fechas genéricas para todo el módulo (fecha_in/fecha_out) — cualquiera de
// las dos vacía = sin límite de ese lado. Países SON por link, no genéricos
// del módulo (rediseño 2026-09-03, 2da vuelta): la misma plataforma puede
// repetirse con países y links distintos (ej. Apple TV AR/UY con un link,
// Apple TV Brasil con otro) — cada fila de "plataformas" decide sola si le
// aplica al visitante, jb3_contenido_pais_permitido() de arriba.
function jb3_contenido_render_renta( array $fila ) {
	$data = $fila['data'];

	if ( ! jb3_contenido_fecha_vigente( (string) ( $data['fecha_in'] ?? '' ), (string) ( $data['fecha_out'] ?? '' ) ) ) {
		return;
	}

	$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';

	// El logo no se carga en esta fila: se busca por el slug de
	// "plataforma" en Ajustes del sitio → Plataformas de streaming.
	$items = [];
	foreach ( (array) ( $data['plataformas'] ?? [] ) as $fila_plataforma ) {
		$slug  = $fila_plataforma['plataforma'] ?? '';
		$link  = $fila_plataforma['link'] ?? '';
		$paises_fila = (array) ( $fila_plataforma['paises'] ?? [] );

		if ( ! $slug || ! $link || ! jb3_contenido_pais_permitido( $paises_fila, $pais_visitante ) ) {
			continue; // Fila incompleta, o simplemente no le aplica al país del visitante — se salta, no rompe el resto.
		}
		$logo = jb3_contenido_plataforma_logo( $slug );
		if ( ! $logo ) {
			continue; // Plataforma sin logo subido todavía en Ajustes del sitio.
		}
		$items[] = [
			'slug'  => $slug,
			'link'  => $link,
			'logo'  => $logo,
			'label' => jb3_contenido_plataformas_renta()[ $slug ] ?? '',
		];
	}
	if ( ! $items ) {
		return; // Ningún link le aplica a este visitante en este momento — no imprime un módulo vacío.
	}

	$titulo = jb3_contenido_renta_titulo( $data, $pais_visitante );
	?>
	<section id="<?php echo esc_attr( jb3_contenido_subnav_id( $titulo ) ); ?>" class="jb3-contenido-bloque jb3-contenido-bloque--renta">
		<h2 class="jb3-contenido-bloque__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<div class="jb3-contenido-renta__plataformas">
			<?php foreach ( $items as $item ) : ?>
				<a class="jb3-contenido-renta__plataforma" href="<?php echo esc_url( $item['link'] ); ?>">
					<img src="<?php echo esc_url( $item['logo'] ); ?>" alt="<?php echo esc_attr( $item['label'] ); ?>" loading="lazy">
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
