<?php
/**
 * Plugin Name: JB3 Regenerar imágenes
 * Description: Herramienta para regenerar las imágenes ya subidas con los tamaños y el formato WebP del tema (ver _init_imagenes.php). Herramientas → Regenerar imágenes. Usar una vez después de cambiar tamaños/formato/calidad, y DESACTIVAR al terminar.
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

// Mismo proceso que se corrió en local el 2026-10-01 (264 imágenes, sin
// errores): para cada JPG/PNG de la biblioteca, parte del ORIGINAL subido
// (wp_get_original_image_path) y llama a wp_create_image_subsizes(), que
// aplica lo que define el tema: tamaños propios, copias en WebP calidad 80 y
// la imagen principal también en WebP. No borra archivos: los JPG/PNG
// originales y las copias viejas quedan en el disco, así no se rompe ninguna
// imagen insertada a mano en un contenido.
//
// De a pocas imágenes por pedido (JB3_REGEN_LOTE), disparadas desde el
// navegador, para no chocar con el tiempo máximo de ejecución del hosting.
// Se puede interrumpir y volver a empezar: regenerar dos veces la misma
// imagen da el mismo resultado.

const JB3_REGEN_LOTE = 5;

add_action( 'admin_menu', function () {
	add_management_page( 'Regenerar imágenes', 'Regenerar imágenes', 'manage_options', 'jb3-regenerar-imagenes', 'jb3_regen_pagina' );
} );

function jb3_regen_ids(): array {
	return get_posts( array(
		'post_type'      => 'attachment',
		'post_mime_type' => array( 'image/jpeg', 'image/png' ),
		'post_status'    => 'inherit',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );
}

function jb3_regen_pagina(): void {
	$tema_ok = function_exists( 'jb3_picture' ) && has_filter( 'image_editor_output_format' );
	$total   = count( jb3_regen_ids() );
	?>
	<div class="wrap">
		<h1>Regenerar imágenes</h1>
		<?php if ( ! $tema_ok ) : ?>
			<div class="notice notice-error"><p><strong>Falta subir el código nuevo del tema</strong> (<code>_init_imagenes.php</code> y <code>functions.php</code>). Sin eso, regenerar no aplicaría los tamaños ni el WebP — subí primero los archivos del tema.</p></div>
		<?php else : ?>
			<p>Va a regenerar <strong><?php echo (int) $total; ?> imágenes</strong> (JPG y PNG) con los tamaños y el formato WebP del tema. Tarda aproximadamente <?php echo (int) max( 1, ceil( $total / 90 ) ); ?> minuto(s). <strong>No cierres esta pestaña</strong> hasta que termine; si se corta, volvé a apretar el botón.</p>
			<p>Antes: hacé un <strong>backup de la carpeta <code>wp-content/uploads</code></strong>. Al terminar: <strong>purgá la caché de LiteSpeed</strong> y <strong>desactivá este plugin</strong>.</p>
			<p><button class="button button-primary" id="jb3-regen-btn">Regenerar <?php echo (int) $total; ?> imágenes</button></p>
			<div id="jb3-regen-estado" style="font-size:14px;"></div>
			<ul id="jb3-regen-errores" style="color:#b32d2e;"></ul>
			<script>
			( function () {
				var btn = document.getElementById( 'jb3-regen-btn' ), estado = document.getElementById( 'jb3-regen-estado' ), errores = document.getElementById( 'jb3-regen-errores' );
				var total = <?php echo (int) $total; ?>, ok = 0, mal = 0;
				function lote( desde ) {
					var datos = new FormData();
					datos.append( 'action', 'jb3_regen_lote' );
					datos.append( 'desde', desde );
					datos.append( '_wpnonce', '<?php echo esc_js( wp_create_nonce( 'jb3_regen' ) ); ?>' );
					fetch( ajaxurl, { method: 'POST', body: datos, credentials: 'same-origin' } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( r ) {
							if ( ! r.success ) { throw new Error( r.data || 'error' ); }
							ok += r.data.ok; mal += r.data.errores.length;
							r.data.errores.forEach( function ( e ) { var li = document.createElement( 'li' ); li.textContent = e; errores.appendChild( li ); } );
							var hechas = Math.min( r.data.siguiente, total );
							estado.textContent = 'Procesadas ' + hechas + ' de ' + total + ' — ' + ok + ' ok, ' + mal + ' con error';
							if ( r.data.siguiente < total ) { lote( r.data.siguiente ); }
							else { estado.innerHTML = '<strong>Listo:</strong> ' + ok + ' ok, ' + mal + ' con error. Ahora: purgá la caché de LiteSpeed y desactivá este plugin.'; btn.disabled = false; }
						} )
						.catch( function ( e ) { estado.textContent = 'Se cortó en la imagen ' + desde + ' de ' + total + ' (' + e.message + '). Volvé a apretar el botón.'; btn.disabled = false; } );
				}
				btn.addEventListener( 'click', function () { btn.disabled = true; ok = 0; mal = 0; errores.innerHTML = ''; estado.textContent = 'Empezando…'; lote( 0 ); } );
			} )();
			</script>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'wp_ajax_jb3_regen_lote', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'jb3_regen', '_wpnonce', false ) ) {
		wp_send_json_error( 'sin permiso' );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	$desde   = max( 0, (int) ( $_POST['desde'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$ids     = array_slice( jb3_regen_ids(), $desde, JB3_REGEN_LOTE );
	$ok      = 0;
	$errores = array();
	foreach ( $ids as $id ) {
		$orig = wp_get_original_image_path( $id );
		if ( ! $orig || ! file_exists( $orig ) ) {
			$errores[] = "#$id: no se encontró el archivo original";
			continue;
		}
		$meta = wp_create_image_subsizes( $orig, $id );
		if ( is_wp_error( $meta ) || empty( $meta['file'] ) ) {
			$errores[] = "#$id " . basename( $orig ) . ': ' . ( is_wp_error( $meta ) ? $meta->get_error_message() : 'no se pudo procesar' );
			continue;
		}
		wp_update_attachment_metadata( $id, $meta );
		$ok++;
	}
	wp_send_json_success( array( 'ok' => $ok, 'errores' => $errores, 'siguiente' => $desde + JB3_REGEN_LOTE ) );
} );
