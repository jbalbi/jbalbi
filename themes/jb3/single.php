<?php
/**
 * Entrada individual.
 *
 * Cada sección lleva su propio "is-layout-constrained has-global-padding"
 * para que los bloques alignwide/alignfull del contenido funcionen.
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header is-layout-constrained has-global-padding">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				<?php jb3_posted_on(); ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="is-layout-constrained has-global-padding">
					<figure class="post-thumbnail alignwide"><?php the_post_thumbnail( 'large' ); ?></figure>
				</div>
			<?php endif; ?>

			<div class="entry-content is-layout-constrained has-global-padding">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>

			<footer class="entry-footer is-layout-constrained has-global-padding">
				<?php the_tags( '<p class="entry-tags">', ', ', '</p>' ); ?>
			</footer>
		</article>

		<div class="is-layout-constrained has-global-padding">
			<?php
			the_post_navigation( array(
				'prev_text' => '&larr; %title',
				'next_text' => '%title &rarr;',
			) );
			?>
		</div>
	<?php endwhile; ?>
</main>

<?php
get_footer();
