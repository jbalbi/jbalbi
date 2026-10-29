<?php
defined( 'ABSPATH' ) || exit;

// ─── REDES SOCIALES: motor genérico + capa fina de Canal ────────────────────
// jb3_redes_sociales_html() no sabe nada de "canal" — recibe una lista de
// filas {red, url} (el mismo formato de fila que devuelve get_field() de un
// Repeater con sub-campos "red"/"url") y dibuja los íconos. Reutilizable
// para cualquier otra cosa que necesite su propia fila de redes más
// adelante (ej. el Host de Aventura Gastronómica, con su propio Repeater
// "Redes Sociales" directo en ESE bloque) — sin duplicar ni un ícono SVG.
//
// jb3_canal_redes_sociales_html() es la única parte específica de Canal:
// busca el dato en el término de la taxonomía "canales" (campo
// "redes_sociales", grupo ACF "Canales" — dato de identidad del canal, no
// varía ni se repite, por eso vive ahí y no en un campo de bloque) y se lo
// pasa al motor genérico de arriba.

/** Un ícono SVG simple por red — currentColor para heredar el color del link. */
function jb3_red_social_icono( string $red ): string {
	$iconos = array(
		'youtube'   => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4L15.8 12Z"/></svg>',
		'facebook'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></svg>',
		'instagram' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2c2.7 0 3.1 0 4.1.1 1.1 0 1.8.2 2.5.5.7.3 1.2.6 1.8 1.2.6.6.9 1.1 1.2 1.8.3.7.5 1.4.5 2.5.1 1 .1 1.4.1 4.1s0 3.1-.1 4.1c0 1.1-.2 1.8-.5 2.5a5 5 0 0 1-1.2 1.8c-.6.6-1.1.9-1.8 1.2-.7.3-1.4.5-2.5.5-1 .1-1.4.1-4.1.1s-3.1 0-4.1-.1c-1.1 0-1.8-.2-2.5-.5a5 5 0 0 1-1.8-1.2 5 5 0 0 1-1.2-1.8c-.3-.7-.5-1.4-.5-2.5-.1-1-.1-1.4-.1-4.1s0-3.1.1-4.1c0-1.1.2-1.8.5-2.5.3-.7.6-1.2 1.2-1.8.6-.6 1.1-.9 1.8-1.2.7-.3 1.4-.5 2.5-.5C8.9 2 9.3 2 12 2Zm0 1.8c-2.6 0-3 0-4 .1-.9 0-1.4.2-1.7.3-.4.2-.7.3-1 .6-.3.3-.5.6-.6 1-.1.3-.3.8-.3 1.7-.1 1-.1 1.4-.1 4s0 3 .1 4c0 .9.2 1.4.3 1.7.2.4.3.7.6 1 .3.3.6.5 1 .6.3.1.8.3 1.7.3 1 .1 1.4.1 4 .1s3 0 4-.1c.9 0 1.4-.2 1.7-.3.4-.2.7-.3 1-.6.3-.3.5-.6.6-1 .1-.3.3-.8.3-1.7.1-1 .1-1.4.1-4s0-3-.1-4c0-.9-.2-1.4-.3-1.7a2.7 2.7 0 0 0-.6-1 2.7 2.7 0 0 0-1-.6c-.3-.1-.8-.3-1.7-.3-1-.1-1.4-.1-4-.1Zm0 3.5a4.7 4.7 0 1 1 0 9.4 4.7 4.7 0 0 1 0-9.4Zm0 1.8a2.9 2.9 0 1 0 0 5.8 2.9 2.9 0 0 0 0-5.8Zm5-2a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0Z"/></svg>',
		'tiktok'    => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M16.6 2h-3.3v14.1a2.9 2.9 0 1 1-2.1-2.8V9.9a6.3 6.3 0 1 0 5.4 6.2V8.4a8 8 0 0 0 4.6 1.5V6.6a4.7 4.7 0 0 1-4.6-4.6Z"/></svg>',
		'x'         => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M13.6 10.6 21 2h-2l-6.4 7.5L7.5 2H2l7.7 11.2L2 22h2l6.8-8 5.4 8H22l-8.4-11.4ZM11.2 13l-.8-1.1L4.2 3.5h2.7l5 7.2.8 1.1 6.6 9.4h-2.7L11.2 13Z"/></svg>',
	);
	return $iconos[ $red ] ?? '';
}

/** Nombre legible por red — para el atributo aria-label de accesibilidad. */
function jb3_red_social_nombre( string $red ): string {
	$nombres = array(
		'youtube'   => 'YouTube',
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'tiktok'    => 'TikTok',
		'x'         => 'X',
	);
	return $nombres[ $red ] ?? ucfirst( $red );
}

/**
 * Motor genérico: dibuja una fila de íconos a partir de filas {red, url}.
 * No sabe nada de canal, host, ni de dónde salió la lista — cualquier
 * feature que tenga su propio Repeater con sub-campos "red"/"url" (+
 * "texto" opcional) puede usar esta misma función.
 *
 * $con_texto = false (default, uso de Canales — sin tocar): fila
 * horizontal, solo íconos, sin importar si la fila trae "texto" cargado.
 * $con_texto = true (uso del bloque genérico "Redes Sociales", ej. redes de
 * un invitado/host): lista vertical, ícono + el handle/texto de esa fila.
 */
function jb3_redes_sociales_html( array $filas, bool $con_texto = false ): string {
	if ( empty( $filas ) ) {
		return '';
	}

	$clase = 'jb3-canal-redes' . ( $con_texto ? ' jb3-canal-redes--lista' : '' );

	ob_start();
	?>
	<div class="<?php echo esc_attr( $clase ); ?>">
		<?php foreach ( $filas as $fila ) :
			$red   = $fila['red'] ?? '';
			$url   = $fila['url'] ?? '';
			$texto = (string) ( $fila['texto'] ?? '' );
			if ( ! $red || ! $url ) {
				continue;
			}
			$icono = jb3_red_social_icono( $red );
			if ( ! $icono ) {
				continue;
			}
			?>
			<a class="jb3-canal-redes__item" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( jb3_red_social_nombre( $red ) ); ?>">
				<?php echo $icono; ?>
				<?php if ( $con_texto && $texto ) : ?>
					<span class="jb3-canal-redes__texto"><?php echo esc_html( $texto ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Capa fina específica de Canal: busca "redes_sociales" en el término de la
 * taxonomía "canales" (grupo ACF "Canales") y lo pasa al motor genérico.
 */
function jb3_canal_redes_sociales_html( int $canal_term_id ): string {
	if ( ! $canal_term_id || ! function_exists( 'get_field' ) ) {
		return '';
	}

	$filas = get_field( 'redes_sociales', 'canales_' . $canal_term_id );
	return jb3_redes_sociales_html( (array) $filas );
}
