<?php
/**
 * Render del bloque ACF "Módulo de Contenido" (jb3/modulo-contenido).
 * Ver _init_acf_blocks.php para el registro del bloque y los nombres de
 * campo esperados.
 */

defined( 'ABSPATH' ) || exit;

$tipos = array_filter( (array) get_field( 'mc_tipos' ) );
if ( ! $tipos ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>Módulo de Contenido: falta elegir al menos un tipo de contenido.</em></p>';
	}
	return;
}

// Sin canal elegido: sin filtro, todos los canales — mismo criterio que
// usa el resto del sitio con el contenido en sí (un post sin tag de canal
// se considera visible en todos). Nada de "auto-detectar" el canal de la
// página que contiene el bloque: eso le daría un significado distinto a
// "vacío" solo acá, inconsistente con todo lo demás.
$canal_ids = array();
$canales   = get_field( 'mc_canales' );
if ( $canales ) {
	$canal_ids = wp_list_pluck( (array) $canales, 'term_id' );
}

// Título/bajada genéricos + overrides por país: motor compartido
// (jb3_resolver_titulo_bajada_pais(), _init_acf_blocks.php) — mismo
// mecanismo que tenía el shortcode [jb3_modulo_override] viejo, pero con
// formulario en vez de shortcodes anidados.
$resuelto      = jb3_resolver_titulo_bajada_pais(
	(string) get_field( 'mc_titulo' ),
	(string) get_field( 'mc_bajada' ),
	(array) get_field( 'mc_overrides_pais' )
);
$titulo        = $resuelto['titulo'];
$bajada        = $resuelto['bajada'];
$pais_override = $resuelto['pais_override'];

// En el EDITOR se muestra una tarjeta resumen en vez de la grilla real: con
// varios módulos apilados, renderizar las imágenes completas de cada uno
// hace la página imposible de armar (no se distingue dónde empieza/termina
// cada bloque, no se puede agarrar para reordenar, tarda en cargar). En el
// sitio real ($is_preview === false) se imprime la grilla completa, como
// siempre. $is_preview lo pasa ACF solo, no hace falta calcularlo.
if ( $is_preview ) {
	$tipos_labels = array(
		'peliculas'       => 'Películas',
		'series'          => 'Series',
		'prod_originales' => 'Prod. Originales',
		'op_comerciales'  => 'Op. Comerciales',
		'videos'          => 'Videos',
		'integraciones'   => 'Integraciones',
	);
	$tipos_texto = implode( ', ', array_map(
		function ( $t ) use ( $tipos_labels ) {
			return $tipos_labels[ $t ] ?? $t;
		},
		$tipos
	) );
	$canales_texto = $canales ? implode( ', ', wp_list_pluck( (array) $canales, 'name' ) ) : 'Todos';
	?>
	<div style="border:2px dashed #999; border-radius:8px; padding:16px 20px; background:#f6f6f6; font-family:sans-serif;">
		<strong>📦 Módulo de Contenido</strong>
		<div style="margin-top:6px; font-size:13px; color:#444; line-height:1.6;">
			Tipo: <strong><?php echo esc_html( $tipos_texto ); ?></strong><br>
			Canal: <strong><?php echo esc_html( $canales_texto ); ?></strong><br>
			Título: <strong><?php echo esc_html( $titulo ?: '(sin completar)' ); ?></strong>
			<?php if ( $pais_override ) : ?>
				<br>Mostrando override de: <strong><?php echo esc_html( $pais_override ); ?></strong> (según "Ver como país…")
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
}

echo jb3_modulo_render(
	$tipos,
	$canal_ids,
	$titulo,
	$bajada,
	(int) get_field( 'mc_limite' ),
	(string) get_field( 'mc_boton_texto' ),
	(string) get_field( 'mc_boton_link' )
);
