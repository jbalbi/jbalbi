<?php
defined( 'ABSPATH' ) || exit;

class JB3_Representatives {

    public static function init() {
        add_shortcode( 'jb3_representantes', [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
    }

    // ─── Table names ─────────────────────────────────────────────────────────

    private static function tbl() {
        global $wpdb;
        return $wpdb->prefix . 'jb3_representatives';
    }

    private static function tbl_countries() {
        global $wpdb;
        return $wpdb->prefix . 'jb3_representative_countries';
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public static function get_all() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $reps = $wpdb->get_results(
            'SELECT * FROM ' . self::tbl() . ' ORDER BY is_default DESC, last_name ASC, first_name ASC, id ASC'
        );
        return self::attach_countries( $reps );
    }

    /**
     * Visible representatives for a given country — falls back to the
     * visible default rep(s) when the country has none of its own, so the
     * visitor always sees at least someone to contact (decisión explícita
     * 2026-08-21). Used by the [jb3_representantes] contact-page shortcode.
     */
    public static function get_visible_by_country( $country_code ) {
        $reps = self::get_by_country( $country_code );
        return array_values( array_filter( $reps, static fn( $r ) => ! empty( $r->visible ) ) );
    }

    public static function get( $id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rep = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE id = %d', (int) $id )
        );
        return $rep ? self::attach_countries( [ $rep ] )[0] : null;
    }

    /**
     * Representatives assigned to a given country, falling back to the
     * default representative(s) when none are defined for that country —
     * $fallback_to_default=false lo desactiva (usado por
     * get_visible_by_country() para la página de Contacto pública).
     * Con fallback (el caso por defecto): usado para la "new registration
     * pending approval" email y el aviso de descarga — ALL matching reps
     * get it, regardless of the notify_downloads flag (that flag only
     * gates the separate download-alert email below). Siempre con
     * fallback ahí a propósito: sin esto, un registro desde un país sin
     * representante asignado no le llegaría a nadie y quedaría "pendiente"
     * para siempre sin que nadie se entere de aprobarlo.
     */
    public static function get_by_country( $country_code, bool $fallback_to_default = true ) {
        global $wpdb;
        $country_code = strtoupper( sanitize_text_field( $country_code ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rep_ids = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT DISTINCT representative_id FROM ' . self::tbl_countries() . ' WHERE country_code = %s',
                $country_code
            )
        );

        if ( empty( $rep_ids ) ) {
            return $fallback_to_default ? self::get_defaults() : [];
        }

        return self::get_by_ids( $rep_ids );
    }

    /**
     * Same as get_by_country(), filtered to reps who opted in to
     * download-alert emails — for the op_comerciales download notice.
     */
    public static function get_notifiable_by_country( $country_code ) {
        $reps = self::get_by_country( $country_code );
        return array_values( array_filter( $reps, static fn( $r ) => ! empty( $r->notify_downloads ) ) );
    }

    public static function get_defaults() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $reps = $wpdb->get_results( 'SELECT * FROM ' . self::tbl() . ' WHERE is_default = 1' );
        return self::attach_countries( $reps );
    }

