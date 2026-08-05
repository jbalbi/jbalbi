<?php
/**
 * Plugin Name: JB3 Content Expiration
 * Description: Fecha y hora opcional de despublicación automática (fecha_out) para Entradas, Páginas y los CPT del sitio. Sin fecha_out, el contenido queda publicado hasta que se despublique a mano.
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

const JB3_EXP_META      = '_jb3_fecha_out';
const JB3_EXP_NONCE     = 'jb3_fecha_out_nonce';
const JB3_EXP_CRON_HOOK = 'jb3_expirar_contenido';

/** Tipos de contenido con fecha_out disponible: Entradas, Páginas y los 6 CPT del sitio. */
function jb3_exp_post_types(): array {
	return [ 'post', 'page', 'videos', 'op_comerciales', 'series', 'peliculas', 'prod_originales', 'integraciones' ];
}

// ─── METABOX: fecha_out en la barra lateral de edición ─────────────────────
add_action( 'add_meta_boxes', function () {
	foreach ( jb3_exp_post_types() as $post_type ) {
		add_meta_box(
			'jb3_fecha_out',
			'Despublicación programada',
			'jb3_exp_render_metabox',
			$post_type,
			'side',
			'high'
		);
	}
} );

// La prioridad "high" no alcanza para pegarla al botón de Publicar: Canales/
// Países (los checkbox de taxonomía) también quedan ahí, y como se registran
// antes que la nuestra, salen primero igual. Se reordena a mano, corriendo
// esto DESPUÉS de que todo ya se registró (prioridad 999 en el mismo hook
// "add_meta_boxes"): se saca la caja de donde haya quedado y se reinserta
// justo después de "submitdiv" (la caja nativa de Publicar), sea cual sea
// su prioridad.
add_action( 'add_meta_boxes', function ( $post_type ) {
	if ( ! in_array( $post_type, jb3_exp_post_types(), true ) ) {
		return;
	}

	global $wp_meta_boxes;
	if ( empty( $wp_meta_boxes[ $post_type ]['side'] ) ) {
		return;
	}

	$ours = null;
	foreach ( $wp_meta_boxes[ $post_type ]['side'] as &$boxes ) {
		if ( isset( $boxes['jb3_fecha_out'] ) ) {
			$ours = $boxes['jb3_fecha_out'];
			unset( $boxes['jb3_fecha_out'] );
			break;
		}
	}
	unset( $boxes );

	if ( ! $ours ) {
		return;
	}

	foreach ( $wp_meta_boxes[ $post_type ]['side'] as &$boxes ) {
		if ( ! isset( $boxes['submitdiv'] ) ) {
			continue;
		}
		$reordenadas = [];
		foreach ( $boxes as $id => $box ) {
			$reordenadas[ $id ] = $box;
			if ( 'submitdiv' === $id ) {
				$reordenadas['jb3_fecha_out'] = $ours;
			}
		}
		$boxes = $reordenadas;
		return;
	}
	unset( $boxes );

	// Fallback si no se encontró submitdiv (no debería pasar): la dejamos
	// primera en "high" en vez de perderla.
	$wp_meta_boxes[ $post_type ]['side']['high'] = [ 'jb3_fecha_out' => $ours ] + ( $wp_meta_boxes[ $post_type ]['side']['high'] ?? [] );
}, 999 );

