<?php
/**
 * Plugin Name: JB3 Uso de IA
 * Description: Registra cada llamado que los plugins del sitio (hoy jb3-ai-search) hacen a Gemini/Claude, y muestra un panel en Herramientas → Uso de IA con pedidos OK/Error por día y motivos de error. No consulta ninguna API externa — solo cuenta lo que YA se llama; no puede mostrar el saldo/cuota oficial de Google/Anthropic (eso solo existe en sus propios paneles).
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

const JB3IA_USO_DIAS_HISTORIAL = 14; // cuántos días atrás muestra el gráfico principal.
const JB3IA_USO_MOTIVOS        = [ 'cuota', 'sin_clave', 'red', 'error' ]; // motivos de fallo que reportan los clientes de IA.

// ─── API pública — llamada por otros plugins ────────────────────────────────

/**
 * Registra un llamado a una IA. Pensado para llamarse SIEMPRE detrás de
 * function_exists('jb3ia_registrar_uso') — si este plugin está desactivado,
 * el que llama sigue funcionando igual, solo sin quedar registrado.
 *
 * @param string $proveedor 'gemini' | 'claude' | el que corresponda.
 * @param string $origen    Qué plugin/función hizo el llamado (ej. 'jb3-ai-search').
 * @param bool   $exito     true si la llamada devolvió un resultado usable.
 * @param string $motivo    Si $exito es false: 'cuota' | 'sin_clave' | 'red' | 'error' (u otro texto libre).
 */
function jb3ia_registrar_uso( string $proveedor, string $origen, bool $exito, string $motivo = '' ): void {
	$clave = 'jb3ia_uso_' . current_time( 'Y-m-d' );
	$datos = get_option( $clave, [] );
	if ( ! is_array( $datos ) ) {
		$datos = [];
	}

	if ( ! isset( $datos[ $proveedor ][ $origen ] ) ) {
		$datos[ $proveedor ][ $origen ] = [ 'ok' => 0, 'error' => 0, 'motivos' => [] ];
	}

	if ( $exito ) {
		++$datos[ $proveedor ][ $origen ]['ok'];
	} else {
		++$datos[ $proveedor ][ $origen ]['error'];
		$motivo = $motivo ?: 'error';
		if ( ! isset( $datos[ $proveedor ][ $origen ]['motivos'][ $motivo ] ) ) {
			$datos[ $proveedor ][ $origen ]['motivos'][ $motivo ] = 0;
		}
		++$datos[ $proveedor ][ $origen ]['motivos'][ $motivo ];
	}

	// autoload=no — esta opción se lee solo en una pantalla puntual de
	// Herramientas, no hace falta que WordPress la cargue en cada request.
	update_option( $clave, $datos, false );
}

// ─── Menú: Herramientas → Uso de IA ─────────────────────────────────────────

add_action( 'admin_menu', function () {
	add_management_page(
		__( 'Uso de IA', 'jb3-uso-ia' ),
		__( 'Uso de IA', 'jb3-uso-ia' ),
		'manage_options',
		'jb3-uso-ia',
		'jb3ia_render_pagina'
	);
} );

// ─── Agregación de datos para el panel ──────────────────────────────────────

/** @return array<string,array> Un registro por día (más reciente primero), ya sumado entre todos los proveedores/orígenes. */
function jb3ia_datos_por_dia( int $dias ): array {
	$resultado = [];
	for ( $i = 0; $i < $dias; $i++ ) {
		$fecha = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " -{$i} days" ) );
		$datos = get_option( 'jb3ia_uso_' . $fecha, [] );

		$ok    = 0;
		$error = 0;
		foreach ( (array) $datos as $por_origen ) {
			foreach ( (array) $por_origen as $fila ) {
				$ok    += (int) ( $fila['ok'] ?? 0 );
				$error += (int) ( $fila['error'] ?? 0 );
			}
		}
		$resultado[ $fecha ] = [ 'ok' => $ok, 'error' => $error ];
	}
	return $resultado;
}

/** @return array<string,int> Motivos de error sumados en los últimos $dias, para el gráfico de barras horizontales. */
function jb3ia_motivos_periodo( int $dias ): array {
	$totales = array_fill_keys( JB3IA_USO_MOTIVOS, 0 );
	for ( $i = 0; $i < $dias; $i++ ) {
		$fecha = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " -{$i} days" ) );
		$datos = get_option( 'jb3ia_uso_' . $fecha, [] );
		foreach ( (array) $datos as $por_origen ) {
			foreach ( (array) $por_origen as $fila ) {
				foreach ( (array) ( $fila['motivos'] ?? [] ) as $motivo => $cant ) {
					$totales[ $motivo ] = ( $totales[ $motivo ] ?? 0 ) + (int) $cant;
				}
			}
		}
	}
	return array_filter( $totales ); // saca los motivos en 0, no ensucian el gráfico.
}

