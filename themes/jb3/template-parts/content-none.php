<?php
/**
 * Sin resultados.
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="no-results">
	<h2><?php esc_html_e( 'No hay nada por aquí', 'jb3' ); ?></h2>
	<?php if ( is_search() ) : ?>
		<p><?php esc_html_e( 'No hubo coincidencias. Prueba con otras palabras.', 'jb3' ); ?></p>
	<?php endif; ?>
	<?php get_search_form(); ?>
</section>
