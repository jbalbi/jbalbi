<?php
/**
 * Cabecera: documento HTML + header del sitio (sticky, transparente sobre
 * el hero/rotador).
 *
 *   - Preheader: logo de Sony Pictures a la izquierda y el de Sony a la
 *     derecha, dentro del mismo <header> para que suban y bajen juntos al
 *     scrollear (un solo position:sticky).
 *   - Fila principal: logo + menú a la izquierda; LinkedIn, cuenta y
 *     buscador a la derecha. Hasta 1279px el menú pasa a hamburguesa
 *     (.jb3-menu-controles) y los íconos se muestran dentro del panel.
 *
 * El <div id="page"> que se abre al final se cierra en footer.php.
 *
 * @package JB3_Child
 */

defined( 'ABSPATH' ) || exit;

$jb3_logo_id = (int) get_theme_mod( 'custom_logo' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#jb3-main-content"><?php esc_html_e( 'Saltar al contenido', 'jb3' ); ?></a>

<header id="masthead" class="site-header">
	<?php
	// Imágenes hotlinkeadas desde conectados.sonypictures.com (URLs que pasó
	// el cliente) — conviene subirlas a la Biblioteca de medios propia más
	// adelante y reemplazar acá los src, para no depender de un dominio de
	// terceros.
	?>
	<div class="jb3-preheader">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="jb3-preheader__logo jb3-preheader__logo--left">
			<img src="https://conectados.sonypictures.com/wp-content/uploads/2024/02/preheader_logo-sonypictures.png" alt="Sony Pictures">
		</a>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="jb3-preheader__logo jb3-preheader__logo--right">
			<img src="https://conectados.sonypictures.com/wp-content/uploads/2024/02/preheader_logo-sony.png" alt="Sony">
		</a>
	</div>

	<div class="site-header__inner">
		<?php if ( $jb3_logo_id ) : ?>
			<div class="site-logo">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php
					// Mismo logo que el footer (Apariencia > Personalizar > Identidad
					// del sitio > Logo). Se ve a 120px de ancho (.site-logo img).
					echo jb3_img( $jb3_logo_id, '120px', array( 'alt' => get_bloginfo( 'name' ), 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jb3_img() ya escapa todo.
					?>
				</a>
			</div>
		<?php endif; ?>

		<div class="jb3-menu-controles">
			<button type="button" class="jb3-boton-icono jb3-menu-controles__buscar" data-jb3-buscador aria-haspopup="dialog" aria-controls="jb3-buscador">
				<?php jb3_icono( 'buscar' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Buscar', 'jb3' ); ?></span>
			</button>
			<button type="button" class="jb3-boton-icono menu-toggle" aria-controls="site-navigation" aria-expanded="false">
				<?php jb3_icono( 'menu' ); ?>
				<?php jb3_icono( 'cerrar' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Menú', 'jb3' ); ?></span>
			</button>
		</div>

		<nav id="site-navigation" class="main-navigation" aria-label="<?php esc_attr_e( 'Menú principal', 'jb3' ); ?>">
			<?php
			// Apariencia > Menús, ubicación "primary". El ítem destacado
			// ("Conéctate con nosotros") lleva la clase CSS "nav-cta", puesta
			// a mano en el editor de menús.
			wp_nav_menu(
				array(
					'theme_location'  => 'primary',
					'container'       => 'div',
					'container_class' => 'main-nav',
					'menu_id'         => 'primary-menu',
					'menu_class'      => '',
					'depth'           => 2,
					'fallback_cb'     => false,
				)
			);
			?>
		</nav>

		<div class="site-header-icons">
			<a class="site-header-icons__linkedin" href="<?php echo esc_url( 'https://www.linkedin.com/company/' . JB3_LINKEDIN_EMPRESA_ID . '/' ); ?>" target="_blank" rel="noopener">
				<?php jb3_icono( 'linkedin' ); ?>
				<span class="screen-reader-text">LinkedIn</span>
			</a>

			<?php if ( is_user_logged_in() ) : ?>
				<div class="site-header-account">
					<button type="button" class="jb3-boton-icono site-header-account__toggle" aria-haspopup="true" aria-expanded="false">
						<span class="screen-reader-text"><?php esc_html_e( 'Mi cuenta', 'jb3' ); ?></span>
						<?php jb3_icono( 'cuenta' ); ?>
					</button>
					<ul class="site-header-account__menu">
						<li><a href="/perfil/"><?php esc_html_e( 'TUS DATOS', 'jb3' ); ?></a></li>
						<li><a href="/logout/"><?php esc_html_e( 'CERRAR SESIÓN', 'jb3' ); ?></a></li>
					</ul>
				</div>
			<?php else : ?>
				<a href="/registro/" class="site-header-icons__auth-link"><?php esc_html_e( 'Regístrate', 'jb3' ); ?></a>
				<a href="/login/" class="site-header-icons__auth-link"><?php esc_html_e( 'Ingresa', 'jb3' ); ?></a>
			<?php endif; ?>

			<button type="button" class="jb3-boton-icono site-header-icons__search" data-jb3-buscador aria-haspopup="dialog" aria-controls="jb3-buscador">
				<?php jb3_icono( 'buscar' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Buscar', 'jb3' ); ?></span>
			</button>
		</div>
	</div>
</header>

<div id="page" class="site">
