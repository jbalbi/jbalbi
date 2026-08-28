/**
 * Bloquea el scroll de fondo mientras el menú mobile está abierto, sin usar
 * "overflow:hidden" (rompe el position:sticky del header — ver comentario
 * largo en style.css, sección "OVERLAY DEL MENÚ MÓVIL").
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
			scrollY = window.scrollY || window.pageYOffset || 0;
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
