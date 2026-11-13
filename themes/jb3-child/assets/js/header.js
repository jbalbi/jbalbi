/**
 * Scripts del header, en un solo archivo (auditoría de rendimiento
 * 2026-10-29: antes eran 5 archivos chicos, 5 pedidos al servidor en cada
 * página). Cada sección es la misma función autocontenida que antes vivía
 * en su propio archivo, en el mismo orden en que se cargaban.
 */

// ═══════════════════════════════════════════════════════════════════════════
// Menú: hamburguesa y submenús (antes assets/js/nav.js)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Navegación del header (header.php): toggle del menú hamburguesa + submenús
 * por click.
 *
 * Clases que maneja (las usa main.css):
 *   - #site-navigation.toggled  → panel mobile abierto;
 *   - <html>.mobile-menu-open   → overlay + bloqueo de scroll (ver
 *                                 mobile-menu-scroll-lock.js);
 *   - li.sfHover + .sub-menu.toggled-on → submenú abierto.
 */
( function () {
	var html = document.documentElement;
	var nav = document.getElementById( 'site-navigation' );
	var toggleButton = document.querySelector( '.jb3-menu-controles .menu-toggle' );
	var menuList = nav ? nav.querySelector( 'ul' ) : null;

	if ( ! nav || ! toggleButton ) {
		return;
	}

	// ─── Accesibilidad: cada ítem padre controla su submenú ─────────────────
	nav.querySelectorAll( '.sub-menu' ).forEach( function ( subMenu ) {
		var parentLi = subMenu.closest( 'li' );
		var parentLink = parentLi ? parentLi.querySelector( ':scope > a' ) : null;
		if ( ! parentLink ) {
			return;
		}

		if ( ! subMenu.id ) {
			subMenu.id = ( parentLi.id || 'menu-item-' + Math.floor( Math.random() * 100000 ) ) + '-sub-menu';
		}

		parentLink.setAttribute( 'role', 'button' );
		parentLink.setAttribute( 'aria-haspopup', 'true' );
		parentLink.setAttribute( 'aria-expanded', 'false' );
		parentLink.setAttribute( 'aria-controls', subMenu.id );
	} );

	// ─── Submenús: abrir/cerrar con click en el link del ítem padre ─────────
	// Clickear un ítem con hijos nunca navega, solo abre/cierra su submenú.
	var closeAllSubmenus = function ( exceptLi ) {
		nav.querySelectorAll( 'li.sfHover' ).forEach( function ( li ) {
			if ( exceptLi && ( li === exceptLi || li.contains( exceptLi ) ) ) {
				return;
			}

			li.classList.remove( 'sfHover' );

			var link = li.querySelector( ':scope > a' );
			if ( link ) {
				link.setAttribute( 'aria-expanded', 'false' );
			}

			var subMenu = li.querySelector( ':scope > .sub-menu' );
			if ( subMenu ) {
				subMenu.classList.remove( 'toggled-on' );
			}
		} );
	};

	nav.querySelectorAll( '.menu-item-has-children > a' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			e.stopPropagation();

			var li = link.closest( 'li' );
			var subMenu = li.querySelector( ':scope > .sub-menu' );
			var opening = ! li.classList.contains( 'sfHover' );

			closeAllSubmenus( li );

			li.classList.toggle( 'sfHover', opening );
			link.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );

			if ( subMenu ) {
				subMenu.classList.toggle( 'toggled-on', opening );
			}
		} );
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest( '.sfHover' ) ) {
			closeAllSubmenus();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			closeAllSubmenus();
		}
	} );

	// ─── Toggle del menú hamburguesa (mobile) ───────────────────────────────
	var setMobileMenuOpen = function ( open ) {
		// Scroll actual ANTES de agregar la clase: con "mobile-menu-open" la
		// página pasa a position:fixed y desde ahí window.scrollY vale 0 (ver
		// "Bloqueo del scroll" más abajo, que lo usa para restaurarlo al cerrar).
		if ( open && ! html.classList.contains( 'mobile-menu-open' ) ) {
			html.setAttribute( 'data-jb3-scroll', String( window.scrollY || window.pageYOffset || 0 ) );
		}
		nav.classList.toggle( 'toggled', open );
		html.classList.toggle( 'mobile-menu-open', open );
		toggleButton.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

		if ( menuList ) {
			menuList.setAttribute( 'aria-hidden', open ? 'false' : 'true' );
		}

		if ( ! open ) {
			closeAllSubmenus();
		}
	};

	toggleButton.addEventListener( 'click', function () {
		setMobileMenuOpen( ! nav.classList.contains( 'toggled' ) );
		// Sin esto el botón queda enfocado después del click y el color de
		// :focus-visible se quedaba "pegado" en vez de volver a blanco al
		// cerrar (algunos navegadores sí disparan :focus-visible en click de
		// mouse sobre un <button>, no solo con teclado).
		toggleButton.blur();
	} );

	// Si al agrandar la ventana el botón hamburguesa deja de estar visible
	// (pasamos a desktop) con el menú mobile todavía abierto, lo cerramos
	// para que no quede "fantasma abierto" al volver a achicar.
	var checkStillMobile = function () {
		if ( nav.classList.contains( 'toggled' ) && toggleButton.offsetParent === null ) {
			setMobileMenuOpen( false );
		}
	};
	window.addEventListener( 'resize', checkStillMobile );
	window.addEventListener( 'orientationchange', checkStillMobile );
} )();

