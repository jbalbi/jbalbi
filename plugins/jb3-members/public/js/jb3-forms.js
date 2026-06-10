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

        var $btn = $form.find( 'button[type="submit"]' );
        $btn.prop( 'disabled', true ).text( $btn.data( 'loading' ) || '...' );

        // Re-enable after 8 s as a safety fallback.
        setTimeout( function () {
            $btn.prop( 'disabled', false );
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
