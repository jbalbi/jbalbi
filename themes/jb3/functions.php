<?php
/**
 * Funciones del tema JB3 (híbrido).
 *
 * ═══════════════════════════════════════════════════════════════════════════
 *  LEER ANTES DE USAR JB3 EN UN SITIO NUEVO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * JB3 es un tema PADRE genérico y mínimo. Regla: acá va solo lo que sirve
 * a cualquier sitio (hoy: sin comentarios + seguridad básica). Todo lo
 * propio de un sitio (CPT, bloques, diseño, logos, integraciones) va en su
 * tema HIJO. No agregar funciones de un sitio puntual a este tema.
 *
 * Qué trae el padre sin configurar nada:
 *   - inc/disable-comments.php: comentarios, trackbacks y pingbacks
 *     apagados en front, admin, REST, XML-RPC y editor de bloques.
 *   - inc/seguridad.php: XML-RPC apagado, usuarios ocultos a visitantes,
 *     /?author=N redirige a la portada, cabeceras de seguridad.
 *   - "Update URI: false" en style.css: WordPress nunca lo reemplaza por un
 *     tema homónimo de wordpress.org.
 *
 * Al crear el tema hijo de un sitio nuevo (modelo: jb3-child del sitio
 * Conectados):
 *   1. style.css del hijo con "Template: jb3" y "Update URI: false". El hijo
 *      NO hereda el Update URI del padre: cada tema se revisa por separado.
 *   2. Las funciones del hijo con prefijo propio (ej. misitio_*), nunca
 *      jb3_*: el functions.php del hijo se carga ANTES que este y las
 *      funciones de acá no están envueltas en function_exists().
 *   3. Decidir qué del padre usa el sitio y sacar lo que no:
 *      - CSS/JS base (assets/css/main.css, assets/js/main.js):
 *        wp_dequeue_style( 'jb3-main' ) / wp_dequeue_script( 'jb3-main' )
 *        en wp_enqueue_scripts con prioridad > 10.
 *      - theme.json (colores/tipografías de EJEMPLO): reemplazarlo con un
 *        theme.json propio en el hijo, o vaciarlo con el filtro
 *        wp_theme_json_data_theme.
 *      - Partes de plantilla en bloques (parts/header.html, footer.html):
 *        si el hijo trae header.php/footer.php propios, hacer
 *        remove_theme_support( 'block-template-parts' ) en after_setup_theme
 *        con prioridad > 10, para que el admin no muestre un header/footer
 *        que no se usa.
 *      - Favicon de respaldo (logo JB3): remove_action( 'wp_head' y
 *        'admin_head', 'jb3_favicon_fallback' ) si el sitio no carga su
 *        "Icono del sitio".
 *      - Barra lateral "sidebar-1" y el patrón patterns/hero.php: quedan
 *        disponibles; no molestan si el sitio no los usa.
 *   4. ACF Local JSON: por defecto ACF guarda en la carpeta del tema
 *      ACTIVO (el hijo). Dejarlo explícito con los filtros
 *      acf/settings/save_json y acf/settings/load_json.
 *   5. Si el hijo registra Custom Post Types: después de activarlo, ir a
 *      Ajustes > Enlaces permanentes > Guardar (si no, los singles dan 404).
 *   6. Si el sitio necesita comentarios o XML-RPC, reactivarlos desde el
 *      hijo (ver la cabecera de cada archivo en inc/), no tocando el padre.
 *      Comentarios: add_filter( 'jb3_desactivar_comentarios', '__return_false' );
 *
 * Pendientes del padre (cosméticos): Theme URI (example.com), Author,
 * Description y "Tested up to" en style.css.
 *
 * Piezas que hoy viven en jb3-child (Conectados) y podrían servir en otro
 * sitio — copiarlas al hijo nuevo si hacen falta, NO al padre:
 *   - filtro tiny_mce_before_init que saca content_css (campos WYSIWYG de
 *     ACF con texto invisible por el CSS del sitio);
 *   - URLs relativas para assets (sitios detrás de túneles/proxies);
 *   - inc/imagenes.php (tamaños propios + copias WebP), modales de
 *     video/imagen/página, visor de PDF, cargador de sliders (Swiper).
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * @package JB3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// get_template() para leer la versión del tema padre aunque haya un tema hijo activo.
define( 'JB3_VERSION', wp_get_theme( get_template() )->get( 'Version' ) );

// Comentarios desactivados en front, admin, REST y XML-RPC (un hijo puede
// reactivarlos con el filtro jb3_desactivar_comentarios, ver ese archivo).
if ( apply_filters( 'jb3_desactivar_comentarios', true ) ) {
	require get_template_directory() . '/inc/disable-comments.php';
}

// Seguridad básica para cualquier sitio: XML-RPC apagado, usuarios ocultos,
// cabeceras de seguridad.
require get_template_directory() . '/inc/seguridad.php';

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
