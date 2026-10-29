<?php

// ─── ESTILOS ──────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'generatepress_child_enqueue_styles' );
function generatepress_child_enqueue_styles() {
    wp_enqueue_style( 'generatepress-style', get_template_directory_uri() . '/style.css' );
    // filemtime() en vez de wp_get_theme()->get('Version'): la versión del
    // theme es un string fijo (nunca se toca en cada cambio de CSS), así
    // que el navegador podía quedarse sirviendo una copia vieja cacheada
    // después de cualquier edición — con filemtime() el query string
    // cambia solo cada vez que se guarda el archivo, sin depender de
    // acordarse de bumpear nada a mano (mismo criterio que ya usan
    // header-account.js/header-height.js más abajo).
    wp_enqueue_style(
        'generatepress-child-style',
        get_stylesheet_uri(),
        [ 'generatepress-style' ],
        filemtime( get_stylesheet_directory() . '/style.css' )
    );
}

// Tipografía del sitio (var --tipografia-1 en style.css). Para cambiarla:
// actualizar la URL de Google Fonts acá Y el valor de --tipografia-1 en
// style.css — son los dos únicos lugares que hay que tocar.
add_action( 'wp_enqueue_scripts', 'child_enqueue_fonts' );
function child_enqueue_fonts() {
    wp_enqueue_style(
        'google-fonts-tipografia-1',
        'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap',
        [],
        null
    );
}

// El editor de bloques corre en un iframe aparte que, sin esto, NUNCA carga
// el style.css del tema — cualquier bloque que dependa de clases propias
// (.jb3-modulo, .jb3-grid-thumbs, etc., incluidos los ACF Blocks) se ve ahí
// como HTML crudo sin estilo, aunque en el sitio real se vea perfecto.
// add_theme_support('editor-styles') habilita el mecanismo; add_editor_style()
// dice qué hoja de estilos cargar (mismo archivo que ya usa el front-end).
add_action( 'after_setup_theme', function () {
    add_theme_support( 'editor-styles' );
    add_editor_style( 'style.css' );
} );

// El editor de bloques (Gutenberg) NO pasa por este filtro para su propio
// preview — carga los editor-styles por su cuenta (mecanismo de React del
// bloque, vía block_editor_settings_all), así que lo de arriba le sigue
// llegando sin tocar nada. Lo que SÍ pasa por acá es cualquier editor TinyMCE
// "clásico" de toda la vida (wp_editor()) — en este sitio eso es únicamente
// los campos WYSIWYG de ACF (Sinopsis/Información/Imagen+Texto, etc.), nunca
// el contenido principal del post. Ahí SÍ es un problema real (encontrado
// 2026-09-04): el style.css completo del sitio (3600+ líneas, fondos negros,
// texto claro) quedaba adentro del iframe de esos campos, y en filas de
// Flexible Content YA EXISTENTES al recargar la página, esa hoja de estilos
// a veces no llegaba a tiempo (carrera de carga con varias instancias de
// TinyMCE en la misma pantalla) — el campo quedaba con el fondo blanco por
// defecto de TinyMCE pero el texto en el color claro del sitio, invisible
// (blanco sobre blanco), aunque el contenido seguía intacto en la pestaña
// "Texto" (esa es un textarea plano, no le llega ningún CSS).
//
// Sacarle el content_css a CUALQUIER instancia (sin condicionar por
// $editor_id: ACF arma su editor 100% por JS, sin pasar por wp_editor() del
// lado PHP para cada campo puntual — no hay garantía de que su plantilla
// interna use un $editor_id distinguible acá, así que condicionar por id es
// arriesgar que el fix no alcance a todos los campos) resuelve esto de raíz
// para todo TinyMCE clásico del admin (ACF, widgets de texto, bio de
// usuario, etc.) — quedan con los colores default de TinyMCE, siempre
// legibles, sin depender de ninguna carrera de carga. Cero impacto en el
// editor de bloques real (Gutenberg no usa este filtro para su propio
// preview, ver el comentario de arriba).
add_filter( 'tiny_mce_before_init', function ( $mce_init ) {
    unset( $mce_init['content_css'] );
    return $mce_init;
} );

// ─── COMPATIBILIDAD CON TÚNELES Y PROXIES ─────────────────────────────────────

