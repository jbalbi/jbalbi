<?php
/**
 * Render del bloque ACF "Grilla de Imágenes" (jb3/grilla-imagenes).
 * Para filas de imágenes puramente visuales, SIN post real detrás (sin
 * link, sin datos que listar en ningún otro lado del sitio) — ej. carteles
 * curados a mano en una página tipo "Seasonalities". Si la imagen SÍ
 * corresponde a contenido real del catálogo (Películas, Series...), usar
 * el bloque "Módulo de Contenido" en su lugar, no este.
 *
 * Reutiliza la misma grilla y el mismo mecanismo de columnas que
 * jb3_grid_posters() (inc/grids.php, .jb3-grid-posters/--jb3-grid-cols en
 * main.css) — mismo look que el resto del sitio, sin CSS nuevo.
 *
 * Título vs. Bajada — misma regla simétrica en TODO el sitio (ver también
 * jb3_subnav_anchor() en inc/subnav.php): el Título es lo único que entra
 * a la subnav, y solo cuando tiene contenido (jb3-modulo__titulo, con la
 * rayita vertical). La Bajada NUNCA entra a la subnav, tenga o no
 * contenido — para un subtítulo puramente visual (ej. "VERANO / ÉXITOS EN
 * TAQUILLA" dentro de una sección más grande como "Seasonalities"), se
 * carga acá, no en el campo Título.
 */

defined( 'ABSPATH' ) || exit;

$titulo   = (string) get_field( 'gi_titulo' );
$bajada   = (string) get_field( 'gi_bajada' );
$imagenes = (array) get_field( 'gi_imagenes' );
$columnas = (int) get_field( 'gi_columnas' );

if ( ! $imagenes ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Grilla de Imágenes: falta cargar al menos una imagen.</em></p>';
	}
	return;
}

if ( $is_preview ) {
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>🖼️ Grilla de Imágenes</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Título: <strong><?php echo esc_html( $titulo ?: '(sin título — no entra a la navegación)' ); ?></strong><br>
			Bajada: <strong><?php echo $bajada ? 'sí' : '(vacía)'; ?></strong><br>
			Imágenes: <strong><?php echo count( $imagenes ); ?></strong><br>
			Columnas (desktop): <strong><?php echo $columnas > 0 ? esc_html( $columnas ) : 'default'; ?></strong>
		</div>
	</div>
	<?php
	return;
}

$id = '';
if ( $titulo && function_exists( 'jb3_subnav_id_unico' ) && function_exists( 'jb3_subnav_register' ) ) {
	$id = jb3_subnav_id_unico( $titulo );
	jb3_subnav_register( $titulo, $id );
}

$style = $columnas > 0 ? ' style="--jb3-grid-cols:' . (int) $columnas . ';"' : '';
?>
<?php // Sin ".jb3-contenedor" acá: mismo motivo que jb3_modulo_render() —
// bloque ACF insertable en cualquier lugar, incluso adentro de Columnas
// (ver inc/modulo.php). ?>
<section class="jb3-modulo">
	<?php if ( $titulo ) : ?>
		<h2 class="jb3-modulo__titulo"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>><?php echo esc_html( $titulo ); ?></h2>
	<?php endif; ?>
	<?php if ( $bajada ) : ?>
		<div class="jb3-modulo__bajada"><?php echo wp_kses_post( $bajada ); ?></div>
	<?php endif; ?>
	<div class="jb3-grid-posters"<?php echo $style; ?>>
		<?php foreach ( $imagenes as $img ) :
			$src = is_array( $img ) ? ( $img['sizes']['large'] ?? $img['url'] ?? '' ) : '';
			$alt = is_array( $img ) ? ( $img['alt'] ?? '' ) : '';
			if ( ! $src ) {
				continue;
			}
			?>
			<span class="jb3-grid-posters__item">
				<span class="jb3-grid-posters__media">
					<?php
					// Copia justa según columnas (misma grilla que los posters:
					// jb3_grid_sizes(), inc/grids.php).
					$img_html = jb3_img( jb3_img_id( $img ), jb3_grid_sizes( 'posters', $columnas ), array( 'alt' => $alt, 'tamano' => 'large' ) );
					if ( $img_html ) :
						echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo.
					else :
						?>
						<img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
					<?php endif; ?>
				</span>
			</span>
		<?php endforeach; ?>
	</div>
</section>
