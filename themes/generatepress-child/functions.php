<?php

add_action( 'wp_enqueue_scripts', 'generatepress_child_enqueue_styles' );
function generatepress_child_enqueue_styles() {
	wp_enqueue_style(
		'generatepress-style',
		get_template_directory_uri() . '/style.css'
	);

	wp_enqueue_style(
		'generatepress-child-style',
		get_stylesheet_uri(),
		array( 'generatepress-style' ),
		wp_get_theme()->get( 'Version' )
	);
}
