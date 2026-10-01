<?php
defined( 'ABSPATH' ) || exit;

// ─── CONTENIDO ESTRUCTURADO DE PROD. ORIGINALES (2026-09-04) ────────────────
// Mismo mecanismo que Películas/Series (_init_contenido_pelicula.php: un
// campo ACF Flexible Content, un layout por tipo de contenido, el editor
// arma la página agregando/reordenando por drag-and-drop) pero con su PROPIO
// campo — group_jb3_contenido_original, campo "contenido_original" — porque
// Prod. Originales son las páginas más flexibles del sitio y necesitan 3
// layouts propios que Películas/Series no usan: Grid de personas (sharks,
// chefs invitados, staff — cada uno abre su bio en el modal de página ya
// existente), Prensa (grid de miniaturas a notas externas) y Grid de videos
// (grilla compacta de varios videos, a diferencia del layout "Video" que es
// una sección grande con uno o pocos videos destacados).
//
// Todo lo demás (Sinopsis, Información, Galería, Imagen suelta, Banner,
// Integraciones, Contenido libre, Video) reusa TAL CUAL las funciones de
// render ya escritas en _init_contenido_pelicula.php — no hay dos
// implementaciones de lo mismo, esta capa solo agrega los 3 layouts nuevos y
// arma el switch con los que corresponden a este CPT.
function jb3_render_contenido_original( int $post_id ): string {
	if ( ! function_exists( 'have_rows' ) || ! have_rows( 'contenido_original', $post_id ) ) {
		return '';
	}

	$filas = [];
	while ( have_rows( 'contenido_original', $post_id ) ) {
		the_row();
		$filas[] = [
			'layout' => get_row_layout(),
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
				case 'grid_personas':
					jb3_contenido_render_grid_personas( $grupo['filas'][0] );
					break;
				case 'prensa':
					jb3_contenido_render_prensa( $grupo['filas'][0] );
					break;
				case 'grid_videos':
					jb3_contenido_render_grid_videos( $grupo['filas'][0] );
					break;
			}
			?>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

// ─── Fila individual: Grid de personas ──────────────────────────────────────
// Sharks/chefs invitados/staff: imagen + nombre + texto opcional, con un link
// que puede ser tanto una Página propia del sitio (clic abre la bio completa
// en el modal de página ya existente, _init_page_modal.php,
// data-jb3-page-modal) como cualquier URL externa. "link" es un campo Link
// nativo de ACF/WordPress (2026-09-08, segunda vuelta — antes fue post_object
// filtrado a Páginas, y por un rato intermedio un simple campo de texto URL):
// el Link nativo reusa el mismo buscador de "Insertar/editar enlace" que ya
// usa TinyMCE en todo el admin — deja buscar una Página existente O pegar
// cualquier URL externa, y trae su PROPIO checkbox "Abrir en una ventana
// nueva" (por eso no hace falta un checkbox propio para eso: si el editor lo
// tilda ahí, se lee como $link['target'] === '_blank' y jb3_render_tarjeta()
// ya sabe forzar target=_blank y saltear el modal con eso, mismo mecanismo
// que antes usaba el checkbox "nueva_pestana" que se sacó). "modal" queda
// como el ÚNICO checkbox propio de esta grilla: solo importa cuando el link
// resuelve a una Página del sitio Y el editor no tildó "nueva ventana".
// Título de sección y cantidad de columnas 100% libres.
function jb3_contenido_render_grid_personas( array $fila ) {
	$data   = $fila['data'];
	$titulo = $data['titulo'] ?? '';
	if ( ! $titulo ) {
		return;
	}

	$items = [];
	foreach ( (array) ( $data['personas'] ?? [] ) as $persona ) {
		$img = $persona['imagen'] ?? null;
		if ( ! is_array( $img ) ) {
			continue;
		}
		$link = is_array( $persona['link'] ?? null ) ? $persona['link'] : [];
		$items[] = [
			'src'           => $img['sizes']['large'] ?? $img['url'] ?? '',
			'id'            => jb3_img_id( $img ), // copia justa: jb3_render_tarjeta() + jb3_grid_sizes('grilla').
			'alt'           => $img['alt'] ?? '',
			'titulo'        => $persona['titulo'] ?? '',
			'texto'         => $persona['texto'] ?? '',
			'link'          => $link['url'] ?? '',
			'modal'         => (bool) ( $persona['modal'] ?? false ),
			'nueva_pestana' => ( $link['target'] ?? '' ) === '_blank',
		];
	}
	if ( ! $items ) {
		return; // Layout agregado pero sin ninguna persona con imagen cargada.
	}

	$bajada   = $data['bajada'] ?? '';
	$columnas = (int) ( $data['columnas'] ?? 0 );
	$style    = $columnas > 0 ? ' style="--jb3-grilla-cols-desktop:' . $columnas . ';"' : '';
	?>
	<section id="<?php echo esc_attr( jb3_contenido_subnav_id( $titulo ) ); ?>" class="jb3-contenido-bloque jb3-contenido-bloque--grid-personas">
		<h2 class="jb3-contenido-bloque__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<?php if ( $bajada ) : ?>
			<div class="jb3-modulo__bajada"><?php echo wp_kses_post( wpautop( $bajada ) ); ?></div>
		<?php endif; ?>
		<div class="jb3-grilla"<?php echo $style; ?>>
			<?php foreach ( $items as $item ) : ?>
				<div class="jb3-grilla__item">
					<?php jb3_render_tarjeta( $item['src'], $item['alt'], $item['titulo'], $item['texto'], $item['link'], $item['modal'], '', 0, $item['nueva_pestana'], $item['id'], jb3_grid_sizes( 'grilla', $columnas ) ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

// ─── Fila individual: Prensa ─────────────────────────────────────────────────
// Grid de miniaturas a notas de prensa EXTERNAS (siempre otro sitio, nunca
// una Página propia) — mismo ítem visual que "Grid de personas"
// (jb3_render_tarjeta()), pero $modal queda fijo en false: acá no hay
// ninguna Página propia para resolver, siempre es un link normal con
// target="_blank" (mismo criterio que ya usa jb3_render_tarjeta() para
// cualquier link externo).
function jb3_contenido_render_prensa( array $fila ) {
	$data   = $fila['data'];
	$titulo = $data['titulo'] ?? '';
	if ( ! $titulo ) {
		return;
	}

	$items = [];
	foreach ( (array) ( $data['notas'] ?? [] ) as $nota ) {
		$img  = $nota['imagen'] ?? null;
		$link = $nota['link'] ?? '';
		if ( ! is_array( $img ) || ! $link ) {
			continue;
		}
		$items[] = [
			'src'      => $img['sizes']['large'] ?? $img['url'] ?? '',
			'id'       => jb3_img_id( $img ), // copia justa: jb3_render_tarjeta() + jb3_grid_sizes('grilla').
			'alt'      => $img['alt'] ?? '',
			'epigrafe' => $nota['epigrafe'] ?? '',
			'link'     => $link,
		];
	}
	if ( ! $items ) {
		return; // Layout agregado pero ninguna nota completó imagen + link.
	}

	$columnas = (int) ( $data['columnas'] ?? 0 );
	$style    = $columnas > 0 ? ' style="--jb3-grilla-cols-desktop:' . $columnas . ';"' : '';
	?>
	<section id="<?php echo esc_attr( jb3_contenido_subnav_id( $titulo ) ); ?>" class="jb3-contenido-bloque jb3-contenido-bloque--prensa">
		<h2 class="jb3-contenido-bloque__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<div class="jb3-grilla"<?php echo $style; ?>>
			<?php foreach ( $items as $item ) : ?>
				<div class="jb3-grilla__item">
					<?php jb3_render_tarjeta( $item['src'], $item['alt'], '', $item['epigrafe'], $item['link'], false, '', 0, false, $item['id'], jb3_grid_sizes( 'grilla', $columnas ) ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

// ─── Fila individual: Grid de videos ─────────────────────────────────────────
// Grilla compacta de varios videos (ej. "Grabaciones" con muchas tomas
// cortas) — a diferencia del layout "Video" (jb3_contenido_render_video(),
// _init_contenido_pelicula.php), pensado para uno o pocos videos destacados
// en columna única, acá cada video es una miniatura chica más entre varias,
// reusando el mismo look que jb3_grid_thumbs() (_init_grids.php: ícono de
// play + .jb3-grid-thumbs__media) pero con URLs sueltas en vez de posts
// reales, así que no puede reusar esa función tal cual. Título 100%
// opcional a propósito: "Grabaciones" en los ejemplos reales no repite
// título arriba de esta grilla (ya lo tiene la Galería de imágenes de
// arriba) — si no se completa, el bloque no imprime <h2> ni se anota en la
// subnav (jb3_contenido_subnav_id() ya devuelve '' con título vacío).
function jb3_contenido_render_grid_videos( array $fila ) {
	$data   = $fila['data'];
	$titulo = $data['titulo'] ?? '';

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

	$columnas = (int) ( $data['columnas'] ?? 0 );
	$style    = $columnas > 0 ? ' style="--jb3-grid-cols:' . $columnas . ';"' : '';
	$id       = $titulo ? jb3_contenido_subnav_id( $titulo ) : '';
	?>
	<section<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> class="jb3-contenido-bloque jb3-contenido-bloque--grid-videos">
		<?php if ( $titulo ) : ?>
			<h2 class="jb3-contenido-bloque__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<?php endif; ?>
		<div class="jb3-grid-thumbs"<?php echo $style; ?>>
			<?php foreach ( $items as $item ) : ?>
				<a class="jb3-grid-thumbs__item" href="<?php echo esc_url( $item['video_url'] ); ?>" data-jb3-video="<?php echo esc_url( $item['video_url'] ); ?>">
					<span class="jb3-grid-thumbs__media">
						<?php if ( $item['thumb'] ) : ?>
							<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="">
						<?php endif; ?>
						<span class="jb3-grid-thumbs__play" aria-hidden="true">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
						</span>
					</span>
					<?php if ( $item['titulo'] ) : ?>
						<span class="jb3-grid-thumbs__titulo"><?php echo esc_html( $item['titulo'] ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
