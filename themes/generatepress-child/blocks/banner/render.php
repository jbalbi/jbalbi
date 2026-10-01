<?php
/**
 * Render del bloque ACF "Banner" (jb3/banner) — pedido 2026-10-01: el mismo
 * banner que ya existe como layout del módulo de Contenido de Películas,
 * Series y Prod. Originales, ahora también arrastrable a cualquier página.
 *
 * El banner en sí (markup <a><picture>, imagen mobile opcional, gate de país
 * fail-closed y de fechas) vive en jb3_contenido_render_banner()
 * (_init_contenido_pelicula.php) — este archivo solo traduce los campos
 * propios del BLOQUE (bn_*) al mismo formato de datos que usa el layout, así
 * los dos se ven y se comportan exactamente igual, sin duplicar lógica.
 * Mismo patrón que "Galería con Slider" (blocks/galeria-slider/render.php).
 *
 * Grupo de campos ACF "Banner" (Location: Block == Banner), mismos campos y
 * mismas reglas que el layout Banner del módulo de Contenido:
 * (grupo creado copiando los sub-campos del layout — acf-json/group_jb3_banner.json)
 *   bn_imagen_desktop (Image, return=Array — sin ella el banner no se imprime)
 *   bn_imagen_mobile  (Image, return=Array, opcional — vacío = usa la de desktop)
 *   bn_link           (URL, opcional — sin link el banner se ve igual, sin clic)
 *   bn_nueva_pestana  (True/False — el link abre en pestaña nueva; apagado = misma ventana)
 *   Ancho completo: NO es un campo — es el botón nativo "Ancho completo" de la
 *   barra del bloque (supports align full, _init_acf_blocks.php), llega en $block['align'].
 *   bn_paises         (Taxonomy, taxonomy=pais, Checkbox, return=ID —
 *                      CERRADO por defecto: sin países, no se ve para nadie)
 *   bn_fecha_in       (Date Picker, return=Ymd, opcional)
 *   bn_fecha_out      (Date Picker, return=Ymd, opcional)
 */

defined( 'ABSPATH' ) || exit;

$desktop        = get_field( 'bn_imagen_desktop' );
$mobile         = get_field( 'bn_imagen_mobile' );
$link           = (string) get_field( 'bn_link' );
$nueva_pestana  = (bool) get_field( 'bn_nueva_pestana' );
$ancho_completo = 'full' === ( $block['align'] ?? '' ); // botón nativo "Ancho completo" de la barra del bloque.
$paises         = get_field( 'bn_paises' );
$paises         = is_array( $paises ) ? $paises : array(); // Taxonomy vacío devuelve false, no array.

if ( $is_preview ) {
	$nombres_pais = array_filter( array_map( function ( $id ) {
		$term = get_term( $id, 'pais' );
		return $term && ! is_wp_error( $term ) ? $term->name : '';
	}, $paises ) );
	$fecha_in  = (string) get_field( 'bn_fecha_in' );
	$fecha_out = (string) get_field( 'bn_fecha_out' );
	$fmt       = function ( $ymd ) {
		return $ymd ? substr( $ymd, 6, 2 ) . '/' . substr( $ymd, 4, 2 ) . '/' . substr( $ymd, 0, 4 ) : '';
	};
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>🖼️ Banner</strong>
		<?php if ( is_array( $desktop ) && ! empty( $desktop['url'] ) ) : ?>
			<img src="<?php echo esc_url( $desktop['sizes']['medium_large'] ?? $desktop['url'] ); ?>" alt="" style="display:block; width:100%; max-width:640px; margin-top:10px; border-radius:4px;">
		<?php endif; ?>
		<div style="margin-top:8px; font-size:13px; color:#444; line-height:1.6;">
			Imagen desktop: <strong><?php echo is_array( $desktop ) ? 'cargada' : 'FALTA — no se va a ver'; ?></strong>
			· Imagen mobile: <strong><?php echo is_array( $mobile ) ? 'cargada' : 'usa la de desktop'; ?></strong><br>
			Link: <strong><?php echo $link ? esc_html( $link ) . ( $nueva_pestana ? ' (pestaña nueva)' : ' (misma ventana)' ) : 'sin link (no se puede hacer clic)'; ?></strong><br>
			Ancho: <strong><?php echo $ancho_completo ? 'completo, de borde a borde' : 'del contenido'; ?></strong><br>
			Países: <strong><?php echo $nombres_pais ? esc_html( implode( ', ', $nombres_pais ) ) : 'ninguno — NO se ve para nadie todavía'; ?></strong><br>
			Fechas: <strong><?php echo esc_html( ( $fmt( $fecha_in ) ?: 'desde ya' ) . ' → ' . ( $fmt( $fecha_out ) ?: 'sin vencimiento' ) ); ?></strong>
		</div>
	</div>
	<?php
	return;
}

if ( ! function_exists( 'jb3_contenido_render_banner' ) ) {
	return;
}

jb3_contenido_render_banner( array(
	'data' => array(
		'imagen_desktop' => $desktop,
		'imagen_mobile'  => $mobile,
		'link'           => $link,
		'nueva_pestana'  => $nueva_pestana,
		'ancho_completo' => $ancho_completo,
		'paises'         => $paises,
		'fecha_in'       => (string) get_field( 'bn_fecha_in' ),
		'fecha_out'      => (string) get_field( 'bn_fecha_out' ),
	),
) );
