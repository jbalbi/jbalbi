<?php
/**
 * Template para Páginas estándar del sitio.
 *
 * Mismo patrón que los single-{cpt}.php: markup propio y mínimo, sin pasar
 * por content-page.php de GeneratePress (por eso no hace falta el filtro
 * generate_show_title acá tampoco). Todas las Páginas quedan estandarizadas
 * con esta misma estructura.
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
