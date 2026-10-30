<?php
/**
 * Pie: cierre de #page (abierto en header.php), footer del sitio, buscador
 * y cierre del documento.
 *
 * Logo del sitio (el mismo del header) solo en su fila, debajo un grid de 2
 * columnas (apiladas en mobile) y barra de CTA abajo:
 *   - texto institucional y legal;
 *   - el menú principal desplegado, filtrado por país (jb3_footer_menu(),
 *     inc/footer-menu.php).
 *
 * @package JB3_Child
 */

defined( 'ABSPATH' ) || exit;
?>
</div><!-- #page -->

<footer id="site-footer" class="jb3-footer">
	<?php
	// Mismo logo que el header (Apariencia > Personalizar > Identidad del
	// sitio > Logo): si se cambia ahí, cambia también acá.
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) :
		?>
		<div class="jb3-footer-marca">
			<a class="jb3-footer-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php echo jb3_img( $logo_id, '150px', array( 'alt' => get_bloginfo( 'name' ), 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo. ?>
			</a>
		</div>
	<?php endif; ?>

	<div class="jb3-footer-grid">

		<div class="jb3-footer-col jb3-footer-col--main">
			<p><strong>Conectados es tu acceso directo al universo de oportunidades que ofrece Sony Pictures Television en América Latina.</strong></p>
			<p>
				Aquí puedes descubrir alianzas estratégicas, explorar nuestros contenidos originales y encontrar soluciones creativas que conecten a tu marca con millones de personas.<br>
				Nuestro ecosistema incluye televisión paga, producciones premium, artistas de Sony Music, campañas de content marketing y franquicias.
			</p>
			<p><strong>Conéctate con el poder del entretenimiento.</strong></p>
			<p class="jb3-footer-legal">
				<?php // Año automático, con la zona horaria del sitio (Ajustes > Generales). ?>
				© <?php echo esc_html( wp_date( 'Y' ) ); ?> SET Distribution, LLC. Todos los derechos reservados.<br>
				El logo Sony Channel es una marca de SET Distribution, LLC.
			</p>
		</div>

		<nav class="jb3-footer-col jb3-footer-col--menu" aria-label="<?php esc_attr_e( 'Menú del pie de página', 'jb3' ); ?>">
			<?php echo jb3_footer_menu(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_footer_menu() ya escapa todo. ?>
		</nav>

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

<?php // Buscador: lo abren los botones [data-jb3-buscador] del header (assets/js/header.js). ?>
<dialog id="jb3-buscador" class="jb3-buscador" aria-label="<?php esc_attr_e( 'Buscar', 'jb3' ); ?>">
	<form role="search" method="get" class="jb3-buscador__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label for="jb3-buscador-campo" class="screen-reader-text"><?php esc_html_e( 'Buscar:', 'jb3' ); ?></label>
		<input id="jb3-buscador-campo" type="search" class="jb3-buscador__campo" placeholder="<?php esc_attr_e( 'Buscar …', 'jb3' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
		<button type="submit" class="jb3-boton-icono jb3-buscador__enviar">
			<?php jb3_icono( 'buscar' ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Buscar', 'jb3' ); ?></span>
		</button>
	</form>
</dialog>

<?php wp_footer(); ?>
</body>
</html>
