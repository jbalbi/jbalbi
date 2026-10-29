<?php
/**
 * Template para el CPT "pagina_canal" (canal individual: AXN, Sony Music...).
 * Mismo criterio que tenía page-canal.php (plantilla vieja, ver el
 * comentario grande en inc/custom-post-types.php sobre por qué se migró
 * a CPT): el contenido es 100% libre por editor de bloques, cada canal
 * puede tener secciones y módulos completamente distintos, no hay
 * estructura común forzada. Lo único automático es el logo (viene del
 * propio término de "canales", así no hay que volver a subirlo en cada
 * página) y el 404 por país, que ya lo resuelve jb3-geo-targeting mirando
 * el campo ACF "canal" de este post (ver jb3_geo_page_canal_visible())
 * antes de llegar acá — jb3_geo_post_types() en el plugin tiene que incluir
 * "pagina_canal" para que ese chequeo se dispare.
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
$logo          = null;
if ( $canal_term_id ) {
	$logo     = get_field( 'logo', 'canales_' . $canal_term_id );
	$logo_url = is_array( $logo ) ? ( $logo['url'] ?? '' ) : (string) $logo;
}
?>

<main id="jb3-main-content">
	<?php while ( have_posts() ) : the_post(); ?>

		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<?php if ( $logo_url ) : ?>
				<div class="jb3-canal-header jb3-contenedor">
					<?php
					// Copia justa (2026-10-01): caja .jb3-canal-header__logo de
					// máx. 240×120 en main.css — si cambia allá, cambiar acá. Ver
					// jb3_sizes_logo(), inc/imagenes.php. Se ve al entrar: eager.
					$logo_id  = jb3_img_id( $logo );
					$logo_img = $logo_id ? jb3_img( $logo_id, jb3_sizes_logo( $logo_id, array( 120, 120, 120 ), array( 240, 240, 240 ) ), array( 'class' => 'jb3-canal-header__logo', 'loading' => 'eager' ) ) : '';
					if ( $logo_img ) :
						echo $logo_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo.
					else :
						?>
						<img class="jb3-canal-header__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
