<?php
defined( 'ABSPATH' ) || exit;

// ─── VISTA PREVIA AUTOMÁTICA DEL PDF (admin) ────────────────────────────────
// El servidor no tiene Ghostscript/Imagick (confirmado), así que no se
// puede rasterizar un PDF por PHP — pero SÍ hay PDF.js corriendo en el
// navegador (ver _init_pdf_viewer.php), y el editor YA está logueado y
// autorizado a ver el PDF mientras lo carga en el admin. Se reutiliza esa
// misma librería acá: justo cuando se hace clic en "Actualizar"/"Publicar"
// de una Op. Comercial que tiene PDF elegido (aunque todavía no se haya
// guardado) y no tiene imagen destacada, el navegador dibuja la página 1 a
// un <canvas>, la convierte a imagen, y la sube sola como destacada — sin
// necesidad de guardar y recargar para que se dispare.
//
// El enganche es con el ciclo de guardado real del editor de bloques
// (wp.data + isSavingPost()), no con la carga de la página — así también
// funciona en post-new.php (una Op. Comercial nueva, antes del primer
// guardado) y no depende de que get_field() ya tenga el valor guardado
// del lado del servidor.
add_action( 'admin_enqueue_scripts', function ( string $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'op_comerciales' !== $screen->post_type ) {
		return;
	}

	jb3_enqueue_pdfjs();

	// Sin DOMContentLoaded/load acá: este script se imprime en el FOOTER
	// (después de pdf.min.js, vía wp_add_inline_script 'after'), así que el
	// DOM ya está armado — esperar a un evento que ya pasó hace que el
	// callback nunca corra. Se ejecuta directo, y se queda escuchando el
	// store de wp.data hasta que haga falta.
	wp_add_inline_script( 'pdfjs', "
		( function () {
			if ( typeof wp === 'undefined' || ! wp.data || ! wp.data.subscribe ) {
				return;
			}

			var yaCorriendo = false;

			wp.data.subscribe( function () {
				if ( typeof pdfjsLib === 'undefined' ) {
					return;
				}
				var editor = wp.data.select( 'core/editor' );
				if ( ! editor || ! editor.isSavingPost() || editor.isAutosavingPost() ) {
					return;
				}
				if ( yaCorriendo ) {
					return;
				}

				// Ya tiene destacada (guardada o recién elegida en esta misma
				// sesión, sin guardar todavía) — no generar nada, no pisar
				// una elección del editor.
				if ( editor.getEditedPostAttribute( 'featured_media' ) ) {
					return;
				}

				// El campo ACF 'pdf' guarda el ID del adjunto en un input
				// oculto en cuanto hay un archivo elegido (ver
				// class-acf-field-file.php del plugin ACF) — sirve aunque el
				// PDF recién se haya subido y todavía no se guardó el post.
				// Se pide por AJAX (jb3_pdf_admin, abajo) y no por su URL:
				// el acceso directo a los .pdf está bloqueado en
				// wp-content/.htaccess.
				var input = document.querySelector( '.acf-field[data-name=\"pdf\"] .acf-file-uploader > input[type=\"hidden\"]' );
				var pdfId = input ? parseInt( input.value, 10 ) : 0;
				if ( ! pdfId ) {
					return;
				}
				var pdfUrl = ajaxurl + '?action=jb3_pdf_admin&id=' + pdfId + '&nonce=' + " . wp_json_encode( wp_create_nonce( 'jb3_pdf_admin' ) ) . ";

				yaCorriendo = true;
				pdfjsLib.GlobalWorkerOptions.workerSrc = window.jb3PdfWorkerUrl;

				pdfjsLib.getDocument( pdfUrl ).promise.then( function ( pdf ) {
					return pdf.getPage( 1 );
				} ).then( function ( page ) {
					// scale 2.5: mas resolucion nativa que antes (1.5) para
					// que la imagen no quede mas chica que el ancho al que
					// se termina mostrando (portada, preview a pagina
					// completa) - eso es lo que la hace ver estirada/blureada
					// en el sitio aunque el archivo en si este bien.
					var viewport = page.getViewport( { scale: 2.5 } );
					var canvas   = document.createElement( 'canvas' );
					var context  = canvas.getContext( '2d' );
					canvas.width  = viewport.width;
					canvas.height = viewport.height;

					context.imageSmoothingEnabled = true;
					context.imageSmoothingQuality  = 'high';

					return page.render( { canvasContext: context, viewport: viewport } ).promise.then( function () {
						return canvas.toDataURL( 'image/jpeg', 0.95 );
					} );
				} ).then( function ( dataUrl ) {
					var form = new FormData();
					form.append( 'action', 'jb3_generar_preview_pdf' );
					form.append( 'nonce', " . wp_json_encode( wp_create_nonce( 'jb3_generar_preview_pdf' ) ) . " );
					form.append( 'post_id', wp.data.select( 'core/editor' ).getCurrentPostId() );
					form.append( 'image', dataUrl );

					return fetch( ajaxurl, { method: 'POST', body: form, credentials: 'same-origin' } );
				} ).then( function ( r ) {
					return r.json();
				} ).then( function ( res ) {
					if ( res && res.success && res.data && res.data.attachment_id ) {
						wp.data.dispatch( 'core/editor' ).editPost( { featured_media: res.data.attachment_id } );
					} else {
						yaCorriendo = false;
					}
				} ).catch( function () {
					// Si falla, se puede reintentar en el próximo guardado —
					// y el editor siempre puede cargar la miniatura a mano.
					yaCorriendo = false;
				} );
			} );
		} )();
	", 'after' );
} );

