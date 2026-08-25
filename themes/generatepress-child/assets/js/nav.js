/**
 * Navegación propia: toggle del menú hamburguesa + submenús por click.
 * Reemplaza menu.js + dropdown-click.js de GeneratePress (ver
 * _init_navigation.php) manteniendo exactamente las mismas clases/IDs que
 * usaba GP (#site-navigation, .main-navigation, .toggled, .sfHover,
 * .toggled-on, mobile-menu-open en <html>...) para que style.css y
 * mobile-menu-scroll-lock.js sigan funcionando sin cambios.
 */
( function () {
	var html = document.documentElement;
	var nav = document.getElementById( 'site-navigation' );
	var toggleWrapper = document.getElementById( 'mobile-menu-control-wrapper' );
	var toggleButton = toggleWrapper ? toggleWrapper.querySelector( '.menu-toggle' ) : null;
	var menuList = nav ? nav.querySelector( 'ul' ) : null;

	if ( ! nav || ! toggleButton ) {
		return;
	}

	// ─── Accesibilidad: id + aria-controls en cada submenú (paridad con el
	// wiring que hacía menu.js al cargar) ────────────────────────────────────
	var randomId = function () {
		return 'menu-item-' + Math.floor( Math.random() * 100000 );
	};

	nav.querySelectorAll( '.sub-menu' ).forEach( function ( subMenu ) {
		var parentLi = subMenu.closest( 'li' );
		if ( ! parentLi ) {
			return;
		}

		if ( ! subMenu.id ) {
			subMenu.id = ( parentLi.id || randomId() ) + '-sub-menu';
		}

		var parentLink = parentLi.querySelector( ':scope > a' );
		if ( parentLink ) {
			parentLink.setAttribute( 'aria-controls', subMenu.id );
		}
	} );

	// ─── Toggle del menú hamburguesa (mobile) ───────────────────────────────
	var setMobileMenuOpen = function ( open ) {
		nav.classList.toggle( 'toggled', open );
		toggleWrapper.classList.toggle( 'toggled', open );
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
		// :hover/:focus-visible se quedaba "pegado" en vez de volver a
		// blanco al cerrar (algunos navegadores sí disparan :focus-visible
		// en click de mouse sobre un <button>, no solo con teclado).
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

	// ─── Submenús: abrir/cerrar con click en el link del ítem padre ─────────
	// nav_dropdown_type está forzado a "click" en todo el sitio (ver
	// _init_header.php) — clickear un ítem con hijos nunca navega, solo
	// abre/cierra su submenú.
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
} )();
