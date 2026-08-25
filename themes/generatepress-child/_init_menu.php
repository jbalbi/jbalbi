<?php
defined( 'ABSPATH' ) || exit;

// ─── MENÚ HAMBURGUESA: BREAKPOINT ─────────────────────────────────────────────
// GeneratePress decide cuándo pasar a modo "menú móvil" (botón hamburguesa +
// panel desplegable) con un único breakpoint interno, por defecto 768px.
// Lo subimos a 1279px: hamburguesa hasta 1279px, menú completo desde
// 1280px. Se probó bajarlo a 1023px (para que tablet ya use hamburguesa)
// pero a esos anchos angostos (1024-1279px) el menú completo no entra en
// una sola línea y el CTA "Conéctate con nosotros" salta de renglón — se
// volvió a 1280px, que es donde ya entra cómodo.
//
// Ojo: este mismo valor (1279px) también hay que ponerlo en Personalizar >
// Navegación principal > "Punto de despliegue de navegación" (Nav Drop
// Point) — si quedan distintos, vuelve el bug de desalineación del logo
// entre 769px y ese punto (ver comentario en style.css, sección
// "Nav Drop Point").
add_filter( 'generate_mobile_menu_media_query', function () {
    return '(max-width:1279px)';
} );
