<?php
defined( 'ABSPATH' ) || exit;

class JB3_Profile_Update {

    public static function init() {
        add_shortcode( 'jb3_profile', [ __CLASS__, 'shortcode' ] );
        add_action( 'init', [ __CLASS__, 'handle_delete_account' ], 5 );
    }

    // ─── Shortcode ────────────────────────────────────────────────────────────

    public static function shortcode() {
        // Never redirect during REST API requests (Gutenberg save/preview).
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return '';
        }

        // Redirect to login only if the visitor is not logged in as a JB3 member.
        // Admins visiting this page on the frontend should see the form, not a redirect.
        if ( ! is_user_logged_in() || ( ! jb3_is_member( get_current_user_id() ) && ! current_user_can( 'manage_options' ) ) ) {
            $login_page = (int) get_option( 'jb3_login_page_id' );
            $url        = $login_page ? get_permalink( $login_page ) : wp_login_url( get_permalink() );
            wp_redirect( esc_url_raw( $url ) );
            exit;
        }

        $user_id = get_current_user_id();
        $errors  = [];
        $success = false;

        if ( isset( $_POST['jb3_profile_nonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jb3_profile_nonce'] ) ), 'jb3_profile_' . $user_id ) ) {
            [ $errors, $success ] = self::process( $user_id );
        }

        $user = get_userdata( $user_id );
        ob_start();
        include JB3_DIR . 'public/views/update-form.php';
        return ob_get_clean();
    }

    // ─── Form processing ──────────────────────────────────────────────────────

    private static function process( $user_id ) {
        $errors = [];

        // — Sanitise ——————————————————————————————————————————————
        $first_name = sanitize_text_field( wp_unslash( $_POST['first_name']  ?? '' ) );
        $last_name  = sanitize_text_field( wp_unslash( $_POST['last_name']   ?? '' ) );
        $company    = sanitize_text_field( wp_unslash( $_POST['company']     ?? '' ) );
        $job_title  = sanitize_text_field( wp_unslash( $_POST['job_title']   ?? '' ) );
        $phone      = jb3_sanitize_phone( wp_unslash( $_POST['phone']    ?? '' ) );
        $country    = strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ) );
        $sector     = sanitize_text_field( wp_unslash( $_POST['sector']      ?? '' ) );
        $newsletter = ! empty( $_POST['newsletter'] ) ? 1 : 0;
        $raw        = isset( $_POST['interests'] ) && is_array( $_POST['interests'] )
                      ? $_POST['interests'] : [];
        $interests  = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw ) );

        // — Validate ——————————————————————————————————————————————
        if ( '' === $first_name ) {
            $errors[] = __( 'El nombre es obligatorio.', 'jb3-members' );
        }
        if ( '' === $last_name ) {
            $errors[] = __( 'El apellido es obligatorio.', 'jb3-members' );
        }
        if ( '' === $company ) {
            $errors[] = __( 'La empresa es obligatoria.', 'jb3-members' );
        }
        if ( '' === $phone ) {
            $errors[] = __( 'El teléfono es obligatorio.', 'jb3-members' );
        }
        if ( ! array_key_exists( $country, jb3_countries() ) ) {
            $errors[] = __( 'El país seleccionado no es válido.', 'jb3-members' );
        }
        if ( ! in_array( $job_title, jb3_job_titles(), true ) ) {
            $errors[] = __( 'El cargo seleccionado no es válido.', 'jb3-members' );
        }
        if ( ! in_array( $sector, jb3_sectors(), true ) ) {
            $errors[] = __( 'El sector seleccionado no es válido.', 'jb3-members' );
        }

        // Validate interests.
        $valid_keys = array_keys( jb3_interests() );
        $interests  = array_values( array_filter( $interests, fn( $i ) => in_array( $i, $valid_keys, true ) ) );

        if ( ! empty( $errors ) ) {
            return [ $errors, false ];
        }

        // — Update WP user ——————————————————————————————————————————
        wp_update_user( [
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ] );

        // — Update meta ——————————————————————————————————————————————
        update_user_meta( $user_id, 'jb3_company',    $company );
        update_user_meta( $user_id, 'jb3_job_title',  $job_title );
        update_user_meta( $user_id, 'jb3_phone',      $phone );
        update_user_meta( $user_id, 'jb3_country',    $country );
        update_user_meta( $user_id, 'jb3_sector',     $sector );
        update_user_meta( $user_id, 'jb3_newsletter', $newsletter );
        update_user_meta( $user_id, 'jb3_interests',  $interests );

        do_action( 'jb3_after_profile_save', $user_id );

        return [ [], true ];
    }

    // -------------------------------------------------------------------------
    // Account deletion
    // -------------------------------------------------------------------------

    public static function handle_delete_account() {
        if ( ! is_user_logged_in() || empty( $_POST['jb3_delete_account'] ) ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['jb3_delete_nonce'] ?? '' ) ),
            'jb3_delete_account_' . $user_id
        ) ) {
            return;
        }

        if ( ! jb3_is_member( $user_id ) ) {
            return;
        }

        // Eliminar de WordPress — el hook delete_user archiva automáticamente en Mailchimp.
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );

        wp_redirect( home_url( '/' ) );
        exit;
    }
}
