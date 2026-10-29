<?php
/**
 * Contenido protegido con contraseña, mientras el visitante no la ingresó:
 * título + formulario, con el mismo diseño centrado que la 404. La elige el filtro
 * template_include de functions.php: ninguna
 * plantilla del sitio (single-*.php, page.php) llega a cargarse, así que no
 * se ve nada de su contenido — ni campos ACF, ni PDF, ni relacionados.
 *
 * @package GeneratePress_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="jb3-main-content">
	<div class="jb3-mensaje-centrado">
		<?php the_title( '<h1>', '</h1>' ); ?>
		<?php echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML del core, ya escapado. ?>
	</div>
</main>

<?php
get_footer();
