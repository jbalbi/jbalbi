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
//   mc_columnas     (Number, opcional — 0 o vacío = default, 5 posters / 3
//                     thumbs en desktop; tablet/mobile no cambian según
//                     esto. Ej. 8 para una fila de 8 posters.)
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
		// api_version 3 (no el default viejo, 2): sin esto, CUALQUIER página
		// que use este bloque pierde el iframe del editor de bloques por
		// completo (WordPress lo saca solo apenas detecta un bloque API<3 en
		// la página — ver el warning de consola "may work as a non-iframe
		// editor"). Bug real detectado 2026-08-14: sin iframe, el truco de
		// centrado por vw (jb3-mw-*, jb3-contenedor, etc.) mide contra el
		// viewport de LA VENTANA DEL ADMIN completa, no del área de edición
		// — con el panel "Vista de lista" abierto corriendo el contenido
		// hacia la derecha, cualquier texto con esas clases se salía del
		// lienzo. Con iframe, ese truco vuelve a medir contra un viewport
		// propio y centrado, como en el front real.
		'api_version'     => 3,
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
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
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
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
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
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Filas de imágenes puramente visuales, SIN post real detrás (sin link,
	// sin datos que listar en ningún otro lado del sitio) — ej. una página
	// armada a mano tipo "Seasonalities", con varias filas curadas de
	// carteles. Si la imagen SÍ es contenido real del catálogo (Películas,
	// Series...), usar "Módulo de Contenido" en su lugar, no este.
	// Reutiliza jb3-grid-posters / --jb3-grid-cols (mismo motor que
	// jb3_grid_posters(), ver _init_grids.php) — mismo look que el resto
	// del sitio, sin CSS nuevo.
	//
	// Grupo de campos ACF ("Grilla de Imágenes", Location: Block == Grilla
	// de Imágenes), campos sueltos:
	//   gi_titulo    (Text, opcional — SOLO para un título real de sección
	//                 (ej. "SEASONALITIES"): con contenido, entra a la
	//                 subnav; vacío, no entra. Para un subtítulo puramente
	//                 visual que NO tiene que verse en el menú (ej. "VERANO
	//                 / ÉXITOS EN TAQUILLA"), usar gi_bajada en su lugar.
	//   gi_bajada    (Wysiwyg, opcional — nunca entra a la subnav, tenga o
	//                 no contenido.)
	//   gi_imagenes  (Gallery, requerido)
	//   gi_columnas  (Number, opcional — 0 o vacío = default, 5 en
	//                 desktop; tablet/mobile no cambian según esto — mismo
	//                 criterio que mc_columnas del Módulo de Contenido.)
	acf_register_block_type( array(
		'name'            => 'grilla-imagenes',
		'title'           => 'Grilla de Imágenes',
		'description'     => 'Fila de imágenes sueltas (sin link, sin post real detrás) — ej. carteles curados a mano para una página puntual.',
		'render_template' => __DIR__ . '/blocks/grilla-imagenes/render.php',
		'category'        => 'jb3',
		'icon'            => 'format-gallery',
		'keywords'        => array( 'grilla', 'imagenes', 'galeria', 'seasonalities', 'posters' ),
		'mode'            => 'preview',
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Sección de texto suelta (título + bajada), insertable en CUALQUIER
	// lugar del contenido, incluso adentro de otro bloque (ej. una columna
	// de Columnas) — versión en bloque del shortcode [jb3_subnav_anchor]
	// (_init_subnav.php), para no tener que escribirlo a mano. Misma regla
	// simétrica que "Grilla de Imágenes": título con contenido = rayita +
	// entra a la subnav; vacío = no se ve, no entra (aunque haya bajada).
	//
	// Grupo de campos ACF ("Título y Bajada", Location: Block == Título y
	// Bajada), campos sueltos:
	//   tb_titulo  (Text, opcional — con contenido entra a la subnav; vacío,
	//               no entra.)
	//   tb_bajada  (Wysiwyg, opcional — nunca entra a la subnav.)
	acf_register_block_type( array(
		'name'            => 'titulo-bajada',
		'title'           => 'Título y Bajada',
		'description'     => 'Sección de texto suelta (título + bajada) para insertar en cualquier parte del contenido — con la misma rayita y entrada en la subnav que el resto de los módulos.',
		'render_template' => __DIR__ . '/blocks/titulo-bajada/render.php',
		'category'        => 'jb3',
		'icon'            => 'editor-textcolor',
		'keywords'        => array( 'titulo', 'bajada', 'subtitulo', 'seccion', 'subnav', 'anchor' ),
		'mode'            => 'preview',
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// N tarjetas (imagen + título + texto), sin post real detrás — ej. una
	// sección de "Posibilidades Infinitas" con 3 beneficios. Reutiliza el
	// mismo contenedor y los mismos breakpoints que .jb3-grid-thumbs (3/2/1
	// responsive en desktop/tablet/mobile — ver _init_grids.php/style.css)
	// A PROPÓSITO, en vez de que cada página arme esto a mano con el
	// bloque nativo "Columnas": Columnas nativo pasa de 3 a 1 columna de
	// una sola vez al llegar a mobile (sin paso intermedio en tablet),
	// distinto del resto de las grillas del sitio — con este bloque el
	// comportamiento responsive queda idéntico en todos lados.
	//
	// Grupo de campos ACF ("Grilla de Tarjetas", Location: Block == Grilla
	// de Tarjetas), campos sueltos:
	//   gt_columnas (Number, opcional — 0 o vacío = default, 3 en desktop;
	//                mismo criterio que mc_columnas/gi_columnas.)
	//   gt_items    (Repeater, requerido) sub-campos:
	//                  imagen (Image, requerido)
	//                  titulo (Text, requerido)
	//                  texto  (Textarea, opcional)
	acf_register_block_type( array(
		'name'            => 'grilla-tarjetas',
		'title'           => 'Grilla de Tarjetas',
		'description'     => 'N tarjetas con imagen, título y texto — ej. una sección de beneficios/posibilidades, sin post real detrás.',
		'render_template' => __DIR__ . '/blocks/grilla-tarjetas/render.php',
		'category'        => 'jb3',
		'icon'            => 'layout',
		'keywords'        => array( 'tarjetas', 'grilla', 'beneficios', 'posibilidades', 'columnas' ),
		'mode'            => 'preview',
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Ítem suelto (imagen + título + texto + link, todos opcionales salvo
	// la imagen) — SIN saber nada de grilla ni columnas, para insertar
	// adentro de lo que sea: un Grupo con jb3-cols-N (ver style.css), un
	// Grupo con jb3-grid-thumbs/posters, Columnas nativo, donde haga falta,
	// tantas veces como se necesite. Reemplaza, para contenido NUEVO, tanto
	// a "Grilla de Imágenes" (con solo imagen) como a "Grilla de Tarjetas"
	// (con título+texto) — esos dos bloques quedan sin tocar para lo que ya
	// los usa, no hace falta migrar nada existente.
	// Sin proporción de imagen forzada a propósito: tiene que servir tanto
	// para posters verticales como para tarjetas horizontales.
	//
	// Grupo de campos ACF ("Tarjeta", Location: Block == Tarjeta), campos
	// sueltos:
	//   t_imagen (Image, requerido)
	//   t_titulo (Text, opcional)
	//   t_texto  (Textarea, opcional)
	//   t_link   (URL, opcional — con link, la tarjeta entera es clickeable.)
	//   t_modal  (True/False, opcional — "Abrir en modal": el link abre el
	//             contenido de esa Página en un modal (_init_page_modal.php)
	//             en vez de navegar. t_link tiene que ser una Página del
	//             sitio para que esto funcione (se resuelve por URL); si no
	//             se puede resolver, se cae solo al comportamiento normal
	//             (navega, abre en pestaña nueva). Sin marcar, t_link se
	//             comporta como un link normal (interno o externo).)
	//   t_video  (URL, opcional — TIENE PRIORIDAD sobre Link/Modal. Con
	//             contenido, la tarjeta muestra el ícono de play y abre a
	//             pantalla completa con el modal de video del sitio
	//             (_init_video_modal.php) — YouTube, Vimeo o archivo de la
	//             Biblioteca de Medios, igual que Videos/Integraciones
	//             reales.)
	acf_register_block_type( array(
		'name'            => 'tarjeta',
		'title'           => 'Tarjeta',
		'description'     => 'Ítem suelto (imagen + título + texto + link opcionales) para insertar adentro de cualquier contenedor — una grilla, columnas, donde haga falta.',
		'render_template' => __DIR__ . '/blocks/tarjeta/render.php',
		'category'        => 'jb3',
		'icon'            => 'id-alt',
		'keywords'        => array( 'tarjeta', 'imagen', 'poster', 'item', 'card' ),
		'mode'            => 'preview',
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );
} );
