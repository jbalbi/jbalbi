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
	 */
	public static function instrucciones(): string {
		return sprintf(
			'Hoy es %s. Extraé de la pregunta del usuario los filtros de búsqueda de contenido de un sitio de medios, según la estructura de datos pedida — usá la fecha de hoy para resolver referencias relativas ("este año", "el año pasado", "últimos 2 años") a un año concreto. Omití cualquier campo que la pregunta no mencione — no inventes valores que no estén en las opciones permitidas. Si el texto de entrada incluye un bloque "Conversación previa:" seguido de "Pregunta actual:", usá esa conversación previa SOLO para interpretar la pregunta actual (ej. un tema mencionado antes que ahora se retoma implícitamente) — extraé los filtros en base a esa interpretación de la pregunta actual, no de la conversación completa.',
			date_i18n( 'Y-m-d' )
		);
	}
}
