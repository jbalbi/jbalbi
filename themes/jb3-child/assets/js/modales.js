/**
 * Modales de video, imagen y página: el JS que antes se imprimía adentro de
 * CADA página (un <script> en wp_footer por modal, ~7 KB repetidos en cada
 * visita) ahora es un archivo que el navegador baja una vez y reusa
 * (auditoría de rendimiento 2026-10-29). El HTML de cada modal sigue
 * saliendo de su archivo en inc/ (video-modal.php, image-modal.php,
 * page-modal.php), que es donde está documentado cada uno.
 */

// ═══════════════════════════════════════════════════════════════════════════
// Modal de video ([data-jb3-video]) — HTML en inc/video-modal.php
// ═══════════════════════════════════════════════════════════════════════════

( function () {
	function parseVideo( url ) {
		var yt = url.match( /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{6,})/ );
		if ( yt ) {
			return { tipo: 'iframe', src: 'https://www.youtube.com/embed/' + yt[1] + '?autoplay=1&rel=0' };
		}
		var vimeo = url.match( /vimeo\.com\/(?:video\/)?(\d+)/ );
		if ( vimeo ) {
			return { tipo: 'iframe', src: 'https://player.vimeo.com/video/' + vimeo[1] + '?autoplay=1' };
		}
		return { tipo: 'archivo', src: url }; // Subido a la Biblioteca de medios (mp4, etc).
	}

	// Pantalla completa REAL (sin barra de direcciones/pestañas del
	// navegador, no solo el modal ocupando el viewport por CSS) — mismos
	// prefijos que hacen falta para que ande en todos los navegadores.
	function pedirFullscreen( el ) {
		var f = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
		if ( ! f ) {
			return; // Ej. iOS Safari no lo soporta para un div cualquiera — el modal sigue andando igual, sin fullscreen real.
		}
		var resultado = f.call( el );
		if ( resultado && resultado.catch ) {
			resultado.catch( function () {} ); // Bloqueado por el navegador o el usuario — no rompe nada, sigue como modal normal.
		}
	}

	function elementoFullscreen() {
		return document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
	}

	function salirFullscreen() {
		var s = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen;
		if ( s ) {
			var resultado = s.call( document );
			if ( resultado && resultado.catch ) {
				resultado.catch( function () {} );
			}
		}
	}

	function abrir( url ) {
		var video    = parseVideo( url );
		var modal    = document.getElementById( 'jb3-video-modal' );
		var frame    = modal.querySelector( '.jb3-video-modal__frame' );

		// El reproductor se arma con elementos del DOM, no pegando texto
		// como HTML (auditoría 2026-10-29): así la URL nunca se interpreta
		// como código, venga de donde venga el data-jb3-video. Un archivo
		// propio solo se acepta por http(s) o ruta del mismo sitio.
		var el;
		if ( 'iframe' === video.tipo ) {
			el = document.createElement( 'iframe' );
			el.setAttribute( 'frameborder', '0' );
			el.setAttribute( 'allow', 'autoplay; fullscreen; picture-in-picture' );
			el.setAttribute( 'allowfullscreen', '' );
		} else {
			if ( ! /^(https?:)?\//i.test( video.src ) ) {
				return;
			}
			el = document.createElement( 'video' );
			el.controls    = true;
			el.autoplay    = true;
			el.playsInline = true;
		}
		el.src = video.src;
		frame.replaceChildren( el );

		modal.hidden = false;
		document.body.style.overflow = 'hidden';
		pedirFullscreen( modal );
	}

	function cerrar() {
		var modal = document.getElementById( 'jb3-video-modal' );
		modal.hidden = true;
		modal.querySelector( '.jb3-video-modal__frame' ).innerHTML = ''; // corta la reproducción.
		document.body.style.overflow = '';
		if ( elementoFullscreen() ) {
			salirFullscreen();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '[data-jb3-video]' );
		if ( trigger ) {
			e.preventDefault();
			abrir( trigger.getAttribute( 'data-jb3-video' ) );
			return;
		}
		if ( e.target.closest( '[data-jb3-video-close]' ) ) {
			cerrar();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			cerrar();
		}
	} );

	// Si el usuario sale de pantalla completa por afuera de nuestro botón
	// (Esc lo maneja el navegador solo, gesto en mobile, etc.), el modal
	// tiene que cerrarse igual — si no, queda "abierto" atrás sin que se
	// note por qué.
	[ 'fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange' ].forEach( function ( evento ) {
		document.addEventListener( evento, function () {
			var modal = document.getElementById( 'jb3-video-modal' );
			if ( ! elementoFullscreen() && ! modal.hidden ) {
				cerrar();
			}
		} );
	} );
} )();

