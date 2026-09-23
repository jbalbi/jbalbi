<?php
/**
 * Plugin Name: JB3 Contenido IA
 * Description: Campo "ID de IMDb" + botones "Importar sinopsis" / "Importar información" en Películas, Series y Prod. Originales — buscan la info real en la web (vía Claude) y la dejan reescrita en los campos WYSIWYG existentes (Sinopsis / Información del módulo "Contenido") para que el editor la revise antes de guardar. Nunca publica solo.
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

define( 'JBCIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'JBCIA_URL', plugin_dir_url( __FILE__ ) );

require_once JBCIA_DIR . 'includes/class-claude-client.php';
require_once JBCIA_DIR . 'includes/class-gemini-client.php';

const JBCIA_POST_TYPES        = [ 'peliculas', 'series', 'prod_originales' ];
const JBCIA_THROTTLE_KEY      = 'jbcia_throttle_global';
const JBCIA_THROTTLE_MAX      = 50; // llamadas a la API por hora, para todo el sitio — carga de contenido, no trafico de visitantes.

/**
 * ─── PROVEEDOR DE IA: interruptor único ─────────────────────────────────────
 * 'gemini' por ahora — pedido explícito 2026-09-11: sin presupuesto asignado
 * todavía para Claude (pago por uso), mientras que Gemini ya tiene una clave
 * cargada. Mismo criterio que JB3_AISEARCH_PROVIDER en jb3-ai-search.php.
 *
 * OJO — confirmado en vivo el mismo día: la clave de Gemini devolvió 429
 * "RESOURCE_EXHAUSTED" en la primera prueba de este plugin (cuota agotada a
 * nivel de cuenta/proyecto, no de esta clave puntual, o un límite propio y
 * más chico para `google_search` en el nivel gratuito — no se pudo
 * distinguir cuál de las dos cosas es). El cliente de Gemini de este plugin
 * quedó escrito pero SIN verificar en vivo por este motivo — probarlo de
 * nuevo cuando la cuota se libere (suele resetear una vez por día) antes de
 * darlo por andando.
 *
 * El día que haya presupuesto para Claude: cambiar este valor a 'claude' y
 * cargar 'claude_api_key' en jb3-search-cfg.php (ya está el campo, vacío).
 * Los dos clientes exponen exactamente los mismos métodos
 * (obtener_sinopsis()/obtener_informacion()), nada más de este plugin
 * necesita tocarse para el cambio.
 */
const JBCIA_PROVIDER = 'gemini';

// ─── Campo ACF: ID de IMDb ─────────────────────────────────────────────────
// Grupo propio, registrado en PHP (no en la base de datos como el resto de
// los grupos ACF de este sitio) — si el plugin se desactiva, el campo
// desaparece limpio, sin dejar nada suelto en los grupos existentes de
// "Contenido" (Sinopsis/Información), que este plugin no toca.

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( [
		'key'      => 'group_jbcia_imdb_id',
		'title'    => 'Importar desde IMDb (IA)',
		'fields'   => [
			[
				'key'           => 'field_jbcia_imdb_id',
				'label'         => 'ID de IMDb',
				'name'          => 'imdb_id',
				'type'          => 'text',
				'instructions'  => 'Ej: tt1234567 — lo encontrás en la URL de la página de IMDb (imdb.com/title/tt1234567).',
				'placeholder'   => 'tt1234567',
			],
		],
		'location' => [
			[ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'peliculas' ] ],
			[ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'series' ] ],
			[ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'prod_originales' ] ],
		],
		// 'acf_after_title' — opción especial de ACF que dibuja el campo pegado
		// debajo del título, ANTES de todo lo demás (antes del editor de
		// contenido y de "Contenido") — pedido explícito 2026-09-11: que
		// quede al principio de todo, no perdido en la barra lateral ni
		// mezclado con otras cajas de la columna principal.
		'position' => 'acf_after_title',
		'style'    => 'default',
	] );
} );

// Botones inyectados DENTRO de cada bloque Sinopsis/Información del módulo
// "Contenido" — pedido explícito 2026-09-11: no en un campo aparte, sino
// pegados al campo WYSIWYG que van a llenar. Aditivo vía acf/render_field/
// key=X (corre DESPUÉS de que ACF ya dibujó el editor WYSIWYG para esa
// key puntual — no reemplaza nada, solo agrega el botón debajo).
//
// Keys reales (viven en la base de datos, no en código — consultadas en
// vivo con acf_get_field_groups()/acf_get_fields(), no hay forma de
// versionarlas): "Contenido de la película/serie" (peliculas/series) usa el
// prefijo field_jb3_, "Contenido de Prod. Originales" usa field_jb3o_. Si
// algún día se recrean esos grupos desde cero, estas keys van a cambiar y
// hay que volver a consultarlas.
foreach ( [
	'field_jb3_sinopsis_texto'     => 'sinopsis',
	'field_jb3_informacion_texto'  => 'informacion',
	'field_jb3o_sinopsis_texto'    => 'sinopsis',
	'field_jb3o_informacion_texto' => 'informacion',
] as $field_key => $accion ) {
	add_action( "acf/render_field/key={$field_key}", function () use ( $accion ) {
		jbcia_render_boton( $accion );
	} );
}

