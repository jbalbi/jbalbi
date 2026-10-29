<?php
/**
 * Funciones del tema hijo JB3 Child: el sitio completo (Conectados).
 *
 * El tema padre (jb3) queda como base genérica, sin tocar. Este archivo se
 * carga ANTES que el functions.php del padre, así que:
 *   - las funciones de acá llevan prefijo propio (jb3_child_*) para no
 *     chocar con las del padre (jb3_setup, jb3_enqueue_assets...);
 *   - lo que el padre agrega y el sitio no usa (su CSS/JS, su theme.json,
 *     el favicon de respaldo) se saca más abajo, en hooks que corren
 *     después de los del padre.
 * Lo propio de cada funcionalidad vive en inc/*.php (lista al final).
 *
 * @package JB3_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Soportes del tema. Prioridad 11: después de jb3_setup() del padre, para
 * poder reemplazar su hoja de estilos del editor por la del sitio.
 */
function jb3_child_setup() {
	// El editor de bloques corre en un iframe aparte que, sin esto, nunca carga
	// el CSS del tema: cualquier bloque que dependa de clases propias
	// (.jb3-modulo, .jb3-grid-thumbs, etc., incluidos los ACF Blocks) se vería
	// ahí como HTML crudo sin estilo.
	remove_editor_styles();
	add_editor_style( 'assets/css/main.css' );

	register_nav_menus( array(
		'primary' => __( 'Menú principal', 'jb3' ),
		'footer'  => __( 'Footer: enlaces legales (Políticas, Términos…)', 'jb3' ),
	) );

	// Favicon de respaldo del padre (su logo genérico): el sitio usa el
	// "Icono del sitio" de Apariencia > Personalizar, o ninguno.
	remove_action( 'wp_head', 'jb3_favicon_fallback' );
	remove_action( 'admin_head', 'jb3_favicon_fallback' );

	// Partes de plantilla en bloques del padre (parts/header.html y
	// footer.html): el sitio usa header.php/footer.php propios, así que el
	// editor de "Partes de plantilla" del admin mostraría un header/footer
	// que no aparece en ningún lado. Sin este soporte ese menú desaparece.
	remove_theme_support( 'block-template-parts' );
}
add_action( 'after_setup_theme', 'jb3_child_setup', 11 );

/**
 * theme.json del padre: el sitio no lo usa (colores, tipografía, márgenes
 * entre bloques y anchos vienen de assets/css/main.css). Se vacía para que
 * WordPress no genere sus estilos globales encima de los del sitio.
 */
add_filter( 'wp_theme_json_data_theme', function ( $theme_json ) {
	return new WP_Theme_JSON_Data( array( 'version' => 3 ), 'theme' );
} );

/**
 * Y además, para WordPress este tema NO tiene theme.json (2026-10-29) — igual
 * que GeneratePress. Vaciar los datos (arriba) no alcanzaba: con solo que el
 * archivo exista en el padre, WordPress arma distinto el HTML y el CSS de
 * los bloques, y todo main.css se escribió con GeneratePress contando con el
 * comportamiento "clásico":
 *   - Grupo: sin la capa <div class="wp-block-group__inner-container">, los
 *     jb3-cols-N quedaban en una sola columna (ej. los Episodios de Aventura
 *     Gastronómica) y los bloques dentro de un Grupo perdían sus 40px.
 *   - Imagen alineada: sin su <div class="wp-block-image"> exterior.
 *   - Estilos globales: 7 reglas de alineación de más (.is-layout-constrained
 *     > * con margin:auto !important) que centraban títulos y achicaban
 *     módulos dentro de columnas.
 * Con esto el contenido sale con el mismo HTML y los mismos estilos de
 * WordPress que con GeneratePress (comparado página por página). El padre
 * no se toca: su theme.json sigue ahí para cualquier otro sitio que lo use.
 * wp_theme_has_theme_json() pasa la ruta por este mismo filtro.
 */
add_filter( 'theme_file_path', function ( $path, $file ) {
	return 'theme.json' === $file ? '' : $path;
}, 10, 2 );

/**
 * Versión de un archivo del tema para el query string: filemtime() cambia
 * solo cada vez que se guarda el archivo, así el navegador nunca se queda
 * con una copia vieja cacheada después de una edición.
 *
 * @param string $archivo Ruta relativa a la carpeta del tema hijo.
 * @return int|false
 */
function jb3_child_asset_version( $archivo ) {
	$ruta = get_stylesheet_directory() . '/' . $archivo;
	return file_exists( $ruta ) ? filemtime( $ruta ) : false;
}

