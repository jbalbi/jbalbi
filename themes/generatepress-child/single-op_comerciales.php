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
 *   - Logueado: jb3_pdf_viewer() con la URL real + botón "Descargar PDF".
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
		?>

		<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
			<h1 class="jb3-op-comercial__titulo"><?php the_title(); ?></h1>

			<?php if ( get_the_content() ) : ?>
				<div class="entry-content jb3-op-comercial__descripcion">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

			<?php if ( $pdf_url ) : ?>
				<div class="jb3-op-comercial__pdf">
					<?php if ( is_user_logged_in() ) : ?>
						<?php echo jb3_pdf_viewer( $pdf_url ); ?>
						<p class="jb3-op-comercial__pdf-descarga">
							<a class="button" href="<?php echo esc_url( $pdf_url ); ?>" download>Descargar PDF</a>
						</p>
					<?php else : ?>
						<div class="jb3-pdf-viewer jb3-pdf-viewer--bloqueado">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php /* 'full', no 'large': se muestra a width:100% (ver .jb3-pdf-viewer__preview en style.css), y 'large' tiene un tope de 1024px — en un contenedor más ancho que eso el navegador la estira y se ve borrosa aunque el archivo original sea nítido. */ ?>
								<img class="jb3-pdf-viewer__preview" src="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'full' ) ); ?>" alt="">
							<?php endif; ?>
							<div class="jb3-pdf-viewer__login-cta">
								<p>Iniciá sesión para ver el documento completo.</p>
								<a class="button" href="/login/">Ingresar</a>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</article>

		<?php echo jb3_genero_auto( get_the_ID() ); ?>

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

	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
