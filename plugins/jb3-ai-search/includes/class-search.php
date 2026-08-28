<?php
defined( 'ABSPATH' ) || exit;

/**
 * Arma y corre la WP_Query a partir de los filtros que devolvió el modelo
 * (o, si la API falló, a partir de una búsqueda de texto simple — ver
 * buscar_texto_simple()). El país SIEMPRE sale de jb3_geo_visitor_country(),
 * reusando jb3_geo_tax_query_pais() tal cual la usa el resto del sitio
 * (pre_get_posts, [jb3_modulo]) — ningún valor que haya extraído el modelo
 * puede tocar esa parte del filtro.
 */
class JB3_AISearch_Query {

	/**
	 * @param string     $pregunta Texto original (se usa si el modelo no
	 *                              devolvió nada usable).
	 * @param array|null $filtros  Lo que devolvió JB3_AISearch_Claude_Client,
	 *                              o null si la llamada a la API falló.
	 * @return int[] IDs de posts, ya filtrados por geo-targeting.
	 */
	public static function buscar( string $pregunta, ?array $filtros ): array {
		if ( null === $filtros ) {
			return self::buscar_texto_simple( $pregunta );
		}

		// Sin date_query: el sitio no guarda en ningún dato consultable el
		// año real de estreno de cada contenido (confirmado 2026-08-26 —
		// post_date es la fecha en que se cargó en el CMS, no la del
		// estreno; no hay ACF de "año"). Si el modelo detecta un año en la
		// pregunta, lo manda dentro de "texto_libre" (ver class-schema.php)
		// para que la búsqueda de texto normal lo encuentre si aparece
		// escrito en la sinopsis — funciona solo apenas haya sinopsis
		// cargadas, sin tocar nada de este archivo.
		$traducidos = self::filtros_a_query_args( $filtros );

		$args = [
			'post_type'      => $traducidos['post_type'],
			'post_status'    => 'publish',
			'posts_per_page' => 24,
			'tax_query'      => array_merge( self::pais_tax_query(), $traducidos['tax_query'] ),
		];

		if ( '' !== $traducidos['s'] ) {
			$args['s'] = $traducidos['s'];
		}

		$resultados = ( new WP_Query( $args ) )->posts;

		// Ni el modelo ni un tax_query mal armado pueden dar falsos
		// positivos de geo-targeting: cada resultado se revalida acá contra
		// jb3_geo_post_visible() (misma función que usa el "the_posts" del
		// buscador nativo) — doble candado, no solo el tax_query de arriba.
		return self::filtrar_geo_visibles( wp_list_pluck( $resultados, 'ID' ) );
	}

	private static function buscar_texto_simple( string $pregunta ): array {
		$args = [
			'post_type'      => JB3_AISEARCH_POST_TYPES,
			'post_status'    => 'publish',
			'posts_per_page' => 24,
			's'              => $pregunta,
			'tax_query'      => self::pais_tax_query(),
		];

		$resultados = ( new WP_Query( $args ) )->posts;
		return self::filtrar_geo_visibles( wp_list_pluck( $resultados, 'ID' ) );
	}

	/**
	 * jb3_geo_post_visible() vive en el plugin jb3-geo-targeting, no en
	 * este — si por lo que sea no estuviera activo (este plugin depende de
	 * él pero WordPress no fuerza dependencias entre plugins), sin este
	 * guard array_filter() explotaría llamando a una función que no
	 * existe. Sin geo-targeting activo simplemente no hay nada que
	 * filtrar por país — se devuelven los resultados tal cual.
	 *
	 * @param int[] $ids
	 * @return int[]
	 */
	private static function filtrar_geo_visibles( array $ids ): array {
		if ( ! function_exists( 'jb3_geo_post_visible' ) ) {
			return $ids;
		}
		return array_values( array_filter( $ids, 'jb3_geo_post_visible' ) );
	}

	/** Mismo geo-targeting que el resto del sitio (pre_get_posts, [jb3_modulo]) — ver el docblock de la clase. */
	private static function pais_tax_query(): array {
		return function_exists( 'jb3_geo_tax_query_pais' )
			? jb3_geo_tax_query_pais( jb3_geo_visitor_country() )
			: [];
	}

