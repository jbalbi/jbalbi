<?php
defined( 'ABSPATH' ) || exit;

class JB3_Auth_Guard {

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'check_session' ], 1 );
        add_action( 'template_redirect', [ __CLASS__, 'enforce_complete_profile' ], 5 );
        add_action( 'admin_init',        [ __CLASS__, 'block_admin_access' ] );
    }

    // ─── Block wp-admin for non-administrators ────────────────────────────────

    /**
     * Redirect any non-admin user who reaches wp-admin back to the frontend.
     * AJAX requests (admin-ajax.php) are exempted — many frontend features use them.
     */
    public static function block_admin_access() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }
        // Block only jb3_members — editors, authors, and other WP roles pass through normally.
        if ( jb3_is_member( $user_id ) && ! current_user_can( 'manage_options' ) ) {
            $dashboard = (int) get_option( 'jb3_dashboard_page_id' );
            $url       = $dashboard ? get_permalink( $dashboard ) : home_url( '/' );
            wp_redirect( esc_url_raw( $url ) );
            exit;
        }
    }

    // ─── Session expiry check ─────────────────────────────────────────────────

    /**
     * Log out users whose 120-day session has elapsed server-side.
     * (Belt-and-suspenders alongside the auth cookie expiration filter.)
     */
    public static function check_session() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! jb3_is_member( $user_id ) && ! user_can( $user_id, 'manage_options' ) ) {
            return;
        }

        $expiry = (int) get_user_meta( $user_id, JB3_Magic_Link::SESSION_META, true );
        if ( $expiry && time() > $expiry ) {
            JB3_Magic_Link::force_logout( $user_id );
            wp_logout();

            $login_page = (int) get_option( 'jb3_login_page_id' );
            $url        = $login_page ? get_permalink( $login_page ) : home_url( '/' );
            wp_redirect( add_query_arg( 'jb3_error', 'session_expired', esc_url_raw( $url ) ) );
            exit;
        }
    }

    // ─── Incomplete profile redirect ──────────────────────────────────────────

    /**
     * When a logged-in member has not completed all required fields,
     * redirect them to the profile update page unless they are already there.
     */
    public static function enforce_complete_profile() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        // Only enforce for users tracked by this plugin.
        if ( ! jb3_is_member( $user_id ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $profile_page_id = (int) get_option( 'jb3_profile_page_id' );
        if ( ! $profile_page_id ) {
            return;
        }

        // Allow the profile page itself and the login page through.
        $login_page_id = (int) get_option( 'jb3_login_page_id' );
        $current_id    = (int) get_queried_object_id();
        if ( $current_id === $profile_page_id || $current_id === $login_page_id ) {
            return;
        }

        if ( ! jb3_profile_complete( $user_id ) ) {
            $current_url  = esc_url_raw( ( is_ssl() ? 'https' : 'http' ) . '://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ) );
            $redirect_url = add_query_arg( 'redirect_to', rawurlencode( $current_url ), get_permalink( $profile_page_id ) );
            wp_redirect( esc_url_raw( $redirect_url ) );
            exit;
        }
    }
}
