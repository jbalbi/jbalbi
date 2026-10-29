<?php
/**
 * Render del bloque ACF "Redes Sociales del Canal" (jb3/redes-sociales-canal).
 * Sin campos propios: lee el campo "canal" de la PÁGINA que lo contiene
 * ($post_id, la página real — no el bloque, ver la misma aclaración en
 * blocks/modulo-contenido/render.php) y las Redes Sociales cargadas en el
 * TÉRMINO de ese canal (inc/canal-redes.php) — el editor solo elige dónde
 * insertar el bloque, el dato en sí vive en la taxonomía "Canales".
 */

defined( 'ABSPATH' ) || exit;

$canal_term_id = get_field( 'canal', $post_id );

if ( ! $canal_term_id ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Redes Sociales del Canal: esta página no tiene un "Canal" asignado, no hay de dónde sacar las redes.</em></p>';
	}
	return;
}

$html = jb3_canal_redes_sociales_html( (int) $canal_term_id );

if ( ! $html && current_user_can( 'edit_posts' ) ) {
	echo '<p><em>Redes Sociales del Canal: este canal todavía no tiene ninguna cargada (Canales → editar el término → Redes Sociales).</em></p>';
	return;
}

echo $html;
