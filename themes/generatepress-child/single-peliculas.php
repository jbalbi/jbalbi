<?php
/**
 * Template para single del CPT "peliculas".
 *
 * Sin título suelto a propósito: este CPT va a tener hero + barra de
 * título propia más adelante (no un <h1> plano como el resto de las
 * páginas) — se agrega cuando armemos esa parte. GeneratePress tampoco
 * imprime el suyo acá (generate_show_title forzado a false en
 * _init_layout.php) — este template es 100% custom y nunca pasa por
 * content-single.php de GP de todas formas.
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
