<?php
/**
 * Render del bloque ACF "Grilla" (jb3/grilla).
 * Contenedor CSS Grid genérico, con InnerBlocks real (como
 * "Contenido por País" — el editor arrastra adentro lo que quiera, no sabe
 * nada de tarjetas ni de ningún otro bloque puntual). El editor solo
 * define, con números, cuántas columnas quiere en cada tamaño de pantalla
 * — sin tocar ningún panel de Layout nativo ni escribir clases CSS a mano
 * (ver la ronda larga de idas y vueltas con el layout "Cuadrícula" nativo
 * de Gutenberg que motivó este bloque: su combinación "Máx. columnas" +
 * "Mín. ancho" no respeta el gap real del sitio cuando los dos campos
 * están cargados a la vez — desajuste confirmado leyendo
 * wp-includes/block-supports/layout.php, 2026-09-01).
 *
 * Columnas: repeat(N, 1fr) simple, no auto-fit/auto-fill — mismo criterio
 * que jb3-cols-N (style.css): un número fijo de columnas por breakpoint,
 * que se agrandan solas en pantallas grandes en vez de multiplicarse.
 *
 * Alineación de la última fila incompleta (gr_alineacion): con columnas
 * FIJAS, un ítem que queda solo en la última fila ya ocupa solo 1/N del
 * ancho (no se estira como pasaría con Flexbox) — lo único que hace falta
 * resolver es DÓNDE arranca esa fila incompleta (izquierda/centro/derecha).
 * CSS puro no puede hacer esto de forma genérica (nth-child necesita un
 * número de columnas fijo escrito a mano en la hoja de estilos, no puede
 * leer una variable) — por eso se calcula acá, contando de verdad los
 * bloques hijos ($wp_block->parsed_block['innerBlocks'], disponible desde
 * WP 5.5) y armando, por cada ítem "sobrante", en qué columna tiene que
 * arrancar — para CADA breakpoint por separado (la cantidad de columnas, y
 * por lo tanto cuántos ítems sobran, es distinta en desktop/tablet/mobile).
 * Ese cálculo con conteo real SOLO se hace en el front (no en el editor):
 * en preview/edición se imprime el <InnerBlocks /> nativo de siempre, para
 * no perder la experiencia estándar de Gutenberg (seleccionar, arrastrar,
 * reordenar cada tarjeta) — la alineación de la fila incompleta es un
 * detalle puramente visual del resultado final, no algo que haga falta ver
 * pixel-perfect mientras se edita.
 */

defined( 'ABSPATH' ) || exit;

$cols_desktop = (int) get_field( 'gr_columnas_desktop' ) ?: 3;
$cols_tablet  = (int) get_field( 'gr_columnas_tablet' ) ?: 2;
$cols_mobile  = (int) get_field( 'gr_columnas_mobile' ) ?: 1;
$alineacion   = (string) get_field( 'gr_alineacion' ) ?: 'izquierda';

$style = sprintf(
	' style="--jb3-grilla-cols-desktop:%d;--jb3-grilla-cols-tablet:%d;--jb3-grilla-cols-mobile:%d;"',
	$cols_desktop,
	$cols_tablet,
	$cols_mobile
);

if ( $is_preview ) {
	?>
	<div class="jb3-grilla"<?php echo $style; ?>>
		<InnerBlocks />
	</div>
	<?php
	return;
}

/**
 * Columna (1-indexada) donde tiene que arrancar el ítem número $indice
 * (0-indexado, contado DESDE el principio de la fila incompleta) cuando
 * esa fila tiene $sobrantes ítems sobre $columnas totales.
 *
 * function_exists(): este archivo se vuelve a incluir (include, no
 * include_once) cada vez que el bloque se renderiza en la misma carga de
 * página — con más de una "Grilla" en la página (o el propio mecanismo de
 * preview de ACF re-incluyéndolo), una función declarada acá sin esta
 * protección tira "Cannot redeclare" la segunda vez. Bug real, detectado
 * 2026-09-01.
 */
if ( ! function_exists( 'jb3_grilla_columna_inicio' ) ) {
	function jb3_grilla_columna_inicio( int $indice, int $sobrantes, int $columnas, string $alineacion ): ?int {
		if ( 'centro' === $alineacion ) {
			$base = (int) floor( ( $columnas - $sobrantes ) / 2 ) + 1;
		} elseif ( 'derecha' === $alineacion ) {
			$base = ( $columnas - $sobrantes ) + 1;
		} else {
			return null; // izquierda: comportamiento default de grid, sin corrimiento.
		}
		return $base + $indice;
	}
}

$children = ( $wp_block instanceof WP_Block ) ? ( $wp_block->parsed_block['innerBlocks'] ?? array() ) : array();
$total    = count( $children );

if ( ! $total ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Grilla: no hay nada cargado adentro todavía.</em></p>';
	}
	return;
}

$breakpoints = array(
	'desktop' => $cols_desktop,
	'tablet'  => $cols_tablet,
	'mobile'  => $cols_mobile,
);
?>
<div class="jb3-grilla"<?php echo $style; ?>>
	<?php foreach ( $children as $i => $child ) :
		$vars = array();
		foreach ( $breakpoints as $bp => $columnas ) {
			$sobrantes = $columnas > 0 ? ( $total % $columnas ) : 0;
			if ( ! $sobrantes || $i < ( $total - $sobrantes ) ) {
				continue; // esta fila (en este breakpoint) está completa, o este ítem no es parte de la incompleta.
			}
			$indice = $i - ( $total - $sobrantes ); // posición del ítem DENTRO de la fila incompleta, 0-indexada.
			$col    = jb3_grilla_columna_inicio( $indice, $sobrantes, $columnas, $alineacion );
			if ( null !== $col ) {
				$vars[] = '--jb3-grilla-gc-' . $bp . ':' . $col;
			}
		}
		$item_style = $vars ? ' style="' . esc_attr( implode( ';', $vars ) ) . ';"' : '';
		?>
		<div class="jb3-grilla__item"<?php echo $item_style; ?>><?php echo render_block( $child ); ?></div>
	<?php endforeach; ?>
</div>
