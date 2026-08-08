<?php
/**
 * Template Name: Canal
 *
 * Plantilla para las páginas individuales de cada canal (ej. "AXN",
 * "Sony Music"...). A diferencia de single-{cpt}.php, acá el contenido es
 * 100% libre por editor de bloques — cada canal puede tener secciones y
 * módulos completamente distintos, no hay estructura común forzada. Lo
 * único automático es el logo (viene del propio término de "canales", así
 * no hay que volver a subirlo en cada página) y el 404 por país, que ya lo
 * resuelve jb3-geo-targeting mirando el campo ACF "canal" de esta página
 * (ver jb3_geo_page_vinculo_canal_visible()) antes de llegar acá.
 *
 * El editor arma el resto del contenido con bloques normales, incluyendo
 * el shortcode [jb3_modulo canal="slug-del-canal" ...] tantas veces como
 * quiera para las grillas de contenido.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$canal_term_id = function_exists( 'get_field' ) ? get_field( 'canal' ) : 0;
$logo_url      = '';
if ( $canal_term_id ) {
	$logo     = get_field( 'logo', 'canales_' . $canal_term_id );
	$logo_url = is_array( $logo ) ? ( $logo['url'] ?? '' ) : (string) $logo;
}
?>

<main id="jb3-main-content">
	<?php while ( have_posts() ) : the_post(); ?>

		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<?php if ( $logo_url ) : ?>
				<div class="jb3-canal-header">
					<img class="jb3-canal-header__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
				</div>
			<?php endif; ?>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
