<?php
/**
 * Render del bloque ACF "Playlist de YouTube" (jb3/playlist-youtube).
 * Todos los videos de una playlist en la grilla de miniaturas del sitio
 * (.jb3-grid-thumbs, misma que Op. Comerciales: 16:9, ícono de play,
 * agrandamiento al pasar el mouse); cada uno se abre en el reproductor a
 * pantalla completa (data-jb3-video, inc/video-modal.php). De dónde salen
 * los videos y sus límites: ver inc/youtube-playlist.php.
 *
 * Campos (grupo ACF "Playlist de YouTube", prefijo yt_):
 *   yt_titulo   (texto, opcional — con rayita, como el resto de los módulos,
 *                y entra a la subnav, igual que "Título y Bajada")
 *   yt_link     (url, obligatorio — link de la playlist, con "list=")
 *   yt_cantidad (número, opcional — cuántos mostrar; vacío = todos los que
 *                entrega YouTube: los primeros 15 de la playlist)
 * Si hay más videos que los que se muestran, debajo va el botón "Ver
 * playlist completa" (abre la playlist en YouTube, pestaña nueva).
 * Orden: SIEMPRE el de la playlist en YouTube — para cambiarlo, se reordena
 * la playlist allá (ver inc/youtube-playlist.php).
 */

defined( 'ABSPATH' ) || exit;

$titulo      = (string) get_field( 'yt_titulo' );
$link        = (string) get_field( 'yt_link' );
$cantidad    = (int) get_field( 'yt_cantidad' );
$playlist_id = jb3_youtube_playlist_id( $link );
$videos      = $playlist_id ? jb3_youtube_playlist_videos( $playlist_id ) : array();

// Botón "Ver playlist completa" (pedido 2026-10-20) cuando hay más videos
// que los que se muestran. El listado de YouTube no dice el total — solo
// trae hasta JB3_YT_PLAYLIST_MAXIMO —, así que "hay más" = llegaron esos 15
// (casi seguro hay más; si la playlist tiene justo 15, el botón igual lleva
// a la misma playlist, no molesta) o el editor limitó la cantidad por debajo
// de lo que hay.
$hay_mas = count( $videos ) >= JB3_YT_PLAYLIST_MAXIMO || ( $cantidad > 0 && count( $videos ) > $cantidad );
if ( $cantidad > 0 ) {
	$videos = array_slice( $videos, 0, $cantidad );
}

if ( $is_preview ) {
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>▶️ Playlist de YouTube</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Título: <strong><?php echo esc_html( $titulo ?: '(sin título)' ); ?></strong><br>
			<?php if ( ! $link ) : ?>
				<strong>Falta pegar el link de la playlist.</strong>
			<?php elseif ( ! $playlist_id ) : ?>
				<strong>El link no es de una playlist</strong> — tiene que tener "list=" (ej. https://www.youtube.com/playlist?list=PL…).
			<?php elseif ( ! $videos ) : ?>
				<strong>No se pudieron leer los videos</strong> — fijate que la playlist sea pública o "no listada".
			<?php else : ?>
				Videos: <strong><?php echo count( $videos ); ?></strong> — <?php echo esc_html( implode( ' · ', wp_list_pluck( array_slice( $videos, 0, 3 ), 'titulo' ) ) ); ?><?php echo count( $videos ) > 3 ? '…' : ''; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
}

if ( ! $videos ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Playlist de YouTube: no hay videos para mostrar — revisá el link de la playlist (tiene que ser pública o "no listada").</em></p>';
	}
	return;
}

$id = '';
if ( $titulo && function_exists( 'jb3_subnav_id_unico' ) && function_exists( 'jb3_subnav_register' ) ) {
	$id = jb3_subnav_id_unico( $titulo );
	jb3_subnav_register( $titulo, $id );
}
$sizes = jb3_grid_sizes( 'thumbs' );
?>
<?php // Sin ".jb3-contenedor": bloque insertable en cualquier lado (ver titulo-bajada/render.php). ?>
<section class="jb3-modulo">
	<?php if ( $titulo ) : ?>
		<h2 class="jb3-modulo__titulo"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>><?php echo esc_html( $titulo ); ?></h2>
	<?php endif; ?>
	<div class="jb3-grid-thumbs">
		<?php
		foreach ( $videos as $video ) :
			$url  = 'https://www.youtube.com/watch?v=' . $video['id'];
			$base = 'https://i.ytimg.com/vi/' . $video['id'] . '/';
			// Miniaturas: mqdefault (16:9) y hqdefault (4:3 con franjas negras
			// arriba y abajo, que el recorte 16:9 de la grilla — object-fit:
			// cover — deja afuera) existen siempre; las grandes, solo las que
			// YouTube tiene para ESE video (ver jb3_youtube_miniaturas_grandes()).
			$srcset = array( $base . 'mqdefault.jpg 320w', $base . 'hqdefault.jpg 480w' );
			foreach ( $video['grandes'] ?? array() as $nombre => $ancho ) {
				$srcset[] = $base . $nombre . '.jpg ' . (int) $ancho . 'w';
			}
			$srcset = implode( ', ', $srcset );
			?>
			<a class="jb3-grid-thumbs__item" href="<?php echo esc_url( $url ); ?>" data-jb3-video="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
				<span class="jb3-grid-thumbs__media">
					<img src="<?php echo esc_url( $base . 'hqdefault.jpg' ); ?>" srcset="<?php echo esc_attr( $srcset ); ?>" sizes="<?php echo esc_attr( $sizes ); ?>" alt="" loading="lazy" decoding="async">
					<span class="jb3-grid-thumbs__play" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
					</span>
				</span>
				<span class="jb3-grid-thumbs__titulo"><?php echo esc_html( $video['titulo'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php if ( $hay_mas ) : ?>
		<p class="jb3-playlist-youtube__mas">
			<a class="button" href="<?php echo esc_url( 'https://www.youtube.com/playlist?list=' . $playlist_id ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver playlist completa', 'jb3' ); ?></a>
		</p>
	<?php endif; ?>
</section>
