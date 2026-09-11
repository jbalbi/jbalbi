<?php
/**
 * Render del bloque ACF "Tarjeta" (jb3/tarjeta).
 * Ítem suelto — imagen + título + texto + link, todos opcionales salvo la
 * imagen — sin saber nada de grilla ni de columnas: se inserta adentro de
 * lo que sea (un Grupo con jb3-cols-N, jb3-grid-thumbs/posters, Columnas
 * nativo, donde haga falta), tantas veces como se necesite. Reemplaza,
 * para contenido nuevo, tanto a "Grilla de Imágenes" (con solo imagen) como
 * a "Grilla de Tarjetas" (con título+texto) — esos dos bloques quedan sin
 * tocar para lo que ya los usa.
 *
 * Sin proporción de imagen forzada a propósito (a diferencia de
 * .jb3-grid-posters__media/.jb3-grid-thumbs__media, pensadas para posters
 * verticales o thumbs horizontales puntualmente) — esta tarjeta tiene que
 * servir para cualquiera de los dos casos, así que la imagen mantiene su
 * proporción natural.
 *
 * "Abrir en modal" (t_modal): el Link tiene que ser una Página del sitio —
 * se resuelve a su ID con url_to_postid() y se imprime como
 * data-jb3-page-modal="ID" sobre el mismo <a href> real (nunca se pierde el
 * link real: sin JS, o con Ctrl+click/click-medio, sigue abriendo la
 * Página de verdad — ver _init_page_modal.php). Si la URL no resuelve a
 * una Página (ej. un link externo cargado por error con el checkbox
 * tildado), se cae solo al comportamiento normal de link.
 *
 * "Video" (t_video): tiene prioridad sobre Link/Modal — si está cargado,
 * la tarjeta muestra el ícono de play (mismo ícono/estilo que ya usa
 * jb3_grid_thumbs() para Videos/Integraciones reales, _init_grids.php) y
 * abre a pantalla completa con el mismo modal de video del resto del sitio
 * (data-jb3-video, _init_video_modal.php — soporta YouTube, Vimeo o un
 * archivo de la Biblioteca de Medios). El <a href> real sigue apuntando al
 * video igual, para Ctrl+click/sin JS.
 *
 * El markup del ítem en sí (imagen + título + texto + link/modal/video) vive
 * en jb3_render_tarjeta() (_init_tarjeta.php, extraído 2026-09-04) — este
 * archivo solo resuelve los campos ACF del bloque y se lo pasa.
 */

defined( 'ABSPATH' ) || exit;

$imagen = get_field( 't_imagen' );
$titulo = (string) get_field( 't_titulo' );
$texto  = (string) get_field( 't_texto' );
$link   = (string) get_field( 't_link' );
$modal  = (bool) get_field( 't_modal' );
$video  = (string) get_field( 't_video' );

$src = is_array( $imagen ) ? ( $imagen['sizes']['large'] ?? $imagen['url'] ?? '' ) : '';
$alt = is_array( $imagen ) ? ( $imagen['alt'] ?? '' ) : '';

if ( ! $src ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Tarjeta: falta cargar una imagen.</em></p>';
	}
	return;
}

if ( $is_preview ) {
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>🃏 Tarjeta</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Título: <strong><?php echo esc_html( $titulo ?: '(sin título)' ); ?></strong><br>
			Texto: <strong><?php echo $texto ? 'sí' : '(vacío)'; ?></strong><br>
			<?php if ( $video ) : ?>
				Video: <strong><?php echo esc_html( $video ); ?></strong> (abre a pantalla completa)
			<?php else : ?>
				Link: <strong><?php echo $link ? esc_html( $link ) : '(sin link)'; ?></strong><?php if ( $modal && $link ) : ?> (abre en modal)<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
}

jb3_render_tarjeta( $src, $alt, $titulo, $texto, $link, $modal, $video );
