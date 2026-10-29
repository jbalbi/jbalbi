<?php
/**
 * Entrada individual (y respaldo de cualquier CPT sin su propio
 * single-{cpt}.php): título + contenido, con el padding estándar del sitio.
 *
 * @package JB3_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="jb3-main-content">
	<?php while ( have_posts() ) : the_post(); ?>

		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<div class="jb3-contenedor">
				<?php the_title( '<h1 class="jb3-modulo__titulo">', '</h1>' ); ?>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</div>
		</article>

	<?php endwhile; ?>
</main>

<?php
get_footer();
