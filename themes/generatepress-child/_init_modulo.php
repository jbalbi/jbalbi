<?php
defined( 'ABSPATH' ) || exit;

// ─── MÓDULO DE CONTENIDO ─────────────────────────────────────────────────────
// Junta lo de _init_grids.php y _init_subnav.php: consulta por canal(es)+
// país+tipo(s), imprime título/bajada/grilla (+ botón "Ver más" si hay más
// contenido del que entra en el límite), y se anota solo en la subnav si
// hubo contenido. jb3_modulo_render() es la función real (la llaman tanto
// el shortcode de abajo como, más adelante, cualquier plantilla directo
// por PHP — mismo patrón de "una función, dos entradas" que ya usamos en
// _init_hero.php). Título/bajada llegan ya resueltos acá — de dónde salen
// (atributo de shortcode, lo que sea) es responsabilidad de quien llama.
//
// El módulo puede usarse SIN canal (una página con "todas las Op.
// Comerciales de todos los canales"), con UNO, o con VARIOS a la vez — por
// eso jb3_modulo_query()/render() reciben siempre un array de IDs de
// término (vacío = sin filtrar por canal), nunca uno solo. Mismo criterio
// para $tipos: puede ser un solo tipo, varios combinados (ej. Series +
// Prod. Originales), o TODOS (sin especificar tipo — "todo lo que tenga
// este canal, sea lo que sea"). Como los distintos tipos no son todos de
// la misma familia de grilla (posters vs thumbs), jb3_modulo_render()
// separa el resultado en hasta dos grillas dentro del mismo módulo en vez
// de forzar todo a un solo layout.

/** Tipos que usan grilla de posters (vertical). */
function jb3_modulo_tipos_poster(): array {
	return [ 'peliculas', 'series', 'prod_originales' ];
}

/** Tipos que usan grilla de thumbs (horizontal). */
function jb3_modulo_tipos_thumb(): array {
	return [ 'op_comerciales', 'videos', 'integraciones' ];
}

/** Todos los tipos de contenido con módulo — usado cuando no se especifica "tipo". */
function jb3_modulo_tipos_todos(): array {
	return array_merge( jb3_modulo_tipos_poster(), jb3_modulo_tipos_thumb() );
}

// $limite > 0: pide una de más ($limite + 1) para saber si hay más
// contenido del que se va a mostrar, sin traer de más innecesariamente.
// $excluir_id: para el caso "más oportunidades como esta" en la propia
// página de una Op. Comercial — sin esto, el post que se está viendo se
// listaría a sí mismo entre las "más recientes". 0 = no excluir nada
// (comportamiento de siempre, el shortcode no lo usa).
function jb3_modulo_query( array $tipos, array $canal_term_ids = [], int $limite = 0, int $excluir_id = 0 ): array {
	$pais = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';

	$tax_query = [];

	if ( $canal_term_ids ) {
		// Match estricto A PROPÓSITO: el criterio "sin canal asignado =
		// visible en todos lados" es del FILTRO del módulo (ver más abajo,
		// $canal_term_ids vacío), no del contenido en sí — si el módulo
		// filtra por un canal puntual (ej. AXN), tiene que traer SOLO lo
		// cargado para ese canal, nunca lo que no tiene canal cargado.
		// Bug real detectado 2026-08-13: el módulo de Op. Comerciales de
		// AXN (filtrado a "AXN") traía también las que no tenían ningún
		// canal asignado, colándose en un listado que se suponía exclusivo.
		$tax_query[] = [
			'taxonomy' => 'canales',
			'field'    => 'term_id',
			'terms'    => $canal_term_ids,
		];
	}

	// Match estricto: sin país tildado, NO se muestra en ningún país (cambiado
	// 2026-08-14, reforzado a fail-closed 2026-08-26). Cláusulas compartidas
	// con el resto del sitio — ver jb3_geo_tax_query_pais() en
	// jb3-geo-targeting.php. OJO: la condición es solo function_exists(), NO
	// "$pais &&" — jb3_geo_tax_query_pais('') ya devuelve la cláusula que no
	// matchea nada (fail-closed); si acá se saltara la llamada cuando $pais
	// está vacío, un visitante sin país detectado vería contenido restringido
	// sin filtro alguno (bug real, corregido en la auditoría pre-producción).
	if ( function_exists( 'jb3_geo_tax_query_pais' ) ) {
		$tax_query = array_merge( $tax_query, jb3_geo_tax_query_pais( $pais ) );
	}

	$query = new WP_Query( [
		'post_type'      => $tipos,
		'posts_per_page' => $limite > 0 ? $limite + 1 : -1,
		'tax_query'      => $tax_query,
		'post__not_in'   => $excluir_id ? [ $excluir_id ] : [],
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	] );

	return $query->posts;
}

