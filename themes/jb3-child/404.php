<?php
/**
 * Página no encontrada: aviso centrado (.jb3-mensaje-centrado) + buscador.
 *
 * @package JB3_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="jb3-main-content">
	<div class="jb3-mensaje-centrado">
		<h1><?php esc_html_e( '¡Ups! No se encontró esa página.', 'jb3' ); ?></h1>
		<p><?php esc_html_e( 'Parece que no hay nada en esta ubicación. ¿Querés probar con una búsqueda?', 'jb3' ); ?></p>
		<?php get_search_form(); ?>
	</div>
</main>

<?php
get_footer();
