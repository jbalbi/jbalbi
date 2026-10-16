<?php
defined( 'ABSPATH' ) || exit;

// ─── MENÚ DEL FOOTER: la navegación principal, desplegada ───────────────────
// Pedido 2026-10-16: en el footer, todos los ítems del menú principal
// (Apariencia > Menús, ubicación "primary") con sus submenús abiertos, en
// columnas — ej. "NUESTRAS MARCAS" y debajo cada canal. Se lee del MISMO
// menú, así que agregar/sacar un ítem ahí actualiza el footer solo.
//
// Mismo filtro por país que el menú de arriba: los ítems pasan por
// "wp_nav_menu_objects", donde jb3-geo-targeting saca los que no
// corresponden al país del visitante (jb3_geo_post_visible()). La caché de
// página guarda una copia por país, así que cada país ve su propio footer.
//
// No usa wp_nav_menu() a propósito: GeneratePress engancha filtros al menú
// "primary" (flechita de submenú, role="button" en los padres) que en el
// footer sobran. Armando el HTML acá no hay nada que deshacer.
//
// Ubicación "footer" (Apariencia > Menús > "Footer: enlaces legales"): para
// Políticas de privacidad, Términos, etc. — se suman al final, con el mismo
// estilo que los títulos del menú principal, y debajo de todo el link para
// reabrir la configuración de cookies.

add_action( 'after_setup_theme', function () {
	register_nav_menus( array( 'footer' => 'Footer: enlaces legales (Políticas, Términos…)' ) );
} );

/** Ítems del menú asignado a $ubicacion, ya filtrados por país (wp_nav_menu_objects). */
function jb3_footer_menu_items( string $ubicacion ): array {
	$ubicaciones = get_nav_menu_locations();
	if ( empty( $ubicaciones[ $ubicacion ] ) ) {
		return array();
	}

	$items = wp_get_nav_menu_items( $ubicaciones[ $ubicacion ] );
	if ( ! $items ) {
		return array();
	}

	return (array) apply_filters( 'wp_nav_menu_objects', $items, (object) array( 'theme_location' => $ubicacion ) );
}

/** Link de un ítem — o solo texto si no lleva a ningún lado (los padres "#" como "NUESTRAS MARCAS"). */
function jb3_footer_menu_link( WP_Post $item, string $clase ): string {
	$titulo = esc_html( wp_strip_all_tags( $item->title ) );
	if ( '' === $item->url || '#' === $item->url ) {
		return '<span class="' . esc_attr( $clase ) . '">' . $titulo . '</span>';
	}

	$target = '_blank' === $item->target ? ' target="_blank" rel="noopener"' : '';
	return '<a class="' . esc_attr( $clase ) . '" href="' . esc_url( $item->url ) . '"' . $target . '>' . $titulo . '</a>';
}