/** @return array Desglose de HOY, para la tabla y las tarjetas — [proveedor][origen] => ['ok'=>N,'error'=>M]. */
function jb3ia_datos_hoy(): array {
	$datos = get_option( 'jb3ia_uso_' . current_time( 'Y-m-d' ), [] );
	return is_array( $datos ) ? $datos : [];
}

// ─── Render ──────────────────────────────────────────────────────────────────

function jb3ia_render_pagina(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sin permiso.', 'jb3-uso-ia' ) );
	}

	$por_dia       = jb3ia_datos_por_dia( JB3IA_USO_DIAS_HISTORIAL );
	$motivos_7d    = jb3ia_motivos_periodo( 7 );
	$hoy           = jb3ia_datos_hoy();

	$hoy_ok    = 0;
	$hoy_error = 0;
	foreach ( $hoy as $por_origen ) {
		foreach ( $por_origen as $fila ) {
			$hoy_ok    += (int) ( $fila['ok'] ?? 0 );
			$hoy_error += (int) ( $fila['error'] ?? 0 );
		}
	}
	$hoy_total = $hoy_ok + $hoy_error;
	$hoy_pct   = $hoy_total > 0 ? round( $hoy_ok / $hoy_total * 100 ) : null;

	$etiquetas_motivo = [
		'cuota'     => __( 'Cuota agotada', 'jb3-uso-ia' ),
		'sin_clave' => __( 'Falta la clave', 'jb3-uso-ia' ),
		'red'       => __( 'Error de red', 'jb3-uso-ia' ),
		'error'     => __( 'Otro error', 'jb3-uso-ia' ),
	];
	?>
	<style>
		.jb3ia-wrap { --jb3ia-good:#0ca30c; --jb3ia-critical:#d03b3b; --jb3ia-muted:#898781; --jb3ia-grid:#e1e0d9; --jb3ia-ink:#0b0b0b; --jb3ia-ink-2:#52514e; --jb3ia-surface:#fcfcfb; }
		.jb3ia-kpis { display:flex; gap:16px; margin:20px 0; flex-wrap:wrap; }
		.jb3ia-tile { background:var(--jb3ia-surface); border:1px solid var(--jb3ia-grid); padding:16px 20px; min-width:160px; }
		.jb3ia-tile .valor { font-size:28px; font-weight:600; color:var(--jb3ia-ink); font-variant-numeric:tabular-nums; }
		.jb3ia-tile .label { font-size:12px; color:var(--jb3ia-ink-2); margin-top:4px; }
		.jb3ia-leyenda { display:flex; gap:16px; font-size:13px; color:var(--jb3ia-ink-2); margin-bottom:8px; }
		.jb3ia-leyenda span { display:inline-flex; align-items:center; gap:6px; }
		.jb3ia-leyenda i { width:10px; height:10px; border-radius:2px; display:inline-block; }
		.jb3ia-chart-wrap { background:var(--jb3ia-surface); border:1px solid var(--jb3ia-grid); padding:20px; max-width:900px; }
		.jb3ia-bar-label { font-size:11px; fill:var(--jb3ia-muted); }
		.jb3ia-valor-label { font-size:11px; fill:var(--jb3ia-ink-2); font-variant-numeric:tabular-nums; }
		table.jb3ia-tabla { border-collapse:collapse; margin-top:24px; max-width:900px; }
		table.jb3ia-tabla th, table.jb3ia-tabla td { padding:6px 14px; border-bottom:1px solid var(--jb3ia-grid); font-size:13px; text-align:left; }
		table.jb3ia-tabla th { color:var(--jb3ia-ink-2); font-weight:600; }
		table.jb3ia-tabla td.num { font-variant-numeric:tabular-nums; text-align:right; }
	</style>
	<div class="wrap jb3ia-wrap">
		<h1><?php esc_html_e( 'Uso de IA', 'jb3-uso-ia' ); ?></h1>
		<p style="max-width:700px;color:var(--jb3ia-ink-2);">
			<?php esc_html_e( 'Cuenta los pedidos que este sitio hace a Gemini/Claude — no consulta a Google ni Anthropic, así que no muestra el saldo/cuota oficial de tu cuenta (eso solo está en sus propios paneles, ai.dev/rate-limit y console.anthropic.com).', 'jb3-uso-ia' ); ?>
		</p>

		<!-- KPIs de hoy -->
		<div class="jb3ia-kpis">
			<div class="jb3ia-tile">
				<div class="valor"><?php echo esc_html( $hoy_total ); ?></div>
				<div class="label"><?php esc_html_e( 'Pedidos hoy', 'jb3-uso-ia' ); ?></div>
			</div>
			<div class="jb3ia-tile">
				<div class="valor" style="color:<?php echo null === $hoy_pct || $hoy_pct >= 90 ? 'var(--jb3ia-good)' : 'var(--jb3ia-critical)'; ?>;">
					<?php echo null === $hoy_pct ? '—' : esc_html( $hoy_pct . '%' ); ?>
				</div>
				<div class="label"><?php esc_html_e( 'Éxito hoy', 'jb3-uso-ia' ); ?></div>
			</div>
			<div class="jb3ia-tile">
				<div class="valor"><?php echo esc_html( $hoy_error ); ?></div>
				<div class="label"><?php esc_html_e( 'Errores hoy', 'jb3-uso-ia' ); ?></div>
			</div>
		</div>

		<!-- Gráfico: pedidos por día, últimos 14 días -->
		<h2><?php esc_html_e( 'Pedidos por día', 'jb3-uso-ia' ); ?></h2>
		<div class="jb3ia-leyenda">
			<span><i style="background:var(--jb3ia-good);"></i> <?php esc_html_e( 'OK', 'jb3-uso-ia' ); ?></span>
			<span><i style="background:var(--jb3ia-critical);"></i> <?php esc_html_e( 'Error', 'jb3-uso-ia' ); ?></span>
		</div>
		<div class="jb3ia-chart-wrap">
			<?php jb3ia_svg_barras_apiladas( $por_dia ); ?>
		</div>

		<!-- Gráfico: motivos de error, últimos 7 días -->
		<?php if ( ! empty( $motivos_7d ) ) : ?>
			<h2 style="margin-top:32px;"><?php esc_html_e( 'Motivos de error (últimos 7 días)', 'jb3-uso-ia' ); ?></h2>
			<div class="jb3ia-chart-wrap">
				<?php jb3ia_svg_barras_horizontales( $motivos_7d, $etiquetas_motivo ); ?>
			</div>
		<?php endif; ?>

		<!-- Tabla: detalle de hoy por proveedor/plugin -->
		<h2 style="margin-top:32px;"><?php esc_html_e( 'Detalle de hoy, por proveedor y plugin', 'jb3-uso-ia' ); ?></h2>
		<?php if ( empty( $hoy ) ) : ?>
			<p style="color:var(--jb3ia-ink-2);"><?php esc_html_e( 'Todavía no se registró ningún pedido hoy.', 'jb3-uso-ia' ); ?></p>
		<?php else : ?>
			<table class="jb3ia-tabla">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Proveedor', 'jb3-uso-ia' ); ?></th>
						<th><?php esc_html_e( 'Plugin', 'jb3-uso-ia' ); ?></th>
						<th class="num"><?php esc_html_e( 'OK', 'jb3-uso-ia' ); ?></th>
						<th class="num"><?php esc_html_e( 'Error', 'jb3-uso-ia' ); ?></th>
						<th><?php esc_html_e( 'Motivos de error', 'jb3-uso-ia' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $hoy as $proveedor => $por_origen ) : ?>
					<?php foreach ( $por_origen as $origen => $fila ) : ?>
						<tr>
							<td><?php echo esc_html( ucfirst( $proveedor ) ); ?></td>
							<td><?php echo esc_html( $origen ); ?></td>
							<td class="num"><?php echo esc_html( $fila['ok'] ?? 0 ); ?></td>
							<td class="num"><?php echo esc_html( $fila['error'] ?? 0 ); ?></td>
							<td>
								<?php
								$partes = [];
								foreach ( (array) ( $fila['motivos'] ?? [] ) as $motivo => $cant ) {
									$partes[] = ( $etiquetas_motivo[ $motivo ] ?? $motivo ) . ': ' . $cant;
								}
								echo esc_html( $partes ? implode( ', ', $partes ) : '—' );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Barras apiladas (OK + Error) por día — SVG plano, sin librerías. Anchos/altos
 * fijos, pensado para 14 días — con más días habría que hacerlo responsive,
 * no hace falta para este panel interno.
 */
function jb3ia_svg_barras_apiladas( array $por_dia ): void {
	$dias      = array_reverse( $por_dia, true ); // más viejo primero, se lee izquierda -> derecha.
	$max       = max( 1, ...array_values( array_map( fn( $d ) => $d['ok'] + $d['error'], $dias ) ) );
	$ancho_svg = 860;
	$alto_svg  = 220;
	$alto_barras = 160;
	$n         = max( 1, count( $dias ) );
	$gap       = 8;
	$ancho_barra = ( $ancho_svg - ( $gap * ( $n - 1 ) ) ) / $n;

	echo '<svg viewBox="0 0 ' . esc_attr( $ancho_svg ) . ' ' . esc_attr( $alto_svg ) . '" width="100%" style="max-width:' . esc_attr( $ancho_svg ) . 'px;">';

	// línea base
	echo '<line x1="0" y1="' . esc_attr( $alto_barras ) . '" x2="' . esc_attr( $ancho_svg ) . '" y2="' . esc_attr( $alto_barras ) . '" stroke="var(--jb3ia-grid)" stroke-width="1" />';

	$x = 0;
	foreach ( $dias as $fecha => $valores ) {
		$total = $valores['ok'] + $valores['error'];
		$h_ok    = $total > 0 ? round( $valores['ok'] / $max * $alto_barras ) : 0;
		$h_error = $total > 0 ? round( $valores['error'] / $max * $alto_barras ) : 0;

		$y_error = $alto_barras - $h_error;
		$y_ok    = $y_error - $h_ok;

		$fecha_label = esc_html( date_i18n( 'd/m', strtotime( $fecha ) ) );

		echo '<g>';
		echo '<title>' . esc_html( $fecha ) . ' — OK: ' . esc_html( $valores['ok'] ) . ', Error: ' . esc_html( $valores['error'] ) . '</title>';
		if ( $h_error > 0 ) {
			echo '<rect x="' . esc_attr( $x ) . '" y="' . esc_attr( $y_error ) . '" width="' . esc_attr( $ancho_barra ) . '" height="' . esc_attr( $h_error ) . '" fill="var(--jb3ia-critical)" rx="2" />';
		}
		if ( $h_ok > 0 ) {
			echo '<rect x="' . esc_attr( $x ) . '" y="' . esc_attr( $y_ok ) . '" width="' . esc_attr( $ancho_barra ) . '" height="' . esc_attr( $h_ok ) . '" fill="var(--jb3ia-good)" rx="2" />';
		}
		if ( $total > 0 ) {
			echo '<text x="' . esc_attr( $x + $ancho_barra / 2 ) . '" y="' . esc_attr( $y_ok - 4 ) . '" text-anchor="middle" class="jb3ia-valor-label">' . esc_html( $total ) . '</text>';
		}
		echo '<text x="' . esc_attr( $x + $ancho_barra / 2 ) . '" y="' . esc_attr( $alto_barras + 16 ) . '" text-anchor="middle" class="jb3ia-bar-label">' . $fecha_label . '</text>';
		echo '</g>';

		$x += $ancho_barra + $gap;
	}

	echo '</svg>';
}

/** Barras horizontales — para "motivos de error", más fácil de comparar que un donut cuando importa la cantidad exacta. */
function jb3ia_svg_barras_horizontales( array $totales, array $etiquetas ): void {
	arsort( $totales );
	$max        = max( 1, ...array_values( $totales ) );
	$ancho_svg  = 700;
	$alto_fila  = 32;
	$alto_svg   = $alto_fila * count( $totales );
	$ancho_max_barra = 420;
	$x_barra    = 140;

	echo '<svg viewBox="0 0 ' . esc_attr( $ancho_svg ) . ' ' . esc_attr( $alto_svg ) . '" width="100%" style="max-width:' . esc_attr( $ancho_svg ) . 'px;">';

	$y = 0;
	foreach ( $totales as $motivo => $cant ) {
		$ancho = round( $cant / $max * $ancho_max_barra );
		$label = $etiquetas[ $motivo ] ?? $motivo;

		echo '<g>';
		echo '<title>' . esc_html( $label ) . ': ' . esc_html( $cant ) . '</title>';
		echo '<text x="0" y="' . esc_attr( $y + $alto_fila / 2 + 4 ) . '" class="jb3ia-bar-label" style="font-size:12px;">' . esc_html( $label ) . '</text>';
		echo '<rect x="' . esc_attr( $x_barra ) . '" y="' . esc_attr( $y + 6 ) . '" width="' . esc_attr( max( 2, $ancho ) ) . '" height="' . esc_attr( $alto_fila - 12 ) . '" fill="var(--jb3ia-critical)" rx="2" />';
		echo '<text x="' . esc_attr( $x_barra + $ancho + 8 ) . '" y="' . esc_attr( $y + $alto_fila / 2 + 4 ) . '" class="jb3ia-valor-label">' . esc_html( $cant ) . '</text>';
		echo '</g>';

		$y += $alto_fila;
	}

	echo '</svg>';
}
