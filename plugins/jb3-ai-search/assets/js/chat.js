/**
 * Burbuja de chat flotante (class-chat.php/class-chat-ajax.php) — misma
 * pregunta → filtros → resultados que [jb3_buscador_ia], pero con memoria de
 * la conversación (sessionStorage) y respuesta conversacional.
 *
 * Memoria: se guarda {pregunta, respuesta} por intercambio en sessionStorage
 * (clave JB3AICHAT_HISTORIAL_KEY), tope HISTORIAL_MAX — sobrevive a navegar
 * entre páginas en la misma pestaña (sitio de varias páginas, sin SPA), se
 * borra al cerrarla. Al recargar cualquier página, se repintan las burbujas
 * de TEXTO guardadas (pregunta+respuesta) — a propósito NO se guardan ni
 * repintan los links de resultados, para no inflar el storage ni mostrar
 * links potencialmente viejos/rotos.
 */
( function () {
	var toggle = document.getElementById( 'jb3-ai-chat-toggle' );
	var panel  = document.getElementById( 'jb3-ai-chat-panel' );
	var hilo   = document.getElementById( 'jb3-ai-chat-hilo' );
	var form   = document.getElementById( 'jb3-ai-chat-form' );

	if ( ! toggle || ! panel || ! hilo || ! form || typeof jb3AIChat === 'undefined' ) {
		return;
	}

	var input          = form.querySelector( '.jb3-ai-chat__input' );
	var enviando       = false;
	var HISTORIAL_KEY  = 'jb3AIChatHistorial';
	var HISTORIAL_MAX  = parseInt( jb3AIChat.historialMax, 10 ) || 4;
	// Bandera para reabrir el panel solo al llegar de un click en un
	// resultado del chat — no en cualquier carga de página normal (ver el
	// listener de click más abajo, que la escribe justo antes de que el
	// navegador navegue).
	var REABRIR_KEY    = 'jb3AIChatReabrir';

	function leerHistorial() {
		try {
			var guardado = JSON.parse( sessionStorage.getItem( HISTORIAL_KEY ) || '[]' );
			return Array.isArray( guardado ) ? guardado : [];
		} catch ( e ) {
			return [];
		}
	}

	function guardarHistorial( historial ) {
		try {
			sessionStorage.setItem( HISTORIAL_KEY, JSON.stringify( historial.slice( -HISTORIAL_MAX ) ) );
		} catch ( e ) {
			// sessionStorage lleno/bloqueado (navegación privada, etc.) — la
			// conversación sigue funcionando, solo no persiste entre páginas.
		}
	}

	function agregarBurbuja( texto, clase ) {
		var burbuja = document.createElement( 'div' );
		burbuja.className = 'jb3-ai-chat__burbuja ' + clase;
		burbuja.textContent = texto; // nunca innerHTML acá: texto de usuario/modelo, no confiar.
		hilo.appendChild( burbuja );
		hilo.scrollTop = hilo.scrollHeight;
		return burbuja;
	}

	function agregarResultadosHtml( html ) {
		var contenedor = document.createElement( 'div' );
		// innerHTML seguro acá: html ya viene escapado del servidor
		// (jb3_aisearch_chat_render_resultados(), esc_url()/esc_html()) —
		// mismo criterio que respuesta.data.html en buscador-ia.js.
		contenedor.innerHTML = html;
		hilo.appendChild( contenedor );
		hilo.scrollTop = hilo.scrollHeight;
	}

	function abrirPanel() {
		panel.hidden = false;
		toggle.setAttribute( 'aria-expanded', 'true' );
		input.focus();
	}

	function cerrarPanel() {
		panel.hidden = true;
		toggle.setAttribute( 'aria-expanded', 'false' );
	}

	// Repinta la conversación guardada al cargar la página (ver comentario
	// de arriba: solo texto, nunca los links de resultados). Si todavía no
	// hay ninguna conversación, muestra el saludo de bienvenida en su lugar
	// — nunca los dos juntos, y el saludo NO se guarda en sessionStorage (no
	// es un intercambio real, es puro saludo de UI).
	var historialInicial = leerHistorial();
	if ( historialInicial.length ) {
		historialInicial.forEach( function ( turno ) {
			agregarBurbuja( turno.pregunta, 'jb3-ai-chat__burbuja--usuario' );
			if ( turno.respuesta ) {
				agregarBurbuja( turno.respuesta, 'jb3-ai-chat__burbuja--bot' );
			}
		} );
	} else if ( jb3AIChat.saludo ) {
		agregarBurbuja( jb3AIChat.saludo, 'jb3-ai-chat__burbuja--bot' );
	}

	// Si la página anterior era el propio chat (el visitante clickeó un link
	// de un resultado, ver el listener de click más abajo), reabre el panel
	// solo, así no se pierde la sensación de continuidad. Se borra al toque
	// para no reabrirlo de nuevo en la siguiente navegación normal.
	try {
		if ( sessionStorage.getItem( REABRIR_KEY ) ) {
			sessionStorage.removeItem( REABRIR_KEY );
			abrirPanel();
		}
	} catch ( e ) {
		// sessionStorage bloqueado — sin reapertura automática, el resto del
		// chat sigue funcionando igual.
	}

	// Delegación de clicks — mismo patrón que los modales del tema
	// (_init_video_modal.php/_init_page_modal.php): un solo listener en
	// document, agarra el trigger/close aunque el DOM cambie después.
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-jb3-chat-toggle]' ) ) {
			panel.hidden ? abrirPanel() : cerrarPanel();
		} else if ( e.target.closest( '[data-jb3-chat-close]' ) ) {
			cerrarPanel();
		} else if ( e.target.closest( '.jb3-ai-chat__resultado' ) ) {
			// El link navega normal (no se llama preventDefault) — solo se
			// deja la bandera lista ANTES de que el navegador se vaya.
			try {
				sessionStorage.setItem( REABRIR_KEY, '1' );
			} catch ( err ) {
				// sin sessionStorage no hay forma de avisarle a la próxima
				// página — la navegación sigue funcionando igual, solo no
				// se reabre sola.
			}
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! panel.hidden ) {
			cerrarPanel();
		}
	} );

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		if ( enviando ) {
			return;
		}

		var pregunta = input.value.trim();
		if ( ! pregunta ) {
			return;
		}

		enviando = true;
		input.value = '';
		agregarBurbuja( pregunta, 'jb3-ai-chat__burbuja--usuario' );
		var cargando = agregarBurbuja( 'Pensando…', 'jb3-ai-chat__burbuja--bot jb3-ai-chat__burbuja--cargando' );

		var datos = new FormData();
		datos.append( 'action', 'jb3_ai_chat' );
		datos.append( 'nonce', jb3AIChat.nonce );
		datos.append( 'pregunta', pregunta );
		datos.append( 'historial', JSON.stringify( leerHistorial() ) );

		fetch( jb3AIChat.ajaxUrl, { method: 'POST', body: datos, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( respuesta ) {
				enviando = false;
				cargando.remove();

				if ( ! respuesta.success ) {
					agregarBurbuja( ( respuesta.data && respuesta.data.mensaje ) || 'Ocurrió un error, probá de nuevo.', 'jb3-ai-chat__burbuja--bot' );
					return;
				}

				var texto = respuesta.data.respuesta || '';
				agregarBurbuja( texto, 'jb3-ai-chat__burbuja--bot' );
				if ( respuesta.data.html ) {
					agregarResultadosHtml( respuesta.data.html );
				}

				var historial = leerHistorial();
				historial.push( { pregunta: pregunta, respuesta: texto } );
				guardarHistorial( historial );
			} )
			.catch( function () {
				enviando = false;
				cargando.remove();
				agregarBurbuja( 'Ocurrió un error de conexión, probá de nuevo.', 'jb3-ai-chat__burbuja--bot' );
			} );
	} );
} )();
