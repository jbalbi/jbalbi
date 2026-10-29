<?php
defined( 'ABSPATH' ) || exit;

// ─── VISOR DE PDF: PDF.js autohospedado, sin controles nativos ─────────────
// Mismo criterio que Swiper (inc/sliders.php): autohospedado en
// assets/vendor/pdfjs/ en vez de CDN, para no depender de un dominio de
// terceros. A diferencia de un <iframe> (que deja que el navegador — o una
// extensión como Adobe Acrobat — se haga cargo del PDF con su propia
// interfaz, imposible de controlar del todo desde acá), acá cada página se
// dibuja a mano en un <canvas> propio: el navegador nunca "ve" que es un
// PDF, así que ninguna extensión tiene nada que interceptar.
//
// IMPORTANTE — esta función SIEMPRE muestra el PDF completo, sin ningún
// chequeo de sesión: quien la llama es responsable de no invocarla para un
// visitante sin permiso (ver single-op_comerciales.php, que solo la llama
// dentro de un if ( is_user_logged_in() )). Esto es a propósito: mostrar
// solo la página 1 en el propio navegador de todos modos requiere bajar el
// PDF completo primero (PDF.js necesita el archivo entero para poder
// armar cualquier página), así que un límite del lado del cliente NO es
// protección real — cualquiera con las herramientas de desarrollador
// (pestaña Red) podría sacar el archivo completo igual. La única forma de
// que alguien sin sesión no pueda conseguirlo es que el PDF ni siquiera
// viaje hasta su navegador — por eso el gate vive en el template, no acá.
function jb3_enqueue_pdfjs() {
	$js_path = get_stylesheet_directory() . '/assets/vendor/pdfjs/pdf.min.js';

	wp_enqueue_script(
		'pdfjs',
		get_stylesheet_directory_uri() . '/assets/vendor/pdfjs/pdf.min.js',
		[],
		file_exists( $js_path ) ? filemtime( $js_path ) : false,
		true
	);

	// wp_add_inline_script(), no wp_localize_script(): esta última espera un
	// array y con un string suelto dejaba un aviso de WordPress en el log en
	// cada página con PDF. Mismo resultado: la variable global jb3PdfWorkerUrl.
	wp_add_inline_script(
		'pdfjs',
		'var jb3PdfWorkerUrl = ' . wp_json_encode( get_stylesheet_directory_uri() . '/assets/vendor/pdfjs/pdf.worker.min.js' ) . ';',
		'before'
	);
}

/** Markup del visor — todas las páginas del PDF, una debajo de la otra, con scroll vertical normal de la página. */
function jb3_pdf_viewer( string $pdf_url ): string {
	if ( ! $pdf_url ) {
		return '';
	}

	jb3_enqueue_pdfjs();

	ob_start();
	?>
	<div class="jb3-pdf-viewer" data-pdf-url="<?php echo esc_url( $pdf_url ); ?>">
		<p class="jb3-pdf-viewer__cargando">Cargando PDF…</p>
	</div>
	<?php

	// static: el script de inicialización se agrega UNA sola vez aunque
	// jb3_pdf_viewer() se llame varias veces en la misma página — igual
	// resuelve todos los .jb3-pdf-viewer que encuentre, sea cual sea la
	// cantidad.
	static $script_encolado = false;
	if ( ! $script_encolado ) {
		$script_encolado = true;
		wp_add_inline_script( 'pdfjs', "
			window.addEventListener( 'load', function () {
				if ( typeof pdfjsLib === 'undefined' ) {
					return;
				}
				pdfjsLib.GlobalWorkerOptions.workerSrc = window.jb3PdfWorkerUrl;

				document.querySelectorAll( '.jb3-pdf-viewer' ).forEach( function ( el ) {
					var url = el.getAttribute( 'data-pdf-url' );
					if ( ! url ) {
						return;
					}

					pdfjsLib.getDocument( url ).promise.then( function ( pdf ) {
						el.innerHTML = '';

						function renderPage( num ) {
							pdf.getPage( num ).then( function ( page ) {
								var containerWidth = el.clientWidth || 800;
								var viewportBase   = page.getViewport( { scale: 1 } );
								var scale          = containerWidth / viewportBase.width;
								var viewport       = page.getViewport( { scale: scale } );

								var canvas  = document.createElement( 'canvas' );
								canvas.className = 'jb3-pdf-viewer__pagina';
								var context = canvas.getContext( '2d' );
								canvas.width  = viewport.width;
								canvas.height = viewport.height;
								el.appendChild( canvas );

								page.render( { canvasContext: context, viewport: viewport } );

								if ( num < pdf.numPages ) {
									renderPage( num + 1 );
								}
							} );
						}

						renderPage( 1 );
					} ).catch( function () {
						el.innerHTML = '<p class=\"jb3-pdf-viewer__error\">No se pudo cargar el PDF.</p>';
					} );
				} );
			} );
		", 'after' );
	}

	return ob_get_clean();
}
