/**
 * Filtro por canal del Módulo de Contenido (jb3_filtro_canal_html(),
 * inc/modulo.php). Muestra solo las tarjetas del canal elegido, sin recargar.
 * El canal queda en la dirección (?canal=axn), así el link se puede compartir
 * y abre ya filtrado.
 */
( function () {
	'use strict';

	var PARAM = 'canal';

	function iniciar( fila ) {
		var modulo  = fila.closest( '.jb3-modulo' ) || fila.parentNode;
		var botones = Array.prototype.slice.call( fila.querySelectorAll( '[data-canal]' ) );
		var items   = Array.prototype.slice.call( modulo.querySelectorAll( '[data-jb3-canales]' ) );

		function aplicar( canal, actualizarUrl ) {
			var existe = botones.some( function ( b ) { return b.getAttribute( 'data-canal' ) === canal; } );
			if ( ! existe ) {
				canal = '';
			}

			botones.forEach( function ( b ) {
				b.setAttribute( 'aria-pressed', b.getAttribute( 'data-canal' ) === canal ? 'true' : 'false' );
			} );
			fila.classList.toggle( 'jb3-filtro-canal--activo', canal !== '' );

			items.forEach( function ( item ) {
				var canales = ( item.getAttribute( 'data-jb3-canales' ) || '' ).split( ' ' );
				item.style.display = canal === '' || canales.indexOf( canal ) !== -1 ? '' : 'none';
			} );

			if ( actualizarUrl && window.history && window.history.replaceState ) {
				var url = new URL( window.location.href );
				if ( canal ) {
					url.searchParams.set( PARAM, canal );
				} else {
					url.searchParams.delete( PARAM );
				}
				window.history.replaceState( null, '', url.toString() );
			}
		}

		botones.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				aplicar( b.getAttribute( 'data-canal' ), true );
			} );
		} );

		var inicial = new URLSearchParams( window.location.search ).get( PARAM );
		if ( inicial ) {
			aplicar( inicial, false );
		}
	}

	document.querySelectorAll( '[data-jb3-filtro-canal]' ).forEach( iniciar );
} )();
