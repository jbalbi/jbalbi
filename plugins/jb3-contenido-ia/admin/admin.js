/**
 * JB3 Contenido IA — botones "Importar sinopsis (IA)" / "Importar
 * información (IA)", inyectados DENTRO de cada bloque Sinopsis/Información
 * del módulo "Contenido" (ver jbcia_render_boton() en jb3-contenido-ia.php).
 *
 * Como el botón vive literalmente adentro del campo WYSIWYG que tiene que
 * llenar, no hace falta buscar la fila por layout — alcanza con subir al
 * ACF field más cercano (".acf-field") y usar su propia key para pedirle a
 * la API de ACF ese campo. Si el bloque no existe todavía, el botón
 * tampoco existe — no hay caso de "no encontré la fila" que manejar.
 */
( function ( $ ) {
	'use strict';

	function importar( $boton ) {
		var accion    = $boton.data( 'accion' ); // 'sinopsis' | 'informacion'
		var $campoAcf = $boton.closest( '.acf-field' );
		var fieldKey  = $campoAcf.data( 'key' );
		var campo     = window.acf ? acf.getField( fieldKey ) : null;
		var $status   = $boton.siblings( '.jbcia-status' );

		var imdbId = ( $( '#acf-field_jbcia_imdb_id' ).val() || '' ).trim();
		if ( ! /^tt\d{6,10}$/i.test( imdbId ) ) {
			$status.text( 'Ingresá un ID de IMDb válido (ej: tt1234567) arriba antes de importar.' ).css( 'color', '#c0392b' );
			return;
		}
		if ( ! campo ) {
			$status.text( 'No se pudo ubicar el campo a llenar — recargá la página e intentá de nuevo.' ).css( 'color', '#c0392b' );
			return;
		}

		var textoOriginal = $boton.text();
		$boton.prop( 'disabled', true ).text( 'Buscando…' );
		$status.text( 'Buscando en IMDb ' + imdbId + '…' ).css( 'color', '#555' );

		var form = new FormData();
		form.append( 'action', 'jbcia_import_' + accion );
		form.append( 'nonce', jbciaData.nonce );
		form.append( 'post_id', jbciaData.postId );
		form.append( 'imdb_id', imdbId );

		fetch( jbciaData.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( resp ) {
				$boton.prop( 'disabled', false ).text( textoOriginal );

				if ( ! resp || ! resp.success ) {
					$status.text( ( resp && resp.data ) || 'Error desconocido.' ).css( 'color', '#c0392b' );
					return;
				}

				campo.val( resp.data.html );
				$status.text( 'Importado — revisalo antes de guardar.' ).css( 'color', '#2e7d32' );
			} )
			.catch( function ( e ) {
				$boton.prop( 'disabled', false ).text( textoOriginal );
				$status.text( 'Error de red: ' + e.message ).css( 'color', '#c0392b' );
			} );
	}

	$( document ).on( 'click', '.jbcia-btn', function ( e ) {
		e.preventDefault();
		importar( $( this ) );
	} );

}( jQuery ) );
