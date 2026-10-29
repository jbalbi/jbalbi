<?php
/**
 * Cabecera: documento HTML + parte de bloques "header".
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks site">
	<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Saltar al contenido', 'jb3' ); ?></a>

	<header class="site-header">
		<?php jb3_template_part( 'header' ); ?>
	</header>
