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
		if ( ! header ) {
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