// $columnas > 0: cantidad de columnas en DESKTOP para la grilla de este
// módulo (ver jb3_grid_posters()/jb3_grid_thumbs() en _init_grids.php) — 0
// (default) deja el valor de siempre (4 posters / 3 thumbs — posters
// múltiplo de las 2 columnas de mobile, así un límite elegido igual como
// múltiplo de 4 no deja huérfano en ningún tamaño de pantalla). Tablet/
// mobile no cambian según este parámetro, quedan con los valores ya
// ajustados.
function jb3_modulo_render(
	array $tipos,
	array $canal_term_ids,
	string $titulo,
	string $bajada = '',
	int $limite = 0,
	string $boton_texto = '',
	string $boton_link = '',
	int $excluir_id = 0,
	int $columnas = 0
): string {
	// Se separan los tipos pedidos en las dos familias de grilla — si el
	// módulo mezcla Películas (poster) con Videos (thumb), o directamente
	// no especifica tipo (jb3_modulo_tipos_todos()), cada familia consulta
	// y se recorta por separado; el módulo termina con hasta dos grillas
	// adentro en vez de forzar todo a un solo layout o descartar la mitad.
	$tipos_poster = array_values( array_intersect( $tipos, jb3_modulo_tipos_poster() ) );
	$tipos_thumb  = array_values( array_intersect( $tipos, jb3_modulo_tipos_thumb() ) );

	$posts_poster = $tipos_poster ? jb3_modulo_query( $tipos_poster, $canal_term_ids, $limite, $excluir_id ) : [];
	$posts_thumb  = $tipos_thumb ? jb3_modulo_query( $tipos_thumb, $canal_term_ids, $limite, $excluir_id ) : [];

	if ( empty( $posts_poster ) && empty( $posts_thumb ) ) {
		return ''; // Sin contenido = sin módulo y sin entrada en la subnav.
	}

	$hay_mas = false;
	if ( $limite > 0 ) {
		if ( count( $posts_poster ) > $limite ) {
			$hay_mas      = true;
			$posts_poster = array_slice( $posts_poster, 0, $limite );
		}
		if ( count( $posts_thumb ) > $limite ) {
			$hay_mas     = true;
			$posts_thumb = array_slice( $posts_thumb, 0, $limite );
		}
	}

	// ID a partir del título visible (jb3_subnav_id_unico(), _init_subnav.php)
	// en vez de post_type+term_id internos — así un link a esta sección se ve
	// como "#peliculas", no "#modulo-peliculas-28".
	$id = jb3_subnav_id_unico( $titulo );
	jb3_subnav_register( $titulo, $id );

	// El ícono de play (si corresponde) lo decide jb3_grid_thumbs() solo,
	// mirando el campo ACF "video_url" de cada post — no hace falta
	// pasárselo desde acá.
	ob_start();
	?>
	<?php // Sin ".jb3-contenedor" acá: esta función la llaman tanto plantillas
	// PHP directas como el shortcode [jb3_modulo] y el bloque ACF "Módulo de
	// Contenido" — ambos insertables por Gutenberg en CUALQUIER lugar, incluso
	// adentro de un bloque Columnas. Si este <section> se autoaplicara el
	// breakout a 100vw de ".jb3-contenedor", en ese caso se saldría de la
	// columna y rompería el layout (no tiene forma de saber en qué contexto
	// quedó insertado). Como hijo directo de .entry-content ya lo cubre el
	// selector genérico de ahí abajo; quien lo llame por PHP directo y
	// necesite el breakout se lo agrega en el propio call site (ver
	// single-op_comerciales.php). ?>
	<section class="jb3-modulo" id="<?php echo esc_attr( $id ); ?>">
		<h2 class="jb3-modulo__titulo"><?php echo esc_html( $titulo ); ?></h2>
		<?php if ( $bajada ) : ?>
			<div class="jb3-modulo__bajada"><?php echo wp_kses_post( $bajada ); ?></div>
		<?php endif; ?>
		<?php if ( $posts_poster ) : ?>
			<?php echo jb3_grid_posters( $posts_poster, $columnas ); ?>
		<?php endif; ?>
		<?php if ( $posts_thumb ) : ?>
			<?php echo jb3_grid_thumbs( $posts_thumb, $columnas ); ?>
		<?php endif; ?>
		<?php if ( $hay_mas && $boton_texto && $boton_link ) : ?>
			<p class="jb3-modulo__ver-mas">
				<a class="button" href="<?php echo esc_url( $boton_link ); ?>"><?php echo esc_html( $boton_texto ); ?></a>
			</p>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

// ─── OVERRIDE POR PAÍS ───────────────────────────────────────────────────────
// [jb3_modulo_override] no imprime nada donde está — se anota en un buffer
// que [jb3_modulo] lee justo después de procesar su propio contenido (el
// do_shortcode() de más abajo ya ejecutó este shortcode como efecto
// secundario, así que el buffer está listo para leerse). Así un editor
// puede escribir, dentro de un mismo [jb3_modulo], tanto el título/bajada
// genérico como uno o más overrides por país, cada uno con su propio HTML:
//
// [jb3_modulo tipo="peliculas" titulo="Películas"]
//     <p>Bajada genérica.</p>
//     [jb3_modulo_override pais="ar" titulo="Películas Argentinas"]<p>Bajada bien argentinizada.</p>[/jb3_modulo_override]
// [/jb3_modulo]
$GLOBALS['jb3_modulo_overrides'] = [];

add_shortcode( 'jb3_modulo_override', function ( $atts, $content = null ) {
	$atts = shortcode_atts( [
		'pais'   => '',
		'titulo' => '',
	], $atts );

	if ( ! $atts['pais'] ) {
		return '';
	}

	// trim(): sin esto, la indentación/salto de línea alrededor del <p> que
	// deja el editor entre las etiquetas del shortcode le queda pegada a la
	// bajada — wpautop() (que corre sobre la página entera después) la
	// convierte en un <p></p> vacío de más antes y después del contenido
	// real. Mismo mecanismo que el bug de la bajada genérica, más chico.
	$content = $content ? trim( $content ) : '';

	$GLOBALS['jb3_modulo_overrides'][ strtolower( $atts['pais'] ) ] = [
		'titulo' => $atts['titulo'],
		'bajada' => $content ? do_shortcode( $content ) : '',
	];

	return '';
} );

// El bloque nativo de Gutenberg "Shortcode" (core/shortcode) le aplica
// wpautop() a su contenido ANTES de expandir cualquier shortcode (ver
// wp-includes/blocks/shortcode.php: render_block_core_shortcode() hace
// literalmente `return wpautop($content)`, sin do_shortcode()). Con
// [jb3_modulo]/[jb3_modulo_override] escritos cada uno en su propia línea
// (sin línea en blanco entre medio, como los deja el editor), wpautop las
// interpreta como UN solo párrafo separado por <br> — y esos <br> quedan
// pegados en el HTML final aunque el shortcode que los rodeaba termine
// vaciándose. Bug real detectado 2026-08-10: el hueco debajo de la bajada
// crecía un <br> por cada [jb3_modulo_override] agregado, en cualquier país
// que no matcheara ninguno de los definidos.
// Se evita de raíz: para bloques de shortcode que contengan [jb3_modulo o
// [jb3_subnav_anchor (este último ganó el mismo riesgo cuando aprendió a
// aceptar bajada como contenido encerrado, ver _init_subnav.php), se salta
// el wpautop() del bloque y se corre do_shortcode() directo sobre el texto
// realmente crudo — que es lo que ambos shortcodes ya esperan (el editor
// escribe sus propios <p> a mano en la bajada).
add_filter( 'pre_render_block', function ( $pre_render, $parsed_block ) {
	if ( ( $parsed_block['blockName'] ?? '' ) !== 'core/shortcode' ) {
		return $pre_render;
	}
	$raw = $parsed_block['innerHTML'] ?? '';
	if ( ! str_contains( $raw, '[jb3_modulo' ) && ! str_contains( $raw, '[jb3_subnav_anchor' ) ) {
		return $pre_render;
	}
	return do_shortcode( $raw );
}, 10, 2 );

// ─── SHORTCODE ───────────────────────────────────────────────────────────────
// [jb3_modulo tipo="peliculas" canal="axn" titulo="Películas" limite="5" boton_texto="Ver todas" boton_link="/peliculas/"]
// tipo es opcional — sin especificarlo trae TODOS los tipos de contenido
// (todo lo que tenga el canal, sin importar cuál sea). También admite
// varios separados por coma: tipo="series,prod_originales".
// canal es opcional (vacío = todos los canales) y también admite varios:
// canal="axn,sony-channel".
// limite/boton_texto/boton_link son opcionales — sin límite, muestra todo
// sin botón. El botón el link lo escribe quien arma el módulo (no hay
// forma de calcularlo solo cuando se combinan varios tipos/canales).
// La bajada (opcional, con HTML) va como contenido encerrado, no como
// atributo — así se puede escribir HTML normal entre las etiquetas:
// [jb3_modulo tipo="videos" titulo="Videos"]<p>Bajada con <strong>HTML</strong></p>[/jb3_modulo]
// columnas es opcional (0 o vacío = default, 5 posters / 3 thumbs en
// desktop) — ej. columnas="8" para una fila de 8 posters.
add_shortcode( 'jb3_modulo', function ( $atts, $content = null ) {
	$atts = shortcode_atts( [
		'tipo'        => '',
		'canal'       => '',
		'titulo'      => '',
		'limite'      => '0',
		'boton_texto' => '',
		'boton_link'  => '',
		'columnas'    => '0',
	], $atts );

	if ( ! $atts['titulo'] ) {
		return '';
	}

	$tipos = $atts['tipo']
		? array_filter( array_map( 'trim', explode( ',', $atts['tipo'] ) ) )
		: jb3_modulo_tipos_todos();

	$canal_term_ids = [];
	if ( $atts['canal'] ) {
		$slugs = array_filter( array_map( 'trim', explode( ',', $atts['canal'] ) ) );
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'canales' );
			if ( $term ) {
				$canal_term_ids[] = $term->term_id;
			}
		}
		if ( empty( $canal_term_ids ) ) {
			return ''; // Se pidieron canales puntuales pero ninguno existe.
		}
	}

	$GLOBALS['jb3_modulo_overrides'] = [];
	do_shortcode( $content ); // Dispara [jb3_modulo_override] como efecto secundario; el resultado en sí se descarta.

	// Bajada "genérica" (sin país que matchee ningún override): el $content
	// crudo de arriba, pero SACANDO los bloques [jb3_modulo_override]...
	// [/jb3_modulo_override] antes de usarla — do_shortcode() los deja en
	// '' pero NO borra los saltos de línea que quedan alrededor de cada
	// uno, y wpautop() (que corre después, sobre la página entera) convierte
	// esas líneas en blanco sueltas en <br> visibles: uno de más por cada
	// override que haya definido el editor, sin importar si el visitante
	// termina viéndolo o no. Bug real detectado 2026-08-10 (el hueco debajo
	// de la bajada crecía cada vez que se agregaba un [jb3_modulo_override]
	// nuevo, en cualquier país que no matcheara ninguno de los definidos).
	$bajada_generica = '';
	if ( $content ) {
		$sin_overrides    = preg_replace( '#\[jb3_modulo_override\b[^\]]*\].*?\[/jb3_modulo_override\]#s', '', $content );
		$sin_overrides    = trim( preg_replace( '#(\r?\n\s*){2,}#', "\n", $sin_overrides ) );
		$bajada_generica  = $sin_overrides ? do_shortcode( $sin_overrides ) : '';
	}

	$titulo = $atts['titulo'];
	$bajada = $bajada_generica;
	$pais   = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';

	if ( $pais && isset( $GLOBALS['jb3_modulo_overrides'][ $pais ] ) ) {
		$override = $GLOBALS['jb3_modulo_overrides'][ $pais ];
		if ( $override['titulo'] ) {
			$titulo = $override['titulo'];
		}
		$bajada = $override['bajada'];
	}

	return jb3_modulo_render(
		$tipos,
		$canal_term_ids,
		$titulo,
		$bajada,
		(int) $atts['limite'],
		$atts['boton_texto'],
		$atts['boton_link'],
		0,
		(int) $atts['columnas']
	);
} );
