<?php
/**
 * Plugin Name: JB3 Geo Targeting
 * Description: Restringe la visibilidad del contenido según el país del visitante (taxonomía "pais"), usando Geolocation IP Detection. Sin país asignado el contenido NO es visible en ningún país — hay que asignar explícitamente dónde se ve.
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

const JB3_GEO_TAXONOMY       = 'pais';
const JB3_GEO_CANAL_TAXONOMY = 'canales';
const JB3_GEO_CANAL_FIELD    = 'paises'; // Campo ACF (Taxonomy → pais) del término de "canales".
const JB3_GEO_PAGE_CANAL_FIELD = 'canal'; // Campo ACF (Taxonomy → canales) de la Página con plantilla "Canal".
const JB3_GEO_COOKIE         = 'jb3_country_override';
const JB3_GEO_CACHE_PREFIX   = 'jb3_geo_';
const JB3_GEO_CACHE_TTL      = DAY_IN_SECONDS;

/** Tipos de contenido con restricción por país (misma taxonomía "pais" que registra el theme). */
function jb3_geo_post_types(): array {
	return [ 'peliculas', 'series', 'prod_originales', 'op_comerciales', 'videos', 'integraciones', 'post', 'page' ];
}

// ─── PAÍS DEL VISITANTE ──────────────────────────────────────────────────────
// Prioridad: 1) cookie de override, solo válida para quien puede administrar
// (previsualizar el sitio como si fuera de otro país, sin depender de una
// VPN) 2) caché por IP (evita pegarle a la base de GeoIP en cada request)
// 3) detección real con el plugin Geolocation IP Detection.
// Devuelve el código ISO en minúscula (coincide con los slugs de la
// taxonomía "pais": ar, bo, cl...) o '' si no se pudo determinar — ese caso
// se trata como "sin restricción" en jb3_geo_post_visible() (fail-open).
function jb3_geo_visitor_country(): string {
	if ( current_user_can( 'manage_options' ) && ! empty( $_COOKIE[ JB3_GEO_COOKIE ] ) ) {
		return sanitize_key( $_COOKIE[ JB3_GEO_COOKIE ] );
	}

	if ( ! function_exists( 'geoip_detect2_get_info_from_current_ip' ) ) {
		return '';
	}

	$ip        = geoip_detect2_get_client_ip();
	$cache_key = JB3_GEO_CACHE_PREFIX . md5( $ip );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$info   = geoip_detect2_get_info_from_current_ip();
	$codigo = ( ! $info->isEmpty && $info->country && $info->country->isoCode )
		? strtolower( $info->country->isoCode )
		: '';

	set_transient( $cache_key, $codigo, JB3_GEO_CACHE_TTL );
	return $codigo;
}

// ─── COOKIE DE VARY PARA EL CACHÉ DE PÁGINA (LiteSpeed) ─────────────────────
// El caché de página completa sirve la MISMA respuesta HTML a todos los
// visitantes de una URL — sin esto, un visitante de un país vería el HTML
// (hasta un 404) que le tocó a otro país, porque nada de este archivo vuelve
// a correr en un cache hit. Esta cookie hace que LiteSpeed guarde una copia
// de caché separada por país en vez de una sola compartida — hacen falta LAS
// DOS registraciones de abajo (litespeed_vary_cookies Y
// litespeed_vary_curr_cookies); con una sola, LiteSpeed sigue marcando la
// página entera como no cacheable por el simple hecho de hacer setcookie().
const JB3_GEO_VARY_COOKIE = 'jb3_pais';

/**
 * setcookie() con los flags de hardening que le faltaban (auditoría de
 * seguridad 2026-08-27) — ninguna de las cookies de este plugin la lee JS
 * (confirmado, ver el resto del código), así que HttpOnly no rompe nada.
 * Secure sale de is_ssl() en vez de hardcodeado: local corre por HTTP
 * plano, forzar Secure=true ahí haría que el navegador ignore la cookie.
 */
