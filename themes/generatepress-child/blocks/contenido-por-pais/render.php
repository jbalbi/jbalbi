<?php
/**
 * Render del bloque ACF "Contenido por País" (jb3/contenido-por-pais).
 * Envoltorio genérico: adentro va CUALQUIER otro bloque (InnerBlocks,
 * soporte nativo de ACF Pro — no hace falta JS propio, alcanza con
 * <InnerBlocks /> acá abajo). Cerrado por defecto: sin ningún país marcado
 * en cp_paises, no se imprime nada de adentro para nadie — hay que marcar
 * explícitamente los países que SÍ lo pueden ver. Evita que un bloque
 * insertado y no configurado todavía quede visible para todo el mundo por
 * descuido.
 *
 * Sin elemento propio en el front (el filtro acf/blocks/wrap_frontend_
 * innerblocks en _init_acf_blocks.php desactiva el <div> que ACF pondría
 * por defecto): el contenido de adentro queda exactamente como si no
 * hubiera ningún envoltorio — participa normal de la cascada de espaciado
 * (".entry-content > * + *") sin necesitar CSS propio para este bloque.
 */

defined( 'ABSPATH' ) || exit;

// get_field() en un campo Taxonomy vacío devuelve false, no un array vacío
// — (array) false da [false] (¡no-vacío!), lo que rompía la regla de "sin
// selección = sin restricción". Se normaliza a mano. Además, acá adentro
// get_field() siempre devuelve IDs de término (no slugs, aunque el campo
// esté configurado como return_format=slug — no se respeta en este
// contexto de bloque), así que se trabaja con IDs en vez de confiar en esa
// conversión.
$paises_ids = get_field( 'cp_paises' );
$paises_ids = is_array( $paises_ids ) ? $paises_ids : array();

if ( $is_preview ) {
	$nombres = array_filter( array_map( function ( $id ) {
		$term = get_term( $id, 'pais' );
		return $term && ! is_wp_error( $term ) ? $term->name : '';
	}, $paises_ids ) );
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:10px 16px; margin-bottom:12px; background:#f6f6f6; font-family:sans-serif; font-size:13px; color:#444;">
		<strong>🌎 Contenido por País</strong> —
		<?php echo $nombres ? 'visible solo en: ' . esc_html( implode( ', ', $nombres ) ) : 'sin países marcados — NO se ve para nadie todavía'; ?>
	</div>
	<InnerBlocks />
	<?php
	return;
}

$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';
$termino        = $pais_visitante ? get_term_by( 'slug', $pais_visitante, 'pais' ) : false;
$id_visitante   = $termino ? $termino->term_id : 0;

if ( ! $paises_ids || ! in_array( $id_visitante, $paises_ids, true ) ) {
	return;
}
?>
<InnerBlocks />
