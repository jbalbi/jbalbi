<?php
/**
 * Plugin Name: JB3 Buscador IA
 * Description: Buscador en lenguaje natural: un LLM traduce la pregunta a filtros estructurados (tipo de contenido, canal, etiqueta, texto libre) y esos filtros arman una WP_Query normal — el país siempre sale de la geolocalización del visitante, nunca de lo que el modelo interpreta. Reemplaza la búsqueda nativa del sitio automáticamente (vía pre_get_posts, sin tocar el tema — se revierte solo al desactivar) y suma el shortcode [jb3_buscador_ia] para usarlo aparte.
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

const JB3_AISEARCH_POST_TYPES   = [ 'peliculas', 'series', 'prod_originales', 'op_comerciales', 'videos', 'integraciones' ];
const JB3_AISEARCH_NONCE        = 'jb3_ai_search';
const JB3_AISEARCH_THROTTLE_KEY = 'jb3_ai_search_throttle_';
const JB3_AISEARCH_THROTTLE_MAX = 8; // búsquedas por IP por minuto (solo el shortcode/AJAX) — freno a abuso obvio, no un rate-limit fino.
// Tope agregado a nivel SITIO, sin importar de dónde vengan los pedidos —
// el de arriba es por IP y alguien con varias IPs (proxies) podría
// esquivarlo; esto pone un piso duro al gasto total del proveedor de IA
// por hora. Se aplica de forma centralizada en jb3_aisearch_extraer_filtros()
// acá abajo, así cubre TANTO el shortcode/AJAX COMO la búsqueda nativa del
// sitio (class-native-search.php) sin tener que repetirlo en cada lugar.
const JB3_AISEARCH_THROTTLE_GLOBAL_MAX = 300; // llamadas a la API por hora, para todo el sitio.
const JB3_AISEARCH_CHAT_HISTORIAL_MAX  = 4; // intercambios (pregunta/respuesta) que el chat bubble manda como contexto — ver class-chat-ajax.php.

/**
 * ─── PROVEEDOR DE IA: interruptor único ─────────────────────────────────────
 * 'gemini' hoy porque la API de Gemini tiene capa gratuita real (Anthropic y
 * OpenAI no) y arrancamos el proyecto sin presupuesto para esto. El día que
 * se quiera pasar a Claude (mejor calidad esperable, o ya en producción con
 * presupuesto asignado) alcanza con:
 *   1) cambiar este valor a 'claude'
 *   2) cargar 'claude_api_key' en el archivo de credenciales (ver
 *      jb3_aisearch_api_key() acá abajo) — se genera en console.anthropic.com
 * Los dos clientes (class-client-gemini.php / class-client-claude.php)
 * exponen exactamente el mismo método (extraer_filtros()) y devuelven el
 * mismo formato — nada más de este plugin necesita tocarse para el cambio.
 *
 * Si más adelante se escala a búsqueda semántica (embeddings) en vez de
 * "LLM → filtros estructurados" — la Opción B que se descartó al arrancar
 * por no hacer falta con el volumen de contenido actual — este archivo es
 * el lugar natural para sumar un tercer modo, sin tocar class-search.php
 * (que ya recibe los filtros ya resueltos, no le importa de dónde salieron).
 */
const JB3_AISEARCH_PROVIDER = 'gemini';