// ═══════════════════════════════════════════════════════════════════════════
// Bloqueo del scroll con el menú mobile abierto (antes assets/js/mobile-menu-scroll-lock.js)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Bloquea el scroll de fondo mientras el menú mobile está abierto, sin usar
 * "overflow:hidden" (rompe el position:sticky del header — ver comentario
 * largo en main.css, sección "OVERLAY DEL MENÚ MÓVIL").
 *
 * Técnica estándar: al abrir, se guarda el scroll actual y la página se
 * "congela" con position:fixed + top negativo igual a ese valor — visualmente
 * queda exactamente donde estaba, pero ya no puede scrollear de fondo. Al
 * cerrar, se saca el fixed y se restaura el scroll a mano (si no, el
 * navegador saltaría solo a scroll=0).
 *
 * Se engancha con un MutationObserver sobre la clase del <html> en vez de
 * escuchar el click del botón hamburguesa directamente: nav.js (jb3-nav) es
 * quien agrega/saca esa clase "mobile-menu-open", así que reaccionar al
 * cambio de clase funciona pase lo que pase del lado de nav.js.
 */
( function () {
	var html = document.documentElement;
	var scrollY = 0;
	var wasOpen = false;

	function sync() {
		var isOpen = html.classList.contains( 'mobile-menu-open' );
		if ( isOpen === wasOpen ) {
			return;
		}
		wasOpen = isOpen;

		if ( isOpen ) {
			// El MutationObserver corre DESPUÉS de que se agregó la clase, y
			// con la página ya en position:fixed window.scrollY da 0: por eso
			// al cerrar el menú se volvía siempre arriba de todo (2026-11-04).
			// El valor lo guarda setMobileMenuOpen() justo antes; window.scrollY
			// queda solo de respaldo.
			var guardado = parseFloat( html.getAttribute( 'data-jb3-scroll' ) );
			scrollY = isNaN( guardado ) ? ( window.scrollY || window.pageYOffset || 0 ) : guardado;
			html.removeAttribute( 'data-jb3-scroll' );
			html.style.top = ( -scrollY ) + 'px';
		} else {
			html.style.top = '';
			window.scrollTo( 0, scrollY );
		}
	}

	new MutationObserver( sync ).observe( html, {
		attributes: true,
		attributeFilter: [ 'class' ],
	} );
} )();

// ═══════════════════════════════════════════════════════════════════════════
// Buscador del header (antes assets/js/buscador.js)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Buscador del header: <dialog id="jb3-buscador"> (footer.php), abierto por
 * cualquier botón [data-jb3-buscador]. showModal() ya trae el foco atrapado
 * adentro y el cierre con Escape; acá solo falta abrirlo y cerrarlo al
 * hacer click fuera del formulario.
 */
