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

	// Ítem suelto (imagen + título + texto + link, todos opcionales salvo
	// la imagen) — SIN saber nada de grilla ni columnas, para insertar
	// adentro de lo que sea: un Grupo con jb3-cols-N (ver style.css), un
	// Grupo con layout Grid nativo, jb3-grid-thumbs/posters, Columnas
	// nativo, donde haga falta, tantas veces como se necesite. Reemplaza,
	// para contenido NUEVO, a "Grilla de Imágenes" (con solo imagen) — ese
	// bloque queda sin tocar para lo que ya lo usa, no hace falta migrar
	// nada existente. ("Grilla de Tarjetas", el equivalente con título+
	// texto, existió en paralelo un tiempo pero nunca llegó a usarse en
	// ningún post — eliminado 2026-09-01, ver comentario en style.css.)
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

	// Carrusel de imágenes (Swiper) — para filas de fotos que necesitan
	// deslizarse, no una grilla fija (ej. backstage/grabaciones). Gutenberg
	// no tiene bloque nativo de carrusel, así que esto sí justifica algo
	// propio (a diferencia de una grilla común, ya cubierta por "Grilla de
	// Imágenes"). Reutiliza Swiper autohospedado (jb3_enqueue_swiper(),
	// mismo motor que el slider de Home y el de Canales).
	//
	// Grupo de campos ACF ("Galería con Slider", Location: Block ==
	// Galería con Slider), campos sueltos:
	//   gs_titulo   (Text, opcional — con contenido entra a la subnav)
	//   gs_bajada   (Wysiwyg, opcional — nunca entra a la subnav)
	//   gs_imagenes (Gallery, requerido)
	//   gs_paises   (Taxonomy, taxonomy=pais, Checkbox, opcional — CERRADO
	//               por defecto: vacío = no se ve para nadie, hay que
	//               marcar los países que sí la pueden ver.)
	acf_register_block_type( array(
		'name'            => 'galeria-slider',
		'title'           => 'Galería con Slider',
		'description'     => 'Carrusel de imágenes (Swiper) con flechas y paginación — para fotos que necesitan deslizarse, no una grilla fija.',
		'render_template' => __DIR__ . '/blocks/galeria-slider/render.php',
		'category'        => 'jb3',
		'icon'            => 'images-alt2',
		'keywords'        => array( 'galeria', 'slider', 'carrusel', 'swiper', 'fotos', 'grabaciones' ),
		'mode'            => 'preview',
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
		'supports'        => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Banner (pedido 2026-10-01): el mismo layout "Banner" del módulo de
	// Contenido de Películas/Series/Prod. Originales, arrastrable a cualquier
	// página. Comparte el render con ese layout (jb3_contenido_render_banner(),
	// _init_contenido_pelicula.php) — ver blocks/banner/render.php para la
	// guía de campos (grupo ACF "Banner", prefijo bn_). Trae su propio gate
	// de país y fechas, igual que el layout, así que no hace falta meterlo
	// adentro de "Contenido por País".
	acf_register_block_type( array(
		'name'            => 'banner',
		'title'           => 'Banner',
		'description'     => 'Imagen con versión mobile opcional, link opcional (misma ventana o pestaña nueva), al ancho del contenido o de borde a borde, con países y fechas de vigencia — el mismo banner de Películas/Series.',
		'render_template' => __DIR__ . '/blocks/banner/render.php',
		'category'        => 'jb3',
		'icon'            => 'format-image',
		'keywords'        => array( 'banner', 'imagen', 'publicidad', 'promo', 'link' ),
		'mode'            => 'preview',
		'api_version'     => 3, // ver comentario largo en "modulo-contenido" más arriba.
		'supports'        => array(
			// Sin alineación nativa, igual que el resto de los bloques: el
			// "Ancho completo" del banner es una casilla en el panel de
			// propiedades (bn_ancho_completo), donde el editor la encuentra
			// junto al resto de los campos — el botón de la barra del bloque
			// (primera versión, 2026-10-01) pasaba desapercibido.
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Envoltorio genérico para restringir CUALQUIER contenido por país — en
	// vez de agregarle un campo "países" a cada bloque nuevo (Banner,
	// Galería, etc.) uno por uno, este bloque contiene otros adentro
	// (InnerBlocks, soporte nativo de ACF Pro — ver <InnerBlocks /> en el
	// render) y aplica el filtro una sola vez, en un solo lugar. Si el país
	// del visitante no está entre los marcados, no se imprime NADA del
	// contenido de adentro (ni siquiera un contenedor vacío).
	// Sin div propio en el front (acf/blocks/wrap_frontend_innerblocks
	// filtrado a false más abajo): el contenido de adentro queda como si no
	// hubiera ningún envoltorio — mismo criterio de "insertable en cualquier
	// lado, sin asumir nada" que el resto de los bloques del sitio.
	//
	// Grupo de campos ACF ("Contenido por País", Location: Block ==
	// Contenido por País), campo suelto:
	//   cp_paises (Taxonomy, taxonomy=pais, Checkbox, opcional — CERRADO por
	//              defecto: vacío = no se ve para nadie, hay que marcar los
	//              países que sí lo pueden ver.)
	acf_register_block_type( array(
		'name'              => 'contenido-por-pais',
		'title'             => 'Contenido por País',
		'description'       => 'Envuelve cualquier otro bloque y lo muestra solo en los países marcados — vacío no restringe nada.',
		'render_template'   => __DIR__ . '/blocks/contenido-por-pais/render.php',
		'category'          => 'jb3',
		'icon'              => 'admin-site-alt3',
		'keywords'          => array( 'pais', 'geo', 'geotargeting', 'region', 'restriccion' ),
		'mode'              => 'preview',
		'api_version'       => 3, // ver comentario largo en "modulo-contenido" más arriba.
		// Sin esto, un bloque registrado por PHP (sin block.json) queda en
		// "ACF Block Version 1" — la variante vieja, que no soporta un área
		// de InnerBlocks real dentro del editor (el bloque quedaba roto,
		// "An error occurred when loading the block in edit mode"). Este
		// fue el primer bloque del sitio que necesitó InnerBlocks (después
		// se sumó "Grilla", más abajo) — la versión 2 no soporta InnerBlocks.
		// El campo "Países" no se guardaba antes con la versión 3 — la
		// causa real NO era la versión del bloque, sino una key de campo
		// duplicada en la base (dos posts acf-field con el mismo
		// post_name, uno de ellos basura creado sin querer por un
		// acf_update_field() con el array incompleto — ver la
		// conversación 2026-08-18). Ya limpiado, la versión 3 es segura.
		'acf_block_version' => 3,
		'supports'          => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// N columnas (CSS Grid), configurables por número — no por panel de
	// Layout nativo ni por clase CSS escrita a mano (ver la ronda larga con
	// el layout "Cuadrícula" nativo de Gutenberg que motivó este bloque:
	// su combo "Máx. columnas"+"Mín. ancho" no respeta el gap real del
	// sitio cuando los dos campos están cargados a la vez — desajuste
	// confirmado leyendo wp-includes/block-supports/layout.php,
	// 2026-09-01). InnerBlocks real (como "Contenido por País" — adentro va
	// cualquier otro bloque, "Tarjeta" u otro). Sin título propio a
	// propósito: para un título con entrada en la subnav, poner un bloque
	// "Título y Bajada" antes — no duplicar esa lógica acá (ver
	// blocks/grilla/render.php para el detalle completo).
	//
	// Grupo de campos ACF ("Grilla", Location: Block == Grilla), campos
	// sueltos:
	//   gr_columnas_desktop (Number, opcional — vacío/0 = default 3)
	//   gr_columnas_tablet  (Number, opcional — vacío/0 = default 2)
	//   gr_columnas_mobile  (Number, opcional — vacío/0 = default 1)
	//   gr_alineacion       (Select, choices = izquierda/centro/derecha,
	//                        default izquierda) — dónde arranca la última
	//                        fila cuando queda incompleta (ej. 7 tarjetas
	//                        en 3 columnas: la 7ma sola, ¿pegada a la
	//                        izquierda, centrada, o pegada a la derecha?).
	acf_register_block_type( array(
		'name'               => 'grilla',
		'title'              => 'Grilla',
		'description'        => 'Contenedor de columnas (CSS Grid) configurable por número, con InnerBlocks real — insertar adentro cualquier otro bloque (Tarjeta, imágenes, lo que haga falta).',
		'render_template'    => __DIR__ . '/blocks/grilla/render.php',
		'category'           => 'jb3',
		'icon'               => 'layout',
		'keywords'           => array( 'grilla', 'grid', 'columnas', 'tarjetas', 'contenedor' ),
		'mode'               => 'preview',
		'api_version'        => 3,
		'acf_block_version'  => 3, // ver comentario largo en "contenido-por-pais" más arriba — hace falta para InnerBlocks real.
		'supports'           => array(
			'align'           => false,
			'customClassName' => false,
			'anchor'          => false,
		),
	) );

	// Ver comentario del bloque más arriba: sin este filtro, ACF envuelve el
	// InnerBlocks en un <div class="acf-innerblocks-container"> propio —
	// un nivel de DOM extra que rompería la cascada de espaciado unificado
	// (".entry-content > * + *" no llega hasta ahí) para lo que sea que se
	// ponga adentro de "Contenido por País".
	// "Grilla" (blocks/grilla/render.php) entra acá por otro motivo, NO por
	// el front: ese bloque arma su HTML del front a mano (recorre
	// $wp_block->parsed_block['innerBlocks'] y llama render_block() él
	// mismo, sin usar la etiqueta <InnerBlocks /> en absoluto), así que ahí
	// este filtro no hace nada — este 'wrap_frontend_innerblocks' solo
	// entra en juego cuando ACF reemplaza un <InnerBlocks /> real, y eso
	// pasa únicamente en la vista previa del editor ($is_preview, 'mode' =>
	// 'preview' en el registro del bloque, más abajo) — que sí imprime esa
	// etiqueta tal cual. Sin excluir "acf/grilla" acá, la vista previa
	// dentro del editor mostraría el mismo div de ACF de más, rompiendo el
	// Grid visualmente MIENTRAS SE EDITA (display:grid necesita que los
	// ítems sean hijos directos de .jb3-grilla) — aunque en el sitio real
	// nunca se hubiera notado.
	add_filter( 'acf/blocks/wrap_frontend_innerblocks', function ( $wrap, $block_name ) {
		return in_array( $block_name, array( 'acf/contenido-por-pais', 'acf/grilla' ), true ) ? false : $wrap;
	}, 10, 2 );
} );
