<?php
defined( 'ABSPATH' ) || exit;

// ─── ÍTEM "TARJETA" COMPARTIDO (extraído 2026-09-04) ────────────────────────
// Markup de imagen + título + texto + link/modal/video, usado originalmente
// solo por el bloque Gutenberg "Tarjeta" (blocks/tarjeta/render.php) — se
// extrajo a acá (mismo criterio que jb3_render_galeria_slider(),
// _init_galeria_slider.php) para que el módulo de Contenido de Prod.
// Originales (_init_contenido_original.php: layouts "Grid de personas" y
// "Prensa") pueda dibujar el mismo ítem sin reimplementar la prioridad
// video > modal > link.
//
// $page_id_conocido: si quien llama YA tiene el ID de la Página a mano, lo
// pasa directo acá y se salta el url_to_postid(). Ningún llamador actual lo
// usa (todos tienen una URL de texto — campo url/link — y dejan que esta
// función resuelva el ID sola), pero se mantiene por si hace falta a futuro.
//
// $nueva_pestana: control explícito del editor para forzar target="_blank"
// incluso en un link interno (lo que además desactiva el modal — un pedido
// de pestaña nueva le gana a la conveniencia del modal). Un link externo ya
// se abre solo en pestaña nueva sin necesidad de tildar esto (comportamiento
// sin cambios).
//
// Los 3 estados posibles de un link a una Página propia (2026-09-08 — antes
// $page_id se resolvía SOLO si $modal estaba tildado, así que "ni modal ni
// nueva pestaña" terminaba forzando target="_blank" igual, por asumir sin
// querer que "no es modal" == "es externo"; ver [[project_contenido_prod_originales]]):
//   1. modal=true,  nueva_pestana=false → abre en el modal de página.
//   2. modal=false, nueva_pestana=true  → target="_blank", sin modal.
//   3. modal=false, nueva_pestana=false → link normal, MISMA pestaña, sin
//      modal — el caso que faltaba. $page_id_real se resuelve siempre
//      (independiente de $modal) para poder distinguir este caso de un link
//      genuinamente externo (que sí necesita target="_blank" automático,
//      pase lo que pase con los checkboxes — mismo criterio de siempre).
function jb3_render_tarjeta( string $src, string $alt, string $titulo, string $texto, string $link, bool $modal, string $video, int $page_id_conocido = 0, bool $nueva_pestana = false ): void {
	if ( ! $src ) {
		return;
	}

	$page_id_real  = ( $video || ! $link || $nueva_pestana ) ? 0 : ( $page_id_conocido ?: url_to_postid( $link ) );
	$page_id_modal = ( $modal && $page_id_real ) ? $page_id_real : 0;

	$tag    = ( $video || $link ) ? 'a' : 'div';
	$href   = $video ? ' href="' . esc_url( $video ) . '"' : ( $link ? ' href="' . esc_url( $link ) . '"' : '' );
	$target = ( $link && ! $video && ( $nueva_pestana || ! $page_id_real ) ) ? ' target="_blank" rel="noopener"' : '';
	$data   = $video ? ' data-jb3-video="' . esc_url( $video ) . '"' : ( $page_id_modal ? ' data-jb3-page-modal="' . (int) $page_id_modal . '"' : '' );
	?>
	<<?php echo $tag; ?> class="jb3-tarjeta"<?php echo $href . $target . $data; ?>>
		<span class="jb3-tarjeta__media">
			<img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
			<?php if ( $video ) : ?>
				<span class="jb3-grid-thumbs__play" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
				</span>
			<?php endif; ?>
		</span>
		<?php if ( $titulo ) : ?>
			<span class="jb3-tarjeta__titulo"><?php echo esc_html( $titulo ); ?></span>
		<?php endif; ?>
		<?php if ( $texto ) : ?>
			<span class="jb3-tarjeta__texto"><?php echo esc_html( $texto ); ?></span>
		<?php endif; ?>
	</<?php echo $tag; ?>>
	<?php
}
