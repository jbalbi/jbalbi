/**
 * [jb3_buscador_ia]: manda la pregunta al endpoint AJAX (jb3-ai-search),
 * muestra estado de carga y el resultado (HTML ya armado del lado del
 * servidor con jb3_grid_thumbs()) o el mensaje de "sin resultados".
 */
( function () {
	document.querySelectorAll( '.jb3-aisearch' ).forEach( function ( bloque ) {
		var form        = bloque.querySelector( '.jb3-aisearch__form' );
		var input       = bloque.querySelector( '.jb3-aisearch__input' );
		var estado      = bloque.querySelector( '.jb3-aisearch__estado' );
		var resultados  = bloque.querySelector( '.jb3-aisearch__resultados' );
		var buscando    = false;

		function mostrarEstado( texto ) {
			if ( ! texto ) {
				estado.hidden = true;
				estado.textContent = '';
				return;
			}
			estado.hidden = false;
			estado.textContent = texto;
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( buscando ) {
				return;
			}

			var pregunta = input.value.trim();
			if ( ! pregunta ) {
				return;
			}

			buscando = true;
			resultados.innerHTML = '';
			mostrarEstado( 'Buscando…' );

			var datos = new FormData();
			datos.append( 'action', 'jb3_ai_search' );
			datos.append( 'nonce', jb3AISearch.nonce );
			datos.append( 'pregunta', pregunta );

			fetch( jb3AISearch.ajaxUrl, { method: 'POST', body: datos, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( respuesta ) {
					buscando = false;

					if ( ! respuesta.success ) {
						mostrarEstado( ( respuesta.data && respuesta.data.mensaje ) || 'Ocurrió un error, probá de nuevo.' );
						return;
					}

					if ( ! respuesta.data.count ) {
						mostrarEstado( 'No encontramos nada con esa búsqueda — probá reformularla.' );
						return;
					}

					mostrarEstado( '' );
					resultados.innerHTML = respuesta.data.html;
				} )
				.catch( function () {
					buscando = false;
					mostrarEstado( 'Ocurrió un error de conexión, probá de nuevo.' );
				} );
		} );
	} );
} )();
