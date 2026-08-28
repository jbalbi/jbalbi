/**
 * reCAPTCHA v3 en el formulario de registro (ver class-recaptcha.php).
 *
 * Intercepta el submit, pide un token a Google (invisible, sin desafío
 * visual), lo mete en un input oculto y recién ahí manda el form de
 * verdad — form.submit() (a diferencia de un click normal) NO vuelve a
 * disparar el evento "submit", así que no hace falta ninguna bandera para
 * evitar un loop infinito.
 *
 * capture:true: corre ANTES que el handler de jb3-forms.js (deshabilita
 * el botón de submit) — dejamos que ese SÍ siga corriendo después (no se
 * llama stopPropagation), así el botón se deshabilita mientras se espera
 * el token, evitando doble click.
 */
( function () {
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form.classList || ! form.classList.contains( 'jb3-form--recaptcha' ) ) {
			return;
		}

		e.preventDefault();

		// Si el script de Google no llegó a cargar (bloqueado por un
		// adblocker, sin red, lo que sea), mandamos el form igual —
		// verify() del lado servidor no bloquea si no hay token Y no hay
		// credenciales... pero acá SÍ hay credenciales configuradas, así
		// que sin token el servidor va a rechazar el registro con un
		// mensaje claro en vez de dejar al usuario con el botón trabado.
		if ( typeof grecaptcha === 'undefined' || ! window.jb3Recaptcha ) {
			form.submit();
			return;
		}

		grecaptcha.ready( function () {
			grecaptcha.execute( window.jb3Recaptcha.siteKey, { action: 'register' } )
				.then( function ( token ) {
					var input = form.querySelector( 'input[name="g-recaptcha-response"]' );
					if ( ! input ) {
						input = document.createElement( 'input' );
						input.type = 'hidden';
						input.name = 'g-recaptcha-response';
						form.appendChild( input );
					}
					input.value = token;
					form.submit();
				} )
				.catch( function () {
					form.submit();
				} );
		} );
	}, true );
} )();