function jb3_geo_setcookie( string $name, string $value, int $expire ): void {
	setcookie( $name, $value, [
		'expires'  => $expire,
		'path'     => COOKIEPATH,
		'domain'   => COOKIE_DOMAIN,
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	] );
}

add_action( 'init', function () {
	if ( is_admin() ) {
		return;
	}

	$pais = jb3_geo_visitor_country();
	if ( ( $_COOKIE[ JB3_GEO_VARY_COOKIE ] ?? '' ) !== $pais ) {
		jb3_geo_setcookie( JB3_GEO_VARY_COOKIE, $pais, time() + DAY_IN_SECONDS );
	}
} );

function jb3_geo_vary_cookies( array $cookies ): array {
	$cookies[] = JB3_GEO_VARY_COOKIE;
	return $cookies;
}
add_filter( 'litespeed_vary_cookies', 'jb3_geo_vary_cookies' );
add_filter( 'litespeed_vary_curr_cookies', 'jb3_geo_vary_cookies' );

// ─── PAÍSES PERMITIDOS DE UN CANAL ──────────────────────────────────────────
// Lee el campo ACF "paises_permitidos" (Taxonomy → pais, checkbox, return
// format "Term ID") del término de "canales". Vacío = SIN países tildados,
// el canal NO se ve en ningún país — mismo criterio fail-closed que ahora
// usa "pais" a nivel de post individual (cambiado 2026-08-14: antes vacío
// = todos, pero con el botón "Seleccionar todo"/"Invertir selección" ya no
// hace falta ese atajo, y "vacío = todos" escondía errores de carga real).
function jb3_geo_canal_paises_permitidos( int $canal_term_id ): array {
	if ( ! function_exists( 'get_field' ) ) {
		return [];
	}

	$ids = get_field( JB3_GEO_CANAL_FIELD, JB3_GEO_CANAL_TAXONOMY . '_' . $canal_term_id );
	if ( empty( $ids ) ) {
		return [];
	}

	$terms = get_terms( [
		'taxonomy'   => JB3_GEO_TAXONOMY,
		'include'    => array_map( 'intval', (array) $ids ),
		'fields'     => 'slugs',
		'hide_empty' => false,
	] );
	return is_wp_error( $terms ) ? [] : $terms;
}

function jb3_geo_canal_visible_para( int $canal_term_id, string $pais ): bool {
	$permitidos = jb3_geo_canal_paises_permitidos( $canal_term_id );
	return in_array( $pais, $permitidos, true );
}

// Term IDs de "canales" visibles para $pais — usado para armar tax_query en
// listados (resolverlo acá evita chequear canal por canal en cada post).
// Cacheado en memoria: son pocos canales y se pide varias veces por request.
function jb3_geo_canales_visibles( string $pais ): array {
	static $cache = [];
	if ( isset( $cache[ $pais ] ) ) {
		return $cache[ $pais ];
	}

	$canales = get_terms( [
		'taxonomy'   => JB3_GEO_CANAL_TAXONOMY,
		'fields'     => 'ids',
		'hide_empty' => false,
	] );
	if ( is_wp_error( $canales ) ) {
		return $cache[ $pais ] = [];
	}

	return $cache[ $pais ] = array_values( array_filter( $canales, function ( $id ) use ( $pais ) {
		return jb3_geo_canal_visible_para( (int) $id, $pais );
	} ) );
}

