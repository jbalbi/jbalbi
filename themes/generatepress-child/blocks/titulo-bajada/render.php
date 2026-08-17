<?php
/**
 * Render del bloque ACF "Título y Bajada" (jb3/titulo-bajada).
 * Sección de texto suelta, insertable en cualquier parte del contenido
 * (incluso adentro de otro bloque, ej. una columna de Columnas) — mismo
 * motor y misma regla simétrica que usan "Grilla de Imágenes" y el
 * shortcode [jb3_subnav_anchor] (_init_subnav.php):
 *   - Título con contenido → se ve con la rayita vertical
 *     (.jb3-modulo__titulo) Y entra a la subnav.
 *   - Título vacío → no se ve ningún título, no entra a la subnav (aunque
 *     haya Bajada).
 *   - Bajada nunca entra a la subnav, tenga o no contenido — para un
 *     subtítulo puramente visual que no tiene que verse en el menú.
 */

defined( 'ABSPATH' ) || exit;

$titulo = (string) get_field( 'tb_titulo' );
$bajada = (string) get_field( 'tb_bajada' );

if ( ! $titulo && ! $bajada ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Título y Bajada: falta cargar al menos uno de los dos.</em></p>';
	}
	return;
}

if ( $is_preview ) {
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>📝 Título y Bajada</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Título: <strong><?php echo esc_html( $titulo ?: '(sin título — no entra a la navegación)' ); ?></strong><br>
			Bajada: <strong><?php echo $bajada ? 'sí' : '(vacía)'; ?></strong>
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
?>
<?php // Sin ".jb3-contenedor" acá: bloque ACF insertable en cualquier
// lugar, incluso adentro de Columnas — mismo motivo que el resto de los
// bloques ACF del sitio (ver _init_modulo.php). ?>
<section class="jb3-modulo">
	<?php if ( $titulo ) : ?>
		<h2 class="jb3-modulo__titulo"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>><?php echo esc_html( $titulo ); ?></h2>
	<?php endif; ?>
	<?php if ( $bajada ) : ?>
		<div class="jb3-modulo__bajada"><?php echo wp_kses_post( $bajada ); ?></div>
	<?php endif; ?>
</section>
