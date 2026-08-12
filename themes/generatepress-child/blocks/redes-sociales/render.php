<?php
/**
 * Render del bloque ACF "Redes Sociales" (jb3/redes-sociales) — versión
 * genérica, independiente de canal: usar en cualquier página que necesite
 * su PROPIA fila de íconos (ej. el Host de un programa, más adelante).
 * Campo propio (grupo ACF "Redes Sociales", Location: Block == Redes
 * Sociales): "redes_sociales" (Repeater, mismos sub-campos "red"/"url" que
 * usa la versión de Canal — ver _init_canal_redes.php).
 */

defined( 'ABSPATH' ) || exit;

$filas = get_field( 'redes_sociales' );
$html  = jb3_redes_sociales_html( (array) $filas );

if ( ! $html && current_user_can( 'edit_posts' ) ) {
	echo '<p><em>Redes Sociales: todavía no cargaste ninguna fila.</em></p>';
	return;
}

echo $html;
