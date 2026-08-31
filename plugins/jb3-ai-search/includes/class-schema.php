<?php
defined( 'ABSPATH' ) || exit;

/**
 * Definición de los campos que le pedimos al modelo, en formato neutral
 * (ni el "tool use" de Claude ni el "responseSchema" de Gemini directamente)
 * — cada cliente (class-client-claude.php / class-client-gemini.php) la
 * traduce a su propio formato de pedido. Un solo lugar para agregar/tocar un
 * campo, en vez de mantenerlo repetido en cada proveedor.
 */
class JB3_AISearch_Schema {

	/**
	 * @return array Lista de campos: cada uno con 'nombre', 'tipo'
	 *               ('string'|'integer'|'string[]'), 'descripcion', 'enum'
	 *               opcional (valores permitidos), y 'requerido' (default
	 *               true — false para los que el modelo tiene que poder
	 *               DEJAR AFUERA de la respuesta en vez de forzar un valor).
	 *
	 *               'canal' es el caso que importa: tiene una lista cerrada
	 *               de valores válidos (enum) SIN incluir "vacío" — Gemini
	 *               rechaza un string vacío como valor de enum (ver
	 *               class-client-gemini.php). Si "canal" fuera requerido,
	 *               el modelo quedaría obligado a inventar un canal real
	 *               aunque la pregunta no mencione ninguno (confirmado
	 *               2026-08-26: "películas de acción" devolvía canal
	 *               "sony-movies" de la nada) — por eso queda opcional, así
	 *               puede omitirlo del todo en vez de "rellenar" con algo.
	 */
	public static function campos(): array {
		$canales = get_terms( [
			'taxonomy'   => 'canales',
			'hide_empty' => true,
			'fields'     => 'slugs',
		] );
		$canales = is_wp_error( $canales ) ? [] : $canales;

		// "etiquetas" es libre (los editores cargan tags nuevos al vuelo,
		// ver _init_taxonomias.php) — NO se restringe con "enum" como
		// canal/tipo_contenido, porque eso le impediría al modelo elegir
		// una etiqueta real que se haya creado después de este código. En
		// cambio se le pasa la lista actual como REFERENCIA en la
		// descripción, para que pueda corregir errores de tipeo/acentos
		// contra algo que existe de verdad en vez de repetir literal lo
		// que escribió el usuario (bug real confirmado 2026-08-26: "fomantica"
		// devolvía un slug que no matcheaba nada, se descartaba, y la
		// búsqueda quedaba sin ese filtro — traía todo el contenido).
		$etiquetas = get_terms( [
			'taxonomy'   => 'etiquetas',
			'hide_empty' => true,
			'fields'     => 'slugs',
			'number'     => 80,
		] );
		$etiquetas = is_wp_error( $etiquetas ) ? [] : $etiquetas;

		return [
			[
				'nombre'      => 'tipo_contenido',
				'tipo'        => 'string[]',
				'enum'        => JB3_AISEARCH_POST_TYPES,
				'descripcion' => 'Tipos de contenido pedidos (peliculas, series, prod_originales, op_comerciales, videos, integraciones). Array vacío si la pregunta no especifica tipo.',
			],
			[
				'nombre'      => 'canal',
				'tipo'        => 'string',
				'enum'        => $canales,
				'requerido'   => false,
				'descripcion' => 'Slug del canal, SOLO si la pregunta lo menciona explícitamente y coincide con uno de la lista. Si no lo menciona, omitir este campo por completo — no elegir uno al azar.',
			],
			[
				'nombre'      => 'etiqueta',
				'tipo'        => 'string',
				'requerido'   => false,
				'descripcion' => 'Palabra clave de género/tema mencionada, en minúsculas y sin acentos. Etiquetas que ya existen (elegí la más parecida si el usuario escribió con error de tipeo, sin acento, o en otro orden de palabras — no hace falta que sea calcado): ' . ( $etiquetas ? implode( ', ', $etiquetas ) : '(sin etiquetas cargadas todavía)' ) . '. Si ninguna se parece a lo que pidió pero igual queda claro qué tema es, proponé una etiqueta nueva razonable. Omitir el campo si no aplica.',
			],
			[
				'nombre'      => 'texto_libre',
				'tipo'        => 'string',
				'requerido'   => false,
				'descripcion' => 'Palabras clave para buscar en el título y la sinopsis — nombre propio, año/fecha mencionado, o cualquier cosa que no se resuelva con los filtros de arriba (ej. el nombre de una película, o "2023" si la pregunta menciona un año — NO hay un campo de fecha aparte, el año va acá como palabra clave). Omitir si los filtros de arriba ya cubren toda la pregunta.',
			],
			[
				'nombre'      => 'resumen',
				'tipo'        => 'string',
				'requerido'   => false,
				// Usado SOLO por el chat bubble (jb3_aisearch_chat_texto_respuesta(),
				// class-chat-ajax.php) para armar una respuesta en lenguaje
				// natural en vez de repetir la pregunta del usuario tal cual
				// entre comillas — pedido explícito 2026-08-30 ("no encontré
				// nada para 'y alguna pelicula con tom hanks?'" sonaba robótico).
				// A PROPÓSITO se le pide una frase corta que describe la
				// BÚSQUEDA, nunca una respuesta — en este punto el modelo
				// todavía no sabe si hay resultados o no (esta extracción
				// corre ANTES de la consulta real), así que no puede afirmar
				// que algo existe/no existe; eso lo decide el conteo real de
				// WP_Query del lado del código, no el modelo. El shortcode de
				// búsqueda [jb3_buscador_ia] también recibe este campo (mismo
				// schema para los dos) pero no lo usa — no rompe nada.
				'descripcion' => 'Frase corta (3-6 palabras) en español que describe QUÉ está buscando el usuario, sin punto final — para mostrarla en una respuesta tipo "Encontré resultados de {resumen}" / "No encontré {resumen}". Mantené la capitalización normal de nombres propios (ej. "Tom Hanks", no "tom hanks"), el resto en minúsculas salvo que corresponda mayúscula. Ej: "películas con Tom Hanks", "series de comedia en AXN", "producciones de este año". NO es una respuesta ni una afirmación de que algo existe — solo una paráfrasis corta de la pregunta. Omitir si no se puede resumir en pocas palabras.',
			],
		];
	}

