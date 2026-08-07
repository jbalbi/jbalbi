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

		function abrir( url ) {
			var video    = parseVideo( url );
			var modal    = document.getElementById( 'jb3-video-modal' );
			var frame    = modal.querySelector( '.jb3-video-modal__frame' );

			frame.innerHTML = 'iframe' === video.tipo
				? '<iframe src="' + video.src + '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>'
				: '<video src="' + video.src + '" controls autoplay playsinline></video>';

			modal.hidden = false;
			document.body.style.overflow = 'hidden';
		}

		function cerrar() {
			var modal = document.getElementById( 'jb3-video-modal' );
			modal.hidden = true;
			modal.querySelector( '.jb3-video-modal__frame' ).innerHTML = ''; // corta la reproducción.
			document.body.style.overflow = '';
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
	} )();
	</script>
	<?php
} );