add_action( 'wp_ajax_jb3_generar_preview_pdf', function () {
	check_ajax_referer( 'jb3_generar_preview_pdf', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'No autorizado.' );
	}

	$data_url = isset( $_POST['image'] ) ? (string) $_POST['image'] : '';
	if ( ! preg_match( '/^data:image\/jpeg;base64,(.+)$/', $data_url, $m ) ) {
		wp_send_json_error( 'Imagen inválida.' );
	}

	$bits = base64_decode( $m[1] );
	if ( ! $bits ) {
		wp_send_json_error( 'No se pudo decodificar la imagen.' );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload = wp_upload_bits( 'preview-op-comercial-' . $post_id . '.jpg', null, $bits );
	if ( ! empty( $upload['error'] ) ) {
		wp_send_json_error( $upload['error'] );
	}

	$attachment_id = wp_insert_attachment( [
		'post_mime_type' => 'image/jpeg',
		'post_title'     => get_the_title( $post_id ) . ' — vista previa PDF',
		'post_status'    => 'inherit',
	], $upload['file'], $post_id );

	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( $attachment_id->get_error_message() );
	}

	// Esta imagen se usa en dos tamaños nada más: 'full' (single-op_comerciales.php,
	// a ancho completo) y 'large' (jb3_grid_thumbs, tarjetas de "Más
	// Oportunidades Comerciales") — generar además thumbnail/medium/medium_large
	// sería procesar y guardar 3 archivos de más por cada PDF sin que nada los use.
	add_filter( 'intermediate_image_sizes_advanced', function ( $sizes ) {
		return array_intersect_key( $sizes, [ 'large' => true ] );
	} );
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

	set_post_thumbnail( $post_id, $attachment_id );

	wp_send_json_success( [ 'attachment_id' => $attachment_id ] );
} );

// ─── AJAX: el PDF elegido en el campo, para dibujar la página 1 ─────────────
// Única vía de lectura de un PDF desde el editor — el acceso directo a los
// .pdf está bloqueado en wp-content/.htaccess (revisión pre-producción
// 2026-10-01). Solo para quien puede subir archivos (editores/admins).
add_action( 'wp_ajax_jb3_pdf_admin', function () {
	check_ajax_referer( 'jb3_pdf_admin', 'nonce' );

	$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$path = $id ? (string) get_attached_file( $id ) : '';
	if ( ! current_user_can( 'upload_files' ) || 'application/pdf' !== get_post_mime_type( $id ) || ! is_file( $path ) ) {
		wp_die( '', '', [ 'response' => 403 ] );
	}

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path );
	exit;
} );