    private static function get_by_ids( array $ids ) {
        global $wpdb;
        $ids = array_map( 'intval', $ids );
        if ( empty( $ids ) ) {
            return [];
        }
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $reps = $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . self::tbl() . " WHERE id IN ({$placeholders})", $ids )
        );
        return self::attach_countries( $reps );
    }

    private static function attach_countries( array $reps ) {
        foreach ( $reps as $rep ) {
            $rep->country_codes = self::get_country_codes( $rep->id );
        }
        return $reps;
    }

    public static function get_country_codes( $rep_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_col(
            $wpdb->prepare(
                'SELECT country_code FROM ' . self::tbl_countries() . ' WHERE representative_id = %d ORDER BY country_code ASC',
                (int) $rep_id
            )
        );
    }

    // ─── Write ────────────────────────────────────────────────────────────────

    public static function insert( array $data ) {
        global $wpdb;
        $wpdb->insert( self::tbl(), self::prepare_data( $data ), self::formats() );
        $rep_id = (int) $wpdb->insert_id;
        if ( ! empty( $data['is_default'] ) ) {
            self::clear_other_defaults( $rep_id );
        }
        self::save_countries( $rep_id, $data['country_codes'] ?? [] );
        return $rep_id;
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
        $result = $wpdb->update( self::tbl(), self::prepare_data( $data ), [ 'id' => (int) $id ], self::formats(), [ '%d' ] );
        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'No se pudo guardar el representante.', 'jb3-members' ) );
        }
        // Nunca puede haber más de un representante "por defecto" al mismo
        // tiempo — bug real confirmado 2026-08-21: nada impedía tildar
        // is_default en un segundo representante, y una vez que pasaba eso,
        // la protección de arriba (no se le puede QUITAR el estado a quien
        // ya lo tiene) dejaba el dato roto sin forma de arreglarlo desde el
        // form normal — los dos terminaban apareciendo como fallback en
        // cualquier país sin representante asignado.
        if ( ! empty( $data['is_default'] ) ) {
            self::clear_other_defaults( (int) $id );
        }
        self::save_countries( (int) $id, $data['country_codes'] ?? [] );
        return $result;
    }

    /** Le saca is_default a cualquier representante que no sea $except_id — ver comentario en update(). */
    private static function clear_other_defaults( int $except_id ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::tbl() . ' SET is_default = 0 WHERE id != %d',
            $except_id
        ) );
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
        $wpdb->delete( self::tbl_countries(), [ 'representative_id' => (int) $id ], [ '%d' ] );
        return $wpdb->delete( self::tbl(), [ 'id' => (int) $id ], [ '%d' ] );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function prepare_data( array $data ) {
        return [
            'first_name'       => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'        => sanitize_text_field( $data['last_name'] ?? '' ),
            'email'            => sanitize_email( $data['email'] ?? '' ),
            'phone'            => jb3_sanitize_phone( $data['phone'] ?? '' ),
            'whatsapp'         => jb3_sanitize_phone( $data['whatsapp'] ?? '' ),
            'photo_id'         => isset( $data['photo_id'] ) ? absint( $data['photo_id'] ) : 0,
            'is_default'       => isset( $data['is_default'] ) ? (int) (bool) $data['is_default'] : 0,
            'visible'          => isset( $data['visible'] ) ? (int) (bool) $data['visible'] : 0,
            'notify_downloads' => isset( $data['notify_downloads'] ) ? (int) (bool) $data['notify_downloads'] : 0,
        ];
    }

    private static function formats() {
        return [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ];
    }

    /** Replaces the full set of countries for a representative. Codes not in jb3_countries() are dropped silently. */
    private static function save_countries( $rep_id, array $country_codes ) {
        global $wpdb;
        $rep_id = (int) $rep_id;

        $wpdb->delete( self::tbl_countries(), [ 'representative_id' => $rep_id ], [ '%d' ] );

        $valid = jb3_countries();
        foreach ( array_unique( $country_codes ) as $code ) {
            $code = strtoupper( sanitize_text_field( $code ) );
            if ( isset( $valid[ $code ] ) ) {
                $wpdb->insert(
                    self::tbl_countries(),
                    [ 'representative_id' => $rep_id, 'country_code' => $code ],
                    [ '%d', '%s' ]
                );
            }
        }
    }

    // ─── Frontend: [jb3_representantes] ────────────────────────────────────────

    public static function maybe_enqueue() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }
        if ( has_shortcode( $post->post_content, 'jb3_representantes' ) ) {
            wp_enqueue_style( 'jb3-forms', JB3_URL . 'public/css/jb3-forms.css', [], jb3_asset_ver( 'public/css/jb3-forms.css' ) );
        }
    }

    /**
     * Representatives for the VISITOR's own country (detected the same way
     * as the rest of the site's geo-targeting, jb3-geo-targeting plugin) —
     * not a full directory. Falls back to the visible default rep(s) when
     * the country isn't covered or couldn't be detected.
     */
    public static function shortcode() {
        $country_code = function_exists( 'jb3_geo_visitor_country' )
            ? strtoupper( jb3_geo_visitor_country() )
            : '';

        $reps = self::get_visible_by_country( $country_code );
        if ( empty( $reps ) ) {
            return '';
        }

        $countries = jb3_countries();
        $con_pais  = ( $country_code && isset( $countries[ $country_code ] ) );
        $heading   = $con_pais
            ? sprintf(
                /* translators: %s: country name */
                __( 'Nuestros representantes en %s', 'jb3-members' ),
                $countries[ $country_code ]
            )
            : __( 'Nuestros representantes', 'jb3-members' );

        $mapa_url = $con_pais ? self::pais_asset_url( 'mapa', $country_code ) : '';

        ob_start();
        ?>
        <div class="jb3-representantes">
            <div class="jb3-representantes__col-izq">
                <h2 class="jb3-modulo__titulo jb3-representantes__pais"><?php echo esc_html( $heading ); ?></h2>

                <div class="jb3-representantes__lista">
                    <?php foreach ( $reps as $rep ) : ?>
                        <div class="jb3-representante">
                            <span class="jb3-representante__info">
                                <?php if ( ! empty( $rep->photo_id ) ) : ?>
                                    <span class="jb3-representante__foto">
                                        <?php echo wp_get_attachment_image( (int) $rep->photo_id, 'thumbnail', false, [ 'alt' => trim( $rep->first_name . ' ' . $rep->last_name ) ] ); ?>
                                    </span>
                                <?php endif; ?>

                                <span class="jb3-representante__nombre">
                                    <?php echo esc_html( trim( $rep->first_name . ' ' . $rep->last_name ) ); ?>
                                </span>
                            </span>

                            <span class="jb3-representante__contacto">
                                <?php if ( $rep->whatsapp ) : ?>
                                    <a class="jb3-representante__dato" href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $rep->whatsapp ) ); ?>" target="_blank" rel="noopener">
                                        <?php echo self::icono( 'whatsapp' ); ?>
                                        <?php echo esc_html( $rep->whatsapp ); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ( $rep->phone ) : ?>
                                    <span class="jb3-representante__dato">
                                        <?php echo self::icono( 'phone' ); ?>
                                        <?php echo esc_html( $rep->phone ); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( $rep->email ) : ?>
                                    <a class="jb3-representante__dato" href="mailto:<?php echo esc_attr( $rep->email ); ?>">
                                        <?php echo self::icono( 'mail' ); ?>
                                        <?php echo esc_html( $rep->email ); ?>
                                    </a>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ( $mapa_url ) : ?>
                <div class="jb3-representantes__col-der">
                    <img class="jb3-representantes__mapa" src="<?php echo esc_url( $mapa_url ); ?>" alt="">
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * URL de wp-content/uploads/paises/{tipo}_{CODE}.svg (ej. flag_AR.svg,
     * mapa_AR.svg) — '' si el archivo no está ahí, para no romper el diseño
     * con una imagen caída si falta subir alguno.
     */
    private static function pais_asset_url( string $tipo, string $code ): string {
        $upload_dir = wp_get_upload_dir();
        $filename   = $tipo . '_' . strtoupper( $code ) . '.svg';

        if ( ! file_exists( $upload_dir['basedir'] . '/paises/' . $filename ) ) {
            return '';
        }

        return $upload_dir['baseurl'] . '/paises/' . $filename;
    }

    /** Iconos rellenos e inline (sin librería externa) para WhatsApp/teléfono/email. */
    private static function icono( $nombre ) {
        $iconos = [
            'whatsapp' => '<path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.05 2h-.01zm5.71 14.09c-.24.68-1.19 1.24-1.95 1.4-.53.11-1.22.2-3.55-.76-2.98-1.23-4.9-4.26-5.05-4.46-.15-.2-1.2-1.6-1.2-3.05 0-1.45.76-2.16 1.03-2.46.27-.3.59-.37.79-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.24.6.83 2.06.9 2.21.07.15.12.33.02.53-.1.2-.15.33-.3.5-.15.18-.31.4-.45.54-.15.15-.3.31-.13.6.17.3.76 1.26 1.64 2.04 1.13 1 2.08 1.32 2.38 1.47.3.15.47.13.65-.08.18-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.28.1 1.75.83 2.05.98.3.15.5.22.57.35.08.13.08.75-.16 1.43z"></path>',
            'phone'    => '<path d="M6.62 10.79c1.44 2.83 3.76 5.15 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"></path>',
            'mail'     => '<path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"></path>',
        ];
        if ( ! isset( $iconos[ $nombre ] ) ) {
            return '';
        }
        // width/height explícitos en el propio <svg>, no solo por CSS: sin
        // esto Firefox puede renderizarlo a un tamaño por defecto enorme si
        // la regla .jb3-representante__icono no llega a aplicar a tiempo.
        return '<svg class="jb3-representante__icono" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $iconos[ $nombre ] . '</svg>';
    }
}
