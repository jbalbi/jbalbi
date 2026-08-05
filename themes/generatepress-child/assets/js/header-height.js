( function () {
	function setHeaderHeight() {
		var header = document.querySelector( '.site-header' );
		if ( ! header ) {
			return;
		}
		document.documentElement.style.setProperty( '--header-height', header.offsetHeight + 'px' );
	}

	window.addEventListener( 'load', setHeaderHeight );
	window.addEventListener( 'resize', setHeaderHeight );
} )();
