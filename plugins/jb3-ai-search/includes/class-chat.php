<?php
defined( 'ABSPATH' ) || exit;

/**
 * Burbuja de chat flotante, sitio completo — a diferencia de [jb3_buscador_ia]
 * (shortcode.php, se encola SOLO donde el shortcode está presente), esto vive
 * en TODAS las páginas: mismo criterio de encolado incondicional que usa el
 * tema para nav.js/header-account.js (_init_navigation.php/_init_header.php),
 * y mismo criterio de markup único inyectado por wp_footer que usan los
 * modales de video/página del tema (_init_video_modal.php/_init_page_modal.php).
 *
 * Reusa la MISMA acción de nonce que el buscador (JB3_AISEARCH_NONCE) — un
 * nonce autentica "pedido legítimo contra este recurso compartido" (la capa
 * gratuita de Gemini), no "qué botón puntual lo mandó"; los dos endpoints
 * protegen el mismo presupuesto (ver class-chat-ajax.php). La acción AJAX en
 * sí (wp_ajax_jb3_ai_chat) es nueva y propia, porque el handler y la forma
 * de la respuesta son distintos al del buscador.
 */
/**
 * El toggle queda pegado a .jb3-footer-bar (pedido explícito 2026-08-30,
 * "que no ocupe lugar") — offset chico, casi tocando la barra. Eso lo deja
 * MUY cerca de donde .grecaptcha-badge se reposiciona en la página de
 * registro (mismo criterio bottom:calc(var(--footer-bar-height)+Npx), ver
 * style.css del tema) — en vez de perseguir un offset que evite el choque
 * en ESA página en particular sin desarmar el "pegado a la barra" en el
 * resto del sitio, directamente no se muestra el chat en la página con
 * [jb3_register]: es un formulario puntual, no pierde sentido no tener ahí
 * el bot de contenido, y reCAPTCHA necesita ese rincón para sí solo.
 */
function jb3_aisearch_chat_deberia_mostrar(): bool {
	global $post;
	return ! ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'jb3_register' ) );
}

add_action( 'wp_enqueue_scripts', 'jb3_aisearch_chat_enqueue' );
function jb3_aisearch_chat_enqueue(): void {
	if ( ! jb3_aisearch_chat_deberia_mostrar() ) {
		return;
	}

	$css_path = __DIR__ . '/../assets/css/chat.css';
	$js_path  = __DIR__ . '/../assets/js/chat.js';

	wp_enqueue_style(
		'jb3-aisearch-chat',
		JB3_AISEARCH_URL . 'assets/css/chat.css',
		[],
		file_exists( $css_path ) ? filemtime( $css_path ) : false
	);
	wp_enqueue_script(
		'jb3-aisearch-chat',
		JB3_AISEARCH_URL . 'assets/js/chat.js',
		[],
		file_exists( $js_path ) ? filemtime( $js_path ) : false,
		true
	);

	// Saludo de bienvenida (pedido explícito 2026-08-30) — personalizado si
	// hay sesión iniciada. Se arma acá (server) y chat.js solo lo muestra
	// como PRIMERA burbuja cuando no hay conversación guardada todavía —
	// nunca se manda al servidor ni cuenta como intercambio real, es puro
	// saludo de UI.
	$usuario = wp_get_current_user();
	if ( $usuario->exists() ) {
		$nombre = $usuario->first_name ?: $usuario->display_name;
		$saludo = sprintf(
			/* translators: %s: nombre del usuario logueado */
			__( 'Hola %s, ¿en qué puedo ayudarte?', 'jb3-ai-search' ),
			$nombre
		);
	} else {
		$saludo = __( 'Hola, ¿en qué puedo ayudarte?', 'jb3-ai-search' );
	}

	wp_localize_script( 'jb3-aisearch-chat', 'jb3AIChat', [
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( JB3_AISEARCH_NONCE ),
		'historialMax' => JB3_AISEARCH_CHAT_HISTORIAL_MAX,
		'saludo'       => $saludo,
	] );
}

// Markup único, inyectado una sola vez por página (mismo patrón que
// _init_video_modal.php/_init_page_modal.php: un solo overlay reusado,
// nunca uno por cada trigger). [hidden] arranca puesto — chat.js lo saca
// al abrir; sin JS (bloqueado, falló la carga), el panel queda inaccesible
// pero el botón visible no rompe nada del resto de la página.
add_action( 'wp_footer', 'jb3_aisearch_chat_markup' );
function jb3_aisearch_chat_markup(): void {
	if ( ! jb3_aisearch_chat_deberia_mostrar() ) {
		return;
	}
	?>
	<button type="button" id="jb3-ai-chat-toggle" class="jb3-ai-chat__toggle" data-jb3-chat-toggle aria-label="<?php esc_attr_e( 'Preguntanos algo', 'jb3-ai-search' ); ?>" aria-expanded="false" aria-controls="jb3-ai-chat-panel">
		<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<rect x="4" y="8" width="16" height="12" rx="3"></rect>
			<path d="M12 8V4"></path>
			<circle cx="12" cy="3" r="1" fill="currentColor" stroke="none"></circle>
			<circle cx="9" cy="14" r="1.2" fill="currentColor" stroke="none"></circle>
			<circle cx="15" cy="14" r="1.2" fill="currentColor" stroke="none"></circle>
			<path d="M9 17.5h6"></path>
			<path d="M2 13h2"></path>
			<path d="M20 13h2"></path>
		</svg>
	</button>
	<div id="jb3-ai-chat-panel" class="jb3-ai-chat__panel" hidden>
		<div class="jb3-ai-chat__header">
			<span><?php esc_html_e( 'Preguntanos', 'jb3-ai-search' ); ?></span>
			<button type="button" class="jb3-ai-chat__close" data-jb3-chat-close aria-label="<?php esc_attr_e( 'Cerrar', 'jb3-ai-search' ); ?>">&times;</button>
		</div>
		<div class="jb3-ai-chat__hilo" id="jb3-ai-chat-hilo"></div>
		<form class="jb3-ai-chat__form" id="jb3-ai-chat-form">
			<textarea class="jb3-ai-chat__input" placeholder="<?php esc_attr_e( 'Ej: películas de terror', 'jb3-ai-search' ); ?>" autocomplete="off" maxlength="200" rows="2"></textarea>
			<button type="submit" class="jb3-ai-chat__submit"><?php esc_html_e( 'Enviar', 'jb3-ai-search' ); ?></button>
		</form>
	</div>
	<?php
}
