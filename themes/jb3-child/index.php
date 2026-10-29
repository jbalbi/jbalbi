<?php
/**
 * Respaldo general para listados (blog, categorías, archivos de CPT...):
 * mismo armado que search.php — título + jb3_grid_mixto(), que elige sola la
 * proporción de cada ítem (afiche o miniatura).
 *
 * @package JB3_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="jb3-main-content">
	<div class="entry-content">
		<div class="jb3-contenedor">
			<h1 class="jb3-modulo__titulo">
				<?php
				if ( is_home() ) {
					single_post_title();
				} else {
					the_archive_title();
				}
				?>
			</h1>

			<?php if ( have_posts() ) : ?>

				<?php
				$items = array();
				while ( have_posts() ) {
					the_post();
					$items[] = get_the_ID();
				}
				echo jb3_grid_mixto( $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_grid_mixto() ya escapa todo.
				?>

				<div class="jb3-search-pagination">
					<?php the_posts_pagination(); ?>
				</div>

			<?php else : ?>

				<p class="jb3-search-sin-resultados">
					<?php esc_html_e( 'No hay contenido para mostrar.', 'jb3' ); ?>
				</p>

			<?php endif; ?>
		</div>
	</div>
</main>

<?php
get_footer();
