<?php
defined( 'ABSPATH' ) || exit;

// ─── MENÚ HAMBURGUESA: BREAKPOINT ─────────────────────────────────────────────
// GeneratePress decide cuándo pasar a modo "menú móvil" (botón hamburguesa +
// panel desplegable) con un único breakpoint interno, por defecto 768px.
// Lo subimos a 1279px: hamburguesa hasta 1279px, menú completo desde
// 1280px (a propósito — 1280px es el ancho de pantalla de muchos monitores).
//
// Ojo: este mismo valor (1279px) también hay que ponerlo en Personalizar >
// Navegación principal > "Punto de despliegue de navegación" (Nav Drop
// Point) — si quedan distintos, vuelve el bug de desalineación del logo
// entre 769px y ese punto (ver comentario en style.css, sección
// "Nav Drop Point").
add_filter( 'generate_mobile_menu_media_query', function () {
    return '(max-width:1279px)';
} );
