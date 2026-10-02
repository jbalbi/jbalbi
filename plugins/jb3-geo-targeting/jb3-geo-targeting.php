<?php
/**
 * Plugin Name: JB3 Geo Targeting
 * Description: Restringe la visibilidad del contenido según el país del visitante (taxonomía "pais"), detectado por el GeoIP del servidor (LiteSpeed) o, si no hay, por Geolocation IP Detection. Sin país asignado el contenido NO es visible en ningún país — hay que asignar explícitamente dónde se ve. Incluye la caché de página por país (ver "CACHÉ DE PÁGINA POR PAÍS").
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

const JB3_GEO_TAXONOMY       = 'pais';
const JB3_GEO_CANAL_TAXONOMY = 'canales';
const JB3_GEO_CANAL_FIELD    = 'paises'; // Campo ACF (Taxonomy → pais) del término de "canales".
const JB3_GEO_PAGE_CANAL_FIELD = 'canal'; // Campo ACF (Taxonomy → canales) de la página de canal (CPT "pagina_canal").
const JB3_GEO_COOKIE         = 'jb3_country_override';
const JB3_GEO_CACHE_PREFIX   = 'jb3_geo_';
const JB3_GEO_CACHE_TTL      = DAY_IN_SECONDS;

/** Tipos de contenido con restricción por país (misma taxonomía "pais" que registra el theme). */
function jb3_geo_post_types(): array {
	// "pagina_canal": CPT de páginas de canal (migrado desde Página +
	// plantilla "Canal", 2026-09-02) — no tiene la taxonomía "pais" propia,
	// pero SÍ necesita el chequeo de jb3_geo_post_visible() (que revisa el
	// campo "canal" antes que nada) para que template_redirect siga dando
	// 404 por país. Sin esto acá, la migración al CPT saca silenciosamente
	// la restricción geográfica de todas las páginas de canal.
	return [ 'peliculas', 'series', 'prod_originales', 'op_comerciales', 'videos', 'integraciones', 'post', 'page', 'pagina_canal' ];
}

