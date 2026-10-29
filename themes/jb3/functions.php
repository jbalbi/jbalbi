<?php
/**
 * Funciones del tema JB3 (híbrido).
 *
 * @package JB3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// get_template() para leer la versión del tema padre aunque haya un tema hijo activo.
define( 'JB3_VERSION', wp_get_theme( get_template() )->get( 'Version' ) );

// Sitio institucional: comentarios desactivados en front, admin, REST y XML-RPC.
require get_template_directory() . '/inc/disable-comments.php';

/**
 * Soportes del tema.
 */
function jb3_setup() {
	load_theme_textdomain( 'jb3', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 240,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	// Clave del enfoque híbrido: habilita Apariencia > Partes de plantilla
	// para editar parts/header.html y parts/footer.html con bloques.
	add_theme_support( 'block-template-parts' );

	// Estilos del front también dentro del editor.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/main.css' );

	register_nav_menus( array(
		'primary' => __( 'Menú principal', 'jb3' ),
		'footer'  => __( 'Menú del pie', 'jb3' ),
	) );
}
add_action( 'after_setup_theme', 'jb3_setup' );

/**
 * CSS y JS del front.
 */
function jb3_enqueue_assets() {
	wp_enqueue_style( 'jb3-main', get_template_directory_uri() . '/assets/css/main.css', array(), JB3_VERSION );
	wp_enqueue_script( 'jb3-main', get_template_directory_uri() . '/assets/js/main.js', array(), JB3_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
}
add_action( 'wp_enqueue_scripts', 'jb3_enqueue_assets' );

/**
 * Zonas de widgets.
 */
function jb3_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Barra lateral', 'jb3' ),
		'id'            => 'sidebar-1',
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
}
add_action( 'widgets_init', 'jb3_widgets_init' );

/**
 * Categoría propia para los patrones del tema (carpeta /patterns).
 */
function jb3_register_pattern_category() {
	register_block_pattern_category( 'jb3', array( 'label' => __( 'JB3', 'jb3' ) ) );
}
add_action( 'init', 'jb3_register_pattern_category' );

/**
 * Imprime una parte de plantilla de bloques (parts/*.html) y, si no existe,
 * cae al template-part PHP equivalente.
 *
 * @param string $slug Nombre de la parte (header, footer...).
 */
function jb3_template_part( $slug ) {
	if ( function_exists( 'block_template_part' ) && current_theme_supports( 'block-template-parts' ) ) {
		block_template_part( $slug );
		return;
	}
	get_template_part( 'template-parts/' . $slug );
}

/**
 * HTML del logo: el subido en el Personalizador o, si no hay, el SVG del tema.
 *
 * @param int $width Ancho en píxeles del logo por defecto.
 * @return string
 */
function jb3_get_logo( $width = 56 ) {
	if ( has_custom_logo() ) {
		return get_custom_logo();
	}

	return sprintf(
		'<a href="%1$s" class="custom-logo-link" rel="home"><img src="%2$s" class="custom-logo" width="%3$d" height="%3$d" alt="%4$s"></a>',
		esc_url( home_url( '/' ) ),
		esc_url( get_template_directory_uri() . '/assets/images/logo.svg' ),
		absint( $width ),
		esc_attr( get_bloginfo( 'name', 'display' ) )
	);
}

/**
 * El bloque "Logo del sitio" no imprime nada si no hay logo subido:
 * en ese caso mostramos el SVG del tema.
 *
 * @param string $block_content HTML del bloque.
 * @param array  $block         Datos del bloque.
 * @return string
 */
function jb3_site_logo_fallback( $block_content, $block ) {
	if ( '' !== trim( $block_content ) ) {
		return $block_content;
	}

	$width = isset( $block['attrs']['width'] ) ? $block['attrs']['width'] : 56;

	return '<div class="wp-block-site-logo">' . jb3_get_logo( $width ) . '</div>';
}
add_filter( 'render_block_core/site-logo', 'jb3_site_logo_fallback', 10, 2 );

/**
 * Favicon con el logo del tema si no se ha configurado un icono del sitio.
 */
function jb3_favicon_fallback() {
	if ( has_site_icon() ) {
		return;
	}
	printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( get_template_directory_uri() . '/assets/images/logo.svg' ) );
}
add_action( 'wp_head', 'jb3_favicon_fallback' );
add_action( 'admin_head', 'jb3_favicon_fallback' );

/**
 * Meta de entrada: fecha y autor.
 */
function jb3_posted_on() {
	printf(
		'<p class="entry-meta"><time datetime="%1$s">%2$s</time> · %3$s</p>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() ),
		esc_html( get_the_author() )
	);
}