function jb3_exp_render_metabox( WP_Post $post ): void {
	wp_nonce_field( 'jb3_fecha_out_save', JB3_EXP_NONCE );

	// Hora por defecto 23:59: mismo criterio que fecha_out en el slider de
	// Home (_shortcode_home_slider.php), que compara por día ("today <=
	// fecha_out") — el día elegido queda vigente completo, hasta el final,
	// no se corta al arrancar.
	$valor = get_post_meta( $post->ID, JB3_EXP_META, true );
	[ $fecha, $hora ] = $valor ? explode( 'T', $valor ) : [ '', '23:59' ];
	?>
	<p>
		<label for="jb3_fecha_out_fecha"><strong>Fecha y hora de baja</strong></label>
	</p>
	<p>
		<input type="date" id="jb3_fecha_out_fecha" name="jb3_fecha_out_fecha" value="<?php echo esc_attr( $fecha ); ?>" style="width:100%; box-sizing:border-box;">
	</p>
	<p>
		<input type="time" id="jb3_fecha_out_hora" name="jb3_fecha_out_hora" value="<?php echo esc_attr( $hora ); ?>" style="width:100%; box-sizing:border-box;">
	</p>
	<p class="description">
		Vacío = no se despublica sola, queda publicada hasta que la bajes a mano.
		<?php if ( $valor ) : ?>
			— <a href="#" id="jb3_fecha_out_vaciar">Vaciar</a>
		<?php endif; ?>
	</p>
	<script>
	( function () {
		var vaciar = document.getElementById( 'jb3_fecha_out_vaciar' );
		if ( vaciar ) {
			vaciar.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				document.getElementById( 'jb3_fecha_out_fecha' ).value = '';
				document.getElementById( 'jb3_fecha_out_hora' ).value  = '';
			} );
		}
	} )();
	</script>
	<?php
}

// ─── GUARDADO: programa/reprograma/cancela el evento de WP-Cron ────────────

add_action( 'save_post', 'jb3_exp_save', 10, 2 );
function jb3_exp_save( int $post_id, WP_Post $post ): void {
	if ( ! in_array( $post->post_type, jb3_exp_post_types(), true ) ) {
		return;
	}
	if ( ! isset( $_POST[ JB3_EXP_NONCE ] ) || ! wp_verify_nonce( $_POST[ JB3_EXP_NONCE ], 'jb3_fecha_out_save' ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Se cancela siempre el evento anterior antes de decidir si hace falta uno
	// nuevo — evita que quede un cron viejo programado si se vacía el campo o
	// se cambia la fecha.
	wp_clear_scheduled_hook( JB3_EXP_CRON_HOOK, [ $post_id ] );

	$fecha = sanitize_text_field( $_POST['jb3_fecha_out_fecha'] ?? '' );
	$hora  = sanitize_text_field( $_POST['jb3_fecha_out_hora'] ?? '' );

	if ( ! $fecha || ! $hora ) {
		delete_post_meta( $post_id, JB3_EXP_META );
		return;
	}

	$fecha_out = $fecha . 'T' . $hora;

	update_post_meta( $post_id, JB3_EXP_META, $fecha_out );

	// Fecha y hora quedan en hora local del sitio (Ajustes > General > Zona
	// horaria) — wp_schedule_single_event necesita el timestamp en GMT,
	// igual que usa el propio WP para las entradas programadas a futuro.
	$timestamp_gmt = strtotime( get_gmt_from_date( $fecha_out ) . ' UTC' );

	if ( $timestamp_gmt && $timestamp_gmt > time() ) {
		wp_schedule_single_event( $timestamp_gmt, JB3_EXP_CRON_HOOK, [ $post_id ] );
	}
}

// ─── CRON: pasa el contenido vencido a Borrador ─────────────────────────────

add_action( JB3_EXP_CRON_HOOK, function ( int $post_id ) {
	$post = get_post( $post_id );

	// Chequeo defensivo: si entre que se programó el evento y que corrió el
	// cron alguien ya lo despublicó/borró a mano, no hay nada que hacer acá.
	if ( ! $post || 'publish' !== $post->post_status ) {
		return;
	}

	wp_update_post( [
		'ID'          => $post_id,
		'post_status' => 'draft',
	] );
} );

// ─── LIMPIEZA: cancelar el cron si el post se borra o se manda a la papelera ──

add_action( 'before_delete_post', 'jb3_exp_unschedule' );
add_action( 'wp_trash_post', 'jb3_exp_unschedule' );
function jb3_exp_unschedule( int $post_id ): void {
	wp_clear_scheduled_hook( JB3_EXP_CRON_HOOK, [ $post_id ] );
}
