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
	// Valor crudo (ID) en vez del formato de retorno del campo (URL): hace
	// falta el adjunto para armar el srcset. Copia justa por pantalla y
	// prioridad de carga (es lo primero que se ve): jb3_picture(),
	// _init_imagenes.php.
	$picture = jb3_picture(
		jb3_img_id( get_field( 'imagen_hero_desktop', false, false ) ),
		jb3_img_id( get_field( 'imagen_hero_mobile', false, false ) ),
		array(
			'sizes'     => jb3_sizes_ancho_completo(),
			'prioridad' => true,
			'class'     => 'jb3-hero__picture',
		)
	);
	if ( ! $picture ) {
		return;
	}
	?>
	<div class="jb3-hero"><?php echo $picture; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_picture() ya escapa todo. ?></div>
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