	/**
	 * Texto de instrucciones común a cualquier proveedor. Incluye la fecha
	 * de hoy a propósito — el modelo no sabe qué fecha es "hoy" por su
	 * cuenta, y sin este dato una referencia relativa como "de este año" se
	 * resolvía con un año inventado (confirmado 2026-08-26: devolvía 2024
	 * estando hoy en 2026). No hay campo de fecha estructurado (el sitio no
	 * guarda el año real de estreno de cada contenido en ningún dato
	 * consultable todavía, solo puede aparecer escrito en la sinopsis) —
	 * el año resuelto va como palabra clave dentro de "texto_libre".
	 *
	 * Última frase agregada para el chat bubble (class-chat-ajax.php,
	 * 2026-08-30): cuando hay conversación previa, ese llamador arma un solo
	 * string "Conversación previa: ... Pregunta actual: ..." y lo manda acá
	 * tal cual — esta función no cambia de firma ni sabe nada de historial,
	 * solo le avisa al modelo cómo leer esa forma si aparece.
	 *
	 * Ampliada el mismo día — reportado en vivo: preguntar "películas de
	 * terror" y después, sin relación, "contenido de cocina" devolvía
	 * resultados de terror — el modelo se "anclaba" al tema fuerte de la
	 * conversación previa en vez de darse cuenta de que era un tema nuevo.
	 * Se le aclara explícitamente que use el historial SOLO ante una
	 * continuación real (pronombres, "y", "también", etc.) y lo ignore por
	 * completo si la pregunta actual es un tema independiente.
	 */
	public static function instrucciones(): string {
		return sprintf(
			'Hoy es %s. Extraé de la pregunta del usuario los filtros de búsqueda de contenido de un sitio de medios, según la estructura de datos pedida — usá la fecha de hoy para resolver referencias relativas ("este año", "el año pasado", "últimos 2 años") a un año concreto. Omití cualquier campo que la pregunta no mencione — no inventes valores que no estén en las opciones permitidas. Si el texto de entrada incluye un bloque "Conversación previa:" seguido de "Pregunta actual:": usá esa conversación previa SOLO si la pregunta actual es una continuación real y explícita de lo anterior (ej. empieza con "y...", "también...", usa un pronombre que se refiere a algo mencionado antes, o pide un ajuste tipo "¿y en otro canal?"). Si la pregunta actual plantea un tema nuevo sin relación con lo anterior (ej. cambia completamente de género, tipo de contenido o tema), IGNORÁ POR COMPLETO la conversación previa y extraé los filtros como si fuera la primera pregunta — nunca arrastres un filtro de la conversación anterior a un tema no relacionado.',
			date_i18n( 'Y-m-d' )
		);
	}

	/**
	 * Instrucciones para la SEGUNDA llamada a la IA que usa el chat bubble
	 * (ver jb3_aisearch_chat_redactar_respuesta() en jb3-ai-search.php) —
	 * redacta la frase final que ve el usuario, distinta de instrucciones()
	 * de arriba (esa extrae filtros, esta redacta texto). Vive acá, un solo
	 * lugar, para que class-client-gemini.php y class-client-claude.php usen
	 * exactamente el mismo texto sin duplicarlo — pedido explícito
	 * 2026-08-31 ("que TODAS las conversaciones sean más naturales").
	 *
	 * Todo lo que puede decir sobre resultados sale de datos REALES que el
	 * llamador ya le pasa como contenido del pedido (cantidad, títulos) —
	 * acá solo se le prohíbe explícitamente inventar algo fuera de eso.
	 */
	public static function instrucciones_chat_respuesta( bool $es_seguimiento ): string {
		$base = 'Sos el asistente conversacional de un sitio de streaming (películas, series, producciones originales, contenido comercial, videos e integraciones de marca). '
			. 'Te paso el resultado REAL de una búsqueda que ya se hizo — nunca inventes títulos, cantidades ni datos que no estén en lo que te paso. '
			. 'Redactá UNA sola respuesta corta (1-2 frases), tono cercano y natural en español, sin comillas, sin emojis, sin markdown, sin repetir la pregunta del usuario entre comillas. '
			. 'Si la cantidad de resultados es 0: avisá con calidez que no encontraste nada de eso y sugerí reformular o probar otra búsqueda, sin sonar como un error técnico. '
			. 'Si es mayor a 0: ofrecé los resultados con entusiasmo genuino — podés nombrar uno o dos títulos de la lista como ejemplo si suena natural, pero no hace falta nombrarlos todos porque se muestran aparte como tarjetas con imagen y título.';

		if ( $es_seguimiento ) {
			$base .= ' Esta pregunta es una continuación de la conversación anterior — si tiene sentido, redactalo como una continuación natural, no como si fuera la primera vez que hablan.';
		}

		return $base;
	}
}
