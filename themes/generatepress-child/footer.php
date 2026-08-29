<?php
/**
 * Footer propio del sitio (reemplaza al de GeneratePress).
 *
 * Grid de 3 columnas (8/2/2 en desktop, apiladas en mobile) + barra de CTA
 * abajo. El contenido de las columnas es placeholder — reemplazar los
 * textos, links y nombres de marca por los reales de JB3 antes de publicar.
 *
 * Los dos </div> de acá abajo cierran '.page' y '.site-content', abiertos
 * sin cerrar en header.php (líneas 58 y 67) — hacen falta para no dejar
 * HTML mal formado, ya que seguimos usando el header.php del tema padre.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

	</div>
</div>

<footer id="site-footer" class="jb3-footer">
	<div class="jb3-footer-grid">

		<div class="jb3-footer-col jb3-footer-col--main">
			<h2 class="jb3-footer-title">Título institucional</h2>
			<p>Texto institucional de introducción — reemplazar por la copy real de JB3.</p>
			<p>Segundo párrafo institucional con más detalle sobre la propuesta del sitio.</p>
			<p><strong>Frase de cierre institucional.</strong></p>
			<p class="jb3-footer-legal">
				© <?php echo esc_html( gmdate( 'Y' ) ); ?> JB3. Todos los derechos reservados.
			</p>
		</div>

		<div class="jb3-footer-col jb3-footer-col--links">
			<h3 class="jb3-footer-heading">Columna 2</h3>
			<ul class="jb3-footer-list">
				<li><a href="#">Enlace 1</a></li>
				<li><a href="#">Enlace 2</a></li>
				<li><a href="#">Enlace 3</a></li>
				<li><a href="#">Enlace 4</a></li>
			</ul>
		</div>

		<div class="jb3-footer-col jb3-footer-col--secondary">
			<h3 class="jb3-footer-heading">Columna 3a</h3>
			<ul class="jb3-footer-list">
				<li><a href="#">Enlace 1</a></li>
				<li><a href="#">Enlace 2</a></li>
			</ul>
			<h3 class="jb3-footer-heading">Columna 3b</h3>
			<ul class="jb3-footer-list">
				<li><a href="#">Enlace 1</a></li>
				<li><a href="#">Enlace 2</a></li>
			</ul>
		</div>

	</div>

	<div class="jb3-footer-bar">
		<a href="<?php echo esc_url( home_url( '/conectate-con-nosotors/' ) ); ?>" class="jb3-footer-bar__cta"><span>CONECTATE <strong>AQUÍ</strong> CON NUESTRO EQUIPO COMERCIAL</span></a>
		<?php
		// revisitCkyConsent() la expone el plugin CookieYes (cookie-law-info)
		// como función global de JS, no de PHP — no hay forma de chequear
		// desde acá si el plugin está activo, así que el link se imprime
		// siempre; el "if (window.revisitCkyConsent)" del onclick es la
		// única protección real (sin el plugin activo, el link no hace
		// nada en vez de tirar un error de JS). Reemplaza al ícono flotante
		// propio del plugin, que quedaba pisando esta misma barra — hay que
		// desactivarlo en su panel (Content & Colours → Revisit Consent
		// Button) para no tener los dos a la vez.
		?>
		<a href="#" class="jb3-footer-bar__cookies" aria-label="Configurar cookies" onclick="if (window.revisitCkyConsent) { revisitCkyConsent(); } return false;">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<circle cx="12" cy="12" r="10"></circle>
				<circle cx="8.5" cy="10" r="1.2" fill="currentColor"></circle>
				<circle cx="14" cy="8.5" r="1" fill="currentColor"></circle>
				<circle cx="15" cy="14" r="1.2" fill="currentColor"></circle>
				<circle cx="10" cy="15" r="1" fill="currentColor"></circle>
			</svg>
			<span class="jb3-footer-bar__cookies-tooltip" aria-hidden="true">Cookies</span>
		</a>
		<a href="#" class="jb3-footer-bar__totop" aria-label="Volver arriba">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<polyline points="18 15 12 9 6 15"></polyline>
			</svg>
			<span class="jb3-footer-bar__totop-tooltip" aria-hidden="true">Ir arriba</span>
		</a>
	</div>
</footer>

<?php wp_footer(); ?>

</body>
</html>
