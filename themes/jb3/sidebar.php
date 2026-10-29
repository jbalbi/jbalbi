<?php
/**
 * Barra lateral (úsala con get_sidebar() donde la necesites).
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>
<aside class="widget-area">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
