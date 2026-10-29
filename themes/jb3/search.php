<?php
/**
 * Resultados de búsqueda.
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main is-layout-constrained has-global-padding">

	<header class="page-header">
		<h1 class="page-title">
			<?php
			/* translators: %s: término buscado */
			printf( esc_html__( 'Resultados para: %s', 'jb3' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
			?>
		</h1>
		<?php get_search_form(); ?>
	</header>

	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content', get_post_type() );
		endwhile;

		the_posts_pagination();
	else :
		get_template_part( 'template-parts/content', 'none' );
	endif;
	?>

</main>

<?php
get_footer();
