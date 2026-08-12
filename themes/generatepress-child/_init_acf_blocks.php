<?php
defined( 'ABSPATH' ) || exit;

// ─── ACF BLOCKS: categoría propia en el inserter ────────────────────────────
// Sin esto, los bloques del sitio se mezclan con los nativos de WordPress
// (Párrafo, Imagen, etc.) en la categoría genérica "Formato" — con una
// categoría propia, el editor los encuentra todos juntos.
add_filter( 'block_categories_all', function ( $categories ) {
	return array_merge(
		array( array( 'slug' => 'jb3', 'title' => 'JB3' ) ),
		$categories
	);
} );

// ─── TÍTULO/BAJADA + OVERRIDE POR PAÍS: motor compartido entre bloques ──────
// Mismo mecanismo que usaba [jb3_modulo_override] (título/bajada distintos
// según el país detectado/forzado), pero con formulario en vez de
// shortcodes anidados. Extraído de blocks/modulo-contenido/render.php para
// que cualquier otro bloque con la misma necesidad (ej. Programación) lo
// reutilice sin duplicar la resolución de país — cada bloque solo necesita
// sus propios campos "_titulo"/"_bajada"/"_overrides_pais" (el Repeater con
// sub-campos pais[]/titulo/bajada, "pais" con Appearance Checkbox para
// poder cubrir varios países en una sola fila).
function jb3_resolver_titulo_bajada_pais( string $titulo_generico, string $bajada_generica, array $overrides ): array {
	$titulo        = $titulo_generico;
	$bajada        = $bajada_generica;
	$pais_override = '';

	$pais_visitante = function_exists( 'jb3_geo_visitor_country' ) ? jb3_geo_visitor_country() : '';
	if ( $pais_visitante ) {
		foreach ( $overrides as $fila ) {
			$terminos = (array) ( $fila['pais'] ?? array() );
			$slugs    = array_map(
				function ( $t ) {
					return is_object( $t ) ? $t->slug : '';
				},
				$terminos
			);
			if ( ! in_array( $pais_visitante, $slugs, true ) ) {
				continue;
			}
			if ( ! empty( $fila['titulo'] ) ) {
				$titulo = $fila['titulo'];
			}
			if ( ! empty( $fila['bajada'] ) ) {
				$bajada = $fila['bajada'];
			}
			$pais_override = strtoupper( $pais_visitante );
			break;
		}
	}

	return array(
		'titulo'        => $titulo,
		'bajada'        => $bajada,
		'pais_override' => $pais_override,
	);
}

