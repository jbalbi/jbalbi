<?php
defined( 'ABSPATH' ) || exit;

// ─── GRILLAS DE CONTENIDO ────────────────────────────────────────────────────
// Dos grillas reutilizables en cualquier página/módulo: posters (vertical,
// hasta 4 en desktop — Películas/Series/Prod. Originales) y thumbs
// (horizontal, hasta 3 en desktop — Op. Comerciales/Videos/Integraciones,
// con ícono de play opcional). Reciben WP_Post o IDs indistintamente y no
// saben nada de canal/país/tipo de contenido — esa lógica vive en el
// módulo que las llama (paso siguiente), acá solo se dibuja lo que llega.
//
// La imagen va en su propio contenedor (__media), separado del título: el
// overflow:hidden que recorta el zoom del rollover tiene que tener EXACTO
// el alto de la imagen, no el de imagen+título juntos — si no, el zoom
// crece libre hacia abajo (ahí hay más alto que la imagen sola) y tapa el
// título en vez de cortarse parejo por los 4 lados.
//
// $columnas (ambas funciones): cantidad de columnas en DESKTOP — 0 (default)
// deja el valor de siempre puesto a mano en style.css (5 posters / 3 thumbs).
// Tablet/mobile NO se tocan por este parámetro: siguen con los valores ya
// ajustados (3/2 posters, 2/1 thumbs) sin importar cuántas columnas se pidan
// en desktop, para no tener que pensar cascada completa cada vez que un
// módulo puntual necesita, por ejemplo, 8 posters por fila en vez de 5.

function jb3_grid_posters( array $posts, int $columnas = 0 ): string {
	if ( empty( $posts ) ) {
		return '';
	}
	$style = $columnas > 0 ? ' style="--jb3-grid-cols:' . (int) $columnas . ';"' : '';
	ob_start();
	?>
	<div class="jb3-grid-posters"<?php echo $style; ?>>
		<?php foreach ( $posts as $item ) : $post = get_post( $item ); if ( ! $post ) continue; ?>
			<a class="jb3-grid-posters__item" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
				<span class="jb3-grid-posters__media">
					<?php if ( has_post_thumbnail( $post ) ) : ?>
						<?php echo get_the_post_thumbnail( $post, 'large' ); ?>
					<?php endif; ?>
				</span>
				<span class="jb3-grid-posters__titulo"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

// El ícono de play y el click-para-reproducir salen los dos del mismo
// lugar, 100% automático: el campo ACF "video_url" (Videos/Integraciones).
// Si el post lo tiene cargado, se ve el ícono Y el link abre el modal (ver
// _init_video_modal.php) en vez de ir a su página; si no lo tiene, es un
// link normal, sin ícono. Nada de esto depende de quien arma el módulo.
// Grilla MIXTA: para listados de tipos de contenido heterogéneos donde
// además interesa preservar el orden tal cual llega (ej. resultados de
// búsqueda por relevancia) — a diferencia de jb3_modulo_render(), que
// separa posters/thumbs en dos grillas prolijas una debajo de la otra,
// acá NO se puede reordenar/agrupar sin perder ese orden. Cada item elige
// su propia proporción (afiche vertical o miniatura horizontal, según
// jb3_modulo_tipos_poster()) y la grilla es "masonry" por columnas CSS
// (column-count, sin JS) en vez de filas parejas — así una fila con
// afiches altos y miniaturas bajas no deja huecos ni fuerza a todos al
// mismo alto. Pedido explícito 2026-08-28 (resultados del buscador con
// afiches recortados al forzarlos todos al formato horizontal de
// jb3_grid_thumbs()).
function jb3_grid_mixto( array $posts, int $columnas = 0 ): string {
	if ( empty( $posts ) ) {
		return '';
	}
	$tipos_poster = jb3_modulo_tipos_poster();
	$style        = $columnas > 0 ? ' style="--jb3-grid-cols:' . (int) $columnas . ';"' : '';
	ob_start();
	?>
	<div class="jb3-grid-mixto"<?php echo $style; ?>>
		<?php foreach ( $posts as $item ) :
			$post = get_post( $item );
			if ( ! $post ) continue;
			$es_poster = in_array( $post->post_type, $tipos_poster, true );
			$video_url = function_exists( 'get_field' ) ? get_field( 'video_url', $post->ID ) : '';
		?>
			<a
				class="jb3-grid-mixto__item jb3-grid-mixto__item--<?php echo $es_poster ? 'poster' : 'thumb'; ?>"
				href="<?php echo esc_url( get_permalink( $post ) ); ?>"
				<?php if ( $video_url ) : ?>data-jb3-video="<?php echo esc_url( $video_url ); ?>"<?php endif; ?>
			>
				<span class="jb3-grid-mixto__media">
					<?php if ( has_post_thumbnail( $post ) ) : ?>
						<?php echo get_the_post_thumbnail( $post, 'large' ); ?>
					<?php endif; ?>
					<?php if ( $video_url ) : ?>
						<span class="jb3-grid-thumbs__play" aria-hidden="true">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
						</span>
					<?php endif; ?>
				</span>
				<span class="jb3-grid-mixto__titulo"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

function jb3_grid_thumbs( array $posts, int $columnas = 0 ): string {
	if ( empty( $posts ) ) {
		return '';
	}
	$style = $columnas > 0 ? ' style="--jb3-grid-cols:' . (int) $columnas . ';"' : '';
	ob_start();
	?>
	<div class="jb3-grid-thumbs"<?php echo $style; ?>>
		<?php foreach ( $posts as $item ) :
			$post      = get_post( $item );
			if ( ! $post ) continue;
			$video_url = function_exists( 'get_field' ) ? get_field( 'video_url', $post->ID ) : '';
		?>
			<a
				class="jb3-grid-thumbs__item"
				href="<?php echo esc_url( get_permalink( $post ) ); ?>"
				<?php if ( $video_url ) : ?>data-jb3-video="<?php echo esc_url( $video_url ); ?>"<?php endif; ?>
			>
				<span class="jb3-grid-thumbs__media">
					<?php if ( has_post_thumbnail( $post ) ) : ?>
						<?php echo get_the_post_thumbnail( $post, 'large' ); ?>
					<?php endif; ?>
					<?php if ( $video_url ) : ?>
						<span class="jb3-grid-thumbs__play" aria-hidden="true">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
						</span>
					<?php endif; ?>
				</span>
				<span class="jb3-grid-thumbs__titulo"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
