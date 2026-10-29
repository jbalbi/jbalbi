<?php
/**
 * Página.
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
			<header class="entry-header is-layout-constrained has-global-padding<?php echo is_front_page() ? ' screen-reader-text' : ''; ?>">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header>

			<div class="entry-content is-layout-constrained has-global-padding">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
