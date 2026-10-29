<?php
/**
 * Formulario de búsqueda (get_search_form(), ej. en la 404): campo + botón
 * con la lupa.
 *
 * @package JB3_Child
 */

defined( 'ABSPATH' ) || exit;

$jb3_campo_id = wp_unique_id( 'search-form-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $jb3_campo_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Buscar:', 'jb3' ); ?></label>
	<input id="<?php echo esc_attr( $jb3_campo_id ); ?>" type="search" class="search-field" placeholder="<?php esc_attr_e( 'Buscar …', 'jb3' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
	<button type="submit" class="search-submit" aria-label="<?php esc_attr_e( 'Buscar', 'jb3' ); ?>"><?php jb3_icono( 'buscar' ); ?></button>
</form>
