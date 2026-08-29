<?php
defined( 'ABSPATH' ) || exit;

// ─── GOOGLE ANALYTICS (GA4) ──────────────────────────────────────────────────
// Bloqueado por CookieYes (plugin cookie-law-info) hasta que el visitante
// acepte la categoría "Analytics" del aviso de cookies armado 2026-08-28
// — si no, ese aviso quedaría de adorno, sin cumplir su función real.
// type="text/plain" + data-cookieyes="cookieyes-analytics" es el mecanismo
// que reconoce CookieYes para NO ejecutar un script hasta que corresponda
// (recién ahí le cambia el type a text/javascript de verdad) — confirmado
// leyendo su JS (_ckyShouldChangeType()/_ckyAddProviderToList(),
// lite/frontend/js/script.js). Si en algún momento se desactiva CookieYes,
// estos dos <script> quedan con type="text/plain" para siempre y GA nunca
// corre — comportamiento correcto (sin aviso de cookies activo, tampoco
// debería trackear nada).
//
// El ID de medición (G-...) NO es secreto — cualquier sitio con GA lo
// expone directo en su HTML público, así que va acá como constante en vez
// de en un archivo de credenciales fuera del repo (criterio distinto al
// de Gemini/reCAPTCHA, cuyas claves sí habilitan gasto/acciones si se
// filtran).
// Segunda propiedad "Sitio JB3": la primera (G-K41YJGPLJ5) había quedado
// creada por error dentro de la cuenta "consorciosricchetti.com" (otro
// proyecto del cliente, sin relación) — Google Analytics no permite mover
// una propiedad entre cuentas, así que se recreó de cero en la cuenta
// correcta ("jbalbi.com.ar") y se da de baja la cuenta vieja completa.
const JB3_GA_MEASUREMENT_ID = 'G-EJZW4LPX6Y';

add_action( 'wp_head', 'jb3_ga_output' );
function jb3_ga_output(): void {
	if ( is_admin() ) {
		return;
	}
	?>
	<script type="text/plain" data-cookieyes="cookieyes-analytics" async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( JB3_GA_MEASUREMENT_ID ); ?>"></script>
	<script type="text/plain" data-cookieyes="cookieyes-analytics">
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', '<?php echo esc_js( JB3_GA_MEASUREMENT_ID ); ?>');
	</script>
	<?php
}
