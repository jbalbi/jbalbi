<?php
defined( 'ABSPATH' ) || exit;

// ─── CONTENIDO ESTRUCTURADO DE PELÍCULA/SERIE (prototipo, 2026-09-03) ──────
// Alternativa al editor de bloques libre para Películas/Series: un campo
// ACF Flexible Content ("Contenido de la película/serie", grupo creado por
// script — group_jb3_contenido_pelicula) con un layout por tipo de
// contenido (Sinopsis, Información, Video, Galería, Imagen suelta,
// Imagen + Texto, Banner, Integraciones, Contenido libre, Renta/Alquiler).
// El editor arma la página
// agregando/reordenando esos layouts por drag-and-drop, en el orden que
// quiera — misma libertad de orden que un builder, pero cada bloque sigue
// siendo un campo estructurado (no HTML libre).
//
// "Video" (unificado 2026-09-04, reemplaza a los viejos layouts fijos
// Trailer/Película completa/Especial): título de sección 100% libre —
// el editor escribe "Trailer", "Ver completo", "Especiales", "Grabaciones"
// o lo que corresponda — con un repetidor interno de video(s), así varios
// videos que comparten un mismo título de sección van TODOS dentro de un
// solo bloque "Video" en vez de agrupar filas repetidas del mismo layout
// por código. Ver jb3_contenido_render_video() más abajo.
//
// AGRUPACIÓN (conversación 2026-09-03): Galería se puede repetir tantas
// veces como haga falta (ej. 2 galerías) pero NUNCA agrupa (cada fila ya es
// su propio slider con su propio título) — jb3_contenido_agrupar() solo
// junta Sinopsis + Información entre sí (aunque son layouts distintos) para
// quedar en 2 columnas cuando las dos están cargadas. "Imagen suelta",
// "Integraciones" y "Video" NUNCA se agrupan — cada fila es su propia
// sección, así el editor puede intercalar una entre dos videos sin que se
// mezclen.
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
// (inc/subnav.php). El editor no agrega nada a mano ni elige qué entra:
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
	<div class="jb3-contenido-modulo">
		<?php foreach ( jb3_contenido_agrupar( $filas ) as $grupo ) : ?>
			<?php
			switch ( $grupo['tipo'] ) {
				case 'texto_par':
					jb3_contenido_render_texto_par( $grupo['filas'] );
					break;
				case 'video':
					jb3_contenido_render_video( $grupo['filas'][0] );
					break;
				case 'galeria':
					jb3_contenido_render_galeria( $grupo['filas'][0] );
					break;
				case 'imagen':
					jb3_contenido_render_imagen( $grupo['filas'][0] );
					break;
				case 'imagen_texto':
					jb3_contenido_render_imagen_texto( $grupo['filas'][0] );
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
// "imagen"/"banner"/"integraciones"/"contenido_libre"/"renta"/"galeria"/
// "video" nunca agrupan: cada fila es siempre su propio grupo, para poder
// intercalarlas libremente entre cualquier otro bloque. "galeria" en
// particular usa el carrusel compartido (jb3_render_galeria_slider(),
// inc/galeria-slider.php) — cada fila ya es un slider completo con su
// propio título, agruparlas no tendría sentido. "video" (unificado
// 2026-09-04) tampoco agrupa por código: el propio layout ya trae un
// repetidor interno de videos, así que varios videos bajo un mismo título
// de sección van todos DENTRO de una sola fila del campo, no en filas
// separadas que haya que agrupar acá.
function jb3_contenido_agrupar( array $filas ): array {
	$grupos = [];
	$actual = null;

	foreach ( $filas as $fila ) {
		$layout     = $fila['layout'];
		$es_texto   = in_array( $layout, [ 'sinopsis', 'informacion' ], true );
		$tipo_grupo = $es_texto ? 'texto_par' : $layout;

		$topo_texto_lleno = $es_texto && $actual && count( $actual['filas'] ) >= 2;

		if ( $es_texto && $actual && $actual['tipo'] === $tipo_grupo && ! $topo_texto_lleno ) {
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

// Registra $titulo en la subnav dinámica (inc/subnav.php) y devuelve el id
// para usar como anchor — mismo patrón que ya usa [jb3_modulo] y el bloque
// "Galería con Slider" (jb3_subnav_id_unico() + jb3_subnav_register()).
// Pedido 2026-09-04: la subnav de Películas/Series tiene que reflejar SOLA
// lo que el editor cargó, sin que tenga que agregar [jb3_subnav_anchor] a
// mano en cada sección — cada función de render de acá abajo llama a esto
// antes de imprimir su <section>, así el link "#id" siempre apunta a la
// sección real. function_exists(): por las dudas si este archivo se
// llegara a cargar sin inc/subnav.php, no debería pasar nunca en este
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
// modal (inc/video-modal.php, parseVideo()) para reconocer un link de
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

// Punto único que usa jb3_contenido_render_video() — prueba YouTube
// primero (no pega a ninguna API, es gratis) y recién si no matchea prueba
// Vimeo (si tampoco matchea, ni siquiera intenta la llamada HTTP). Un
// archivo propio no tiene forma de sacar un frame sin procesarlo del lado
// del servidor — ahí vuelve null y el bloque se ve negro con el ícono de
// play, como antes.
function jb3_contenido_video_thumb( string $url ): ?string {
	return jb3_contenido_youtube_thumb( $url ) ?? jb3_contenido_vimeo_thumb( $url );
}

// ─── Fila individual: Video ──────────────────────────────────────────────
// Reemplaza a los viejos layouts fijos Trailer/Película completa/Especial
// (unificados 2026-09-04, ver comentario de cabecera del archivo). Título
// de sección 100% libre — el editor escribe el que corresponda — con una
// "bajada" opcional (mismo estilo que ya usa el módulo/galería,
// .jb3-modulo__bajada) y un repetidor interno "videos" donde CADA fila
// tiene su propio subtítulo opcional (solo hace falta si hay más de uno) y
// su propio $video_url, que va tal cual al modal de video ya existente
// (inc/video-modal.php, data-jb3-video) — mismo campo/comportamiento que
// Videos/Op. Comerciales: el JS del modal detecta solo si es YouTube,
// Vimeo o un archivo propio.
function jb3_contenido_render_video( array $fila ) {
	$data   = $fila['data'];
	$titulo = $data['titulo'] ?? '';
	if ( ! $titulo ) {
		return; // Layout agregado pero sin título de sección — no imprime una sección sin nombre.
	}

	$items = [];
	foreach ( (array) ( $data['videos'] ?? [] ) as $fila_video ) {
		$url = $fila_video['video_url'] ?? '';
		if ( ! $url ) {
			continue;
		}
		$items[] = [
			'titulo'    => $fila_video['titulo'] ?? '',
			'video_url' => $url,
			'thumb'     => jb3_contenido_video_thumb( $url ),
		];
	}
	if ( ! $items ) {
		return;
	}

	$bajada = $data['bajada'] ?? '';
	?>
	<section id="<?php echo esc_attr( jb3_contenido_subnav_id( $titulo ) ); ?>" class="jb3-contenido-bloque jb3-contenido-bloque--video">
		<h2 class="jb3-contenido-bloque__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<?php if ( $bajada ) : ?>
			<div class="jb3-modulo__bajada"><?php echo wp_kses_post( wpautop( $bajada ) ); ?></div>
		<?php endif; ?>
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
// (jb3_render_galeria_slider(), inc/galeria-slider.php — extraído de ese
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
			$slides[] = [ 'src' => $src, 'full' => $full, 'alt' => $alt, 'id' => jb3_img_id( $img ) ]; // id: copia justa en jb3_render_galeria_slider().
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
		<?php
		// Copia justa (tope de 720px en CSS) — jb3_img(), inc/imagenes.php.
		echo jb3_img( jb3_img_id( $img ), jb3_sizes_imagen_suelta(), array( 'alt' => (string) ( $img['alt'] ?? '' ), 'tamano' => 'large' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo.
			?: '<img src="' . esc_url( $img['sizes']['large'] ?? $img['url'] ) . '" alt="' . esc_attr( $img['alt'] ?? '' ) . '" loading="lazy">';
		?>
		<?php if ( $epigrafe ) : ?>
			<figcaption class="jb3-contenido-bloque__epigrafe"><?php echo esc_html( $epigrafe ); ?></figcaption>
		<?php endif; ?>
	</figure>
	<?php
}

// ─── Fila individual: Imagen + Texto ────────────────────────────────────────
// Genérico a propósito (pedido 2026-09-04, en vez de un layout puntual
// "Host"): imagen a la izquierda + texto libre a la derecha, 2 columnas
// (mismo grid que ya usa Sinopsis+Información, .jb3-contenido-fila--dos-
// columnas), título de sección libre. Sirve tanto para el bloque "Host" de
// Prod. Originales (nombre + bio del conductor) como para cualquier otro
// contenido futuro con esta misma forma — si algún día hace falta soportar
// más de una persona a la vez (ej. 2 co-hosts), por ahora alcanza con que el
// editor escriba las dos bios en el mismo campo de texto; no hace falta un
// repetidor todavía.
//
// Campo 'texto' tipo 'wysiwyg' (cambiado 2026-09-04 a pedido del usuario,
// pese al riesgo conocido de TinyMCE en filas de Flexible Content agregadas
// dinámicamente que motivó el textarea de "Contenido libre" — acá SÍ hace
// falta negrita/links reales, no alcanza con texto plano). Mismo criterio
// que Sinopsis/Información (jb3_contenido_render_texto_par): el wysiwyg ya
// devuelve HTML con párrafos armados solo (ACF aplica su propio wpautop
// interno al formatear el valor), así que NO hace falta wpautop() acá — a
// diferencia de "Contenido libre"/"Video" (bajada), que sí son texto plano.
// Si en el futuro se confirma que este campo queda roto en filas agregadas
// con el "+" (mismo bug), hay que volver a textarea.
function jb3_contenido_render_imagen_texto( array $fila ) {
	$data   = $fila['data'];
	$titulo = $data['titulo'] ?? '';
	$img    = $data['imagen'] ?? null;
	$texto  = $data['texto'] ?? '';
	if ( ! is_array( $img ) || ! $texto ) {
		return; // Layout agregado pero sin imagen o sin texto — no imprime un bloque a medias.
	}
	$id = $titulo ? jb3_contenido_subnav_id( $titulo ) : '';
	?>
	<section<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> class="jb3-contenido-bloque jb3-contenido-bloque--imagen-texto">
		<?php if ( $titulo ) : ?>
			<h2 class="jb3-contenido-bloque__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<?php endif; ?>
		<div class="jb3-contenido-fila--dos-columnas">
			<?php
			// Copia justa (media columna; una sola en mobile) — jb3_img(), inc/imagenes.php.
			echo jb3_img( jb3_img_id( $img ), jb3_sizes_media_columna(), array( 'alt' => (string) ( $img['alt'] ?? '' ), 'tamano' => 'large' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo.
				?: '<img src="' . esc_url( $img['sizes']['large'] ?? $img['url'] ) . '" alt="' . esc_attr( $img['alt'] ?? '' ) . '" loading="lazy">';
			?>
			<div class="jb3-contenido-bloque__texto"><?php echo wp_kses_post( $texto ); ?></div>
		</div>
	</section>
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
	$link    = (string) ( $data['link'] ?? '' );
	if ( ! is_array( $desktop ) ) {
		return; // Layout agregado pero sin imagen de desktop — no imprime un banner roto.
	}

	// Opciones (pedido 2026-10-01):
	// - link OPCIONAL: sin link el banner se muestra igual, como <div> (no
	//   clickeable) en vez de <a>. Antes, sin link no se mostraba.
	// - nueva_pestana: target="_blank" (con rel="noopener" por seguridad);
	//   apagado = misma ventana, que era el único comportamiento antes.
	// - ancho_completo: de borde a borde, sin los márgenes laterales del
	//   contenido (.jb3-contenido-bloque--banner-completo en main.css). En el
	//   bloque de páginas lo prende el botón nativo "Ancho completo" de la
	//   barra del bloque (ver blocks/banner/render.php), que llega acá como
	//   este mismo dato.
	$nueva_pestana  = ! empty( $data['nueva_pestana'] );
	$ancho_completo = ! empty( $data['ancho_completo'] );

	// Copia justa por pantalla: al ancho del contenido, o de la pantalla
	// entera si va de borde a borde — jb3_picture(), inc/imagenes.php.
	// Mismo render para el layout del módulo de Contenido y para el bloque
	// Banner de páginas.
	$picture = jb3_picture(
		jb3_img_id( $desktop ),
		jb3_img_id( $mobile ),
		array(
			'sizes' => $ancho_completo ? jb3_sizes_ancho_completo() : jb3_sizes_ancho_contenido(),
			'alt'   => (string) ( $desktop['alt'] ?? '' ),
		)
	);
	if ( ! $picture ) {
		return;
	}

	$clases = 'jb3-contenido-bloque jb3-contenido-bloque--banner' . ( $ancho_completo ? ' jb3-contenido-bloque--banner-completo alignfull' : '' );
	if ( $link ) {
		printf(
			'<a class="%s" href="%s"%s>%s</a>',
			esc_attr( $clases ),
			esc_url( $link ),
			$nueva_pestana ? ' target="_blank" rel="noopener"' : '',
			$picture // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_picture() ya escapa todo.
		);
	} else {
		printf( '<div class="%s">%s</div>', esc_attr( $clases ), $picture ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_picture() ya escapa todo.
	}
}

// ─── Fila individual: Integraciones ─────────────────────────────────────────
// Reusa el shortcode [jb3_modulo] que ya existe (inc/modulo.php) en vez de
// reimplementar nada — el canal se saca solo de la taxonomía "canales" del
// propio post (Películas/Series ya la tienen, ver inc/taxonomias.php), no
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
// Plataformas 100% dinámicas desde Ajustes del sitio → "Plataformas de
// streaming" (cambiado 2026-09-07, a pedido del usuario — antes era una
// lista fija en código, que obligaba a tocar código Y reimportar el campo
// ACF cada vez que hacía falta una plataforma nueva). Un solo loop
// compartido (jb3_contenido_plataformas_streaming_filas()) arma slug→
// {nombre, logo} leyendo esa tabla en vivo; tanto el desplegable "Plataforma"
// del campo Renta (filtro acf/load_field más abajo) como el logo que se
// pinta en el front (jb3_contenido_plataforma_logo()) salen de ahí. Cacheado
// en memoria por request: la tabla no cambia en el medio de una misma carga
// de página, no hace falta re-consultarla más de una vez aunque haya varios
// módulos de Renta o Grid de personas en la misma página.
function jb3_contenido_plataformas_streaming_filas(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = [];
	if ( function_exists( 'have_rows' ) && have_rows( 'plataformas_streaming_logos', 'option' ) ) {
		while ( have_rows( 'plataformas_streaming_logos', 'option' ) ) {
			the_row();
			$slug = get_sub_field( 'slug' );
			if ( ! $slug ) {
				continue;
			}
			$logo           = get_sub_field( 'logo' );
			$cache[ $slug ] = [
				'nombre' => (string) get_sub_field( 'nombre' ),
				'logo'   => ( is_array( $logo ) && ! empty( $logo['url'] ) ) ? $logo['url'] : '',
				'logo_id' => jb3_img_id( $logo ), // copia justa en el módulo Renta (jb3_img()).
			];
		}
	}
	return $cache;
}

// Devuelve slug => nombre de las plataformas con nombre cargado — usado
// tanto para la etiqueta que se ve en el <img alt="..."> del logo
// (jb3_contenido_render_renta()) como para armar el desplegable "Plataforma"
// (filtro acf/load_field más abajo).
function jb3_contenido_plataformas_renta(): array {
	$out = [];
	foreach ( jb3_contenido_plataformas_streaming_filas() as $slug => $fila ) {
		if ( $fila['nombre'] ) {
			$out[ $slug ] = $fila['nombre'];
		}
	}
	return $out;
}

function jb3_contenido_plataforma_logo( string $slug ): string {
	$filas = jb3_contenido_plataformas_streaming_filas();
	return $filas[ $slug ]['logo'] ?? '';
}

// Arma el desplegable "Plataforma" del campo Renta con lo que haya cargado
// en Ajustes del sitio en ESE momento — agregar/sacar una fila ahí se
// refleja solo la próxima vez que se abre el editor, sin tocar código ni
// reimportar el grupo ACF.
add_filter( 'acf/load_field/key=field_jb3_renta_plataformas_plataforma', function ( $field ) {
	$field['choices'] = jb3_contenido_plataformas_renta();
	return $field;
} );

// El "Slug" de cada plataforma se usa como clave de array (jb3_contenido_
// plataforma_logo(), y lo que el editor eligió en el campo Renta de cada
// película se compara contra esto) — sanitize_key() lo deja en minúsculas y
// solo [a-z0-9_-], mismo criterio que ya usan los slugs del resto del sitio
// (canales, países), para que un typo con mayúsculas/espacios no rompa el
// match en silencio.
add_filter( 'acf/update_value/key=field_jb3_plataformas_logos_slug', function ( $value ) {
	return sanitize_key( $value );
} );

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
			'logo_id' => (int) ( jb3_contenido_plataformas_streaming_filas()[ $slug ]['logo_id'] ?? 0 ),
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
					<?php
					// Copia justa (2026-10-01): desktop/tablet = alto fijo de 80px
					// (ancho por proporción); mobile = media fila (2 por fila,
					// calc(50% - 8px) del contenido), ver
					// .jb3-contenido-renta__plataforma en main.css.
					$logo_html = $item['logo_id'] ? jb3_img( $item['logo_id'], '(max-width: 767px) calc(50vw - 28px), ' . jb3_ancho_por_alto( $item['logo_id'], 80 ) . 'px', array( 'alt' => $item['label'] ) ) : '';
					if ( $logo_html ) :
						echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo.
					else :
						?>
						<img src="<?php echo esc_url( $item['logo'] ); ?>" alt="<?php echo esc_attr( $item['label'] ); ?>" loading="lazy">
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

// ─── Cintillo de grilla: título de Renta activa (pedido 2026-09-07) ─────────
// Para Películas/Series, el cintillo que se ve en .jb3-grid-posters/
// .jb3-grid-thumbs tiene que mostrar el título de Renta/Alquiler (ej.
// "Alquiler") en vez del texto manual del campo "cintillo" (group_jb3_
// cintillo) CUANDO la película/serie tiene una fila "renta" vigente para el
// país del visitante — misma validación exacta que usa
// jb3_contenido_render_renta() (fecha vigente + al menos una plataforma con
// país permitido y logo cargado), para no mostrar el cintillo de Renta si
// esa sección en realidad no se va a ver. Devuelve null (no string vacío) si
// no aplica, así jb3_grid_cintillo_texto() (inc/grids.php) sabe que tiene
// que caer al campo manual en vez de mostrar "nada" a propósito.
function jb3_pelicula_renta_titulo_activo( int $post_id ): ?string {
	if ( ! function_exists( 'have_rows' ) || ! have_rows( 'contenido', $post_id ) ) {
		return null;
	}

	$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';

	while ( have_rows( 'contenido', $post_id ) ) {
		the_row();
		if ( 'renta' !== get_row_layout() ) {
			continue;
		}
		$data = get_row( true );
		if ( ! jb3_contenido_fecha_vigente( (string) ( $data['fecha_in'] ?? '' ), (string) ( $data['fecha_out'] ?? '' ) ) ) {
			continue;
		}
		foreach ( (array) ( $data['plataformas'] ?? [] ) as $fila_plataforma ) {
			$slug        = $fila_plataforma['plataforma'] ?? '';
			$link        = $fila_plataforma['link'] ?? '';
			$paises_fila = (array) ( $fila_plataforma['paises'] ?? [] );
			if ( ! $slug || ! $link || ! jb3_contenido_pais_permitido( $paises_fila, $pais_visitante ) ) {
				continue;
			}
			if ( ! jb3_contenido_plataforma_logo( $slug ) ) {
				continue;
			}
			return jb3_contenido_renta_titulo( $data, $pais_visitante );
		}
	}
	return null;
}
