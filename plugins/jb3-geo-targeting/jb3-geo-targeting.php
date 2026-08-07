<?php
/**
 * Plugin Name: JB3 Geo Targeting
 * Description: Restringe la visibilidad del contenido según el país del visitante (taxonomía "pais"), usando Geolocation IP Detection. Sin país asignado el contenido es visible para todos; con país(es) asignado(s), solo ahí.
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

const JB3_GEO_TAXONOMY     = 'pais';
const JB3_GEO_COOKIE       = 'jb3_country_override';
const JB3_GEO_CACHE_PREFIX = 'jb3_geo_';
const JB3_GEO_CACHE_TTL    = DAY_IN_SECONDS;

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

// ─── ¿SE PUEDE MOSTRAR ESTE CONTENIDO? ──────────────────────────────────────
function jb3_geo_post_visible( int $post_id ): bool {
	$terminos = get_the_terms( $post_id, JB3_GEO_TAXONOMY );
	if ( empty( $terminos ) || is_wp_error( $terminos ) ) {
		return true; // Sin país asignado = visible para todos.
	}

	$pais = jb3_geo_visitor_country();
	if ( '' === $pais ) {
		return true; // País no detectado = se muestra igual.
	}

	foreach ( $terminos as $termino ) {
		if ( $termino->slug === $pais ) {
			return true;
		}
	}
	return false;
}

// ─── CONTENIDO INDIVIDUAL: 404 si no corresponde a tu país ──────────────────
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
// tax_query con operator NOT EXISTS matchea posts SIN ningún término de
// "pais" (sin restricción) — junto con el OR de "tiene el término del
// visitante", cubre exactamente la regla de jb3_geo_post_visible() pero a
// nivel de consulta, para que ese contenido ni aparezca en listados.
add_action( 'pre_get_posts', function ( WP_Query $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$pais = jb3_geo_visitor_country();
	if ( '' === $pais ) {
		return;
	}

	$tax_query   = $query->get( 'tax_query' ) ?: [];
	$tax_query[] = [
		'relation' => 'OR',
		[
			'taxonomy' => JB3_GEO_TAXONOMY,
			'operator' => 'NOT EXISTS',
		],
		[
			'taxonomy' => JB3_GEO_TAXONOMY,
			'field'    => 'slug',
			'terms'    => [ $pais ],
		],
	];
	$query->set( 'tax_query', $tax_query );
} );

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
		setcookie( JB3_GEO_COOKIE, $pais, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	} else {
		setcookie( JB3_GEO_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	}

	wp_safe_redirect( remove_query_arg( [ 'jb3_geo_pais', '_wpnonce' ] ) );
	exit;
} );