// Cláusulas de tax_query para "país + canales visibles en ese país" — mismo
// criterio en los 3 lugares que restringen listados de contenido por país:
// los listados nativos (pre_get_posts acá abajo), [jb3_modulo]
// (jb3_modulo_query() en _init_modulo.php) y "Contenido Relacionado"
// automático (jb3_etiquetas_auto() en _init_etiquetas.php). Antes estaba
// copiada a mano en los 3 — eso ya generó un bug real (AXN mostrando Op.
// Comerciales sin canal asignado en un módulo que se suponía exclusivo,
// 2026-08-13) porque una de las copias no incluía la vuelta "OR: sin canal
// asignado" y las otras dos sí. Devuelve un array de cláusulas para sumar
// (array_merge) al 'tax_query' que ya tenga armado quien llama.
//
// Fail-closed (decisión del cliente 2026-08-26): con $pais vacío (no se
// pudo determinar el país del visitante) se devuelve una cláusula que no
// matchea NINGÚN post, en vez de [] (sin filtro = mostraría todo el
// catálogo a cualquiera). Mismo criterio que jb3_geo_post_pais_visible()
// — no afecta a Home/Login/páginas institucionales, esas se validan aparte
// por jb3_geo_post_visible() y ya están exceptuadas ahí.
function jb3_geo_tax_query_pais( string $pais ): array {
	if ( '' === $pais ) {
		return [
			[ 'taxonomy' => JB3_GEO_TAXONOMY, 'field' => 'slug', 'terms' => [ '__jb3_pais_no_detectado__' ] ],
		];
	}

	return [
		[
			'taxonomy' => JB3_GEO_TAXONOMY,
			'field'    => 'slug',
			'terms'    => [ $pais ],
		],
		[
			'relation' => 'OR',
			[ 'taxonomy' => JB3_GEO_CANAL_TAXONOMY, 'operator' => 'NOT EXISTS' ],
			[ 'taxonomy' => JB3_GEO_CANAL_TAXONOMY, 'field' => 'term_id', 'terms' => jb3_geo_canales_visibles( $pais ) ],
		],
	];
}

// ─── ¿SE PUEDE MOSTRAR ESTE CONTENIDO? ──────────────────────────────────────
// Sin país asignado = NO visible en ningún país (cambiado 2026-08-14, ver
// comentario del plugin arriba) — hay que tildar explícitamente dónde se
// muestra. EXCEPTO "page"/"post": el Home, Login/Registro/Perfil/Logout y
// cualquier página institucional del sitio son infraestructura, no
// catálogo — nunca usaron esta taxonomía y no tiene sentido exigírsela.
// Bug real detectado 2026-08-14: sin esta excepción, el Home y el flujo de
// Login quedaban 404 apenas se exigió país en todos lados por igual.
function jb3_geo_post_pais_visible( int $post_id ): bool {
	$terminos = get_the_terms( $post_id, JB3_GEO_TAXONOMY );
	if ( empty( $terminos ) || is_wp_error( $terminos ) ) {
		return in_array( get_post_type( $post_id ), [ 'page', 'post' ], true );
	}

	$pais = jb3_geo_visitor_country();
	if ( '' === $pais ) {
		// Fail-closed (decisión del cliente 2026-08-26, revirtiendo el
		// fail-open original): este contenido SÍ tiene países asignados
		// (algo lo restringe), pero no pudimos determinar el del
		// visitante (GeoIP caído, IP no reconocida, etc.) — con licencias
		// territoriales de por medio, ante la duda no se muestra, en vez
		// de mostrarlo a todo el mundo por un fallo de detección.
		return false;
	}

	foreach ( $terminos as $termino ) {
		if ( $termino->slug === $pais ) {
			return true;
		}
	}
	return false;
}

// Un post puede tener varios canales a la vez (taxonomía "canales" es
// multivalor) — alcanza con que UNO de ellos permita el país del visitante
// (mismo criterio allow-list/OR que ya usa jb3_geo_post_pais_visible()).
// Sin canal asignado = no aplica, no restringe nada.
function jb3_geo_post_canal_visible( int $post_id ): bool {
	$terminos = get_the_terms( $post_id, JB3_GEO_CANAL_TAXONOMY );
	if ( empty( $terminos ) || is_wp_error( $terminos ) ) {
		return true;
	}

	$pais = jb3_geo_visitor_country();
	if ( '' === $pais ) {
		// Fail-closed — ver el mismo cambio (y su motivo) en
		// jb3_geo_post_pais_visible() más arriba.
		return false;
	}

	foreach ( $terminos as $termino ) {
		if ( jb3_geo_canal_visible_para( $termino->term_id, $pais ) ) {
			return true;
		}
	}
	return false;
}

