<?php
/**
 * Render del bloque ACF "Grilla de Tarjetas" (jb3/grilla-tarjetas).
 * Para secciones tipo "Posibilidades Infinitas" (marketing, sin post real
 * detrás): N tarjetas con imagen + título + texto cada una.
 *
 * Reutiliza el mismo contenedor y el mismo mecanismo de columnas que
 * .jb3-grid-thumbs (jb3_grid_thumbs(), _init_grids.php — 3/2/1 responsive
 * en desktop/tablet/mobile, --jb3-grid-cols) para que el comportamiento
 * responsive sea IDÉNTICO al del resto de las grillas del sitio — antes de
 * este bloque, armar esto a mano con el bloque nativo "Columnas" de
 * Gutenberg daba un quiebre distinto (3 columnas hasta mobile, recién ahí
 * pasa a 1, sin paso intermedio de 2 en tablet).
 * Cada tarjeta usa su propia clase (.jb3-tarjeta), no ".jb3-grid-thumbs__item"
 * — esa es un <a> pensado para un post real con link; acá no hay ninguno.
 */

defined( 'ABSPATH' ) || exit;

$columnas = (int) get_field( 'gt_columnas' );
$items    = (array) get_field( 'gt_items' );

if ( ! $items ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Grilla de Tarjetas: falta cargar al menos una tarjeta.</em></p>';
	}
	return;
}

if ( $is_preview ) {
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>🃏 Grilla de Tarjetas</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Tarjetas: <strong><?php echo count( $items ); ?></strong><br>
			Columnas (desktop): <strong><?php echo $columnas > 0 ? esc_html( $columnas ) : 'default (3)'; ?></strong>
		</div>
	</div>
	<?php
	return;
}

$style = $columnas > 0 ? ' style="--jb3-grid-cols:' . (int) $columnas . ';"' : '';
?>
<?php // Sin ".jb3-contenedor" acá: mismo motivo que el resto de los
// bloques ACF del sitio — insertable en cualquier lugar (ver
// _init_modulo.php). ?>
<div class="jb3-grid-thumbs"<?php echo $style; ?>>
	<?php foreach ( $items as $item ) :
		$imagen = $item['imagen'] ?? '';
		$titulo = (string) ( $item['titulo'] ?? '' );
		$texto  = (string) ( $item['texto'] ?? '' );
		$src    = is_array( $imagen ) ? ( $imagen['sizes']['large'] ?? $imagen['url'] ?? '' ) : '';
		$alt    = is_array( $imagen ) ? ( $imagen['alt'] ?? '' ) : '';
		if ( ! $src && ! $titulo ) {
			continue; // Fila cargada a medio hacer, sin nada que mostrar.
		}
		?>
		<div class="jb3-tarjeta">
			<?php if ( $src ) : ?>
				<span class="jb3-grid-thumbs__media">
					<img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
				</span>
			<?php endif; ?>
			<?php if ( $titulo ) : ?>
				<h3 class="jb3-tarjeta__titulo"><?php echo esc_html( $titulo ); ?></h3>
			<?php endif; ?>
			<?php if ( $texto ) : ?>
				<p class="jb3-tarjeta__texto"><?php echo esc_html( $texto ); ?></p>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