// ═══════════════════════════════════════════════════════════════════════════
// Modal de imagen ([data-jb3-image]) — HTML en inc/image-modal.php
// ═══════════════════════════════════════════════════════════════════════════

( function () {
	function abrir( url ) {
		var modal = document.getElementById( 'jb3-image-modal' );
		modal.querySelector( '.jb3-image-modal__img' ).src = url;
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
	}

	function cerrar() {
		var modal = document.getElementById( 'jb3-image-modal' );
		modal.hidden = true;
		modal.querySelector( '.jb3-image-modal__img' ).src = '';
		document.body.style.overflow = '';
	}

	// Expuesta globalmente: la Galería con Slider (carrusel con swipe vía
	// Pointer Events + setPointerCapture) la llama directo en vez de
	// depender del [data-jb3-image] delegado más abajo — con
	// setPointerCapture activo, el navegador reasigna el target del click
	// al elemento que capturó el puntero, no a la foto tocada, así que la
	// delegación por closest() no la encuentra ahí.
	window.jb3AbrirImagenModal = abrir;

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '[data-jb3-image]' );
		if ( trigger ) {
			e.preventDefault();
			abrir( trigger.getAttribute( 'data-jb3-image' ) );
			return;
		}
		if ( e.target.closest( '[data-jb3-image-close]' ) ) {
			cerrar();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			cerrar();
		}
	} );
} )();

// ═══════════════════════════════════════════════════════════════════════════
// Modal de página ([data-jb3-page-modal]) — HTML en inc/page-modal.php
// ═══════════════════════════════════════════════════════════════════════════

( function () {
	var ajaxUrl = jb3Modales.ajaxUrl;

	function abrir( pageId, fallbackUrl ) {
		var modal     = document.getElementById( 'jb3-page-modal' );
		var contenido = modal.querySelector( '.jb3-page-modal__contenido' );

		contenido.innerHTML = '<p class="jb3-page-modal__cargando">Cargando…</p>';
		modal.hidden = false;
		document.body.style.overflow = 'hidden';

		fetch( ajaxUrl + '?action=jb3_page_modal&page_id=' + encodeURIComponent( pageId ) )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( modal.hidden ) {
					return; // el usuario cerró el modal mientras la respuesta viajaba.
				}
				if ( data && data.success ) {
					contenido.innerHTML = data.data.html;
				} else if ( fallbackUrl ) {
					window.location.href = fallbackUrl;
				} else {
					contenido.innerHTML = '<p class="jb3-page-modal__cargando">No se pudo cargar el contenido.</p>';
				}
			} )
			.catch( function () {
				if ( fallbackUrl ) {
					window.location.href = fallbackUrl;
				}
			} );
	}

	function cerrar() {
		var modal = document.getElementById( 'jb3-page-modal' );
		modal.hidden = true;
		modal.querySelector( '.jb3-page-modal__contenido' ).innerHTML = '';
		document.body.style.overflow = '';
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '[data-jb3-page-modal]' );
		if ( trigger ) {
			e.preventDefault();
			abrir( trigger.getAttribute( 'data-jb3-page-modal' ), trigger.getAttribute( 'href' ) );
			return;
		}
		if ( e.target.closest( '[data-jb3-page-modal-close]' ) ) {
			cerrar();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			cerrar();
		}
	} );
} )();
