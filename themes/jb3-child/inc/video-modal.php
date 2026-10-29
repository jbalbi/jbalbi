<?php
defined( 'ABSPATH' ) || exit;

// ─── MODAL DE VIDEO ──────────────────────────────────────────────────────────
// Un solo modal reusado por toda la página (no uno por video). Cualquier
// elemento con data-jb3-video="URL" (ver jb3_grid_thumbs() en
// inc/grids.php) abre este modal en vez de navegar — el JS decide si la
// URL es de YouTube, Vimeo, o un archivo propio (subido a la Biblioteca de
// medios) y arma el embed que corresponda.
// El JS de este modal está en assets/js/modales.js (encolado en functions.php).
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
	<?php
} );
