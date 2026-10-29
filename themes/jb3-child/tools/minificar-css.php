<?php
/**
 * Genera assets/css/main.min.css a partir de assets/css/main.css: saca los
 * comentarios y los espacios de más (149 KB → ~47 KB, auditoría 2026-10-29).
 * main.css queda como el original comentado, el único que se edita.
 *
 * Uso (desde la carpeta del tema):  php tools/minificar-css.php
 * En el deploy se corre solo antes de subir (main.min.css no va a git).
 *
 * Minificación conservadora a propósito — solo lo que no puede cambiar el
 * significado del CSS:
 *   - comentarios /* ... *\/ afuera (nunca dentro de un string);
 *   - cualquier tramo de espacios/saltos de línea → un solo espacio;
 *   - sin espacios alrededor de { } ; , y sin el ; antes de una }.
 * NO toca espacios alrededor de ":" (en un selector "a :hover" ≠ "a:hover"),
 * ni dentro de calc() ("100% - 10px" necesita los espacios), ni ">".
 *
 * functions.php usa main.min.css solo si es MÁS NUEVO que main.css: si
 * alguien edita main.css y no vuelve a correr esto, el sitio carga main.css
 * directo — nunca estilos viejos.
 *
 * @package JB3_Child
 */

if ( 'cli' !== PHP_SAPI ) {
	exit; // Solo por línea de comandos, nunca pedido por web.
}

$origen  = dirname( __DIR__ ) . '/assets/css/main.css';
$destino = dirname( __DIR__ ) . '/assets/css/main.min.css';

$css = file_get_contents( $origen );
if ( false === $css ) {
	fwrite( STDERR, "No se pudo leer $origen\n" );
	exit( 1 );
}

$salida  = '';
$largo   = strlen( $css );
$espacio = false;

for ( $i = 0; $i < $largo; $i++ ) {
	$c = $css[ $i ];

	// Strings: se copian tal cual, con sus escapes.
	if ( '"' === $c || "'" === $c ) {
		if ( $espacio ) {
			$salida .= ' ';
			$espacio = false;
		}
		$fin = $i + 1;
		while ( $fin < $largo && $css[ $fin ] !== $c ) {
			$fin += ( '\\' === $css[ $fin ] ) ? 2 : 1;
		}
		$salida .= substr( $css, $i, $fin - $i + 1 );
		$i       = $fin;
		continue;
	}

	// Comentarios: afuera (cuentan como separador, por si estaban entre dos palabras).
	if ( '/' === $c && '*' === ( $css[ $i + 1 ] ?? '' ) ) {
		$fin = strpos( $css, '*/', $i + 2 );
		$i   = ( false === $fin ) ? $largo : $fin + 1;
		$espacio = true;
		continue;
	}

	if ( ctype_space( $c ) ) {
		$espacio = true;
		continue;
	}

	// Sin espacio antes ni después de estos.
	if ( false !== strpos( '{};,', $c ) ) {
		$salida  = rtrim( $salida, ' ' );
		if ( '}' === $c ) {
			$salida = rtrim( $salida, ';' );
		}
		$salida .= $c;
		$espacio = false;
		continue;
	}

	if ( $espacio && '' !== $salida && false === strpos( '{};,', substr( $salida, -1 ) ) ) {
		$salida .= ' ';
	}
	$espacio = false;
	$salida .= $c;
}

$salida = '/* Generado por tools/minificar-css.php desde main.css — no editar */' . "\n" . trim( $salida ) . "\n";

if ( false === file_put_contents( $destino, $salida ) ) {
	fwrite( STDERR, "No se pudo escribir $destino\n" );
	exit( 1 );
}

printf( "main.min.css: %s → %s bytes (%d%% menos)\n", number_format( strlen( $css ), 0, ',', '.' ), number_format( strlen( $salida ), 0, ',', '.' ), round( 100 - 100 * strlen( $salida ) / strlen( $css ) ) );
