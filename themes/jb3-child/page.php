<?php
/**
 * Template para Páginas estándar del sitio.
 *
 * Mismo patrón que los single-{cpt}.php: markup propio y mínimo, sin título
 * (lo arma cada página con sus bloques). Todas las Páginas quedan
 * estandarizadas con esta misma estructura.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="jb3-main-content">
	<?php while ( have_posts() ) : the_post(); ?>

		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