add_filter( 'script_loader_src', 'make_relative_url' );
add_filter( 'style_loader_src',  'make_relative_url' );
function make_relative_url( $url ) {
    // Solo relativizar URLs del propio sitio. wp_make_link_relative() le saca
    // el esquema+host a CUALQUIER url que reciba — para un asset de terceros
    // (CDN de Swiper, Google Fonts, etc.) eso deja la ruta pidiéndose contra
    // el dominio propio en vez del original, y rompe silenciosamente ese
    // asset (404). Antes de relativizar, comprobamos que sea del mismo host.
    $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
    $url_host  = wp_parse_url( $url, PHP_URL_HOST );
    if ( $url_host && $url_host !== $site_host ) {
        return $url;
    }
    return wp_make_link_relative( $url );
}

// ─── EDITOR: QUITAR PANEL "FORMATO" ───────────────────────────────────────────

add_action( 'after_setup_theme', function () {
    remove_theme_support( 'post-formats' );
}, 20 );

// ─── COMENTARIOS: DESHABILITAR COMPLETAMENTE ──────────────────────────────────

add_action( 'admin_menu', function () {
    remove_menu_page( 'edit-comments.php' );
} );

add_action( 'init', function () {
    foreach ( get_post_types() as $post_type ) {
        if ( post_type_supports( $post_type, 'comments' ) ) {
            remove_post_type_support( $post_type, 'comments' );
            remove_post_type_support( $post_type, 'trackbacks' );
        }
    }
}, 100 );

add_action( 'admin_init', function () {
    global $pagenow;
    if ( $pagenow === 'edit-comments.php' ) {
        wp_safe_redirect( admin_url() );
        exit;
    }
} );

add_filter( 'comments_open',      '__return_false', 20 );
add_filter( 'pings_open',         '__return_false', 20 );
add_filter( 'get_comments_number', '__return_zero',  20 );
add_filter( 'xmlrpc_enabled',     '__return_false' );

add_action( 'wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu( 'comments' );
} );

add_action( 'template_redirect', function () {
    if ( is_comment_feed() ) {
        wp_safe_redirect( home_url( '/' ), 301 );
        exit;
    }
} );

add_filter( 'bloginfo_url', function ( $output, $property ) {
    return $property === 'comments_rss2_url' ? '' : $output;
}, 10, 2 );

remove_action( 'wp_head', 'feed_links_extra', 3 );

add_filter( 'wp_headers', function ( $headers ) {
    unset( $headers['X-Pingback'] );
    return $headers;
} );

// ─── SEGURIDAD BÁSICA (revisión pre-producción 2026-10-01) ────────────────────

// XML-RPC: "xmlrpc_enabled" de arriba solo apaga los métodos con login —
// xmlrpc.php seguía respondiendo 80 métodos (pingbacks incluidos, usados
// para ataques). Sin métodos, no queda nada que llamar. El sitio no usa
// XML-RPC (ni app móvil de WordPress ni Jetpack).
add_filter( 'xmlrpc_methods', '__return_empty_array' );

// Nombres de usuario: /wp-json/wp/v2/users y /?author=1 → /author/jbalbi/
// le regalaban a cualquiera el usuario del administrador (la mitad de un
// ataque de fuerza bruta al login). Solo para visitantes sin sesión: el
// editor de bloques necesita la lista de usuarios estando logueado.
add_filter( 'rest_endpoints', function ( $endpoints ) {
    if ( ! is_user_logged_in() ) {
        unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    }
    return $endpoints;
} );
add_action( 'template_redirect', function () {
    if ( is_author() || isset( $_GET['author'] ) ) {
        wp_safe_redirect( home_url( '/' ), 301 );
        exit;
    }
}, 1 ); // Prioridad 1: antes de redirect_canonical (10), que si no manda /?author=1 a /author/jbalbi/ y ya mostró el nombre.