// Las Páginas de canal (plantilla "Canal", ver page-canal.php) no tienen la
// taxonomía "canales" asignada directamente — se vinculan a un término vía
// el campo ACF "canal" (Taxonomy → canales, un solo valor). Si esa Página
// no tiene el campo cargado, no aplica ninguna restricción por esta vía
// (podría ser una Página normal que no usa esa plantilla).
function jb3_geo_page_canal_visible( int $post_id ): bool {
	if ( ! function_exists( 'get_field' ) ) {
		return true;
	}

	$canal_id = get_field( JB3_GEO_PAGE_CANAL_FIELD, $post_id );
	if ( empty( $canal_id ) ) {
		return true;
	}

	$pais = jb3_geo_visitor_country();
	if ( '' === $pais ) {
		// Fail-closed — ver el mismo cambio (y su motivo) en
		// jb3_geo_post_pais_visible() más arriba.
		return false;
	}

	return jb3_geo_canal_visible_para( (int) $canal_id, $pais );
}

function jb3_geo_post_visible( int $post_id ): bool {
	// Las Páginas de Canal (plantilla "Canal") delegan el país ENTERO al
	// canal vinculado (campo ACF "canal" → jb3_geo_page_canal_visible(),
	// que ya chequea los países permitidos de ESE canal) — nunca tuvieron
	// ni necesitan su propia taxonomía "pais" cargada directamente. Bug
	// real detectado 2026-08-14: al pasar "pais" a fail-closed, TODAS las
	// páginas de canal (AXN, Sony Channel...) empezaron a dar 404, porque
	// jb3_geo_post_pais_visible() les exigía una asignación que esas
	// páginas jamás cargan por diseño.
	if ( function_exists( 'get_field' ) && get_field( JB3_GEO_PAGE_CANAL_FIELD, $post_id ) ) {
		return jb3_geo_page_canal_visible( $post_id );
	}

	return jb3_geo_post_pais_visible( $post_id )
		&& jb3_geo_post_canal_visible( $post_id )
		&& jb3_geo_page_canal_visible( $post_id );
}

