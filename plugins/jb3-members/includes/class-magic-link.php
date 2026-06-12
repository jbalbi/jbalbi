<?php
defined( 'ABSPATH' ) || exit;

class JB3_Magic_Link {

    const SESSION_DAYS  = 120;
    const TOKEN_META    = 'jb3_magic_token';
    const EXPIRY_META   = 'jb3_magic_token_expiry';
    const SESSION_META  = 'jb3_session_expiry';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'handle_magic_link' ], 5 );
        add_filter( 'auth_cookie_expiration', [ __CLASS__, 'extend_cookie_expiration' ], 10, 3 );
    }

    // ─── Token lifecycle ──────────────────────────────────────────────────────

    /**
     * Generate a one-time token valid for SESSION_DAYS and store it in user meta.
     */
    public static function generate_token( $user_id ) {
        $token  = wp_generate_password( 64, false );
        $expiry = time() + ( self::SESSION_DAYS * DAY_IN_SECONDS );

        update_user_meta( $user_id, self::TOKEN_META,  $token );
        update_user_meta( $user_id, self::EXPIRY_META, $expiry );

        return $token;
    }

    /**
     * Generate a fully-qualified magic-link URL for the user.
     * Points to the configured login page (or site root as fallback).
     */
    public static function generate_url( $user_id ) {
        $token   = self::generate_token( $user_id );
        $page_id = (int) get_option( 'jb3_login_page_id' );
        $base    = ( $page_id ? get_permalink( $page_id ) : '' ) ?: home_url( '/' );

        return add_query_arg( [ 'jb3_magic' => $token ], $base );
    }

    // ─── Login handler ────────────────────────────────────────────────────────

    /**
     * Intercepts ?jb3_magic=TOKEN early in the request cycle,
     * validates the token, logs the user in, then redirects.
     */
    public static function handle_magic_link() {
        if ( empty( $_GET['jb3_magic'] ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['jb3_magic'] ) );
        $user  = self::validate_token( $token );

        if ( ! $user ) {
            $page_id = (int) get_option( 'jb3_login_page_id' );
            $base    = $page_id ? get_permalink( $page_id ) : home_url( '/' );
            wp_redirect( add_query_arg( 'jb3_error', 'invalid_link', $base ) );
            exit;
        }

        // One-time use: delete token immediately after validation.
        delete_user_meta( $user->ID, self::TOKEN_META );
        delete_user_meta( $user->ID, self::EXPIRY_META );

        // Log the user in (remember=true → uses our extended expiration via filter).
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );
        do_action( 'wp_login', $user->user_login, $user );

        // Store server-side session expiry for the guard check.
        update_user_meta( $user->ID, self::SESSION_META, time() + ( self::SESSION_DAYS * DAY_IN_SECONDS ) );

        $dashboard_id = (int) get_option( 'jb3_dashboard_page_id' );
        $redirect     = $dashboard_id ? get_permalink( $dashboard_id ) : home_url( '/' );

        wp_redirect( esc_url_raw( $redirect ) );
        exit;
    }

    // ─── Token validation ─────────────────────────────────────────────────────

    public static function validate_token( $token ) {
        global $wpdb;

        if ( empty( $token ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                self::TOKEN_META,
                $token
            )
        );

        if ( empty( $user_ids ) ) {
            return false;
        }

        $user_id = (int) $user_ids[0];
        $expiry  = (int) get_user_meta( $user_id, self::EXPIRY_META, true );

        if ( time() > $expiry ) {
            delete_user_meta( $user_id, self::TOKEN_META );
            delete_user_meta( $user_id, self::EXPIRY_META );
            return false;
        }

        // Only approved members (or admins) can use magic links.
        if ( ! jb3_is_approved( $user_id ) && ! user_can( $user_id, 'manage_options' ) ) {
            return false;
        }

        return get_userdata( $user_id );
    }

    // ─── Cookie expiration ────────────────────────────────────────────────────

    /**
     * Extend "remember me" cookies to 120 days for jb3_member users.
     */
    public static function extend_cookie_expiration( $expiration, $user_id, $remember ) {
        if ( $remember && ( jb3_is_member( $user_id ) || user_can( $user_id, 'manage_options' ) ) ) {
            return self::SESSION_DAYS * DAY_IN_SECONDS;
        }
        return $expiration;
    }

    // ─── Force logout ─────────────────────────────────────────────────────────

    /**
     * Destroy all sessions for a user so they must obtain a fresh magic link.
     */
    public static function force_logout( $user_id ) {
        $sessions = WP_Session_Tokens::get_instance( $user_id );
        $sessions->destroy_all();

        delete_user_meta( $user_id, self::TOKEN_META );
        delete_user_meta( $user_id, self::EXPIRY_META );
        delete_user_meta( $user_id, self::SESSION_META );
    }
}
