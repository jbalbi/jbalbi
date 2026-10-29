<?php
defined( 'ABSPATH' ) || exit;

// ─── MODAL DE PÁGINA ─────────────────────────────────────────────────────────
// Un solo modal reusado por toda la página, para cualquier link con
// data-jb3-page-modal="ID" (ver blocks/tarjeta/render.php, checkbox "Abrir
// en modal") — mismo patrón que el modal de video (_init_video_modal.php):
// intercepta el click de un <a href> REAL (así Ctrl+click/click-medio/sin
// JS siguen abriendo la Página real en pestaña nueva, en vez de quedar
// rotos), pide el contenido de esa Página por AJAX y lo inyecta en el
// modal — SIN header/footer/menú, solo lo que el editor armó con Gutenberg
// adentro de esa Página (título, bio, imagen, lo que sea).
add_action( 'wp_footer', function () {
	?>
	<div id="jb3-page-modal" class="jb3-page-modal" hidden>
		<div class="jb3-page-modal__overlay" data-jb3-page-modal-close></div>
		<div class="jb3-page-modal__dialog">
			<button type="button" class="jb3-page-modal__close" data-jb3-page-modal-close aria-label="Cerrar">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
			<div class="jb3-page-modal__contenido"></div>
		</div>
	</div>
	<script>
	( function () {
		var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

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
	</script>
	<?php
} );

// ─── AJAX: contenido de la Página, sin header/footer/menú ───────────────────
// nopriv también: el modal tiene que funcionar para visitantes sin sesión
// igual que la Página real (los gates de abajo son los que deciden si
// corresponde mostrarla, no el login en sí).
add_action( 'wp_ajax_jb3_page_modal',        'jb3_page_modal_ajax' );
add_action( 'wp_ajax_nopriv_jb3_page_modal', 'jb3_page_modal_ajax' );
function jb3_page_modal_ajax() {
	$page_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
	$post    = $page_id ? get_post( $page_id ) : null;

	if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
		wp_send_json_error( 'not_found' );
	}

	// Página protegida con contraseña (Visibilidad > Protegida con
	// contraseña): sin esto el modal devolvía el contenido completo sin
	// pedirla (auditoría 2026-10-29). Con el error, el JS manda a la Página
	// real, que muestra el formulario (ver functions.php). Si el
	// visitante ya la ingresó, la cookie viaja en este pedido y pasa.
	if ( post_password_required( $post ) ) {
		wp_send_json_error( 'password' );
	}

	// Mismo criterio que el gate de "Contenido privado" (jb3-members,
	// class-content-privacy.php, meta "_jb3_privado") — si la Página está
	// marcada privada, el modal no puede ser una puerta de atrás para
	// esquivar ese gate.
	if ( get_post_meta( $page_id, '_jb3_privado', true ) ) {
		$user_id      = get_current_user_id();
		$tiene_acceso = current_user_can( 'edit_post', $page_id )
			|| ( $user_id && function_exists( 'jb3_is_member' ) && jb3_is_member( $user_id ) )
			|| current_user_can( 'manage_options' );
		if ( ! $tiene_acceso ) {
			wp_send_json_error( 'privado' );
		}
	}

	// Geo-targeting: mismo chequeo que ya decide si esta Página se ve para
	// el país del visitante en cualquier otro lugar del sitio.
	if ( function_exists( 'jb3_geo_post_visible' ) && ! jb3_geo_post_visible( $page_id ) ) {
		wp_send_json_error( 'no_visible' );
	}

	$html = apply_filters( 'the_content', $post->post_content );

	// El "CSS Adicional" por bloque (panel Avanzado del editor — ej. un
	// ancho puntual en un bloque Imagen) NO viaja como parte del HTML de
	// arriba: WordPress lo guarda con wp_add_inline_style() en el handle
	// "wp-block-custom-css" (ver wp-includes/block-supports/custom-css.php)
	// y normalmente se imprime en wp_head()/wp_footer() — hooks que acá
	// nunca se disparan porque no se carga la página completa. Sin este
	// rescate manual, la clase "wp-custom-css-*" queda en el HTML pero sin
	// ninguna regla real detrás — cualquier ancho/estilo puntual cargado
	// así se pierde en el modal aunque en la Página real se vea bien.
	// Detectado 2026-08-16: una imagen alineada con ancho 50% por CSS
	// Adicional se veía perfecta en la Página, pero a ancho completo (sin
	// el texto rodeándola) adentro del modal.
	$css_bloques = wp_styles()->get_data( 'wp-block-custom-css', 'after' );
	if ( $css_bloques ) {
		$html = '<style>' . implode( "\n", (array) $css_bloques ) . '</style>' . $html;
	}

	wp_send_json_success( [ 'html' => $html ] );
}