/**
 * CSS y JS del front. Prioridad 20: después de jb3_enqueue_assets() del
 * padre, para sacar su CSS/JS genéricos.
 *
 * Tipografía del sitio (var --tipografia-1 en main.css). Para cambiarla:
 * actualizar la URL de Google Fonts acá Y el valor de --tipografia-1 en
 * main.css — son los dos únicos lugares que hay que tocar.
 */
function jb3_child_enqueue_assets() {
	wp_dequeue_style( 'jb3-main' );
	wp_dequeue_script( 'jb3-main' );

	wp_enqueue_style( 'jb3-child-tipografia', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap', array(), null );
	// main.min.css (sin comentarios ni espacios, ~1/3 del peso) la genera
	// tools/minificar-css.php — en el deploy, o a mano en local. Se usa solo
	// si es MÁS NUEVA que main.css: si se editó main.css y no se volvió a
	// minificar, se carga main.css directo, nunca estilos viejos.
	$css = 'assets/css/main.css';
	$min = 'assets/css/main.min.css';
	if ( jb3_child_asset_version( $min ) >= jb3_child_asset_version( $css ) ) {
		$css = $min;
	}
	wp_enqueue_style( 'jb3-child-main', get_stylesheet_directory_uri() . '/' . $css, array( 'jb3-child-tipografia' ), jb3_child_asset_version( $css ) );

	// header.js: menú (hamburguesa + submenús), buscador, cuenta y alto real
	// del header (para el hero que pasa por detrás) — un solo archivo.
	// modales.js: modales de video, imagen y página (su HTML sale de
	// inc/video-modal.php, inc/image-modal.php e inc/page-modal.php).
	wp_enqueue_script( 'jb3-header', get_stylesheet_directory_uri() . '/assets/js/header.js', array(), jb3_child_asset_version( 'assets/js/header.js' ), true );
	wp_enqueue_script( 'jb3-modales', get_stylesheet_directory_uri() . '/assets/js/modales.js', array(), jb3_child_asset_version( 'assets/js/modales.js' ), true );
	wp_localize_script( 'jb3-modales', 'jb3Modales', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'wp_enqueue_scripts', 'jb3_child_enqueue_assets', 20 );

// Lo de add_editor_style() de arriba también le llega a cualquier editor
// TinyMCE "clásico" (wp_editor()) — en este sitio, los campos WYSIWYG de ACF
// (Sinopsis/Información/Imagen+Texto, etc.). Ahí es un problema real
// (encontrado 2026-09-04): el CSS del sitio (fondos negros, texto claro)
// quedaba adentro del iframe de esos campos, y en filas de Flexible Content
// ya existentes esa hoja a veces no llegaba a tiempo (carrera de carga con
// varias instancias de TinyMCE en la misma pantalla) — el campo quedaba con
// el fondo blanco de TinyMCE y el texto claro del sitio: invisible.
// Sacarle el content_css a todas las instancias (ACF arma su editor por JS,
// no hay un $editor_id confiable para filtrar) las deja con los colores
// default de TinyMCE, siempre legibles. Gutenberg no usa este filtro para
// su propio preview.
add_filter( 'tiny_mce_before_init', function ( $mce_init ) {
	unset( $mce_init['content_css'] );
	return $mce_init;
} );

// ─── COMPATIBILIDAD CON TÚNELES Y PROXIES ─────────────────────────────────────

add_filter( 'script_loader_src', 'jb3_child_make_relative_url' );
add_filter( 'style_loader_src', 'jb3_child_make_relative_url' );
function jb3_child_make_relative_url( $url ) {
	// Solo relativizar URLs del propio sitio. wp_make_link_relative() le saca
	// el esquema+host a CUALQUIER url que reciba — para un asset de terceros
	// (Google Fonts, etc.) eso deja la ruta pidiéndose contra el dominio
	// propio en vez del original, y rompe ese asset (404).
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$url_host  = wp_parse_url( $url, PHP_URL_HOST );
	if ( $url_host && $url_host !== $site_host ) {
		return $url;
	}
	return wp_make_link_relative( $url );
}

// ─── ACF LOCAL JSON ───────────────────────────────────────────────────────────

// La DEFINICIÓN de los grupos de campos ACF (no los valores cargados) vive
// en acf-json/ dentro de este tema y va a git: si se pierde la base, los
// campos se recuperan desde ahí (ACF > Grupos de campos > "Sincronización
// disponible" > Sincronizar). Cada vez que se guarda un grupo en el admin,
// ACF actualiza su .json solo — hay que commitearlo como cualquier otro
// archivo del tema. ACF ya usa esta carpeta por defecto; se deja explícito
// para que no dependa de ese default ni pase desapercibido.
add_filter( 'acf/settings/save_json', fn() => get_stylesheet_directory() . '/acf-json' );
add_filter( 'acf/settings/load_json', fn() => array( get_stylesheet_directory() . '/acf-json' ) );

// ─── INCLUDES ─────────────────────────────────────────────────────────────────

require_once __DIR__ . '/inc/iconos.php';             // jb3_icono(): SVG del header (buscar, menú, cerrar, cuenta, LinkedIn)
require_once __DIR__ . '/inc/taxonomias.php';         // Taxonomías personalizadas
require_once __DIR__ . '/inc/custom-post-types.php';  // Custom Post Types
require_once __DIR__ . '/inc/footer-menu.php';        // jb3_footer_menu(): menú principal desplegado en el footer, filtrado por país
require_once __DIR__ . '/inc/imagenes.php';           // Tamaños de imagen propios (400/800/1200/1920/2560) + copias en WebP
require_once __DIR__ . '/inc/sliders.php';            // jb3_enqueue_swiper(): loader reutilizable para cualquier slider
require_once __DIR__ . '/inc/galeria-slider.php';     // jb3_render_galeria_slider(): carrusel compartido (bloque "Galería con Slider" + módulo de Contenido)
require_once __DIR__ . '/inc/home-slider.php';        // [home_slider]: repeater ACF "HOME HEADER SLIDER"
require_once __DIR__ . '/inc/backgrounds.php';        // Fondo por página (ACF) + Options Page "Ajustes del sitio"
require_once __DIR__ . '/inc/hero.php';               // jb3_hero_image(): hero de Series/Películas/Prod. Originales
require_once __DIR__ . '/inc/admin-columns.php';      // Columna de miniatura en los listados de admin
require_once __DIR__ . '/inc/subnav.php';             // [jb3_subnav] / [jb3_subnav_anchor]: motor de navegación dinámica
require_once __DIR__ . '/inc/grids.php';              // jb3_grid_posters() / jb3_grid_thumbs(): grillas reutilizables
require_once __DIR__ . '/inc/tarjeta.php';            // jb3_render_tarjeta(): ítem imagen+título+texto+link/modal/video (bloque "Tarjeta" + módulo de Contenido)
require_once __DIR__ . '/inc/modulo.php';             // [jb3_modulo]: consulta canal+país+tipo, título/bajada/grilla + subnav
require_once __DIR__ . '/inc/contenido-pelicula.php'; // jb3_render_contenido_pelicula(): Flexible Content para Películas/Series
require_once __DIR__ . '/inc/contenido-original.php'; // jb3_render_contenido_original(): Flexible Content para Prod. Originales
require_once __DIR__ . '/inc/canales-slider.php';     // [jb3_canales_slider]: "Nuestras Marcas", slider de canales del país
require_once __DIR__ . '/inc/canal-redes.php';        // jb3_canal_redes_sociales_html(): íconos de redes del canal (campo ACF del término)
require_once __DIR__ . '/inc/relacionados.php';       // jb3_relacionados_auto(): módulos de contenido cruzado vía ACF Relationship (+ la relación "de vuelta" por consulta)
require_once __DIR__ . '/inc/etiquetas.php';          // jb3_etiquetas_auto(): "Te puede interesar" automático — mismo canal + taxonomía "etiquetas", al azar
require_once __DIR__ . '/inc/pdf-viewer.php';         // jb3_pdf_viewer(): visor de PDF propio (PDF.js autohospedado), sin controles nativos ni de extensiones
require_once __DIR__ . '/inc/pdf-preview-admin.php';  // Genera la imagen destacada sola desde la página 1 del PDF (PDF.js en el navegador del editor)
require_once __DIR__ . '/inc/youtube-playlist.php';   // jb3_youtube_playlist_videos(): videos de una playlist (bloque "Playlist de YouTube")
require_once __DIR__ . '/inc/video-modal.php';        // Modal de video (YouTube/Vimeo/archivo propio) para [data-jb3-video]
require_once __DIR__ . '/inc/image-modal.php';        // Modal de imagen (mismo patrón que el de video) para [data-jb3-image]
require_once __DIR__ . '/inc/page-modal.php';         // Modal de página: trae el contenido de una Página real por AJAX, sin header/footer, para [data-jb3-page-modal]
require_once __DIR__ . '/inc/acf-blocks.php';         // Bloques ACF del sitio (Módulo de Contenido, Tarjeta, Grilla, Banner, etc.)
require_once __DIR__ . '/inc/analytics.php';          // Google Analytics (GA4), bloqueado por CookieYes hasta aceptar la categoría "Analytics"
