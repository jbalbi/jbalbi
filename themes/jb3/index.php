<?php
/**
 * Plantilla principal (blog y respaldo general).
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main is-layout-constrained has-global-padding">

	<?php if ( is_home() && ! is_front_page() ) : ?>
		<header class="page-header">
			<h1 class="page-title"><?php single_post_title(); ?></h1>
		</header>
	<?php elseif ( is_home() ) : ?>
		<h1 class="screen-reader-text"><?php bloginfo( 'name' ); ?></h1>
	<?php endif; ?>

	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content', get_post_type() );
		endwhile;

		the_posts_pagination( array(
			'prev_text' => __( '&larr; Anteriores', 'jb3' ),
			'next_text' => __( 'Siguientes &rarr;', 'jb3' ),
		) );
	else :
		get_template_part( 'template-parts/content', 'none' );
	endif;
	?>

</main>

<?php
get_footer();