// ─── PAÍS DEL VISITANTE ──────────────────────────────────────────────────────
// Prioridad: 1) cookie de override, solo válida para quien puede administrar
// (previsualizar el sitio como si fuera de otro país, sin depender de una
// VPN) 2) país que detecta el SERVIDOR (GeoIP de LiteSpeed — ver "CACHÉ DE
// PÁGINA POR PAÍS" más abajo) 3) caché por IP (evita pegarle a la base de
// GeoIP en cada request) 4) detección con el plugin Geolocation IP Detection
// (local, o un servidor sin GeoIP).
// Devuelve el código ISO en minúscula (coincide con los slugs de la
// taxonomía "pais": ar, bo, cl...) o '' si no se pudo determinar — ese caso
// se trata como fail-closed (NO se muestra contenido restringido) en
// jb3_geo_post_visible() y afines, decisión del cliente 2026-08-26.
function jb3_geo_visitor_country(): string {
	if ( current_user_can( 'manage_options' ) && ! empty( $_COOKIE[ JB3_GEO_COOKIE ] ) ) {
		return sanitize_key( $_COOKIE[ JB3_GEO_COOKIE ] );
	}

	// Modo 1 (regla de caché por país activa): SOLO el país del servidor, el
	// mismo dato con que LiteSpeed eligió la copia. Si llega vacío (IP que el
	// servidor no reconoce), '' = fail-closed — NO caer a la otra base de
	// GeoIP: LiteSpeed guarda UNA copia compartida para todos los "sin país",
	// y si esa base sí encontrara un país, esa copia se armaría con el
	// contenido de ese país y se le serviría a todos los no reconocidos
	// (detectado en la revisión 2026-10-02, antes del commit).
	$servidor = jb3_geo_pais_servidor();
	if ( '' !== $servidor || jb3_geo_modo_servidor() ) {
		return $servidor;
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

// ─── CACHÉ DE PÁGINA POR PAÍS (LiteSpeed) ───────────────────────────────────
// La caché de página guarda una copia por país. El problema de fondo: en un
// cache hit PHP no corre, así que la copia se elige ANTES de que nada de este
// plugin pueda opinar. Alguien que pasaba de Argentina a una VPN de Honduras
// seguía viendo la copia de Argentina (probado en producción 2026-10-02).
// Dos modos, elegidos solos en cada pedido (jb3_geo_modo_servidor()):
//
// MODO 1 — país del SERVIDOR (el bueno): la caché elige la copia con el país
// que detecta LiteSpeed (GeoIP), sin cookies ni JavaScript. Necesita este
// bloque en el .htaccess de la RAÍZ (public_html, fuera de este repo):
//
//   # BEGIN JB3 GeoIP
//   <IfModule LiteSpeed>
//   GeoIPEnable On
//   RewriteEngine On
//   RewriteRule .* - [E=Cache-Control:vary=pais_%{ENV:GEOIP_COUNTRY_CODE},E=JB3_GEO_VARY:1]
//   </IfModule>
//   # END JB3 GeoIP
//
// GeoIPEnable lo indicó el soporte de Hostinger (plan Premium Web Hosting,
// 2026-10-02; probado en producción con VPN: un solo F5, sin parpadeo).
// JB3_GEO_VARY es la marca que deja la regla para que PHP sepa que está
// activa: con solo GeoIP encendido y sin la regla, la caché NO separaría por
// país y hay que seguir en el modo 2. Las dos variables las pone el servidor
// — un visitante no puede inventarlas (lo que manda llega como HTTP_*).
// Si la regla quedara sin "GeoIPEnable On", ningún visitante tendría país y
// no se vería contenido restringido en ningún lado: falla visible y segura.
//
// MODO 2 — respaldo (servidor sin GeoIP, o sin la regla de arriba, ej. si el
// sitio se muda): la copia se elige por la cookie "jb3_pais", y como en un
// cache hit nadie la verifica, cada página trae un script que le pregunta el
// país real a pais.php (este plugin) y recarga si no coincide. Límites: un
// parpadeo en ese caso, y no corrige a quien tenga JavaScript desactivado.

const JB3_GEO_VARY_COOKIE = 'jb3_pais';

/** true = modo 1 (la regla de caché por país del .htaccess de la raíz está activa); false = modo 2. */
function jb3_geo_modo_servidor(): bool {
	$marca = $_SERVER['JB3_GEO_VARY'] ?? $_SERVER['REDIRECT_JB3_GEO_VARY'] ?? getenv( 'JB3_GEO_VARY' );
	return '1' === (string) $marca;
}

/** País que detecta el servidor (GeoIP de LiteSpeed), en minúscula como los slugs de "pais"; '' si no llega. */
function jb3_geo_pais_servidor(): string {
	$valor = $_SERVER['GEOIP_COUNTRY_CODE'] ?? getenv( 'GEOIP_COUNTRY_CODE' );
	return is_string( $valor ) ? strtolower( preg_replace( '/[^A-Za-z]/', '', $valor ) ) : '';
}

/**
 * setcookie() con flags de hardening (auditoría de seguridad 2026-08-27) —
 * ninguna cookie de este plugin la lee JS, así que HttpOnly no rompe nada.
 * Secure sale de is_ssl(): local corre por HTTP plano.
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

// Modo 2: cookie con el país, para que LiteSpeed guarde una copia por país.
add_action( 'init', function () {
	if ( is_admin() || jb3_geo_modo_servidor() ) {
		return;
	}
	$pais = jb3_geo_visitor_country();
	if ( ( $_COOKIE[ JB3_GEO_VARY_COOKIE ] ?? '' ) !== $pais ) {
		jb3_geo_setcookie( JB3_GEO_VARY_COOKIE, $pais, time() + DAY_IN_SECONDS );
	}
} );

// Modo 2: hacen falta LAS DOS registraciones — con una sola, LiteSpeed marca
// la página como no cacheable por el simple hecho de hacer setcookie(). En
// modo 1 sobra pero no molesta (sin la cookie, no separa nada de más).
function jb3_geo_vary_cookies( array $cookies ): array {
	$cookies[] = JB3_GEO_VARY_COOKIE;
	return $cookies;
}
add_filter( 'litespeed_vary_cookies', 'jb3_geo_vary_cookies' );
add_filter( 'litespeed_vary_curr_cookies', 'jb3_geo_vary_cookies' );

// Modo 2: la página lleva anotado el país con que se armó (queda adentro de
// la copia cacheada) y un script que pide el país real a pais.php (nunca
// cacheado; reescribe la cookie). Si no coincide, recarga una vez y LiteSpeed
// sirve la copia correcta. Solo sin sesión: con sesión LiteSpeed no cachea, y
// un admin con "Ver como país…" no tiene que ser "corregido".
add_action( 'wp_head', function () {
	if ( is_user_logged_in() || jb3_geo_modo_servidor() ) {
		return;
	}
	$pais = jb3_geo_visitor_country();
	$url  = plugins_url( 'pais.php', __FILE__ );
	?>
	<script>
	( function () {
		var paginaPais = <?php echo wp_json_encode( $pais ); ?>;
		var clave      = 'jb3_geo_recarga';
		fetch( <?php echo wp_json_encode( $url ); ?>, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) {
				if ( ! d || 'string' !== typeof d.pais || d.pais === paginaPais ) {
					try { sessionStorage.removeItem( clave ); } catch ( e ) {}
					return;
				}
				// Freno anti-bucle: una sola recarga por país y por pestaña.
				try {
					if ( sessionStorage.getItem( clave ) === d.pais ) { return; }
					sessionStorage.setItem( clave, d.pais );
				} catch ( e ) {}
				location.reload();
			} )
			.catch( function () {} );
	} )();
	</script>
	<?php
}, 1 );

// ─── PAÍSES PERMITIDOS DE UN CANAL ──────────────────────────────────────────
// Lee el campo ACF "paises" (Taxonomy → pais, checkbox, return
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
// (jb3_modulo_query() en _init_modulo.php) y "Te puede interesar"
// (jb3_etiquetas_auto() en _init_etiquetas.php). Antes estaba
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

// Las páginas de canal (CPT "pagina_canal", ver _init_custom_post_types.php
// en el tema) no tienen la taxonomía "canales" asignada directamente — se
// vinculan a un término vía el campo ACF "canal" (Taxonomy → canales, un
// solo valor). Si ese post no tiene el campo cargado, no aplica ninguna
// restricción por esta vía.
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
	// Las páginas de canal (CPT "pagina_canal") delegan el país ENTERO al
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
// Cubre posts (película, serie...) Y las páginas de canal (CPT
// "pagina_canal") — jb3_geo_post_visible() ya combina el chequeo de "pais"
// propio, el de "canales" directo, y el del vínculo página→canal.
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

	// Sin "if ( '' === $pais ) return;" a propósito — corregido en la
	// revisión pre-producción 2026-10-01: ese corte dejaba los listados
	// (archivos, feeds) SIN filtro para un visitante sin país detectado,
	// o sea mostraba todo el catálogo — lo contrario del fail-closed.
	// jb3_geo_tax_query_pais('') ya devuelve la cláusula que no matchea
	// nada; mismo bug que ya se había corregido en jb3_etiquetas_auto().
	$tax_query = array_merge( $query->get( 'tax_query' ) ?: [], jb3_geo_tax_query_pais( jb3_geo_visitor_country() ) );
	$query->set( 'tax_query', $tax_query );
} );

// ─── API REST: mismo filtro por país ────────────────────────────────────────
// La API REST (/wp-json/wp/v2/peliculas, etc.) no pasa por el pre_get_posts
// ni por el 404 de arriba — sin esto, cualquiera listaba ahí el catálogo
// COMPLETO de todos los países (detectado en la revisión pre-producción
// 2026-10-01). Dos filtros: el listado (mismo tax_query que los archivos,
// salvo Páginas/Entradas, igual que arriba) y el post puntual
// (/peliculas/123, mismo chequeo que el 404 de la página real). Quien puede
// editar contenido queda exceptuado: el editor de bloques usa esta misma API.
add_action( 'rest_api_init', function () {
	foreach ( array_diff( jb3_geo_post_types(), [ 'page', 'post' ] ) as $post_type ) {
		add_filter( "rest_{$post_type}_query", function ( array $args ): array {
			if ( ! current_user_can( 'edit_posts' ) ) {
				$args['tax_query'] = array_merge( $args['tax_query'] ?? [], jb3_geo_tax_query_pais( jb3_geo_visitor_country() ) );
			}
			return $args;
		} );
	}
} );

// Post puntual: se corta ANTES de que corra el endpoint — OJO, no con
// rest_prepare_{tipo}: devolver un WP_Error desde ese filtro tira un error
// fatal dentro de WordPress (probado). Este es el punto donde WordPress sí
// acepta cortar un pedido con un error.
add_filter( 'rest_request_before_callbacks', function ( $response, array $handler, WP_REST_Request $request ) {
	if ( is_wp_error( $response ) || current_user_can( 'edit_posts' ) ) {
		return $response;
	}
	if ( ! ( ( $handler['callback'][0] ?? null ) instanceof WP_REST_Posts_Controller ) ) {
		return $response;
	}
	$post = get_post( (int) $request['id'] );
	if ( $post && in_array( $post->post_type, jb3_geo_post_types(), true ) && ! jb3_geo_post_visible( $post->ID ) ) {
		return new WP_Error( 'rest_post_invalid_id', 'No encontrado.', [ 'status' => 404 ] );
	}
	return $response;
}, 10, 3 );

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

// ─── MENÚS DE NAVEGACIÓN: sacar ítems que no corresponden a tu país ─────────
// Sin esto, un canal (o cualquier Página/contenido) restringido por país
// seguía apareciendo en el menú para todo el mundo — el visitante lo veía
// en la navegación y recién se enteraba de que no puede entrar al hacer
// click (404 vía template_redirect más arriba). Reusa jb3_geo_post_visible()
// tal cual, la misma función que ya decide el 404 y el filtro de
// búsqueda — no es una regla aparte solo para canales: cubre CUALQUIER
// ítem de menú que apunte a una Página/Entrada/CPT real (películas, series,
// una Página de concurso con "pais" tildado a mano, lo que sea). Un ítem sin
// restricción de país asignada (la inmensa mayoría de las Páginas
// institucionales) sigue visible siempre, mismo criterio que en el resto
// del sitio.
// Solo alcanza a ítems tipo "post_type" (Página/Entrada/CPT reales,
// $item->object_id existe) — un "Enlace personalizado" del menú (URL
// escrita a mano, sin post real detrás) no tiene forma de chequearse así y
// queda sin filtrar, a propósito: no hay ningún dato de país que consultar.
add_filter( 'wp_nav_menu_objects', function ( array $items ) {
	if ( is_admin() ) {
		return $items;
	}

	return array_values( array_filter( $items, function ( $item ) {
		if ( 'post_type' !== $item->type ) {
			return true;
		}
		return jb3_geo_post_visible( (int) $item->object_id );
	} ) );
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
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jb3_geo_preview' ) ) {
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
