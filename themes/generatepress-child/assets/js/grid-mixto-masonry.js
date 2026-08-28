/**
 * "Masonry" real para .jb3-grid-mixto (_init_grids.php / style.css).
 *
 * Segundo intento (2026-08-28) — el primero usaba CSS Grid con filas finas
 * + grid-row-end:span calculado por JS: se veía bien la mayoría de las
 * veces, pero cuando dos columnas de la misma fila terminaban a alturas
 * ligeramente distintas (ej. un título de 2 líneas vs uno de 1 línea), el
 * algoritmo de auto-posicionamiento de CSS Grid mandaba el siguiente item
 * a la primera columna con hueco libre — no necesariamente la que le
 * tocaba en orden — y el resultado se veía "desalineado, en cualquier
 * lado". Reportado en vivo.
 *
 * Este script hace el masonry a mano (mismo algoritmo que usan librerías
 * tipo Masonry.js, sin depender de ninguna): cada item, en orden, se
 * coloca con position:absolute en la columna que en ESE momento tiene
 * menos altura acumulada, pegado arriba de esa columna — así el
 * posicionamiento es 100% determinístico y siempre alineado a su columna,
 * sin dejar huecos ni "escalones".
 *
 * Corre en la carga inicial, en resize (debounce) y cuando cambian los
 * hijos de una .jb3-grid-mixto (MutationObserver en <body>) — así cubre
 * tanto search.php (HTML ya armado al cargar la página) como el resultado
 * que inserta [jb3_buscador_ia] por AJAX, sin que el plugin necesite
 * llamar a nada de acá.
 */
( function () {
	function layout( grid ) {
		var cs    = getComputedStyle( grid );
		var cols  = parseInt( cs.getPropertyValue( '--jb3-mixto-cols' ), 10 ) || 4;
		var gap   = parseFloat( cs.getPropertyValue( '--jb3-mixto-gap' ) ) || 40;
		var ancho = grid.clientWidth;
		if ( ! ancho || cols < 1 ) {
			return;
		}
		var colAncho    = ( ancho - gap * ( cols - 1 ) ) / cols;
		var colAlturas  = new Array( cols ).fill( 0 );
		var items       = grid.querySelectorAll( '.jb3-grid-mixto__item' );

		for ( var i = 0; i < items.length; i++ ) {
			var item = items[ i ];
			item.style.width = colAncho + 'px';

			var colMenor = 0;
			for ( var c = 1; c < cols; c++ ) {
				if ( colAlturas[ c ] < colAlturas[ colMenor ] ) {
					colMenor = c;
				}
			}

			item.style.left = ( colMenor * ( colAncho + gap ) ) + 'px';
			item.style.top  = colAlturas[ colMenor ] + 'px';

			var alto = item.getBoundingClientRect().height;
			colAlturas[ colMenor ] += alto + gap;
		}

		grid.style.height = ( Math.max.apply( null, colAlturas ) - gap ) + 'px';
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
	// después de que el observer ya corrió layout() una vez (alto
	// todavía sin la imagen real) — "load" no burbujea, hace falta
	// capture:true para delegarlo.
	document.addEventListener( 'load', function ( e ) {
		if ( e.target.tagName === 'IMG' && e.target.closest( '.jb3-grid-mixto' ) ) {
			layoutAll();
		}
	}, true );
} )();
