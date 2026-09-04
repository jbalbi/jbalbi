<?php
/**
 * Template para single del CPT "peliculas".
 *
 * Sin <h1> suelto aparte: jb3_hero_titulo() (_init_hero.php) ya lo imprime,
 * pegado debajo del hero (grupo ACF "Hero", jb3_hero_image()) — GeneratePress
 * tampoco imprime el suyo acá (generate_show_title forzado a false en
 * _init_layout.php) — este template es 100% custom y nunca pasa por
 * content-single.php de GP de todas formas.
 *
 * Subnav: se arma DESPUÉS del título/hero mostrado, pero necesita saber
 * qué módulos hay MÁS ABAJO (etiquetas/relacionados) antes de poder
 * imprimirse — por eso el contenido de la página se arma primero en un
 * buffer (jb3_etiquetas_auto()/jb3_relacionados_auto() registran sus propios
 * ítems de subnav como efecto secundario al renderizar), y recién
 * después se imprime todo en el orden real: hero, título, subnav, resto.
 *
 * Contenido: jb3_render_contenido_pelicula() (prototipo, ver
 * _init_contenido_pelicula.php) imprime el campo Flexible Content
 * ANTES de the_content() — el editor de bloques libre sigue disponible
 * debajo para lo que no entre en ninguno de esos layouts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="jb3-main-content">
	<?php while ( have_posts() ) : the_post(); ?>

		<?php jb3_hero_image(); ?>
		<?php jb3_hero_titulo(); ?>

		<?php
		ob_start();
		?>
		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<div class="entry-content">
				<?php echo jb3_render_contenido_pelicula( get_the_ID() ); ?>
				<?php the_content(); ?>
			</div>
		</article>
		<?php
		$articulo_html      = ob_get_clean();
		$relacionados_html  = jb3_relacionados_auto( get_the_ID() );
		$etiquetas_html     = jb3_etiquetas_auto( get_the_ID() );

		echo jb3_subnav_render( jb3_subnav_items() );
		echo $articulo_html;
		echo $relacionados_html;
		echo $etiquetas_html;
		?>

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
