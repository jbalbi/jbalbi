<?php
defined( 'ABSPATH' ) || exit;

// ─── NAVEGACIÓN PROPIA (reemplaza generate_navigation_position()) ──────────
// GeneratePress trajo bugs recurrentes en el menú mobile (el más grave:
// "overflow:hidden" para bloquear el scroll rompía el "position:sticky" del
// header — ver mobile-menu-scroll-lock.js) por depender de su JS interno
// (menu.js + dropdown-click.js) sin control nuestro sobre esa lógica. Acá
// reemplazamos SOLO el PHP que arma el <nav>/botón hamburguesa y el JS que
// maneja el toggle — el markup y las clases son IDÉNTICOS a los que genera
// GP hoy (#site-navigation, .main-navigation, .main-nav, .menu-toggle,
// .toggled, .sfHover, .toggled-on, .sub-menu, #mobile-menu-control-wrapper),
// así que TODO el CSS de navegación en style.css sigue funcionando sin
// tocar una sola línea.
//
// El menú de WordPress en sí (Apariencia > Menús, theme_location "primary",
// con la clase "nav-cta" puesta a mano en el ítem destacado) no se toca:
// seguimos leyendo el mismo menú con wp_nav_menu(). Y como seguimos
// llamando wp_nav_menu() con container_class "main-nav", los filtros de GP
// enganchados a nivel tema padre (nav_menu_link_attributes agrega
// role="button"/aria-haspopup/aria-expanded a los ítems con hijos;
// nav_menu_item_title agrega la flechita .dropdown-menu-toggle, ya oculta
// por nuestro CSS) se siguen aplicando solos sin que tengamos que
// reimplementarlos.
function jb3_navigation() {
	?>
	<nav id="mobile-menu-control-wrapper" class="main-navigation mobile-menu-control-wrapper" aria-label="<?php esc_attr_e( 'Mobile Toggle', 'generatepress-child' ); ?>">
		<?php
		// Engancha el ícono de lupa (modal de búsqueda) que ya arma GP solo
		// con este hook — generate_add_menu_bar_items() (tema padre) agrega
		// generate_do_search_modal_trigger() acá cuando nav_search_modal
		// está en true (forzado en _init_header.php), reusando el mismo
		// data-gpmodal-trigger="gp-search" que el ícono de lupa de escritorio
		// en .site-header-icons. El CSS de #mobile-menu-control-wrapper
		// .menu-bar-item > a ya está en style.css esperando este markup.
		do_action( 'generate_inside_mobile_menu_control_wrapper' );
		?>
		<button type="button" class="menu-toggle" data-nav="site-navigation" aria-controls="primary-menu" aria-expanded="false">
			<?php generate_do_svg_icon( 'menu-bars', true ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Menú', 'generatepress-child' ); ?></span>
		</button>
	</nav>

	<nav id="site-navigation" class="main-navigation" aria-label="<?php esc_attr_e( 'Primary', 'generatepress-child' ); ?>">
		<div class="inside-navigation">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => 'div',
					'container_class' => 'main-nav',
					'menu_id'        => 'primary-menu',
					'menu_class'     => '',
					'fallback_cb'    => false,
				)
			);
			?>
		</div>
	</nav>
	<?php
}

// ─── SCRIPT PROPIO + BAJA DE LOS DE GP ──────────────────────────────────────
// Prioridad 20: generate_scripts() (donde GP encola generate-menu y
// generate-dropdown-click) está enganchado en wp_enqueue_scripts con
// prioridad default (10) — hay que correr después para poder sacarlos.
// Si quedaran los dos scripts de GP activos junto al nuestro, se pisarían
// los toggles entre sí (mismo click disparando dos handlers que hacen
// classList.toggle => neto no-op, o conflictos de stopPropagation).
add_action( 'wp_enqueue_scripts', 'jb3_enqueue_navigation_script', 20 );
function jb3_enqueue_navigation_script() {
	wp_dequeue_script( 'generate-menu' );
	wp_dequeue_script( 'generate-dropdown-click' );

	$path = get_stylesheet_directory() . '/assets/js/nav.js';
	wp_enqueue_script(
		'jb3-nav',
		get_stylesheet_directory_uri() . '/assets/js/nav.js',
		[],
		file_exists( $path ) ? filemtime( $path ) : false,
		true
	);
}
