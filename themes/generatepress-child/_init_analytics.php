<?php
defined( 'ABSPATH' ) || exit;

// ─── GOOGLE ANALYTICS (GA4) — GOOGLE CONSENT MODE ──────────────────────────
// El enfoque anterior (envolver los dos <script> en type="text/plain"
// data-cookieyes="cookieyes-analytics" para que CookieYes los "desbloquee"
// al aceptar) NUNCA llegó a mandar un solo dato real: confirmado
// 2026-09-18 leyendo el motor de bloqueo de CookieYes
// (lite/frontend/js/script.js) — su MutationObserver solo sabe reactivar
// scripts con atributo "src" (_ckyMutationObserver descarta cualquier nodo
// sin src). El script que realmente manda el dato (gtag('config', ...)) es
// INLINE, sin src, así que nunca quedaba en la lista de nodos a reactivar
// — quedaba bloqueado para siempre, incluso con el consentimiento de
// Analytics ya aceptado. Confirmado en vivo: la cookie "cookieyes-consent"
// tenía "analytics:yes" y aun así Tiempo Real de GA marcaba 0.
//
// Fix (2026-09-18), primera vuelta: gtag.js siempre cargado, y nuestro
// propio código decidiendo si llamar a gtag('config', ...) según la cookie
// de CookieYes / su evento "cookieyes_consent_update" — funcionaba, pero
// era "todo o nada": si el visitante rechazaba, no se mandaba NADA, ni
// siquiera un conteo anónimo.
//
// Esta versión implementa el "Consent Mode" real de Google en su lugar (la
// integración automática es exclusiva de CookieYes Pro, pero Consent Mode
// en sí es solo una convención de gtag() que se puede armar a mano, sin
// depender de esa integración): en vez de no llamar nunca a gtag('config',
// ...) para quien rechaza, se le avisa a Google el estado real de
// consentimiento (gtag('consent', 'default'/'update', {...})) y se llama a
// gtag('config', ...) SIEMPRE. Con el consentimiento en "denied", Google
// mismo no setea cookies ni identifica a la persona entre visitas, pero
// puede seguir sumando al conteo agregado y anónimo de "cuánta gente pasó
// por acá" (lo que Google llama "cookieless pings" / modelado). Con
// "granted", arranca el tracking normal completo.
//
// El ID de medición (G-...) NO es secreto — cualquier sitio con GA lo
// expone directo en su HTML público, así que va acá como constante en vez
// de en un archivo de credenciales fuera del repo (criterio distinto al
// de Gemini/reCAPTCHA, cuyas claves sí habilitan gasto/acciones si se
// filtran).
// Segunda propiedad, "JB3 - Site": la primera ("Sitio JB3", G-K41YJGPLJ5)
// había quedado creada por error dentro de la cuenta
// "consorciosricchetti.com" (otro proyecto del cliente, sin relación) —
// Google Analytics no permite mover una propiedad entre cuentas, así que
// se recreó de cero en la cuenta correcta ("jbalbi.com.ar") y se da de
// baja la cuenta vieja completa.
const JB3_GA_MEASUREMENT_ID = 'G-EJZW4LPX6Y';

add_action( 'wp_head', 'jb3_ga_output' );
function jb3_ga_output(): void {
	if ( is_admin() ) {
		return;
	}
	?>
	<script>
	( function () {
		window.dataLayer = window.dataLayer || [];
		function gtag() { dataLayer.push( arguments ); }
		window.gtag = gtag;

		// Lee la cookie de CookieYes para saber el estado YA elegido por un
		// visitante que vuelve — evita arrancar en "denied" de más y tener
		// que esperar el evento para alguien que ya había aceptado antes.
		function estaAceptada( categoria ) {
			var match = document.cookie.match( /(?:^|; )cookieyes-consent=([^;]*)/ );
			if ( ! match ) {
				return false;
			}
			return decodeURIComponent( match[ 1 ] ).indexOf( categoria + ':yes' ) !== -1;
		}

		function estadoConsentimiento() {
			var analytics = estaAceptada( 'analytics' ) ? 'granted' : 'denied';
			var ads       = estaAceptada( 'advertisement' ) ? 'granted' : 'denied';
			return {
				analytics_storage:  analytics,
				ad_storage:         ads,
				ad_user_data:       ads,
				ad_personalization: ads,
			};
		}

		var consentimientoInicial = estadoConsentimiento();
		consentimientoInicial.wait_for_update = 500;
		gtag( 'consent', 'default', consentimientoInicial );

		gtag( 'js', new Date() );
		gtag( 'config', '<?php echo esc_js( JB3_GA_MEASUREMENT_ID ); ?>' );

		// Visitante que decide (o cambia de idea) durante esta misma visita
		// — evento propio de CookieYes (_ckyFireEvent en su script.js),
		// dispara con { accepted: [...], rejected: [...] }.
		document.addEventListener( 'cookieyes_consent_update', function () {
			gtag( 'consent', 'update', estadoConsentimiento() );
		} );
	} )();
	</script>
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( JB3_GA_MEASUREMENT_ID ); ?>"></script>
	<?php
}
