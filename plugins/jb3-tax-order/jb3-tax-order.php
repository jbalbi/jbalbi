<?php
/**
 * Plugin Name: JB3 Taxonomy Order
 * Description: Drag & drop para ordenar los términos de taxonomías seleccionadas.
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

const JB3_TERM_ORDER_META = 'jb3_term_order';

/** Taxonomías con ordenamiento personalizado. */
function jb3_ordered_taxonomies(): array {
    return [ 'canales', 'pais' ];
}

// ─── Admin: encolar scripts en la página de listado de términos ───────────────

add_action( 'admin_enqueue_scripts', function( string $hook ) {
    if ( 'edit-tags.php' !== $hook ) {
        return;
    }
    $taxonomy = sanitize_key( $_GET['taxonomy'] ?? '' );
    if ( ! in_array( $taxonomy, jb3_ordered_taxonomies(), true ) ) {
        return;
    }

    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_script(
        'jb3-tax-order',
        plugin_dir_url( __FILE__ ) . 'admin.js',
        [ 'jquery', 'jquery-ui-sortable' ],
        '1.0',
        true
    );
    wp_localize_script( 'jb3-tax-order', 'jb3TaxOrder', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'jb3_tax_order' ),
    ] );

    // CSS inline — solo tres reglas, no vale la pena un archivo aparte.
    wp_add_inline_style( 'list-tables', '
        .jb3-drag-handle { cursor: grab; color: #aaa; margin-right: 8px; }
        .jb3-drag-handle:hover { color: #555; }
        .jb3-sort-placeholder { background: #e8f0fe; height: 2em; }
    ' );
} );

// ─── AJAX: guardar orden ──────────────────────────────────────────────────────

add_action( 'wp_ajax_jb3_save_term_order', function() {
    check_ajax_referer( 'jb3_tax_order', 'nonce' );
    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $order = array_map( 'intval', (array) ( $_POST['order'] ?? [] ) );
    foreach ( $order as $position => $term_id ) {
        update_term_meta( $term_id, JB3_TERM_ORDER_META, $position );
    }
    wp_send_json_success();
} );

// ─── Frontend + admin: aplicar orden en get_terms ────────────────────────────

add_filter( 'terms_clauses', function( array $clauses, array $taxonomies, array $args ): array {
    global $wpdb;

    if ( ! array_intersect( $taxonomies, jb3_ordered_taxonomies() ) ) {
        return $clauses;
    }

    // En el admin: si el usuario hizo clic en un encabezado de columna (?orderby=name),
    // respetar ese orden explícito. Fuera del admin (frontend, REST API) aplicar siempre.
    if ( is_admin() && isset( $_GET['orderby'] ) ) {
        return $clauses;
    }

    $clauses['join']   .= " LEFT JOIN {$wpdb->termmeta} AS jb3_om
                             ON (t.term_id = jb3_om.term_id AND jb3_om.meta_key = 'jb3_term_order')";
    // Términos sin orden asignado van al final; dentro del mismo grupo, alfabético.
    $clauses['orderby'] = 'ORDER BY
        CASE WHEN jb3_om.meta_value IS NULL THEN 1 ELSE 0 END,
        CAST(jb3_om.meta_value AS UNSIGNED) ASC,
        t.name ASC';
    // WordPress agrega $clauses['order'] al final del orderby — lo vaciamos para
    // evitar SQL inválido del tipo "t.name ASC ASC".
    $clauses['order'] = '';

    return $clauses;
}, 10, 3 );
