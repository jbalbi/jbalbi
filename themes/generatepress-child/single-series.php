<?php
/**
 * Template para single del CPT "series".
 *
 * Sin título suelto a propósito: la imagen destacada hace de hero (ver
 * .jb3-hero en style.css) y pasa por detrás del header, así que no hace
 * falta un <h1> plano como el resto de las páginas. GeneratePress tampoco
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

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="jb3-hero">
				<?php the_post_thumbnail( 'full' ); ?>
			</div>
		<?php endif; ?>

		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
