<?php
defined( 'ABSPATH' ) || exit;

class JB3_Representatives {

    // ─── Table name ──────────────────────────────────────────────────────────

    private static function tbl() {
        global $wpdb;
        return $wpdb->prefix . 'jb3_representatives';
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public static function get_all() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_results(
            'SELECT * FROM ' . self::tbl() . ' ORDER BY is_default DESC, country_code ASC, id ASC'
        );
    }

    public static function get( $id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE id = %d', (int) $id )
        );
    }

    /**
     * Returns representatives for a given country ISO code.
     * Falls back to default representative(s) when none are defined for that country.
     */
    public static function get_by_country( $country_code ) {
        global $wpdb;
        $country_code = strtoupper( sanitize_text_field( $country_code ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $reps = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::tbl() . ' WHERE country_code = %s AND is_default = 0',
                $country_code
            )
        );
        return ! empty( $reps ) ? $reps : self::get_defaults();
    }

    public static function get_defaults() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_results( 'SELECT * FROM ' . self::tbl() . ' WHERE is_default = 1' );
    }

    // ─── Write ────────────────────────────────────────────────────────────────

    public static function insert( array $data ) {
        global $wpdb;
        return $wpdb->insert( self::tbl(), self::prepare_data( $data ), self::formats() );
    }

    public static function update( $id, array $data ) {
        global $wpdb;
        $rep = self::get( $id );
        if ( ! $rep ) {
            return new WP_Error( 'not_found', __( 'Representante no encontrado.', 'jb3-members' ) );
        }
        // Prevent changing is_default on the seeded default rep via UI.
        if ( $rep->is_default && isset( $data['is_default'] ) && ! $data['is_default'] ) {
            return new WP_Error( 'protected', __( 'No se puede quitar el estado por defecto al representante predeterminado.', 'jb3-members' ) );
        }
        return $wpdb->update( self::tbl(), self::prepare_data( $data ), [ 'id' => (int) $id ], self::formats(), [ '%d' ] );
    }

    public static function delete( $id ) {
        global $wpdb;
        $rep = self::get( $id );
        if ( ! $rep ) {
            return new WP_Error( 'not_found', __( 'Representante no encontrado.', 'jb3-members' ) );
        }
        if ( $rep->is_default ) {
            return new WP_Error( 'protected', __( 'No se puede eliminar el representante por defecto.', 'jb3-members' ) );
        }
        return $wpdb->delete( self::tbl(), [ 'id' => (int) $id ], [ '%d' ] );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function prepare_data( array $data ) {
        return [
            'country_code' => strtoupper( sanitize_text_field( $data['country_code'] ?? '' ) ),
            'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
            'email'        => sanitize_email( $data['email'] ?? '' ),
            'phone'        => jb3_sanitize_phone( $data['phone'] ?? '' ),
            'is_default'   => isset( $data['is_default'] ) ? (int) (bool) $data['is_default'] : 0,
        ];
    }

    private static function formats() {
        return [ '%s', '%s', '%s', '%s', '%s', '%d' ];
    }
}