function jbcia_render_boton( string $accion ): void {
	$label = 'sinopsis' === $accion
		? __( 'Importar sinopsis (IA)', 'jb3-contenido-ia' )
		: __( 'Importar información (IA)', 'jb3-contenido-ia' );
	?>
	<div class="jbcia-boton-wrap" style="margin-top:8px;">
		<button type="button" class="button jbcia-btn" data-accion="<?php echo esc_attr( $accion ); ?>">
			<?php echo esc_html( $label ); ?>
		</button>
		<span class="jbcia-status" style="margin-left:10px;font-size:12px;"></span>
	</div>
	<?php
}

// ─── Assets: solo en pantallas de edicion de los 3 post types ──────────────

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, JBCIA_POST_TYPES, true ) ) {
		return;
	}

	wp_enqueue_script(
		'jbcia-admin',
		JBCIA_URL . 'admin/admin.js',
		[ 'jquery', 'acf-input' ],
		file_exists( JBCIA_DIR . 'admin/admin.js' ) ? filemtime( JBCIA_DIR . 'admin/admin.js' ) : '1.0',
		true
	);

	wp_add_inline_script(
		'jbcia-admin',
		'var jbciaData = ' . wp_json_encode( [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jbcia_ajax' ),
			'postId'  => get_the_ID(),
		] ) . ';',
		'before'
	);
} );

// ─── AJAX: Importar sinopsis ────────────────────────────────────────────────

add_action( 'wp_ajax_jbcia_import_sinopsis', function () {
	check_ajax_referer( 'jbcia_ajax', 'nonce' );

	$post_id = absint( $_POST['post_id'] ?? 0 );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'Sin permiso.', 'jb3-contenido-ia' ) );
	}

	$imdb_id = jbcia_sanitize_imdb_id( $_POST['imdb_id'] ?? '' );
	if ( ! $imdb_id ) {
		wp_send_json_error( __( 'Ingresá un ID de IMDb válido (ej: tt1234567).', 'jb3-contenido-ia' ) );
	}

	if ( ! jbcia_throttle_ok() ) {
		wp_send_json_error( __( 'Se alcanzó el límite de pedidos por hora — probá de nuevo más tarde.', 'jb3-contenido-ia' ) );
	}

	$titulo    = get_the_title( $post_id );
	$sinopsis  = jbcia_obtener_sinopsis( $imdb_id, $titulo );

	if ( null === $sinopsis ) {
		wp_send_json_error( jbcia_mensaje_error( $imdb_id ) );
	}

	wp_send_json_success( [ 'html' => wp_kses_post( $sinopsis ) ] );
} );

// ─── AJAX: Importar información (director, reparto, año, duración, género) ──

add_action( 'wp_ajax_jbcia_import_informacion', function () {
	check_ajax_referer( 'jbcia_ajax', 'nonce' );

	$post_id = absint( $_POST['post_id'] ?? 0 );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'Sin permiso.', 'jb3-contenido-ia' ) );
	}

	$imdb_id = jbcia_sanitize_imdb_id( $_POST['imdb_id'] ?? '' );
	if ( ! $imdb_id ) {
		wp_send_json_error( __( 'Ingresá un ID de IMDb válido (ej: tt1234567).', 'jb3-contenido-ia' ) );
	}

	if ( ! jbcia_throttle_ok() ) {
		wp_send_json_error( __( 'Se alcanzó el límite de pedidos por hora — probá de nuevo más tarde.', 'jb3-contenido-ia' ) );
	}

	$titulo = get_the_title( $post_id );
	$datos  = jbcia_obtener_informacion( $imdb_id, $titulo );

	if ( null === $datos ) {
		wp_send_json_error( jbcia_mensaje_error( $imdb_id ) );
	}

	wp_send_json_success( [ 'html' => wp_kses_post( jbcia_formatear_informacion( $datos ) ) ] );
} );

// ─── Único punto de llamada — el resto del plugin no sabe qué proveedor ─────
// está activo (mismo criterio que jb3_aisearch_extraer_filtros() en jb3-ai-search).

function jbcia_obtener_sinopsis( string $imdb_id, string $titulo ): ?string {
	if ( 'claude' === JBCIA_PROVIDER ) {
		return JBCIA_Claude_Client::obtener_sinopsis( $imdb_id, $titulo );
	}
	return JBCIA_Gemini_Client::obtener_sinopsis( $imdb_id, $titulo );
}

