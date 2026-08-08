<?php
/**
 * Template para single del CPT "videos".
 *
 * Sin título visible a propósito: el título no se muestra en las páginas
 * sin hero (ver page.php). GeneratePress tampoco lo imprime el suyo acá
 * (generate_show_title forzado a false en _init_layout.php) — este template
 * es 100% custom y nunca pasa por content-single.php de GP de todas formas.
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

		<?php echo jb3_relacionados_auto( get_the_ID() ); ?>

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
