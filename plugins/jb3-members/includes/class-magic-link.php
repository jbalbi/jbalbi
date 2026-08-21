<?php
defined( 'ABSPATH' ) || exit;

class JB3_Magic_Link {

    // Cuánto dura CLICKEABLE el link emailado antes de usarse — no tiene
    // relación con cuánto dura la sesión una vez que se usa (ver abajo).
    // Sin cambios respecto de antes.
    const TOKEN_VALID_DAYS = 120;

    // Sesión deslizante: se renueva sola en cada visita mientras el
    // usuario siga activo (INACTIVITY_DAYS sin entrar = vence sola), pero
    // nunca más allá de MAX_SESSION_DAYS desde el login real, aunque haya
    // estado activo todos los días — evita que una sesión (o una cookie
    // robada) quede viva indefinidamente. Recomendado y confirmado con el
    // usuario 2026-08-10: 6 meses de inactividad, techo de 12 meses.
    const INACTIVITY_DAYS = 180;
    const MAX_SESSION_DAYS = 365;

    // No renovar en CADA request (sería una escritura + reemisión de
    // cookie por cada página vista) — solo si ya pasó al menos este
    // margen desde la última renovación. Con esto, un usuario que entra
    // varias veces por día genera como mucho una escritura por día.
    const REFRESH_THRESHOLD = DAY_IN_SECONDS;

    const TOKEN_META          = 'jb3_magic_token';
    const EXPIRY_META         = 'jb3_magic_token_expiry';
    const SESSION_META        = 'jb3_session_expiry';   // Vencimiento deslizante (se actualiza en cada renovación).
    const SESSION_STARTED_META = 'jb3_session_started'; // Ancla fija del techo absoluto — se setea SOLO en el login real.

    public static function init() {
        add_action( 'init', [ __CLASS__, 'handle_magic_link' ], 5 );
        add_filter( 'auth_cookie_expiration', [ __CLASS__, 'extend_cookie_expiration' ], 10, 3 );
    }

    // ─── Token lifecycle ──────────────────────────────────────────────────────

    /**
     * Generate a one-time token valid for TOKEN_VALID_DAYS and store it in user meta.
     */
    public static function generate_token( $user_id ) {
        $token  = wp_generate_password( 64, false );
        $expiry = time() + ( self::TOKEN_VALID_DAYS * DAY_IN_SECONDS );

        update_user_meta( $user_id, self::TOKEN_META,  $token );
        update_user_meta( $user_id, self::EXPIRY_META, $expiry );

        return $token;
    }

    /**
     * Generate a fully-qualified magic-link URL for the user.
     * Points to the configured login page (or site root as fallback).
     *
     * @param int    $user_id
     * @param string $redirect_to Optional URL to return the user to after login
     *                            (e.g. the private page they tried to reach).
     *                            Validated with wp_validate_redirect() both here
     *                            AND again in handle_magic_link() — this URL
     *                            travels inside an emailed link, so it's
     *                            attacker-influenced input twice over.
     */
    public static function generate_url( $user_id, $redirect_to = '' ) {
        $token   = self::generate_token( $user_id );
        $page_id = (int) get_option( 'jb3_login_page_id' );
        $base    = ( $page_id ? get_permalink( $page_id ) : '' ) ?: home_url( '/' );

        $args = [ 'jb3_magic' => $token ];

        $redirect_to = wp_validate_redirect( $redirect_to, '' );
        if ( $redirect_to ) {
            // add_query_arg() already url-encodes its values — passing an
            // already-encoded string here would double-encode it.
            $args['redirect_to'] = $redirect_to;
        }

        return add_query_arg( $args, $base );
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

        // Un escáner de seguridad de correo (Safe Links, Mimecast, etc.) que
        // abre el link automáticamente para revisarlo ANTES de que la
        // persona real lo clickee gastaría el token de un solo uso — acá no
        // hay "reintentar", el usuario se queda afuera sin saber por qué.
        // Mismo criterio best-effort que ya usa el tracking de vistas/
        // descargas (jb3_es_posible_bot(), helpers.php): si el User-Agent
        // matchea una firma conocida, no seguimos — se ignora el parámetro
        // y la página de login se muestra normal, como si no tuviera token,
        // dejando el token intacto para cuando lo abra la persona real.
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        if ( function_exists( 'jb3_es_posible_bot' ) && jb3_es_posible_bot( $ua ) ) {
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

        // Arranca la sesión deslizante de cero: vencimiento por inactividad
        // Y el ancla del techo absoluto (SESSION_STARTED_META), los dos
        // solo se tocan acá, en un login real — nunca en una renovación.
        $now = time();
        update_user_meta( $user->ID, self::SESSION_META, $now + ( self::INACTIVITY_DAYS * DAY_IN_SECONDS ) );
        update_user_meta( $user->ID, self::SESSION_STARTED_META, $now );

        $dashboard_id = (int) get_option( 'jb3_dashboard_page_id' );
        $redirect     = $dashboard_id ? get_permalink( $dashboard_id ) : home_url( '/' );

        // redirect_to survived inside the emailed link (attacker-influenced
        // input) — validate again here, not just when the link was built.
        if ( ! empty( $_GET['redirect_to'] ) ) {
            $requested = wp_unslash( $_GET['redirect_to'] );
            $redirect  = wp_validate_redirect( $requested, $redirect );
        }

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
     * Extend "remember me" cookies para jb3_member/admin — misma ventana
     * que la sesión deslizante (INACTIVITY_DAYS); se recalcula sola cada
     * vez que se reemite la cookie (login real o renovación por actividad).
     */
    public static function extend_cookie_expiration( $expiration, $user_id, $remember ) {
        if ( $remember && ( jb3_is_member( $user_id ) || user_can( $user_id, 'manage_options' ) ) ) {
            return self::INACTIVITY_DAYS * DAY_IN_SECONDS;
        }
        return $expiration;
    }

    // ─── Sesión deslizante ────────────────────────────────────────────────────

    /**
     * Se llama en CADA request de un usuario logueado (JB3_Auth_Guard).
     * No hace nada la mayoría de las veces — throttle por REFRESH_THRESHOLD,
     * ver comentario de la constante. Cuando sí renueva: corre el
     * vencimiento por inactividad otros INACTIVITY_DAYS hacia adelante,
     * pero nunca más allá del techo absoluto (session_started + MAX_SESSION_DAYS),
     * y reemite la cookie del navegador para que coincida — sin reemitir
     * la cookie, el navegador la descartaría en su fecha original sin
     * importar lo que diga este meta del lado del servidor.
     */
    public static function maybe_refresh_session( int $user_id, int $current_expiry, int $session_started ): void {
        $now         = time();
        $full_window = self::INACTIVITY_DAYS * DAY_IN_SECONDS;
        $remaining   = $current_expiry - $now;

        // Todavía queda casi toda la ventana — se renovó hace poco, no hay nada que hacer.
        if ( $remaining > ( $full_window - self::REFRESH_THRESHOLD ) ) {
            return;
        }

        $new_expiry = $now + $full_window;
        $max_expiry = $session_started + ( self::MAX_SESSION_DAYS * DAY_IN_SECONDS );
        if ( $new_expiry > $max_expiry ) {
            $new_expiry = $max_expiry; // El check_session() del caller ya habrá cortado la sesión si $now superó esto.
        }

        update_user_meta( $user_id, self::SESSION_META, $new_expiry );
        wp_set_auth_cookie( $user_id, true );
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
        delete_user_meta( $user_id, self::SESSION_STARTED_META );
    }
}
