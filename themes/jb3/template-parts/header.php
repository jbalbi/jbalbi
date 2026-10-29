<?php
/**
 * Cabecera PHP de respaldo (si no hay soporte de partes de bloques).
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="header-inner alignwide">
	<div class="site-branding">
		<?php echo jb3_get_logo(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado en jb3_get_logo(). ?>
		<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
	</div>
	<?php
	wp_nav_menu( array(
		'theme_location' => 'primary',
		'container'      => 'nav',
		'container_class'=> 'primary-nav',
		'fallback_cb'    => false,
		'depth'          => 2,
	) );
	?>
</div>
