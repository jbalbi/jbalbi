<?php
/**
 * Contenido protegido con contraseña, mientras el visitante no la ingresó:
 * solo el título y el formulario (ver inc/seguridad.php, filtro
 * template_include). El tema hijo puede tener su propio password.php.
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main">
	<article <?php post_class(); ?>>
		<header class="entry-header is-layout-constrained has-global-padding">
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
		</header>

		<div class="entry-content is-layout-constrained has-global-padding">
			<?php echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML del core, ya escapado. ?>
		</div>
	</article>
</main>

<?php
get_footer();