	/**
	 * Traduce los filtros ya extraídos (y validados) a los tres argumentos
	 * de WP_Query que le interesan a un consumidor externo — SIN el
	 * tax_query de país: lo usa JB3_AISearch_Native_Search (pre_get_posts
	 * de la búsqueda nativa), que corre dentro de is_main_query() y ya
	 * queda cubierto por el filtro "the_posts" de geo-targeting que aplica
	 * el sitio para cualquier búsqueda (ver jb3-geo-targeting.php) — sumar
	 * el país acá también sería redundante, no incorrecto, pero de más.
	 *
	 * @return array{post_type: string[], tax_query: array, s: string}
	 */
	public static function filtros_a_query_args( array $filtros ): array {
		$post_types = self::validar_post_types( $filtros['tipo_contenido'] ?? [] );
		$canal      = self::validar_termino( 'canales', $filtros['canal'] ?? '' );
		$etiqueta   = self::validar_termino( 'etiquetas', $filtros['etiqueta'] ?? '' );
		$texto      = trim( (string) ( $filtros['texto_libre'] ?? '' ) ); // (string) explícito acá también — ver el mismo motivo en validar_termino().

		$tax_query = [];
		if ( '' !== $canal ) {
			$tax_query[] = [ 'taxonomy' => 'canales', 'field' => 'slug', 'terms' => [ $canal ] ];
		}
		if ( '' !== $etiqueta ) {
			$tax_query[] = [ 'taxonomy' => 'etiquetas', 'field' => 'slug', 'terms' => [ $etiqueta ] ];
		}

		return [ 'post_type' => $post_types, 'tax_query' => $tax_query, 's' => $texto ];
	}

	/** Solo deja pasar tipos de contenido reales — cualquier otra cosa se ignora. Vacío = los 6. */
	public static function validar_post_types( $tipos ): array {
		$tipos = is_array( $tipos ) ? array_intersect( $tipos, JB3_AISEARCH_POST_TYPES ) : [];
		return $tipos ? array_values( $tipos ) : JB3_AISEARCH_POST_TYPES;
	}

	/**
	 * El modelo puede devolver un slug que no existe (typo del usuario que
	 * no reconoció, sinónimo, etc.) — en vez de descartarlo directo (que
	 * dejaba la búsqueda SIN ese filtro y traía todo el contenido, bug real
	 * confirmado 2026-08-26 con "fomantica"), se busca el término real más
	 * parecido por similitud de texto antes de rendirse. Para "etiqueta" ya
	 * le pasamos al modelo la lista real como referencia (ver
	 * class-schema.php) — esto es la segunda red, por si igual no matcheó
	 * exacto (mayúsculas, tildes, plural/singular, etc.).
	 *
	 * $slug recibido como "mixed" a propósito (no "string" tipado): el JSON
	 * que devuelve el modelo (Gemini/Claude) no está 100% garantizado —
	 * bug real confirmado 2026-08-27, un valor con forma inesperada acá
	 * tiraba un TypeError sin capturar que rompía la búsqueda de TODO el
	 * sitio (esta función corre también en la búsqueda nativa vía
	 * class-native-search.php), y el resultado roto quedaba cacheado 10
	 * minutos. Casteamos adentro en vez de confiar en que cada lugar que
	 * llame a esto se acuerde de hacerlo.
	 *
	 * @param mixed $slug
	 */
	public static function validar_termino( string $taxonomia, $slug ): string {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return '';
		}

		if ( get_term_by( 'slug', $slug, $taxonomia ) ) {
			return $slug;
		}

		return self::termino_mas_parecido( $taxonomia, $slug );
	}

	/** Umbral 60%: bastante tolerante para typos reales, sin matchear cosas sin relación. */
	private static function termino_mas_parecido( string $taxonomia, string $slug ): string {
		$reales = get_terms( [ 'taxonomy' => $taxonomia, 'hide_empty' => true, 'fields' => 'slugs' ] );
		if ( is_wp_error( $reales ) || ! $reales ) {
			return '';
		}

		$mejor_slug = '';
		$mejor_pct  = 0.0;

		foreach ( $reales as $real ) {
			similar_text( $slug, $real, $pct );
			if ( $pct > $mejor_pct ) {
				$mejor_pct  = $pct;
				$mejor_slug = $real;
			}
		}

		return $mejor_pct >= 60.0 ? $mejor_slug : '';
	}
}
