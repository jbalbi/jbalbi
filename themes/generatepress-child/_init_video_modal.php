<?php
defined( 'ABSPATH' ) || exit;

// ─── MODAL DE VIDEO ──────────────────────────────────────────────────────────
// Un solo modal reusado por toda la página (no uno por video). Cualquier
// elemento con data-jb3-video="URL" (ver jb3_grid_thumbs() en
// _init_grids.php) abre este modal en vez de navegar — el JS decide si la
// URL es de YouTube, Vimeo, o un archivo propio (subido a la Biblioteca de
// medios) y arma el embed que corresponda.
add_action( 'wp_footer', function () {
	?>
	<div id="jb3-video-modal" class="jb3-video-modal" hidden>
		<div class="jb3-video-modal__overlay" data-jb3-video-close></div>
		<div class="jb3-video-modal__dialog">
			<button type="button" class="jb3-video-modal__close" data-jb3-video-close aria-label="Cerrar">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
			<div class="jb3-video-modal__frame"></div>
		</div>
	</div>
	<script>
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
	</script>
	<?php
} );
