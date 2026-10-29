<?php
defined( 'ABSPATH' ) || exit;

// ─── ÍCONOS DEL HEADER ────────────────────────────────────────────────────────
// SVG inline (heredan el color del texto con currentColor), todos en una caja
// de 24x24 con el dibujo centrado y casi de borde a borde — así se alinean
// entre sí con solo darles el mismo ancho/alto por CSS, sin compensaciones
// ópticas a mano.

/**
 * Imprime un ícono SVG decorativo (aria-hidden: el texto accesible lo pone
 * el botón o link que lo envuelve).
 *
 * @param string $nombre buscar | menu | cerrar | linkedin | cuenta.
 */
function jb3_icono( string $nombre ): void {
	$trazo = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
	$iconos = array(
		'buscar'   => '<svg class="jb3-icono jb3-icono--buscar" viewBox="0 0 24 24" ' . $trazo . '><circle cx="10" cy="10" r="7.5"/><line x1="15.5" y1="15.5" x2="22" y2="22"/></svg>',
		'menu'     => '<svg class="jb3-icono jb3-icono--menu" viewBox="0 0 24 24" ' . $trazo . '><line x1="2" y1="5" x2="22" y2="5"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="19" x2="22" y2="19"/></svg>',
		'cerrar'   => '<svg class="jb3-icono jb3-icono--cerrar" viewBox="0 0 24 24" ' . $trazo . '><line x1="4" y1="4" x2="20" y2="20"/><line x1="20" y1="4" x2="4" y2="20"/></svg>',
		'cuenta'   => '<svg class="jb3-icono jb3-icono--cuenta" viewBox="0 0 24 24" ' . $trazo . '><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.582-7 8-7s8 3 8 7"/></svg>',
		'linkedin' => '<svg class="jb3-icono jb3-icono--linkedin" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452z"/></svg>',
	);

	if ( isset( $iconos[ $nombre ] ) ) {
		echo str_replace( '<svg ', '<svg aria-hidden="true" focusable="false" ', $iconos[ $nombre ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo, sin datos externos.
	}
}