// (Auditoría 2026-10-29: mismas correcciones que el tema JB3, ver
// themes/jb3/inc/seguridad.php — para poder alternar entre los dos temas.)
// Cabeceras de seguridad:
//   - X-Frame-Options: el sitio no se puede meter en un <iframe> de otro
//     dominio (clickjacking).
//   - X-Content-Type-Options: el navegador no "adivina" tipos de archivo.
//   - Referrer-Policy: al salir a otro sitio no viaja la URL completa.
//   - Permissions-Policy: ninguna página (ni un script de terceros cargado
//     en ella) puede pedir cámara, micrófono, ubicación, pagos ni USB. No
//     toca autoplay/fullscreen/picture-in-picture (los usan los embeds de
//     YouTube/Vimeo). Si un sitio necesita alguno, cambiarlo con el filtro
//     jb3_permissions_policy desde el hijo.
//   - Strict-Transport-Security (HSTS), solo si la página ya llegó por
//     HTTPS: el navegador recuerda por 1 año que este dominio va SIEMPRE por
//     HTTPS (corta ataques que fuerzan http://). Sin includeSubDomains a
//     propósito: no sabemos qué otros subdominios tiene el dominio de cada
//     sitio. OJO: una vez enviado, volver a http:// no es posible hasta que
//     venza; el sitio tiene que tener HTTPS de forma permanente.
// Función con nombre: se puede reemplazar con remove_action( 'send_headers', 'jb3_cabeceras_seguridad' ).
function jb3_cabeceras_seguridad() {
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: ' . apply_filters( 'jb3_permissions_policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()' ) );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000' );
	}
}
add_action( 'send_headers', 'jb3_cabeceras_seguridad' );

// Versión de WordPress oculta: le ahorra a un atacante saber de antemano
// qué fallas conocidas probar. Se saca el <meta name="generator"> (y su
// equivalente en los feeds), y en los CSS/JS del core el "?ver=7.1.4" se
// reemplaza por un código derivado de la versión + las claves del sitio:
// sigue cambiando con cada actualización (el navegador baja los archivos
// nuevos), pero no dice qué versión es.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

