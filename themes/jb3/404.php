<?php
/**
 * Página no encontrada.
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main is-layout-constrained has-global-padding">
	<section class="error-404">
		<h1 class="page-title"><?php esc_html_e( 'Página no encontrada', 'jb3' ); ?></h1>
		<p><?php esc_html_e( 'Lo que buscas no está aquí. Prueba con una búsqueda:', 'jb3' ); ?></p>
		<?php get_search_form(); ?>
	</section>
</main>

<?php
get_footer();