// define(), no const: plugin_dir_url() es una llamada a función, no una
// expresión constante en tiempo de compilación.
define( 'JB3_AISEARCH_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-schema.php';
require_once __DIR__ . '/includes/class-client-claude.php';
require_once __DIR__ . '/includes/class-client-gemini.php';
require_once __DIR__ . '/includes/class-search.php';
require_once __DIR__ . '/includes/class-native-search.php';
require_once __DIR__ . '/includes/class-ajax.php';
require_once __DIR__ . '/includes/shortcode.php';
require_once __DIR__ . '/includes/class-chat.php';
require_once __DIR__ . '/includes/class-chat-ajax.php';

/**
 * Único punto de llamada — el resto del plugin no sabe (ni le importa) qué
 * proveedor está activo. También el único lugar donde se aplica el freno
 * global (ver JB3_AISEARCH_THROTTLE_GLOBAL_MAX) — así cubre a CUALQUIER
 * llamador presente o futuro (shortcode/AJAX y búsqueda nativa hoy) sin
 * depender de que cada uno se acuerde de chequearlo por su cuenta. Si el
 * tope está superado, devuelve null — exactamente lo mismo que "la API
 * falló": cada llamador ya sabe degradar a búsqueda de texto simple.
 */
function jb3_aisearch_extraer_filtros( string $pregunta ): ?array {
	if ( ! jb3_aisearch_throttle_global_ok() ) {
		return null;
	}

	if ( 'claude' === JB3_AISEARCH_PROVIDER ) {
		return JB3_AISearch_Client_Claude::extraer_filtros( $pregunta );
	}
	return JB3_AISearch_Client_Gemini::extraer_filtros( $pregunta );
}

function jb3_aisearch_throttle_global_ok(): bool {
	$key    = 'jb3_aisearch_throttle_global';
	$conteo = (int) get_transient( $key );
	if ( $conteo >= JB3_AISEARCH_THROTTLE_GLOBAL_MAX ) {
		return false;
	}

	set_transient( $key, $conteo + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * Credenciales — fuera de wp-content (y del repo git), mismo criterio que
 * jb3-sc-stats.php (JB3_Google_Sheets en jb3-members): ABSPATH . 'jb3-search-cfg.php',
 * al lado de wp-config.php, nunca se commitea. Un solo archivo con las
 * claves de los DOS proveedores adentro (así cambiar JB3_AISEARCH_PROVIDER
 * más adelante no exige tocar nada más), formato:
 *
 *   <?php
 *   return [
 *       'gemini_api_key' => '...',
 *       'claude_api_key' => '...', // opcional hasta que se use
 *   ];
 *
 * @param string $proveedor 'gemini' o 'claude'.
 */
function jb3_aisearch_api_key( string $proveedor ): string {
	$path = ABSPATH . 'jb3-search-cfg.php';
	if ( ! file_exists( $path ) ) {
		return '';
	}
	$data = include $path;
	$key  = $proveedor . '_api_key';
	return is_array( $data ) && ! empty( $data[ $key ] ) ? (string) $data[ $key ] : '';
}

/**
 * Log propio del plugin, en vez de error_log()/WP_DEBUG_LOG — confirmado
 * 2026-08-28: en el hosting (Hostinger) el mecanismo estándar de WordPress
 * (que redirige error_log() a wp-content/debug.log solo cuando WP_DEBUG Y
 * WP_DEBUG_LOG están los dos en true, vía ini_set('error_log', ...) en el
 * arranque de WP) no estaba generando el archivo pese a tener ambas
 * constantes bien puestas — puede ser una restricción del hosting sobre
 * ini_set(), o un log_errors ya fijado por su propio php.ini/.user.ini que
 * WordPress no puede pisar. Escribir directo con file_put_contents() a un
 * archivo propio evita depender de esa cadena de configuración: solo
 * necesita que WP_DEBUG_LOG esté en true (no hace falta WP_DEBUG) y que
 * wp-content sea escribible, que ya lo es siempre (WordPress lo necesita
 * para medios/uploads). El archivo queda protegido por el mismo
 * .htaccess que ya bloquea cualquier "*.log" (ver wp-content/.htaccess).
 */
function jb3_aisearch_debug_log( string $mensaje ): void {
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}
	$linea = '[' . current_time( 'mysql' ) . '] ' . $mensaje . "\n";
	file_put_contents( WP_CONTENT_DIR . '/jb3-ai-search-debug.log', $linea, FILE_APPEND | LOCK_EX );
}