function jb3_ocultar_version_wp( $src ) {
	static $codigo = null;
	$version = get_bloginfo( 'version' );
	if ( $src && false !== strpos( $src, 'ver=' . $version ) ) {
		if ( null === $codigo ) {
			$codigo = substr( wp_hash( 'jb3-ver-' . $version ), 0, 8 );
		}
		$src = add_query_arg( 'ver', $codigo, $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'jb3_ocultar_version_wp', 9999 );
add_filter( 'script_loader_src', 'jb3_ocultar_version_wp', 9999 );

// Contenido protegido con contraseña (Visibilidad > Protegida con
// contraseña): WordPress solo protege lo que pasa por the_content(); todo lo
// que una plantilla imprime por su cuenta (campos ACF, bloques propios,
// relacionados, PDFs) se mostraba igual sin pedirla. Mientras falte la
// contraseña, se usa password.php (título + formulario, nada más) en vez de
// la plantilla normal — cubre cualquier tipo de contenido, presente o
// futuro, sin depender de que cada plantilla se acuerde de chequearlo.
add_filter( 'template_include', function ( $template ) {
	if ( is_singular() && post_password_required() ) {
		$password = locate_template( 'password.php' );
		if ( $password ) {
			return $password;
		}
	}
	return $template;
}, 99 );

// ─── ACF LOCAL JSON ───────────────────────────────────────────────────────────

// La DEFINICIÓN de los grupos de campos ACF (no los valores cargados) vive
// en acf-json/ dentro de este tema y va a git: si se pierde la base, los
// campos se recuperan desde ahí (ACF > Grupos de campos > "Sincronización
// disponible" > Sincronizar). ACF ya usa esta carpeta por defecto; se deja
// explícito para que no dependa de ese default ni pase desapercibido.
// Cada vez que se guarda un grupo en el admin, ACF actualiza su .json solo
// — hay que commitear esos cambios como cualquier otro archivo del tema.
add_filter( 'acf/settings/save_json', fn() => get_stylesheet_directory() . '/acf-json' );

// ─── INCLUDES ─────────────────────────────────────────────────────────────────

require_once __DIR__ . '/_init_taxonomias.php';         // Taxonomías personalizadas
require_once __DIR__ . '/_init_custom_post_types.php';  // Custom Post Types
require_once __DIR__ . '/_init_menu.php';               // Breakpoint del menú hamburguesa
require_once __DIR__ . '/_init_navigation.php';         // jb3_navigation(): <nav> + hamburguesa propios (reemplaza generate_navigation_position())
require_once __DIR__ . '/_init_footer_menu.php';        // jb3_footer_menu(): menú principal desplegado en el footer, filtrado por país
require_once __DIR__ . '/_init_header.php';             // Header custom: preheader, logo+nav, buscador/cuenta/LinkedIn
require_once __DIR__ . '/_init_layout.php';             // Layout forzado: sin sidebar, sin footer widgets, full-width
require_once __DIR__ . '/_init_imagenes.php';           // Tamaños de imagen propios (400/800/1200/1920/2560) + copias en WebP
require_once __DIR__ . '/_init_sliders.php';             // jb3_enqueue_swiper(): loader reutilizable para cualquier slider
require_once __DIR__ . '/_init_galeria_slider.php';      // jb3_render_galeria_slider(): carrusel compartido (bloque "Galería con Slider" + módulo de Contenido)
require_once __DIR__ . '/_init_home_slider.php';        // [home_slider]: repeater ACF "HOME HEADER SLIDER"
require_once __DIR__ . '/_init_backgrounds.php';        // Fondo por página (ACF) + Options Page "Ajustes del sitio"
require_once __DIR__ . '/_init_hero.php';               // jb3_hero_image(): hero de Series/Películas/Prod. Originales
require_once __DIR__ . '/_init_admin_columns.php';      // Columna de miniatura en los listados de admin
require_once __DIR__ . '/_init_subnav.php';             // [jb3_subnav] / [jb3_subnav_anchor]: motor de navegación dinámica
require_once __DIR__ . '/_init_grids.php';              // jb3_grid_posters() / jb3_grid_thumbs(): grillas reutilizables
require_once __DIR__ . '/_init_tarjeta.php';            // jb3_render_tarjeta(): ítem imagen+título+texto+link/modal/video (bloque "Tarjeta" + módulo de Contenido)
require_once __DIR__ . '/_init_modulo.php';             // [jb3_modulo]: consulta canal+país+tipo, título/bajada/grilla + subnav
require_once __DIR__ . '/_init_contenido_pelicula.php'; // jb3_render_contenido_pelicula(): Flexible Content para Películas/Series
require_once __DIR__ . '/_init_contenido_original.php'; // jb3_render_contenido_original(): Flexible Content para Prod. Originales
require_once __DIR__ . '/_init_canales_slider.php';     // [jb3_canales_slider]: "Nuestras Marcas", slider de canales del país
require_once __DIR__ . '/_init_canal_redes.php';        // jb3_canal_redes_sociales_html(): íconos de redes del canal (campo ACF del término)
require_once __DIR__ . '/_init_relacionados.php';       // jb3_relacionados_auto(): módulos de contenido cruzado vía ACF Relationship (+ la relación "de vuelta" por consulta)
require_once __DIR__ . '/_init_etiquetas.php';          // jb3_etiquetas_auto(): "Te puede interesar" automático — mismo canal + taxonomía "etiquetas", al azar
require_once __DIR__ . '/_init_pdf_viewer.php';          // jb3_pdf_viewer(): visor de PDF propio (PDF.js autohospedado), sin controles nativos ni de extensiones
require_once __DIR__ . '/_init_pdf_preview_admin.php';   // Genera la imagen destacada sola desde la página 1 del PDF (PDF.js en el navegador del editor)
require_once __DIR__ . '/_init_youtube_playlist.php';   // jb3_youtube_playlist_videos(): videos de una playlist (bloque "Playlist de YouTube")
require_once __DIR__ . '/_init_video_modal.php';        // Modal de video (YouTube/Vimeo/archivo propio) para [data-jb3-video]
require_once __DIR__ . '/_init_image_modal.php';        // Modal de imagen (mismo patrón que el de video) para [data-jb3-image]
require_once __DIR__ . '/_init_page_modal.php';          // Modal de página: trae el contenido de una Página real por AJAX, sin header/footer, para [data-jb3-page-modal]
require_once __DIR__ . '/_init_acf_blocks.php';          // Bloque ACF "Módulo de Contenido" (jb3/modulo-contenido): reemplaza a [jb3_modulo] escrito a mano
require_once __DIR__ . '/_init_analytics.php';           // Google Analytics (GA4), bloqueado por CookieYes hasta aceptar la categoría "Analytics"
