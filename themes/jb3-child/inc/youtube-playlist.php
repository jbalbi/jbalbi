<?php
defined( 'ABSPATH' ) || exit;

// ─── PLAYLIST DE YOUTUBE (bloque "Playlist de YouTube") ─────────────────────
// Pedido 2026-10-19: mostrar TODOS los videos de una playlist a la vista, en
// la misma grilla de miniaturas que Op. Comerciales, cada uno abriéndose en
// el reproductor a pantalla completa del sitio (inc/video-modal.php). El
// reproductor que incrusta YouTube muestra un solo video a la vez, con la
// lista escondida en un ícono.
//
// Los videos salen del listado público de la playlist que ofrece YouTube
// (https://www.youtube.com/feeds/videos.xml?playlist_id=…): sin clave de
// API ni costo. Límite de YouTube: trae como máximo los PRIMEROS 15 videos
// de la lista, en el orden de la playlist (verificado 2026-10-20 con una de
// 28: coinciden exactamente con los 15 primeros) — para elegir cuáles se ven,
// se reordena la playlist en YouTube.
// El bloque respeta SIEMPRE el orden de la playlist (decisión 2026-10-20):
// si el editor quiere otro orden, lo cambia en YouTube (a mano, o con el
// ordenamiento automático de la playlist, ej. por fecha de publicación). OJO
// con playlists de más de 15: YouTube agrega los videos nuevos al final por
// defecto, fuera de los 15 que entrega este listado — ahí conviene activar
// en la playlist "Agregar videos nuevos al principio" u ordenarla por fecha.
// Se guarda 6 horas (transient) para no
// consultar a YouTube en cada visita; si la playlist cambia, se ve sola al
// vencer.
// La playlist tiene que ser pública o "no listada".

const JB3_YT_PLAYLIST_CACHE = 6 * HOUR_IN_SECONDS;
const JB3_YT_PLAYLIST_MAXIMO = 15; // Lo máximo que entrega el listado de YouTube (ver arriba).

/** ID de la playlist ("PL…") a partir del link que pega el editor (parámetro list=), o '' si no hay. */
function jb3_youtube_playlist_id( string $url ): string {
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );
	$id = isset( $params['list'] ) ? (string) $params['list'] : '';
	return preg_match( '/^[A-Za-z0-9_-]{10,64}$/', $id ) ? $id : '';
}

/**
 * Videos de la playlist: [ [ 'id' => …, 'titulo' => …, 'grandes' => […] ], … ],
 * en el orden de la playlist ('grandes': ver jb3_youtube_miniaturas_grandes()).
 * Vacío si no se pudo leer (playlist privada, borrada, YouTube caído).
 */
function jb3_youtube_playlist_videos( string $playlist_id ): array {
	$clave  = 'jb3_yt_playlist_v2_' . md5( $playlist_id ); // v2: con 'grandes' (2026-10-20).
	$cached = get_transient( $clave );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$videos    = array();
	$respuesta = wp_remote_get( 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode( $playlist_id ), array( 'timeout' => 8 ) );

	if ( ! is_wp_error( $respuesta ) && 200 === wp_remote_retrieve_response_code( $respuesta ) ) {
		$xml = simplexml_load_string( wp_remote_retrieve_body( $respuesta ), 'SimpleXMLElement', LIBXML_NONET );
		if ( $xml ) {
			foreach ( $xml->entry as $entry ) {
				$id = (string) $entry->children( 'http://www.youtube.com/xml/schemas/2015' )->videoId;
				if ( preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) ) {
					$videos[] = array( 'id' => $id, 'titulo' => (string) $entry->title );
				}
			}
		}
	}

	$grandes = jb3_youtube_miniaturas_grandes( wp_list_pluck( $videos, 'id' ) );
	foreach ( $videos as $i => $video ) {
		$videos[ $i ]['grandes'] = $grandes[ $video['id'] ] ?? array();
	}

	// Si falló, se reintenta a los 15 minutos en vez de dejar la grilla
	// vacía 6 horas por un corte momentáneo de YouTube.
	set_transient( $clave, $videos, $videos ? JB3_YT_PLAYLIST_CACHE : 15 * MINUTE_IN_SECONDS );
	return $videos;
}

/**
 * Qué miniaturas grandes tiene cada video: [ id => [ 'sddefault' => 640, 'maxresdefault' => 1280 ], … ].
 *
 * mqdefault (320) y hqdefault (480) existen SIEMPRE; sddefault (640) y
 * maxresdefault (1280) no — YouTube no las genera para videos viejos o de
 * baja resolución, y cuando faltan responde 404 PERO con una imagen gris
 * genérica (el logo de YouTube) que el navegador muestra como si fuera
 * válida: un onerror no la detecta. Pasó en producción (2026-10-20, playlist
 * de una serie vieja: 12 de 15 en gris). Por eso se pregunta acá, una vez por
 * listado (queda en el mismo transient de 6 horas), y la grilla solo ofrece
 * las que existen. Consultas HEAD en paralelo: no se baja ninguna imagen.
 */
function jb3_youtube_miniaturas_grandes( array $ids ): array {
	$tamanos   = array( 'sddefault' => 640, 'maxresdefault' => 1280 );
	$resultado = array_fill_keys( $ids, array() );
	$urls      = array();
	foreach ( $ids as $id ) {
		foreach ( $tamanos as $nombre => $ancho ) {
			$urls[ $id . '|' . $nombre ] = 'https://i.ytimg.com/vi/' . $id . '/' . $nombre . '.jpg';
		}
	}

	// En paralelo con cURL directo (no con \WpOrg\Requests::request_multiple():
	// esa ignora el 'verify' y usa los certificados del PHP del servidor, que
	// pueden no existir — pasaba en el local de Laragon). CAINFO = los mismos
	// certificados que usa wp_remote_get(). Sin cURL, de a una con
	// wp_remote_head() (más lento, mismo resultado).
	$existe = array();
	if ( function_exists( 'curl_multi_init' ) ) {
		$multi    = curl_multi_init();
		$handles  = array();
		foreach ( $urls as $clave => $url ) {
			$h = curl_init( $url );
			curl_setopt_array( $h, array(
				CURLOPT_NOBODY         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 5,
				CURLOPT_CAINFO         => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
			) );
			curl_multi_add_handle( $multi, $h );
			$handles[ $clave ] = $h;
		}
		do {
			$estado = curl_multi_exec( $multi, $activos );
			if ( $activos ) {
				curl_multi_select( $multi, 1 );
			}
		} while ( $activos && CURLM_OK === $estado );
		foreach ( $handles as $clave => $h ) {
			$existe[ $clave ] = 200 === (int) curl_getinfo( $h, CURLINFO_HTTP_CODE );
			curl_multi_remove_handle( $multi, $h );
			curl_close( $h );
		}
		curl_multi_close( $multi );
	} else {
		foreach ( $urls as $clave => $url ) {
			$existe[ $clave ] = 200 === wp_remote_retrieve_response_code( wp_remote_head( $url, array( 'timeout' => 5 ) ) );
		}
	}

	foreach ( $existe as $clave => $ok ) {
		if ( $ok ) {
			[ $id, $nombre ] = explode( '|', $clave );
			$resultado[ $id ][ $nombre ] = $tamanos[ $nombre ];
		}
	}
	return $resultado;
}
