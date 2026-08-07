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

// [jb3_subnav_anchor titulo="Galería"] — ancla suelta para cualquier punto
// de la página que el editor quiera que aparezca en la subnav, sin que sea
// un módulo de contenido armado por código.
add_shortcode( 'jb3_subnav_anchor', function ( $atts ) {
	$atts = shortcode_atts( [
		'titulo' => '',
		'id'     => '',
	], $atts );

	$titulo = trim( $atts['titulo'] );
	if ( '' === $titulo ) {
		return '';
	}

	$id = $atts['id'] ? sanitize_title( $atts['id'] ) : sanitize_title( $titulo );
	jb3_subnav_register( $titulo, $id );

	return '<span id="' . esc_attr( $id ) . '" class="jb3-subnav-anchor"></span>';
} );
