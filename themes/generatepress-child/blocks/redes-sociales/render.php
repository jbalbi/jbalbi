<?php
/**
 * Render del bloque ACF "Redes Sociales" (jb3/redes-sociales) — versión
 * genérica, independiente de canal: usar en cualquier página que necesite
 * su PROPIA fila de íconos (ej. el Host de un programa, un invitado/chef).
 * Campo propio (grupo ACF "Redes Sociales", Location: Block == Redes
 * Sociales): "redes_sociales" (Repeater, sub-campos "red"/"url" +
 * "texto" opcional — ver _init_canal_redes.php).
 * A diferencia de la versión de Canal (fila horizontal, solo íconos, sin
 * tocar), acá se muestra en lista vertical con el texto/handle de cada
 * fila al lado del ícono (segundo parámetro true de jb3_redes_sociales_html()).
 */

defined( 'ABSPATH' ) || exit;

$filas = get_field( 'redes_sociales' );
$html  = jb3_redes_sociales_html( (array) $filas, true );

if ( ! $html && current_user_can( 'edit_posts' ) ) {
	echo '<p><em>Redes Sociales: todavía no cargaste ninguna fila.</em></p>';
	return;
}

echo $html;
