/**
 * Burbuja de chat flotante (class-chat.php/class-chat-ajax.php) — misma
 * pregunta → filtros → resultados que [jb3_buscador_ia], pero con memoria de
 * la conversación (sessionStorage) y respuesta conversacional.
 *
 * Memoria: se guarda {pregunta, respuesta, html} por intercambio en
 * sessionStorage (clave HISTORIAL_KEY), tope HISTORIAL_MAX — sobrevive a
 * navegar entre páginas en la misma pestaña (sitio de varias páginas, sin
 * SPA), se borra al cerrarla. Al recargar cualquier página, se repinta la
 * conversación completa (texto Y resultados) — reportado en vivo
 * 2026-08-30: un primer intento no guardaba el "html" de resultados para
 * no inflar el storage, pero eso hacía que al volver de clickear un link
 * la conversación se viera "vacía" justo en la parte más útil. El
 * historial que se MANDA al servidor (para que el modelo interprete
 * preguntas de seguimiento) sí sigue siendo solo {pregunta, respuesta} —
 * ver historialParaEnviar() — el modelo no necesita el HTML de resultados
 * para nada, mandarlo solo infla el pedido de cada mensaje nuevo.
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

	// Versión liviana del historial para mandar al servidor — el modelo
	// (ver jb3_aisearch_chat_construir_prompt() en class-chat-ajax.php)
	// solo lee "pregunta"/"respuesta" de cada turno, el "html" de
	// resultados que sí se guarda localmente para repintar no le sirve de
	// nada ahí, así que no se manda.
	function historialParaEnviar( historial ) {
		return historial.map( function ( turno ) {
			return { pregunta: turno.pregunta, respuesta: turno.respuesta };
		} );
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

	// Repinta la conversación guardada al cargar la página, texto Y
	// resultados. Si todavía no hay ninguna conversación, muestra el
	// saludo de bienvenida en su lugar — nunca los dos juntos, y el saludo
	// NO se guarda en sessionStorage (no es un intercambio real, es puro
	// saludo de UI).
	var historialInicial = leerHistorial();
	if ( historialInicial.length ) {
		historialInicial.forEach( function ( turno ) {
			agregarBurbuja( turno.pregunta, 'jb3-ai-chat__burbuja--usuario' );
			if ( turno.respuesta ) {
				agregarBurbuja( turno.respuesta, 'jb3-ai-chat__burbuja--bot' );
			}
			if ( turno.html ) {
				agregarResultadosHtml( turno.html );
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
			// El repintado de arriba corrió con el panel todavía [hidden]
			// (display:none) — con el hilo sin renderizar, scrollHeight daba 0
			// y el scrollTop de cada burbuja no servía de nada. Recién acá,
			// con el panel ya visible, el alto real existe y se puede bajar
			// al final de la conversación (reportado en vivo 2026-08-30: la
			// conversación volvía a aparecer arriba del todo en vez de en la
			// última respuesta).
			hilo.scrollTop = hilo.scrollHeight;
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

	// input pasó a ser un <textarea> (pedido explícito 2026-08-30, "que no
	// parezca un renglón solo") — sin esto, Enter simplemente insertaría un
	// salto de línea en vez de mandar el mensaje, como en un <input> de
	// toda la vida. Shift+Enter sigue insertando el salto de línea normal.
	input.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key && ! e.shiftKey ) {
			e.preventDefault();
			if ( form.requestSubmit ) {
				form.requestSubmit();
			} else {
				form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
			}
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
		datos.append( 'historial', JSON.stringify( historialParaEnviar( leerHistorial() ) ) );

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
				historial.push( { pregunta: pregunta, respuesta: texto, html: respuesta.data.html || '' } );
				guardarHistorial( historial );
			} )
			.catch( function () {
				enviando = false;
				cargando.remove();
				agregarBurbuja( 'Ocurrió un error de conexión, probá de nuevo.', 'jb3-ai-chat__burbuja--bot' );
			} );
	} );
} )();
