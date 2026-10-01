<?php
/**
 * Render del bloque ACF "Galería con Slider" (jb3/galeria-slider).
 * Para filas de imágenes puramente visuales que necesitan carrusel (no
 * grilla fija) — ej. fotos de backstage/grabaciones. Gutenberg no tiene un
 * bloque nativo de carrusel, así que esto sí justifica algo propio (a
 * diferencia de una grilla común, donde ya usamos "Grilla de Imágenes").
 *
 * El carrusel en sí (JS + markup) vive en jb3_render_galeria_slider()
 * (_init_galeria_slider.php, extraído de acá 2026-09-03) — compartido con
 * el módulo de Contenido de película/serie (_init_contenido_pelicula.php),
 * que lo reusa tal cual para su propio layout "Galería". Este archivo solo
 * resuelve lo que es específico del BLOQUE: sus campos ACF propios
 * (gs_titulo/gs_bajada/gs_imagenes/gs_paises), el modo preview del editor,
 * y su gate de país propio.
 *
 * Título/Bajada: misma regla simétrica que el resto de los bloques del
 * sitio (Título y Bajada, Grilla de Imágenes) — ver jb3_subnav_id_unico().
 */

defined( 'ABSPATH' ) || exit;

$titulo   = (string) get_field( 'gs_titulo' );
$bajada   = (string) get_field( 'gs_bajada' );
$imagenes = (array) get_field( 'gs_imagenes' );

// CERRADO por defecto: sin ningún país marcado, la galería no se ve para
// nadie — hay que marcar explícitamente los países que sí la pueden ver
// (mismo criterio que "Contenido por País"). get_field() en un campo
// Taxonomy vacío devuelve false, no array vacío, por eso el chequeo
// is_array() en vez de castear directo con (array).
$paises_ids = get_field( 'gs_paises' );
$paises_ids = is_array( $paises_ids ) ? $paises_ids : array();

if ( ! $imagenes ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Galería con Slider: falta cargar al menos una imagen.</em></p>';
	}
	return;
}

if ( $is_preview ) {
	$nombres_pais = array_filter( array_map( function ( $id ) {
		$term = get_term( $id, 'pais' );
		return $term && ! is_wp_error( $term ) ? $term->name : '';
	}, $paises_ids ) );
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>🎞️ Galería con Slider</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Título: <strong><?php echo esc_html( $titulo ?: '(sin título — no entra a la navegación)' ); ?></strong><br>
			Imágenes: <strong><?php echo count( $imagenes ); ?></strong><br>
			Países: <strong><?php echo $nombres_pais ? esc_html( implode( ', ', $nombres_pais ) ) : 'ninguno — NO se ve para nadie todavía'; ?></strong>
		</div>
	</div>
	<?php
	return;
}

$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';
$termino        = $pais_visitante ? get_term_by( 'slug', $pais_visitante, 'pais' ) : false;
$id_visitante   = $termino ? $termino->term_id : 0;

if ( ! $paises_ids || ! in_array( $id_visitante, $paises_ids, true ) ) {
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

$id = '';
if ( $titulo && function_exists( 'jb3_subnav_id_unico' ) && function_exists( 'jb3_subnav_register' ) ) {
	$id = jb3_subnav_id_unico( $titulo );
	jb3_subnav_register( $titulo, $id );
}

jb3_render_galeria_slider( $slides, $titulo, $bajada, $id );