function jb3_footer_menu(): string {
	$items = jb3_footer_menu_items( 'primary' );
	$ids   = wp_list_pluck( $items, 'ID' );

	// Árbol de 2 niveles. Un hijo cuyo padre quedó afuera por el filtro de
	// país no se muestra suelto: si no se ve el grupo, no se ve nada de él.
	$raiz  = array();
	$hijos = array();
	foreach ( $items as $item ) {
		$padre = (int) $item->menu_item_parent;
		if ( ! $padre ) {
			$raiz[] = $item;
		} elseif ( in_array( $padre, $ids, true ) ) {
			$hijos[ $padre ][] = $item;
		}
	}

	$html = '';
	foreach ( $raiz as $item ) {
		// Título sin link ("#", ej. "NUESTRAS MARCAS") al que el filtro de país
		// le sacó todos los ítems: no se muestra vacío.
		if ( ( '' === $item->url || '#' === $item->url ) && empty( $hijos[ $item->ID ] ) ) {
			continue;
		}
		$html .= '<li class="jb3-footer-menu__grupo">' . jb3_footer_menu_link( $item, 'jb3-footer-menu__titulo' );
		if ( ! empty( $hijos[ $item->ID ] ) ) {
			$html .= '<ul class="jb3-footer-menu__sub">';
			foreach ( $hijos[ $item->ID ] as $hijo ) {
				$html .= '<li>' . jb3_footer_menu_link( $hijo, 'jb3-footer-menu__link' ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '</li>';
	}

	foreach ( jb3_footer_menu_items( 'footer' ) as $item ) {
		if ( ! (int) $item->menu_item_parent ) {
			$html .= '<li class="jb3-footer-menu__grupo">' . jb3_footer_menu_link( $item, 'jb3-footer-menu__titulo' ) . '</li>';
		}
	}

	// Último, debajo de los legales: reabrir el aviso de cookies.
	// revisitCkyConsent() la expone CookieYes (cookie-law-info) como función
	// global de JS — sin el plugin activo, el link no hace nada (no tira error).
	$html .= '<li class="jb3-footer-menu__grupo"><a class="jb3-footer-menu__titulo" href="#" onclick="if (window.revisitCkyConsent) { revisitCkyConsent(); } return false;">'
		. esc_html__( 'Configuración de cookies', 'generatepress-child' ) . '</a></li>';

	// Botón "Seguir" de LinkedIn (ver jb3_footer_linkedin_script() abajo):
	// el <script type="IN/FollowCompany"> es solo un marcador que el script
	// de LinkedIn reemplaza por el botón. Hasta que se cargue (o si no hay
	// consentimiento), se ve el link común a la página de la empresa.
	$html .= '<li class="jb3-footer-menu__grupo jb3-footer-linkedin">'
		. '<a class="jb3-footer-menu__titulo jb3-footer-linkedin__link" href="' . esc_url( 'https://www.linkedin.com/company/' . JB3_LINKEDIN_EMPRESA_ID . '/' ) . '" target="_blank" rel="noopener">LinkedIn</a>'
		. '<script type="IN/FollowCompany" data-id="' . (int) JB3_LINKEDIN_EMPRESA_ID . '" data-counter="right"></script>'
		. '</li>';

	return '<ul class="jb3-footer-menu">' . $html . '</ul>';
}

// ─── BOTÓN "SEGUIR" DE LINKEDIN, CON CONSENTIMIENTO ─────────────────────────
// Pedido 2026-10-16. El script de LinkedIn (platform.linkedin.com/in.js)
// deja cookies de seguimiento en el navegador del visitante (bcookie, lidc,
// etc.), así que se carga SOLO si aceptó la categoría "functional" en el
// aviso de CookieYes — mismo criterio que Google Analytics
// (_init_analytics.php): se lee la cookie "cookieyes-consent" y se escucha
// el evento "cookieyes_consent_update" (si acepta en ese momento, el botón
// aparece sin recargar). Sin consentimiento queda el link común a LinkedIn.
// No se usa el bloqueo de scripts de CookieYes (type="text/plain"): este
// script lleva su configuración ("lang: es_ES") como texto adentro, y el
// desbloqueo de CookieYes no la conserva.
const JB3_LINKEDIN_EMPRESA_ID = 10616796;

add_action( 'wp_footer', 'jb3_footer_linkedin_script' );
function jb3_footer_linkedin_script(): void {
	?>
	<script>
	( function () {
		var CATEGORIA = 'functional';
		var cargado   = false;

		function aceptada() {
			var match = document.cookie.match( /(?:^|; )cookieyes-consent=([^;]*)/ );
			return !! match && decodeURIComponent( match[ 1 ] ).indexOf( CATEGORIA + ':yes' ) !== -1;
		}

		function cargar() {
			var li = document.querySelector( '.jb3-footer-linkedin' );
			if ( cargado || ! li || ! aceptada() ) {
				return;
			}
			cargado = true;
			li.classList.add( 'jb3-footer-linkedin--widget' );
			var s  = document.createElement( 'script' );
			s.src  = 'https://platform.linkedin.com/in.js';
			s.text = 'lang: es_ES';
			document.body.appendChild( s );
		}

		cargar();
		document.addEventListener( 'cookieyes_consent_update', cargar );
	} )();
	</script>
	<?php
}
