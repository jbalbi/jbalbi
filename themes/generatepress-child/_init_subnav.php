<?php
defined( 'ABSPATH' ) || exit;

// ─── SUBNAV DINÁMICA ─────────────────────────────────────────────────────────
// Motor de navegación armado a partir de lo que efectivamente existe en la
// página, no de una lista fija: [jb3_subnav] deja un marcador invisible en
// su lugar; cualquier otra pieza de la página (un módulo de contenido, un
// ancla suelta con [jb3_subnav_anchor]) se anota en una lista compartida a
// medida que se va renderizando. Recién cuando WordPress terminó de
// procesar TODOS los shortcodes del contenido (hook en "the_content",
// prioridad 999 — bien al final de la cadena, do_shortcode corre en la
// 11) se reemplaza el marcador por la barra ya armada — así no importa en
// qué orden haya puesto el editor las piezas, ni si algunas terminan sin
// contenido y no se anotan: la barra siempre refleja lo que hay.
const JB3_SUBNAV_PLACEHOLDER = '<!--jb3-subnav-->';

$GLOBALS['jb3_subnav_items'] = [];

/** Anota un ítem en la subnav (evita duplicados por id). */
function jb3_subnav_register( string $texto, string $id ): void {
	foreach ( $GLOBALS['jb3_subnav_items'] as $item ) {
		if ( $item['id'] === $id ) {
			return;
		}
	}
	$GLOBALS['jb3_subnav_items'][] = [ 'texto' => $texto, 'id' => $id ];
}

function jb3_subnav_items(): array {
	return $GLOBALS['jb3_subnav_items'];
}

/**
 * ID legible para un ancla/entrada de subnav, a partir del texto visible —
 * mismo criterio que ya usaba [jb3_subnav_anchor] (sanitize_title(): saca
 * tildes, pasa a minúscula, cambia espacios por guiones) — para que un link
 * a esta sección nunca exponga post_type ni term_id internos (ej.
 * "modulo-peliculas-28"), solo el texto que el editor ya ve en la subnav
 * (ej. "peliculas"). Si dos secciones de la misma página terminan con el
 * mismo texto (mismo título, dos canales distintos, etc.), se le suma un
 * sufijo -2, -3... para no repetir el mismo id en el HTML.
 */
function jb3_subnav_id_unico( string $texto ): string {
	$base = sanitize_title( $texto ) ?: 'seccion';
	$id   = $base;
	$n    = 2;
	while ( in_array( $id, wp_list_pluck( $GLOBALS['jb3_subnav_items'], 'id' ), true ) ) {
		$id = $base . '-' . $n;
		$n++;
	}
	return $id;
}

function jb3_subnav_render( array $items ): string {
	if ( empty( $items ) ) {
		return '';
	}
	ob_start();
	?>
	<nav class="jb3-subnav" id="jb3-subnav">
		<ul class="jb3-subnav__menu">
			<?php foreach ( $items as $item ) : ?>
				<li class="jb3-subnav__item">
					<a href="#<?php echo esc_attr( $item['id'] ); ?>" class="jb3-subnav__link">
						<?php echo esc_html( $item['texto'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
	return ob_get_clean();
}

add_filter( 'the_content', 'jb3_subnav_replace_placeholder', 999 );
function jb3_subnav_replace_placeholder( string $content ): string {
	if ( false === strpos( $content, JB3_SUBNAV_PLACEHOLDER ) ) {
		return $content;
	}
	return str_replace( JB3_SUBNAV_PLACEHOLDER, jb3_subnav_render( jb3_subnav_items() ), $content );
}

// ─── SHORTCODES ──────────────────────────────────────────────────────────────

add_shortcode( 'jb3_subnav', function () {
	return JB3_SUBNAV_PLACEHOLDER;
} );

// [jb3_subnav_anchor titulo="Sinopsis"] — para cualquier sección suelta del
// editor (no un módulo de contenido armado por código) que el editor quiera
// que aparezca en la subnav CON el mismo título visible que ya usan los
// módulos (rayita vertical + mayúsculas, .jb3-modulo__titulo — mismo trato
// en todo el sitio, ver comentario en style.css). Antes solo dejaba el
// ancla invisible y el editor tenía que armar el título aparte a mano (con
// un bloque Encabezado suelto, sin estilo ni entrada en la subnav) — ahora
// una sola llamada resuelve las dos cosas.
// visible="0" para volver al comportamiento viejo (solo ancla, sin título
// impreso) por si hace falta en algún caso puntual.
// El contenido encerrado (opcional, con HTML) se imprime como bajada debajo
// del título — mismo trato visual que la bajada de "Módulo de Contenido"
// (.jb3-modulo__bajada, sin la rayita vertical del título):
// [jb3_subnav_anchor titulo="Seasonalities"]<p>Bajada con <strong>HTML</strong></p>[/jb3_subnav_anchor]
add_shortcode( 'jb3_subnav_anchor', function ( $atts, $content = null ) {
	$atts = shortcode_atts( [
		'titulo'  => '',
		'id'      => '',
		'visible' => '1',
	], $atts );

	$titulo = trim( $atts['titulo'] );
	if ( '' === $titulo ) {
		return '';
	}

	$id = $atts['id'] ? sanitize_title( $atts['id'] ) : sanitize_title( $titulo );
	jb3_subnav_register( $titulo, $id );

	if ( '0' === $atts['visible'] ) {
		return '<span id="' . esc_attr( $id ) . '" class="jb3-subnav-anchor"></span>';
	}

	$html = '<h2 id="' . esc_attr( $id ) . '" class="jb3-modulo__titulo jb3-subnav-anchor">' . esc_html( $titulo ) . '</h2>';

	// trim(): mismo motivo que en [jb3_modulo] — sin esto, la indentación/
	// salto de línea que deja el editor entre las etiquetas del shortcode
	// queda pegada a la bajada.
	$bajada = $content ? trim( $content ) : '';
	if ( $bajada ) {
		$html .= '<div class="jb3-modulo__bajada">' . wp_kses_post( do_shortcode( $bajada ) ) . '</div>';
	}

	return $html;
} );
