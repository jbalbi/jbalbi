<?php
defined( 'ABSPATH' ) || exit;

// ─── SUBMENÚS: abrir con click, no con hover / BUSCADOR MODAL: activado ─────
// "nav_dropdown_type" y "nav_search_modal" viven en Personalizar >
// Navegación principal. En vez de depender de esos ajustes en el
// Customizer, los forzamos acá por código con el filtro nativo de WP para
// la opción completa — generate_get_option() lee get_option('generate_settings'),
// así que cualquier valor que pisemos ahí gana sin tocar la base de datos.
//
// nav_search_modal en particular es lo que activa TODO el buscador modal
// de GP: sin esto en true, generate_do_search_modal() ni siquiera imprime
// el HTML del modal (#gp-search) en el footer, y generate_enable_search_modal()
// tampoco encola el script que lo hace funcionar — el ícono de lupa que
// armamos en jb3_custom_header() con data-gpmodal-trigger="gp-search" no
// tiene a qué abrir ni JS que reaccione al click.
add_filter( 'option_generate_settings', function ( $settings ) {
    $settings['nav_dropdown_type'] = 'click';
    $settings['nav_search_modal']  = true;
    return $settings;
} );

// ─── PREHEADER Y HEADER: enganche ───────────────────────────────────────────
// El functions.php del child theme carga ANTES que el del theme padre
// (GeneratePress), así que si hacemos remove_action acá directo, la acción
// original todavía no existe y no se quita nada — quedan las dos funciones
// enganchadas y el header se duplica. Por eso esperamos a "after_setup_theme",
// que se dispara recién cuando ambos functions.php ya cargaron.
add_action( 'after_setup_theme', function () {
    // El top-bar nativo de GP se saca del todo: el preheader ahora se
    // renderiza DENTRO de jb3_custom_header() (ver más abajo), como
    // primer hijo de <header>, para que quede en el mismo elemento
    // sticky que el resto del header y no haya que calcular alturas
    // a mano para que peguen uno debajo del otro al hacer scroll.
    remove_action( 'generate_before_header', 'generate_top_bar', 5 );

    remove_action( 'generate_header', 'generate_construct_header' );
    add_action( 'generate_header', 'jb3_custom_header' );
} );

// ─── PREHEADER (TOP BAR) ───────────────────────────────────────────────────
// Franja fija con el logo de Sony Pictures a la izquierda y el de Sony a la
// derecha. Las imágenes hoy están hotlinkeadas desde conectados.sonypictures.com
// (URLs que pasó el cliente) — conviene subirlas a la Biblioteca de medios
// propia más adelante y reemplazar acá los src, para no depender de un
// dominio de terceros.
function jb3_preheader() {
    ?>
    <div <?php generate_do_attr( 'top-bar' ); ?>>
        <div <?php generate_do_attr( 'inside-top-bar' ); ?>>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="jb3-preheader__logo jb3-preheader__logo--left">
                <img src="https://conectados.sonypictures.com/wp-content/uploads/2024/02/preheader_logo-sonypictures.png" alt="Sony Pictures" />
            </a>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="jb3-preheader__logo jb3-preheader__logo--right">
                <img src="https://conectados.sonypictures.com/wp-content/uploads/2024/02/preheader_logo-sony.png" alt="Sony" />
            </a>
        </div>
    </div>
    <?php
}

// ─── HEADER PRINCIPAL ───────────────────────────────────────────────────────
// Reemplazamos el header de GeneratePress por uno propio: logo + nav pegados
// a la izquierda, buscador/cuenta/LinkedIn a la derecha. Reutilizamos las
// funciones y hooks nativos de GP (logo, título, nav, widget "Header") para
// no perder accesibilidad ni el JS del menú móvil (toggle, ARIA, dropdown).
function jb3_custom_header() {
    ?>
    <header <?php generate_do_attr( 'header' ); ?>>
        <?php jb3_preheader(); ?>
        <div <?php generate_do_attr( 'inside-header' ); ?>>
            <?php
            // Logo + título/tagline (maneja sola el wrapper .site-branding-container
            // si hay logo de imagen, igual que el header nativo de GP).
            do_action( 'generate_before_header_content' );
            ?>

            <?php generate_navigation_position(); // <nav> principal + control del hamburguesa ?>

            <div class="site-header-icons">
                <?php generate_construct_header_widget(); // LinkedIn, desde el widget "Header" ?>

                <?php if ( is_user_logged_in() ) : ?>
                    <div class="site-header-account">
                        <button type="button" class="site-header-account__toggle" aria-haspopup="true" aria-expanded="false">
                            <span class="screen-reader-text"><?php esc_html_e( 'Mi cuenta', 'generatepress-child' ); ?></span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4 20c0-4 3.582-7 8-7s8 3 8 7"></path>
                            </svg>
                        </button>
                        <ul class="site-header-account__menu">
                            <li><a href="/perfil/"><?php esc_html_e( 'TUS DATOS', 'generatepress-child' ); ?></a></li>
                            <li><a href="/logout/"><?php esc_html_e( 'CERRAR SESIÓN', 'generatepress-child' ); ?></a></li>
                        </ul>
                    </div>
                <?php else : ?>
                    <a href="/registro/" class="site-header-icons__auth-link"><?php esc_html_e( 'Regístrate', 'generatepress-child' ); ?></a>
                    <a href="/login/" class="site-header-icons__auth-link"><?php esc_html_e( 'Ingresa', 'generatepress-child' ); ?></a>
                <?php endif; ?>

                <a href="#" role="button" aria-label="<?php esc_attr_e( 'Buscar', 'generatepress-child' ); ?>" aria-haspopup="dialog" aria-controls="gp-search" data-gpmodal-trigger="gp-search" class="site-header-icons__search">
                    <?php echo generate_get_svg_icon( 'search', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </a>
            </div>
        </div>
    </header>
    <?php
}

// ─── DROPDOWN DE CUENTA ─────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'jb3_enqueue_header_account_script' );
function jb3_enqueue_header_account_script() {
    $path = get_stylesheet_directory() . '/assets/js/header-account.js';
    wp_enqueue_script(
        'jb3-header-account',
        get_stylesheet_directory_uri() . '/assets/js/header-account.js',
        [],
        file_exists( $path ) ? filemtime( $path ) : false,
        true
    );
}
