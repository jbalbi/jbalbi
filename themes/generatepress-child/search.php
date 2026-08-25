<?php
/**
 * Template para resultados de búsqueda ("Resultados de la búsqueda para: X").
 *
 * Antes caía en el search.php genérico de GeneratePress (sin padding ni
 * grilla propia del sitio — se ve roto). Mismo patrón que single-{cpt}.php:
 * <main id="jb3-main-content"> + .entry-content dentro de un jb3-contenedor
 * para el padding estándar, y jb3_grid_thumbs() (misma grilla que el resto
 * del sitio) para los resultados — sirve para cualquier tipo de contenido
 * mezclado (páginas, entradas, películas, series, etc.), a diferencia de
 * jb3_grid_posters() que es específica para pósters de película/serie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="jb3-main-content">
	<div class="entry-content">
		<div class="jb3-contenedor">
			<h1 class="jb3-modulo__titulo">
				<?php
				printf(
					/* translators: %s: término buscado */
					esc_html__( 'Resultados de la búsqueda para: %s', 'generatepress-child' ),
					'<span>' . esc_html( get_search_query() ) . '</span>'
				);
				?>
			</h1>

			<?php if ( have_posts() ) : ?>

				<?php
				$resultados = [];
				while ( have_posts() ) {
					the_post();
					$resultados[] = get_the_ID();
				}
				echo jb3_grid_thumbs( $resultados );
				?>

				<div class="jb3-search-pagination">
					<?php the_posts_pagination(); ?>
				</div>

			<?php else : ?>

				<p class="jb3-search-sin-resultados">
					<?php esc_html_e( 'No se encontró nada para esa búsqueda.', 'generatepress-child' ); ?>
				</p>

			<?php endif; ?>
		</div>
	</div>
</main>

<?php get_footer(); ?>
