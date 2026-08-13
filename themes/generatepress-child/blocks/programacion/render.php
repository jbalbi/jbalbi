<?php
/**
 * Render del bloque ACF "Programación" (jb3/programacion).
 * Sin campos propios: el dato vive en una caja de metadatos aparte
 * ("Programación", Location: Plantilla de página == Canal) — se carga una
 * sola vez por página, el bloque solo elige DÓNDE se imprime. $post_id es
 * la página real que contiene el bloque (ACF se lo pasa a esta plantilla
 * ya resuelto — ver la misma aclaración en blocks/modulo-contenido).
 *
 * Campos de la caja de metadatos que espera esto (grupo ACF "Programación"):
 *   prog_titulo         (Text, opcional)
 *   prog_bajada          (Wysiwyg Editor, opcional)
 *   prog_overrides_pais  (Repeater, opcional — mismos sub-campos pais[]/
 *                          titulo/bajada que usa jb3_resolver_titulo_bajada_pais())
 *   prog_imagenes        (Repeater, REQUERIDO para que el módulo se
 *                          muestre) — sub-campos:
 *                            paises          (Taxonomy pais, Checkbox — a
 *                                             propósito AL REVÉS del resto
 *                                             del sitio: vacío = la fila NO
 *                                             se muestra en NINGÚN país, no
 *                                             en todos. Sirve para dejar
 *                                             una fila a medio cargar sin
 *                                             que se publique sola.)
 *                            imagen_desktop  (Image, Return Format = Image URL)
 *                            imagen_mobile   (Image, Return Format = Image URL, opcional)
 *                            fecha_in        (Date Picker, Return Format = Ymd,
 *                                             opcional — vacío = ya vigente)
 *                            fecha_out       (Date Picker, Return Format = Ymd,
 *                                             opcional — vacío = sin vencimiento)
 *
 * El módulo entero se oculta si ninguna fila de prog_imagenes aplica al
 * país del visitante + la fecha de hoy — así se puede insertar el bloque
 * en TODOS los canales sin revisar caso por caso: se autorregula solo.
 */

defined( 'ABSPATH' ) || exit;

$resuelto = jb3_resolver_titulo_bajada_pais(
	(string) get_field( 'prog_titulo', $post_id ),
	(string) get_field( 'prog_bajada', $post_id ),
	(array) get_field( 'prog_overrides_pais', $post_id )
);

$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';
$hoy            = current_time( 'Ymd' );

$imagen_desktop = '';
$imagen_mobile  = '';

foreach ( (array) get_field( 'prog_imagenes', $post_id ) as $fila ) {
	// A propósito AL REVÉS del criterio que usa el resto del sitio: acá sin
	// países elegidos la fila NO se muestra en ningún país (no "en todos").
	// Sirve para dejar una fila cargándose a medio hacer (falta la
	// aprobación, falta subir la imagen mobile, lo que sea) sin que se
	// publique sola en todos lados mientras tanto.
	$paises_fila = (array) ( $fila['paises'] ?? array() );
	$slugs       = array_map(
		function ( $t ) {
			return is_object( $t ) ? $t->slug : '';
		},
		$paises_fila
	);
	if ( empty( $slugs ) || ! in_array( $pais_visitante, $slugs, true ) ) {
		continue;
	}

	$fecha_in  = $fila['fecha_in'] ?? '';
	$fecha_out = $fila['fecha_out'] ?? '';
	if ( $fecha_in && $hoy < $fecha_in ) {
		continue;
	}
	if ( $fecha_out && $hoy > $fecha_out ) {
		continue;
	}

	$desktop_raw    = $fila['imagen_desktop'] ?? '';
	$imagen_desktop = is_array( $desktop_raw ) ? ( $desktop_raw['url'] ?? '' ) : (string) $desktop_raw;
	$mobile_raw     = $fila['imagen_mobile'] ?? '';
	$imagen_mobile  = is_array( $mobile_raw ) ? ( $mobile_raw['url'] ?? '' ) : (string) $mobile_raw;
	break;
}

if ( ! $imagen_desktop ) {
	if ( $is_preview && current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Programación: ninguna imagen de la caja de metadatos "Programación" aplica al país/fecha de hoy — el módulo no se muestra en el sitio real hasta que haya una vigente.</em></p>';
	}
	return;
}

if ( $is_preview ) {
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>📦 Programación</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Título: <strong><?php echo esc_html( $resuelto['titulo'] ?: '(sin completar)' ); ?></strong><br>
			Imagen vigente: <strong>sí</strong>
			<?php if ( $resuelto['pais_override'] ) : ?>
				<br>Mostrando override de: <strong><?php echo esc_html( $resuelto['pais_override'] ); ?></strong> (según "Ver como país…")
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
}

$id = 'programacion';
if ( $resuelto['titulo'] && function_exists( 'jb3_subnav_register' ) ) {
	jb3_subnav_register( $resuelto['titulo'], $id );
}
?>
<?php
/* Sin ".jb3-contenedor" acá: es un bloque ACF, insertable por Gutenberg en
   cualquier lugar (incluso adentro de Columnas) — mismo motivo que en
   jb3_modulo_render() (ver _init_modulo.php). */
?>
<section class="jb3-modulo" id="<?php echo esc_attr( $id ); ?>">
	<?php if ( $resuelto['titulo'] ) : ?>
		<h2 class="jb3-modulo__titulo"><?php echo esc_html( $resuelto['titulo'] ); ?></h2>
	<?php endif; ?>
	<?php if ( $resuelto['bajada'] ) : ?>
		<div class="jb3-modulo__bajada"><?php echo wp_kses_post( $resuelto['bajada'] ); ?></div>
	<?php endif; ?>
	<div class="jb3-programacion__imagen">
		<picture>
			<?php if ( $imagen_mobile ) : ?>
				<source media="(max-width: 767px)" srcset="<?php echo esc_url( $imagen_mobile ); ?>">
			<?php endif; ?>
			<img src="<?php echo esc_url( $imagen_desktop ); ?>" alt="">
		</picture>
	</div>
</section>