// ─── CONTENIDO INDIVIDUAL: 404 si no corresponde a tu país ──────────────────
// Cubre posts (película, serie...) Y las Páginas de canal (plantilla
// "Canal") — jb3_geo_post_visible() ya combina el chequeo de "pais" propio,
// el de "canales" directo, y el del vínculo Página→canal.
add_action( 'template_redirect', function () {
	if ( ! is_singular( jb3_geo_post_types() ) ) {
		return;
	}

	if ( jb3_geo_post_visible( get_queried_object_id() ) ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
} );

// ─── LISTADOS/ARCHIVOS/BÚSQUEDA: excluir lo que no corresponde ──────────────
// Match estricto por "pais" (sin el NOT EXISTS de antes) — un post sin país
// tildado ya no aparece en ningún listado, misma regla que
// jb3_geo_post_pais_visible() pero a nivel de consulta, para que ese
// contenido ni aparezca en listados. La rama de "canales" de abajo es OTRO
// eje (post sin CANAL asignado) y sigue fail-open a propósito — no es lo
// que se pidió cambiar acá. Igual que en jb3_geo_post_pais_visible(): si el
// post_type consultado es SOLO "page"/"post" (ej. la búsqueda nativa de
// WordPress, que por defecto busca en los dos), no se aplica este filtro —
// esas páginas nunca usaron "pais" y no tiene sentido exigírselo acá
// tampoco.
add_action( 'pre_get_posts', function ( WP_Query $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$post_type = (array) ( $query->get( 'post_type' ) ?: [ 'post' ] );
	if ( ! array_diff( $post_type, [ 'page', 'post' ] ) ) {
		return;
	}

	$pais = jb3_geo_visitor_country();
	if ( '' === $pais ) {
		return;
	}

	$tax_query = array_merge( $query->get( 'tax_query' ) ?: [], jb3_geo_tax_query_pais( $pais ) );
	$query->set( 'tax_query', $tax_query );
} );

// ─── BÚSQUEDA NATIVA: filtrar resultados por país después de la consulta ────
// Bug real confirmado 2026-08-21: el comentario del filtro de arriba asumía
// que la búsqueda nativa de WordPress solo busca en Páginas/Entradas por
// defecto — falso, "peliculas" (y el resto de los CPT) no tienen
// exclude_from_search seteado, así que quedan incluidos igual y ese filtro
// nunca se aplicaba a la búsqueda (post_type sin declarar → cae en el
// ['post'] de arriba → early return). Se resuelve DESPUÉS de la consulta,
// no extendiendo el tax_query de arriba: ese tax_query exige la taxonomía
// "pais" a rajatabla, y Páginas/Entradas están a propósito exceptuadas de
// esa exigencia en TODO el resto del sitio (nunca usaron esa taxonomía) —
// replicar mal esa excepción acá rompería la búsqueda de contenido
// institucional. jb3_geo_post_visible() ya tiene esa excepción bien
// resuelta (es la misma función que usa el 404 de arriba), así que se
// reusa tal cual en vez de duplicar la lógica en un tax_query nuevo.
add_filter( 'the_posts', function ( array $posts, WP_Query $query ) {
	if ( is_admin() || ! $query->is_search() || ! $query->is_main_query() ) {
		return $posts;
	}
	return array_values( array_filter( $posts, fn( WP_Post $post ) => jb3_geo_post_visible( $post->ID ) ) );
}, 10, 2 );

// ─── BARRA DE ADMIN: previsualizar el sitio como otro país ──────────────────
add_action( 'admin_bar_menu', function ( WP_Admin_Bar $admin_bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$actual = ! empty( $_COOKIE[ JB3_GEO_COOKIE ] ) ? sanitize_key( $_COOKIE[ JB3_GEO_COOKIE ] ) : '';
	$titulo = $actual ? 'Viendo como: ' . strtoupper( $actual ) : 'Ver como país…';

	$admin_bar->add_node( [
		'id'    => 'jb3-geo-preview',
		'title' => $titulo,
	] );

	$terminos = get_terms( [ 'taxonomy' => JB3_GEO_TAXONOMY, 'hide_empty' => false ] );
	if ( is_wp_error( $terminos ) ) {
		return;
	}

	$url_actual = add_query_arg( null, null ); // ruta+query de la página actual, sin armar nada a mano.

	if ( $actual ) {
		$admin_bar->add_node( [
			'id'     => 'jb3-geo-preview-quitar',
			'parent' => 'jb3-geo-preview',
			'title'  => 'Quitar override (ver mi país real)',
			'href'   => wp_nonce_url( add_query_arg( 'jb3_geo_pais', '', $url_actual ), 'jb3_geo_preview' ),
		] );
	}

	foreach ( $terminos as $termino ) {
		$admin_bar->add_node( [
			'id'     => 'jb3-geo-preview-' . $termino->slug,
			'parent' => 'jb3-geo-preview',
			'title'  => $termino->name,
			'href'   => wp_nonce_url( add_query_arg( 'jb3_geo_pais', $termino->slug, $url_actual ), 'jb3_geo_preview' ),
		] );
	}
}, 100 );

// Procesa el click de un país del selector de arriba: guarda/borra la
// cookie y redirige limpio (sin el query arg) a la misma página.
add_action( 'init', function () {
	if ( ! isset( $_GET['jb3_geo_pais'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'jb3_geo_preview' ) ) {
		return;
	}

	$pais = sanitize_key( wp_unslash( $_GET['jb3_geo_pais'] ) );
	if ( $pais ) {
		jb3_geo_setcookie( JB3_GEO_COOKIE, $pais, time() + WEEK_IN_SECONDS );
	} else {
		jb3_geo_setcookie( JB3_GEO_COOKIE, '', time() - HOUR_IN_SECONDS );
	}

	wp_safe_redirect( remove_query_arg( [ 'jb3_geo_pais', '_wpnonce' ] ) );
	exit;
} );
