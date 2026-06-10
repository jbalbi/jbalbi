<?php
defined( 'ABSPATH' ) || exit;

class JB3_Auth_Guard {

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'check_session' ], 1 );
        add_action( 'template_redirect', [ __CLASS__, 'enforce_complete_profile' ], 5 );
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
        if ( ! jb3_is_member( $user_id ) ) {
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
        if ( ! jb3_is_member( $user_id ) ) {
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
            wp_redirect( esc_url_raw( get_permalink( $profile_page_id ) ) );
            exit;
        }
    }
}
