<?php
/**
 * Template para single del CPT "prod_originales".
 *
 * Sin título suelto a propósito: el hero (grupo ACF "Hero", ver
 * jb3_hero_image() en _init_hero.php) pasa por detrás del header, así que
 * no hace falta un <h1> plano como el resto de las páginas. GeneratePress
 * tampoco imprime el suyo acá (generate_show_title forzado a false en
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

		<?php jb3_hero_image(); ?>

		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>

		<?php echo jb3_genero_auto( get_the_ID() ); ?>

		<?php echo jb3_relacionados_auto( get_the_ID() ); ?>

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
