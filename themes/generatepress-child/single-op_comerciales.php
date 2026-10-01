<?php
/**
 * Template para single del CPT "op_comerciales".
 *
 * A diferencia de los demás single-{cpt}.php del sitio, ACÁ el título SÍ se
 * muestra (no hay hero que le dé identidad visual a la página, como en
 * Películas/Series/Prod. Originales). La "bajada"/descripción no es un
 * campo ACF aparte — es el contenido normal del post (the_content(), abajo
 * del título), editable con el editor de bloques como cualquier página; no
 * todas las Op. Comerciales van a tener una, por eso el wrapper solo se
 * imprime si hay contenido real.
 *
 * Estructura fija (no depende de que el editor arme nada por shortcode):
 * título → descripción opcional → PDF → "Más contenido de {tag}" si tiene
 * género/etiqueta cargada (solo cruza con otras Op. Comerciales, no con
 * Videos/Integraciones) → Más Oportunidades Comerciales (automático,
 * últimas 6 para el país del visitante, con botón a la página general).
 * Sin módulo de Relacionados acá a propósito — decisión del 2026-08-08, si
 * hace falta volver a sumarlo alcanza con un
 * jb3_relacionados_auto( get_the_ID() ) antes del módulo de abajo.
 *
 * PDF — gate real de seguridad, acá y no en el visor: jb3_pdf_viewer()
 * (_init_pdf_viewer.php) SIEMPRE muestra el PDF completo, sin chequear
 * sesión — mostrar "solo la página 1" del lado del cliente NO protege
 * nada, porque el navegador necesita bajar el archivo COMPLETO igual para
 * poder armar cualquier página (cualquiera con las herramientas de
 * desarrollador lo saca de la pestaña Red, sin importar cuánto se
 * muestre). La única protección real es que el PDF ni siquiera viaje
 * hasta un visitante sin sesión — por eso el gate vive acá, en el if:
 *   - Logueado: jb3_pdf_viewer() + botón "Descargar PDF", los dos por las
 *     URLs de jb3-members (view_url()/download_url(), atadas a la sesión)
 *     — el acceso directo a cualquier .pdf está bloqueado en
 *     wp-content/.htaccess, la URL real ya no sirve para compartir.
 *   - Sin sesión: la miniatura destacada del post (la misma que ya se usa
 *     en las grillas) + botón a /login/ — el HTML nunca menciona la URL
 *     del PDF en este caso, no hay nada que "ver código fuente" pueda
 *     sacar.
 *
 * El visor en sí se probó primero con un <iframe> (visor nativo del
 * navegador + #toolbar=0), pero una extensión como Adobe Acrobat puede
 * tomar el PDF y ponerle su propia interfaz encima, algo que no se
 * controla por URL — por eso el visor a canvas: el navegador nunca "ve"
 * que es un PDF, así que ninguna extensión tiene nada que interceptar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="jb3-main-content">
	<?php while ( have_posts() ) : the_post();
		$pdf = get_field( 'pdf' );
		$pdf_url = is_array( $pdf ) ? ( $pdf['url'] ?? '' ) : (string) $pdf;

		// URL actual sin "e" — redirect_to tanto del login genérico como del
		// magic link automático de Mailchimp más abajo.
		$current_url = remove_query_arg( 'e', esc_url_raw(
			( is_ssl() ? 'https' : 'http' ) . '://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] )
		) );

		// Entrada validada por Mailchimp (?e=*|UNIQID|*): "e" SOLO importa
		// cuando no hay sesión — sirve para saber a quién mandarle el magic
		// link en vez de mostrarle el formulario genérico. Con sesión activa
		// no se consulta ni tiene efecto: el acceso ya lo decide is_user_logged_in()
		// de abajo, sin importar de quién era el link (ver JB3_Mailchimp_Entry).
		$mc_resultado = null;
		if ( ! is_user_logged_in() && ! empty( $_GET['e'] ) && class_exists( 'JB3_Mailchimp_Entry' ) ) {
			$mc_resultado = JB3_Mailchimp_Entry::verificar( sanitize_text_field( wp_unslash( $_GET['e'] ) ), $current_url );
		}

		$mostrar_pdf_completo = is_user_logged_in();
		?>

		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<div class="jb3-contenedor">
			<h1 class="jb3-op-comercial__titulo"><?php the_title(); ?></h1>

			<?php if ( get_the_content() ) : ?>
				<div class="entry-content jb3-op-comercial__descripcion">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

			<?php if ( $pdf_url ) : ?>
				<div class="jb3-op-comercial__pdf">
					<?php if ( $mostrar_pdf_completo ) : ?>
						<?php
						echo jb3_pdf_viewer( class_exists( 'JB3_Op_Comerciales_Tracking' )
							? JB3_Op_Comerciales_Tracking::view_url( get_the_ID() )
							: $pdf_url );
						?>
						<p class="jb3-op-comercial__pdf-descarga">
							<?php
							// Ruta por JB3_Op_Comerciales_Tracking::download_url() en vez del
							// PDF directo: un <a href="archivo.pdf" download> nunca pasa por
							// PHP (el navegador lo baja solo), así que es la única forma de
							// registrar la descarga en Sheets y avisar a los representantes.
							$descarga_url = class_exists( 'JB3_Op_Comerciales_Tracking' )
								? JB3_Op_Comerciales_Tracking::download_url( get_the_ID() )
								: $pdf_url;
							?>
							<a class="button" href="<?php echo esc_url( $descarga_url ); ?>" target="_blank" rel="noopener">Descargar PDF</a>
						</p>
					<?php elseif ( 'magic_link_enviado' === $mc_resultado ) : ?>
						<div class="jb3-pdf-viewer jb3-pdf-viewer--bloqueado">
							<div class="jb3-pdf-viewer__login-cta">
								<p>¡Revisá tu correo! Te mandamos un enlace de acceso — al hacer clic vas a volver directo acá.</p>
							</div>
						</div>
					<?php else : ?>
						<div class="jb3-pdf-viewer jb3-pdf-viewer--bloqueado">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php
								// Se muestra a width:100% del contenido (.jb3-pdf-viewer__preview
								// en style.css). Antes iba siempre el original ('full', porque
								// 'large' tenía tope de 1024 y se veía borroso en contenedores
								// más anchos). Desde 2026-10-01 va con srcset: cada pantalla
								// baja la copia justa, sin tope que la deje borrosa (ver
								// jb3_img(), _init_imagenes.php).
								$preview = jb3_img( (int) get_post_thumbnail_id(), jb3_sizes_ancho_contenido(), array( 'class' => 'jb3-pdf-viewer__preview' ) );
								if ( $preview ) :
									echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo.
								else :
									?>
									<img class="jb3-pdf-viewer__preview" src="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'full' ) ); ?>" alt="">
								<?php endif; ?>
							<?php endif; ?>
							<?php
							// redirect_to: vuelve acá mismo después de loguearse/registrarse,
							// en vez de mandar al dashboard genérico (JB3_Magic_Link ya
							// soporta esto — ver el paso "redirect_to en el magic link").
							$login_url    = add_query_arg( 'redirect_to', rawurlencode( $current_url ), home_url( '/login/' ) );
							$registro_url = home_url( '/registro/' );
							?>
							<div class="jb3-pdf-viewer__login-cta">
								<p>Iniciá sesión para ver el documento completo.</p>
								<a class="button" href="<?php echo esc_url( $login_url ); ?>">Ingresar</a>
								<a class="button" href="<?php echo esc_url( $registro_url ); ?>">Registrarte</a>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			</div>
		</article>

		<?php echo jb3_etiquetas_auto( get_the_ID() ); ?>

		<?php
		/* jb3_modulo_render() ya no se pone su propio ".jb3-contenedor" (ver
		   _init_modulo.php) porque tambien la usa el bloque ACF/shortcode,
		   insertables adentro de Columnas -- aca, llamada directo por PHP como
		   hermana de .entry-content, si necesita el breakout, asi que se lo
		   da este wrapper. */
		?>
		<div class="jb3-contenedor">
			<?php
			echo jb3_modulo_render(
				[ 'op_comerciales' ],
				[],
				'Más Oportunidades Comerciales',
				'',
				6,
				'Ver más',
				'/op-comerciales/',
				get_the_ID()
			);
			?>
		</div>

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
