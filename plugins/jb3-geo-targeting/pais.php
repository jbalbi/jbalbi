<?php
/**
 * País REAL del visitante — solo para el MODO 2 de la caché por país (ver
 * "CACHÉ DE PÁGINA POR PAÍS" en jb3-geo-targeting.php): servidor sin GeoIP
 * o sin la regla de caché en el .htaccess de la raíz.
 *
 * En ese modo LiteSpeed elige la copia de caché por la cookie "jb3_pais", y
 * en un cache hit nadie verifica que siga siendo cierta (VPN, viajes). El
 * script que lleva cada página le pregunta a ESTE archivo el país real; acá
 * se reescribe la cookie y, si no coincide con el de la página, el script
 * recarga y LiteSpeed sirve la copia correcta.
 *
 * NO carga WordPress, a propósito: se llama en CADA página vista (nunca se
 * cachea), y leer la base de GeoIP directo tarda milisegundos, contra cientos
 * de un arranque completo de WordPress. Solo si no hay otra forma (base de
 * GeoIP en otro lugar, IP local de desarrollo) carga WordPress.
 *
 * Responde siempre JSON: {"pais":"ar"} — "" si no se pudo detectar (mismo
 * fail-closed que el resto del plugin), null si algo falló (el script no hace
 * nada en ese caso).
 */

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
header( 'X-LiteSpeed-Cache-Control: no-cache' ); // LiteSpeed no tiene que guardar ESTA respuesta jamás.
header( 'X-Robots-Tag: noindex' );

$ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$pais = null;

// 1) País del servidor (GeoIP de LiteSpeed) — mismo criterio que
// jb3_geo_pais_servidor() / jb3_geo_visitor_country(), repetido acá porque
// este archivo no carga WordPress: con la regla de caché activa (marca
// JB3_GEO_VARY) vale SOLO el país del servidor, aunque llegue vacío.
$servidor = $_SERVER['GEOIP_COUNTRY_CODE'] ?? getenv( 'GEOIP_COUNTRY_CODE' );
$servidor = is_string( $servidor ) ? strtolower( preg_replace( '/[^A-Za-z]/', '', $servidor ) ) : '';
$marca    = $_SERVER['JB3_GEO_VARY'] ?? $_SERVER['REDIRECT_JB3_GEO_VARY'] ?? getenv( 'JB3_GEO_VARY' );
if ( '' !== $servidor || '1' === (string) $marca ) {
	$pais = $servidor;
}

// 2) Misma base que usa el plugin geoip-detect en modo automático:
// wp-content/uploads/GeoLite2-City.mmdb (ver maxmindGetUploadFilename() en
// geoip-detect/data-sources/auto.php). Solo con IP pública: una IP local
// (ej. 127.0.0.1 en Laragon) no está en la base — el plugin la reemplaza por
// la IP pública real, así que esa va por el paso 3.
$wp_content = dirname( __DIR__, 2 );
$mmdb       = $wp_content . '/uploads/GeoLite2-City.mmdb';
$autoload   = $wp_content . '/plugins/geoip-detect/vendor/autoload.php';
$ip_publica = (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );

if ( null === $pais && $ip_publica && is_readable( $mmdb ) && is_readable( $autoload ) ) {
	try {
		require $autoload;
		$reader = new \MaxMind\Db\Reader( $mmdb );
		$datos  = $reader->get( $ip );
		$reader->close();
		$pais = strtolower( (string) ( $datos['country']['iso_code'] ?? '' ) );
	} catch ( \Throwable $e ) {
		$pais = null;
	}
}

// 3) Respaldo: se resuelve igual que el resto del sitio, con WordPress.
if ( null === $pais ) {
	define( 'WP_USE_THEMES', false );
	$wp_load = dirname( $wp_content ) . '/wp-load.php';
	if ( is_readable( $wp_load ) ) {
		require $wp_load;
		if ( function_exists( 'jb3_geo_visitor_country' ) ) {
			$pais = jb3_geo_visitor_country();
		}
	}
}

if ( null !== $pais ) {
	$pais = preg_replace( '/[^a-z]/', '', strtolower( $pais ) ); // Solo "ar", "hn"... o "".

	// Misma cookie y mismos flags que jb3_geo_setcookie() (jb3-geo-targeting.php).
	$https = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] )
		|| 'https' === ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' );
	setcookie( 'jb3_pais', $pais, [
		'expires'  => time() + 86400,
		'path'     => '/',
		'secure'   => $https,
		'httponly' => true,
		'samesite' => 'Lax',
	] );
}

echo json_encode( [ 'pais' => $pais ] );
