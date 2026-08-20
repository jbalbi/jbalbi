<?php
defined( 'ABSPATH' ) || exit;

// ─── MODAL DE IMAGEN ─────────────────────────────────────────────────────────
// Mismo patrón que el modal de video (_init_video_modal.php): un solo modal
// reusado por toda la página. Cualquier elemento con data-jb3-image="URL"
// lo abre a pantalla completa en vez de navegar.
add_action( 'wp_footer', function () {
	?>
	<div id="jb3-image-modal" class="jb3-image-modal" hidden>
		<div class="jb3-image-modal__overlay" data-jb3-image-close></div>
		<div class="jb3-image-modal__dialog">
			<button type="button" class="jb3-image-modal__close" data-jb3-image-close aria-label="Cerrar">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
			<img class="jb3-image-modal__img" src="" alt="">
		</div>
	</div>
	<script>
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
	</script>
	<?php
} );
