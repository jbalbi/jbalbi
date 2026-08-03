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