// ─── BLOQUE: Módulo de Contenido ─────────────────────────────────────────────
// Reemplaza al shortcode [jb3_modulo] escrito a mano en páginas de Canal
// (y cualquier otra página que lo necesite) — mismo motor por dentro
// (jb3_modulo_render()/jb3_modulo_query(), ver _init_modulo.php), pero con
// un formulario en vez de texto entre corchetes: sin el riesgo de que
// wpautop lo desarme (el bug de los <br> de más en Sony Pictures, detectado
// 2026-08-10) ni de tipear mal un post_type o un slug de canal que deja el
// módulo vacío en silencio.
//
// El grupo de campos ACF ("Módulo de Contenido") se arma desde el admin
// (Custom Fields → Add New Field Group, Location = "Block == Módulo de
// Contenido") — mismo criterio DB-only que el resto de los campos del
// sitio, sin acf-json. Nombres de campo que espera render.php:
//   mc_tipos        (Checkbox, choices = peliculas/series/prod_originales/
//                     op_comerciales/videos/integraciones)
//   mc_canales      (Taxonomy, taxonomy=canales, return=Term Object, opcional —
//                     vacío = sin filtrar por canal, todos)
//   mc_titulo       (Text, requerido)
//   mc_bajada       (Wysiwyg Editor, opcional — texto enriquecido: negrita,
//                     color, links)
//   mc_overrides_pais (Repeater, opcional — título/bajada distintos según
//                     el país detectado/forzado, mismo mecanismo que tenía
//                     [jb3_modulo_override] pero con formulario. Sub-campos:
//                       pais    (Taxonomy, taxonomy=pais, Checkbox —
//                                 multi-selección: una fila puede cubrir
//                                 varios países — return=Term Object)
//                       titulo  (Text, opcional — vacío = mantiene mc_titulo)
//                       bajada  (Wysiwyg Editor, opcional — vacío = mantiene mc_bajada)
//   mc_limite       (Number, opcional — 0 o vacío = sin límite)
//   mc_boton_texto  (Text, opcional)
//   mc_boton_link   (Text/URL, opcional)
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	acf_register_block_type( array(
		'name'            => 'modulo-contenido',
		'title'           => 'Módulo de Contenido',
		'description'     => 'Grilla de contenido por tipo y canal (Películas, Op. Comerciales, Integraciones, etc.), con título y entrada automática en la subnav.',
		'render_template' => __DIR__ . '/blocks/modulo-contenido/render.php',
		'category'        => 'jb3',
		'icon'            => 'grid-view',
		'keywords'        => array( 'modulo', 'grilla', 'canal', 'op comerciales', 'integraciones', 'peliculas', 'series' ),
		// Los campos de un bloque ACF con render_template (sin editor propio
		// en JS, como este) siempre se editan desde la barra lateral —
		// 'mode' NO cambia eso, solo si el lienzo muestra la vista previa
		// real automáticamente ('preview') o hace falta togglearla a mano
		// ('edit'). La barra angosta se resuelve por otro lado: layout del
		// repeater en "Row" en vez de "Table" (ver comentario en la guía de
		// campos).
		'mode'            => 'preview',
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Sin grupo de campos propio: no hay nada que completar, el editor solo
	// elige DÓNDE insertarlo — lee el canal de la página que lo contiene y
	// las Redes Sociales cargadas en el término de ese canal (ver
	// _init_canal_redes.php). Antes se imprimía fijo, pegado al logo, en
	// page-canal.php — esto lo reemplaza para que la posición sea decisión
	// del editor, no algo fijo por código.
	acf_register_block_type( array(
		'name'            => 'redes-sociales-canal',
		'title'           => 'Redes Sociales del Canal',
		'description'     => 'Imprime los íconos de redes sociales cargados en el canal de esta página (Canales → editar el término).',
		'render_template' => __DIR__ . '/blocks/redes-sociales-canal/render.php',
		'category'        => 'jb3',
		'icon'            => 'share',
		'keywords'        => array( 'redes', 'sociales', 'canal', 'youtube', 'facebook', 'instagram', 'tiktok' ),
		'mode'            => 'preview',
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Versión genérica, independiente de canal — para usar en cualquier
	// otro lugar del sitio que necesite su propia fila de redes (ej. el
	// Host de un programa). Grupo de campos propio ("Redes Sociales",
	// Location: Block == Redes Sociales): un único Repeater "redes_sociales"
	// con los mismos sub-campos "red"/"url" que la versión de Canal.
	acf_register_block_type( array(
		'name'            => 'redes-sociales',
		'title'           => 'Redes Sociales',
		'description'     => 'Fila de íconos de redes sociales con su propia lista — para usar donde haga falta, sin depender de ningún canal.',
		'render_template' => __DIR__ . '/blocks/redes-sociales/render.php',
		'category'        => 'jb3',
		'icon'            => 'share',
		'keywords'        => array( 'redes', 'sociales', 'youtube', 'facebook', 'instagram', 'tiktok', 'host' ),
		'mode'            => 'preview',
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Sin grupo de campos propio: el dato ("Programación") vive en una caja
	// de metadatos aparte, Location = "Plantilla de página == Canal" — se
	// carga una vez por página (no es algo que tenga sentido repetir con
	// valores distintos dentro de la misma página), el bloque solo elige
	// DÓNDE se imprime. Ver blocks/programacion/render.php para el detalle
	// completo de los campos que espera esa caja de metadatos.
	acf_register_block_type( array(
		'name'            => 'programacion',
		'title'           => 'Programación',
		'description'     => 'Imprime la imagen de Programación cargada en la caja de metadatos de esta página — se autorregula por país y fecha, se oculta sola si no hay ninguna vigente.',
		'render_template' => __DIR__ . '/blocks/programacion/render.php',
		'category'        => 'jb3',
		'icon'            => 'schedule',
		'keywords'        => array( 'programacion', 'grilla', 'imagen', 'canal' ),
		'mode'            => 'preview',
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );
} );
