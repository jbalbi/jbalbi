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