function jbcia_obtener_informacion( string $imdb_id, string $titulo ): ?array {
	if ( 'claude' === JBCIA_PROVIDER ) {
		return JBCIA_Claude_Client::obtener_informacion( $imdb_id, $titulo );
	}
	return JBCIA_Gemini_Client::obtener_informacion( $imdb_id, $titulo );
}

/**
 * Guarda/lee el MOTIVO del último fallo de la IA — los clientes (Claude,
 * Gemini) lo van seteando en cada punto de fallo de hacer_pedido()/pedir().
 * Sin esto, "cuota agotada", "falta la clave", y "de verdad no encontró
 * nada" mostraban el mismo mensaje ("revisá que el ID sea correcto"),
 * confundiendo al editor cuando el problema era otra cosa por completo
 * (confirmado en vivo 2026-09-11: un 429 de cuota de Gemini se veía
 * indistinguible de un ID mal escrito).
 *
 * @param string|null $set Si se pasa, fija el motivo (llamado por los
 *                          clientes). Si se omite, solo lo lee.
 */
function jbcia_ultimo_error( ?string $set = null ): string {
	static $valor = '';
	if ( null !== $set ) {
		$valor = $set;
	}
	return $valor;
}

function jbcia_mensaje_error( string $imdb_id ): string {
	switch ( jbcia_ultimo_error() ) {
		case 'cuota':
			return __( 'Se alcanzó el límite de uso gratuito de la IA por ahora — probá de nuevo más tarde (suele liberarse en 24hs).', 'jb3-contenido-ia' );
		case 'sin_clave':
			return __( 'Falta configurar la clave de la IA en el servidor — avisale al administrador del sitio.', 'jb3-contenido-ia' );
		case 'red':
			return __( 'Error de conexión al buscar la información — probá de nuevo en un momento.', 'jb3-contenido-ia' );
		default:
			return sprintf(
				/* translators: %s: ID de IMDb */
				__( 'No se pudo encontrar información para %s — revisá que el ID sea correcto.', 'jb3-contenido-ia' ),
				$imdb_id
			);
	}
}

// ─── Helpers ────────────────────────────────────────────────────────────────

/** @return string ID normalizado (ej. "tt1234567") o '' si no matchea el formato de IMDb. */
function jbcia_sanitize_imdb_id( $raw ): string {
	$raw = strtolower( trim( sanitize_text_field( wp_unslash( $raw ) ) ) );
	return preg_match( '/^tt\d{6,10}$/', $raw ) ? $raw : '';
}

function jbcia_throttle_ok(): bool {
	$conteo = (int) get_transient( JBCIA_THROTTLE_KEY );
	if ( $conteo >= JBCIA_THROTTLE_MAX ) {
		return false;
	}
	set_transient( JBCIA_THROTTLE_KEY, $conteo + 1, HOUR_IN_SECONDS );
	return true;
}

/** Arma el HTML final a partir de los datos estructurados — determinístico, no depende de cómo formatee el modelo. */
function jbcia_formatear_informacion( array $datos ): string {
	$filas = [
		'Director'  => $datos['director']  ?? '',
		'Reparto'   => ! empty( $datos['reparto'] ) ? implode( ', ', (array) $datos['reparto'] ) : '',
		'Año'       => $datos['anio']      ?? '',
		'Duración'  => $datos['duracion']  ?? '',
		'Género'    => ! empty( $datos['genero'] ) ? implode( ', ', (array) $datos['genero'] ) : '',
	];

	$html = '';
	foreach ( $filas as $etiqueta => $valor ) {
		if ( '' === (string) $valor ) {
			continue;
		}
		$html .= '<p><strong>' . esc_html( $etiqueta ) . ':</strong> ' . esc_html( $valor ) . '</p>';
	}
	return $html;
}

/**
 * Log propio, sin depender de que WP_DEBUG_LOG este bien encadenado en el
 * hosting — mismo criterio que jb3_aisearch_debug_log() (jb3-ai-search).
 */
function jbcia_debug_log( string $mensaje ): void {
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}
	$linea = '[' . current_time( 'mysql' ) . '] ' . $mensaje . "\n";
	file_put_contents( WP_CONTENT_DIR . '/jb3-contenido-ia-debug.log', $linea, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}

/**
 * Credenciales — mismo archivo compartido que ya usa jb3-ai-search
 * (fuera de wp-content, al lado de wp-config.php, nunca se commitea).
 * Se leen las MISMAS claves ('gemini_api_key'/'claude_api_key') que ese
 * plugin ya declara en su formato — no se agrega un archivo de config nuevo.
 *
 * @param string $proveedor 'gemini' o 'claude'.
 */
function jbcia_api_key( string $proveedor ): string {
	$path = ABSPATH . 'jb3-search-cfg.php';
	if ( ! file_exists( $path ) ) {
		return '';
	}
	$data = include $path;
	$key  = $proveedor . '_api_key';
	return is_array( $data ) && ! empty( $data[ $key ] ) ? (string) $data[ $key ] : '';
}
