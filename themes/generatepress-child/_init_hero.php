<?php
defined( 'ABSPATH' ) || exit;

// ─── HERO: Series, Películas, Prod. Originales ─────────────────────────────
// Imprime el <picture> del hero (grupo ACF "Hero": imagen_hero_desktop/
// imagen_hero_mobile, Location: Post Type == series/peliculas/
// prod_originales) envuelto en .jb3-hero (ver style.css — pasa por detrás
// del header). Reutilizado desde los 3 single-*.php que lo necesitan, en
// vez de repetir el mismo markup en cada uno. Mismo patrón <picture>+
// <source> que ya usa el slider de Home.
function jb3_hero_image() {
	$desktop = get_field( 'imagen_hero_desktop' );
	$mobile  = get_field( 'imagen_hero_mobile' );

	if ( ! $desktop ) {
		return;
	}
	?>
	<div class="jb3-hero">
		<picture class="jb3-hero__picture">
			<?php if ( $mobile ) : ?>
				<source media="(max-width: 767px)" srcset="<?php echo esc_url( $mobile ); ?>">
			<?php endif; ?>
			<img src="<?php echo esc_url( $desktop ); ?>" alt="">
		</picture>
	</div>
	<?php
}

// Barra con el nombre del post, centrado y en mayúsculas, fondo color
// primario — pegada debajo del hero. Reemplaza al <h1> que estos 3
// templates no imprimían (ver comentario arriba de cada single-*.php):
// de paso llena ese hueco de accesibilidad/SEO, no es solo decorativo.
function jb3_hero_titulo() {
	?>
	<div class="jb3-hero-titulo">
		<h1 class="jb3-hero-titulo__texto"><?php echo esc_html( get_the_title() ); ?></h1>
	</div>
	<?php
}
