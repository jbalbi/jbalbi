<?php
defined( 'ABSPATH' ) || exit;

class JB3_Login {

    public static function init() {
        add_shortcode( 'jb3_login',  [ __CLASS__, 'shortcode' ] );
        add_shortcode( 'jb3_logout', [ __CLASS__, 'logout_shortcode' ] );
        add_action( 'wp_logout',         [ __CLASS__, 'on_wp_logout' ] );
        // Priority 1 — runs before enforce_complete_profile (priority 5).
        add_action( 'template_redirect', [ __CLASS__, 'handle_logout_page' ], 1 );
        // Block password-based login for jb3_members — magic link is the only allowed method.
        add_filter( 'authenticate', [ __CLASS__, 'block_password_login' ], 30, 3 );
    }

    // ─── Block native WP password login for jb3_members ─────────────────────

    public static function block_password_login( $user, $username, $password ) {
        if ( ! is_a( $user, 'WP_User' ) ) {
            return $user;
        }
        if ( jb3_is_member( $user->ID ) && ! user_can( $user->ID, 'manage_options' ) ) {
            return new WP_Error(
                'jb3_magic_link_only',
                __( 'Para acceder a tu cuenta usá el enlace de acceso enviado a tu email.', 'jb3-members' )
            );
        }
        return $user;
    }

    // ─── Shortcode ────────────────────────────────────────────────────────────

    public static function shortcode() {
        // Never redirect during REST API requests (Gutenberg save/preview).
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return '';
        }

        // Carries the page the user was trying to reach (set by the private-content
        // gate, or resubmitted via the form's hidden field) through the whole
        // request-magic-link round trip. Re-validated on every read since it's
        // attacker-influenced input (query string / POST body).
        $redirect_to = wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ?? '' ), '' );

        if ( is_user_logged_in() ) {
            $user_id   = get_current_user_id();
            $dashboard = (int) get_option( 'jb3_dashboard_page_id' );
            if ( $redirect_to ) {
                wp_redirect( esc_url_raw( $redirect_to ) );
                exit;
            }
            if ( $dashboard && jb3_is_member( $user_id ) ) {
                wp_redirect( esc_url_raw( get_permalink( $dashboard ) ) );
                exit;
            }
            return '<p class="jb3-notice jb3-info">'
                . esc_html__( 'Ya estás logueado.', 'jb3-members' )
                . '</p>';
        }

        $sent   = false;
        $errors = [];

        // Error passed back from magic-link handler via query string.
        if ( ! empty( $_GET['jb3_error'] ) ) {
            $code = sanitize_text_field( wp_unslash( $_GET['jb3_error'] ) );
            if ( 'invalid_link' === $code ) {
                $errors[] = __( 'El enlace de acceso no es válido o ha expirado. Por favor solicitá uno nuevo.', 'jb3-members' );
            }
        }

        if ( isset( $_POST['jb3_login_nonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jb3_login_nonce'] ) ), 'jb3_login' ) ) {
            [ $errors, $sent ] = self::process( $redirect_to );
        }

        ob_start();
        include JB3_DIR . 'public/views/login-form.php';
        return ob_get_clean();
    }

    // ─── Logout page handler (template_redirect priority 1) ──────────────────

    public static function handle_logout_page() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }
        if ( ! has_shortcode( $post->post_content, 'jb3_logout' ) ) {
            return;
        }
        if ( is_user_logged_in() ) {
            wp_logout();
        }
        wp_redirect( home_url( '/' ) );
        exit;
    }

    // ─── Logout shortcode ────────────────────────────────────────────────────

    public static function logout_shortcode() {
        return '';
    }

    // ─── JB3 token cleanup on any WordPress logout ───────────────────────────

    public static function on_wp_logout( $user_id ) {
        if ( $user_id && ( jb3_is_member( $user_id ) || user_can( $user_id, 'manage_options' ) ) ) {
            JB3_Magic_Link::force_logout( $user_id );
        }
    }

    // ─── Form processing ──────────────────────────────────────────────────────

    private static function process( $redirect_to = '' ) {
        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        if ( ! is_email( $email ) ) {
            return [ [ __( 'Por favor ingresá un email válido.', 'jb3-members' ) ], false ];
        }

        // Rate limit: max 3 solicitudes por email cada 10 minutos — compartido
        // con la entrada automática vía Mailchimp (?e=), ver jb3_rate_limited().
        if ( jb3_rate_limited( $email ) ) {
            return [ [ __( 'Demasiadas solicitudes. Esperá unos minutos antes de intentar nuevamente.', 'jb3-members' ) ], false ];
        }

        $user = get_user_by( 'email', $email );

        // If user doesn't exist or isn't a jb3_member (and isn't an admin), show the same generic
        // success message to prevent email enumeration.
        $is_admin = $user && user_can( $user->ID, 'manage_options' );
        if ( ! $user || ( ! jb3_is_member( $user->ID ) && ! $is_admin ) ) {
            return [ [], true ];
        }

        // User exists but is still pending approval (admins bypass this check).
        if ( ! $is_admin && ! jb3_is_approved( $user->ID ) ) {
            return [
                [ __( 'Tu cuenta está pendiente de aprobación. Recibirás un correo cuando sea activada.', 'jb3-members' ) ],
                false,
            ];
        }

        // All good: generate and send magic link.
        JB3_Email::send_magic_link( $user->ID, $redirect_to );

        return [ [], true ];
    }
}