( function () {
	var dialogo = document.getElementById( 'jb3-buscador' );
	if ( ! dialogo || ! dialogo.showModal ) {
		return;
	}

	var campo = dialogo.querySelector( '.jb3-buscador__campo' );

	document.querySelectorAll( '[data-jb3-buscador]' ).forEach( function ( boton ) {
		boton.addEventListener( 'click', function () {
			dialogo.showModal();
			if ( campo ) {
				campo.focus();
			}
		} );
	} );

	// El <dialog> ocupa toda la pantalla (ver .jb3-buscador en main.css): un
	// click que cae en el dialogo mismo, no en el formulario, es "afuera".
	dialogo.addEventListener( 'click', function ( e ) {
		if ( e.target === dialogo ) {
			dialogo.close();
		}
	} );
} )();

// ═══════════════════════════════════════════════════════════════════════════
// Menú de cuenta (antes assets/js/header-account.js)
// ═══════════════════════════════════════════════════════════════════════════

( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest( '.site-header-account__toggle' );

		document.querySelectorAll( '.site-header-account.is-open' ).forEach( function ( wrapper ) {
			if ( ! toggle || wrapper !== toggle.closest( '.site-header-account' ) ) {
				wrapper.classList.remove( 'is-open' );
				wrapper.querySelector( '.site-header-account__toggle' ).setAttribute( 'aria-expanded', 'false' );
			}
		} );

		if ( ! toggle ) {
			return;
		}

		var wrapper = toggle.closest( '.site-header-account' );
		var isOpen = wrapper.classList.toggle( 'is-open' );
		toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) {
			return;
		}

		document.querySelectorAll( '.site-header-account.is-open' ).forEach( function ( wrapper ) {
			wrapper.classList.remove( 'is-open' );
			wrapper.querySelector( '.site-header-account__toggle' ).setAttribute( 'aria-expanded', 'false' );
		} );
	} );
}() );

// ═══════════════════════════════════════════════════════════════════════════
// Alto real del header (antes assets/js/header-height.js)
// ═══════════════════════════════════════════════════════════════════════════

// Header fijo arriba (.site-header, ver style.css):
// 1) --header-height = alto real del header (lo usan el hero y el rotador
//    de la Home, que pasan por detrás del header).
// 2) --jb3-header-fondo (0 a 1) = cuánto se ve el degradé oscuro detrás del
//    header (ver .site-header::before en style.css), para que el contenido
//    que pasa por debajo no se mezcle con el menú. Crece de a poco con el
//    scroll: 0 arriba de todo, completo recién a los SCROLL_FONDO px.
( function () {
	function setHeaderHeight() {
		var header = document.querySelector( '.site-header' );
		// Con el menú mobile abierto el header mide todo el panel: medirlo
		// ahí (al girar el celular, por ejemplo) arruinaría el alto del hero
		// y el del scroll del menú (main.css, ".main-navigation.toggled").
		if ( ! header || document.documentElement.classList.contains( 'mobile-menu-open' ) ) {
			return;
		}
		document.documentElement.style.setProperty( '--header-height', header.offsetHeight + 'px' );
	}

	var SCROLL_FONDO = 200;
	var pendiente     = false;

	function marcarScroll() {
		pendiente = false;
		var nivel = Math.min( Math.max( window.scrollY / SCROLL_FONDO, 0 ), 1 );
		document.documentElement.style.setProperty( '--jb3-header-fondo', nivel.toFixed( 3 ) );
	}

	function alScrollear() {
		if ( ! pendiente ) {
			pendiente = true;
			requestAnimationFrame( marcarScroll );
		}
	}

	window.addEventListener( 'load', setHeaderHeight );
	window.addEventListener( 'resize', setHeaderHeight );
	window.addEventListener( 'scroll', alScrollear, { passive: true } );
	document.addEventListener( 'DOMContentLoaded', marcarScroll ); // Si la página abre ya scrolleada (recarga, ancla).
} )();
