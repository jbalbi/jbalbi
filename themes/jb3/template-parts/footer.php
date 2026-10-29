<?php
/**
 * Pie PHP de respaldo (si no hay soporte de partes de bloques).
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="footer-inner alignwide">
	<?php
	wp_nav_menu( array(
		'theme_location' => 'footer',
		'container'      => 'nav',
		'depth'          => 1,
		'fallback_cb'    => false,
	) );
	?>
	<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
</div>
