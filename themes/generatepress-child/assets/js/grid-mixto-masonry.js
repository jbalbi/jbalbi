/**
 * "Masonry" real para .jb3-grid-mixto (_init_grids.php / style.css): la
 * grilla usa CSS Grid con filas muy finas (grid-auto-rows) + align-items:
 * start (para que cada item mida su alto natural en vez de estirarse a la
 * fila) — este script mide el alto real de cada item (afiche 2/3 o
 * miniatura 3/2, título incluido) y le asigna el grid-row-end:span que le
 * corresponde. Reemplaza un intento anterior con column-count: ese ordena
 * de arriba a abajo por columna y, cuando la cantidad de items no cierra
 * pareja, deja columnas más cortas que otras con un hueco visible al lado
 * — bug real reportado 2026-08-28. Con CSS Grid + span por alto real no
 * quedan huecos: cada fila se completa con lo que entre según el alto ya
 * ocupado arriba.
 *
 * Corre en la carga inicial, en resize (debounce) y cuando cambian los
 * hijos de una .jb3-grid-mixto (MutationObserver en <body>) — así cubre
 * tanto search.php (HTML ya armado al cargar la página) como el resultado
 * que inserta [jb3_buscador_ia] por AJAX, sin que el plugin necesite
 * llamar a nada de acá: se entera solo apenas el HTML nuevo aparece en el
 * DOM.
 */
( function () {
	function layout( grid ) {
		var cs        = getComputedStyle( grid );
		var rowHeight = parseFloat( cs.getPropertyValue( 'grid-auto-rows' ) ) || 8;
		var rowGap    = parseFloat( cs.getPropertyValue( 'row-gap' ) ) || 0;
		var items     = grid.querySelectorAll( '.jb3-grid-mixto__item' );
		for ( var i = 0; i < items.length; i++ ) {
			var alto = items[ i ].getBoundingClientRect().height;
			if ( ! alto ) {
				continue;
			}
			var span = Math.ceil( ( alto + rowGap ) / ( rowHeight + rowGap ) );
			items[ i ].style.gridRowEnd = 'span ' + Math.max( span, 1 );
		}
	}

	function layoutAll() {
		var grids = document.querySelectorAll( '.jb3-grid-mixto' );
		for ( var i = 0; i < grids.length; i++ ) {
			layout( grids[ i ] );
		}
	}

	window.addEventListener( 'load', layoutAll );

	var resizeTimer;
	window.addEventListener( 'resize', function () {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( layoutAll, 150 );
	} );

	// [jb3_buscador_ia] inserta su grilla después de la carga inicial vía
	// AJAX — un solo observer en <body> alcanza para detectar cualquier
	// .jb3-grid-mixto nueva, sin acoplarse al plugin.
	var observer = new MutationObserver( layoutAll );
	observer.observe( document.body, { childList: true, subtree: true } );

	// Las imágenes de una grilla recién insertada terminan de cargar
	// después de que el observer ya corrió layout() una vez (alto todavía
	// en 0) — "load" no burbujea, hace falta capture:true para delegarlo.
	document.addEventListener( 'load', function ( e ) {
		if ( e.target.tagName === 'IMG' && e.target.closest( '.jb3-grid-mixto' ) ) {
			layoutAll();
		}
	}, true );
} )();
