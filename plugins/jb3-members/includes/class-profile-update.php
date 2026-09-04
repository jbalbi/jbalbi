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

        $user_id     = get_current_user_id();
        $errors      = [];
        $success     = false;
        $redirect_to = '';

        // Capture redirect_to from GET (set by enforce_complete_profile).
        if ( ! empty( $_GET['redirect_to'] ) ) {
            $redirect_to = wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), '' );
        }

        if ( isset( $_POST['jb3_profile_nonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jb3_profile_nonce'] ) ), 'jb3_profile_' . $user_id ) ) {
            [ $errors, $success ] = self::process( $user_id );

            if ( $success && ! empty( $_POST['jb3_redirect_to'] ) ) {
                $back = wp_validate_redirect( wp_unslash( $_POST['jb3_redirect_to'] ), '' );
                if ( $back ) {
                    wp_redirect( esc_url_raw( $back ) );
                    exit;
                }
            }
        }

        $user = get_userdata( $user_id );
        ob_start();
        include JB3_DIR . 'public/views/update-form.php';
        return ob_get_clean();
    }

    // ─── Form processing ──────────────────────────────────────────────────────

    private static function process( $user_id ) {
        // — Sanitise + validate (campos compartidos con el registro, ver
        // helpers.php) ————————————————————————————————————————————
        [ $data, $errors ] = jb3_sanitize_and_validate_profile_fields();
        [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'company'    => $company,
            'job_title'  => $job_title,
            'phone'      => $phone,
            'country'    => $country,
            'sector'     => $sector,
            'newsletter' => $newsletter,
            'interests'  => $interests,
        ] = $data;

        if ( ! empty( $errors ) ) {
            return [ $errors, false ];
        }

        // Valor viejo ANTES de pisarlo — hace falta para saber si esto es una
        // transición real de suscripción (recién se tilda/destilda el
        // checkbox) o el usuario simplemente reguardó su perfil sin tocarlo.
        $old_newsletter = (bool) get_user_meta( $user_id, 'jb3_newsletter', true );

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

        // Solo en la transición real (ver comentario de jb3_sync_newsletter_countries,
        // helpers.php) — si el usuario ya estaba suscripto y solo cambió el
        // teléfono, esto no toca su selección de países.
        if ( (bool) $newsletter !== $old_newsletter ) {
            jb3_sync_newsletter_countries( $user_id, (bool) $newsletter );
        }

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
