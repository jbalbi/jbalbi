<?php
defined( 'ABSPATH' ) || exit;

// ─── MODAL DE IMAGEN ─────────────────────────────────────────────────────────
// Mismo patrón que el modal de video (inc/video-modal.php): un solo modal
// reusado por toda la página. Cualquier elemento con data-jb3-image="URL"
// lo abre a pantalla completa en vez de navegar.
// El JS de este modal está en assets/js/modales.js (encolado en functions.php).
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
	<?php
} );
