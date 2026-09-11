/**
 * JB3 Members – Frontend form enhancements.
 * Lightweight client-side validation (HTML5 + a few UX improvements).
 */
(function ( $ ) {
    'use strict';

    /* ── Phone field: block non-numeric chars (allow + - space parentheses) ── */
    $( document ).on( 'input', 'input[type="tel"]', function () {
        this.value = this.value.replace( /[^0-9+\-\s()]/g, '' );
    } );

    /* ── Text fields: strip leading/trailing whitespace on blur ───────────── */
    $( document ).on( 'blur', '.jb3-form input[type="text"], .jb3-form input[type="email"]', function () {
        this.value = this.value.trim();
    } );

    /* ── Submit: disable button to prevent double-submission ─────────────── */
    $( document ).on( 'submit', '.jb3-form', function ( e ) {
        var $form = $( this );

        // Run HTML5 validation first.
        if ( this.checkValidity && ! this.checkValidity() ) {
            return; // let the browser show native messages.
        }

        // Un onsubmit="return confirm(...)" en el propio <form> (ej. Eliminar
        // cuenta) que el usuario cancela llama a preventDefault() pero NO
        // detiene la propagación — el evento 'submit' sigue burbujeando hasta
        // este handler delegado en document igual. Sin este chequeo, cancelar
        // el confirm() dejaba el botón en "..." y deshabilitado como si el
        // envío hubiera arrancado, aunque nunca se mandó nada.
        if ( e.isDefaultPrevented() ) {
            return;
        }

        var $btn = $form.find( 'button[type="submit"]' );
        // Guarda el texto original la primera vez — así el fallback de abajo
        // puede restaurarlo en vez de dejar "..." pegado para siempre si el
        // envío no navega (ej. el propio fallback se dispara).
        if ( $btn.data( 'original-text' ) === undefined ) {
            $btn.data( 'original-text', $btn.text() );
        }
        $btn.prop( 'disabled', true ).text( $btn.data( 'loading' ) || '...' );

        // Re-enable after 8 s as a safety fallback.
        setTimeout( function () {
            $btn.prop( 'disabled', false ).text( $btn.data( 'original-text' ) );
        }, 8000 );
    } );

    /* ── Auto-scroll to first error notice on page load ─────────────────── */
    $( function () {
        var $err = $( '.jb3-error' ).first();
        if ( $err.length ) {
            $( 'html, body' ).animate( { scrollTop: $err.offset().top - 80 }, 300 );
        }
    } );

}( jQuery ) );
