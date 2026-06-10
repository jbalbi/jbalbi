<?php
defined( 'ABSPATH' ) || exit;

class JB3_Email {

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Send approval-request email to all representatives of the user's country.
     */
    public static function send_approval_request( $user_id ) {
        $user    = get_userdata( $user_id );
        $country = get_user_meta( $user_id, 'jb3_country', true );
        $reps    = JB3_Representatives::get_by_country( $country );
        $token   = get_user_meta( $user_id, 'jb3_approval_token', true );

        $approve_url = add_query_arg(
            [
                'jb3_approve' => '1',
                'uid'             => $user_id,
                'token'           => $token,
            ],
            home_url( '/' )
        );

        $subject = sprintf(
            /* translators: %s: user display name */
            __( '[JB3] Nuevo registro pendiente: %s', 'jb3-members' ),
            $user->display_name
        );

        $body = self::render( 'email-approval-request', [
            'user'        => $user,
            'user_id'     => $user_id,
            'approve_url' => $approve_url,
        ] );

        foreach ( $reps as $rep ) {
            wp_mail( $rep->email, $subject, $body, self::headers() );
        }
    }

    /**
     * Notify the user they have been approved and include a magic-link to log in.
     * Guards against double-sending: only sends when the flag transitions to true.
     */
    public static function send_user_approved( $user_id ) {
        $user      = get_userdata( $user_id );
        $magic_url = JB3_Magic_Link::generate_url( $user_id );

        $subject = sprintf(
            /* translators: %s: site name */
            __( '[JB3] Tu acceso a %s ha sido aprobado', 'jb3-members' ),
            get_bloginfo( 'name' )
        );

        $body = self::render( 'email-user-approved', [
            'user'      => $user,
            'magic_url' => $magic_url,
        ] );

        wp_mail( $user->user_email, $subject, $body, self::headers() );
    }

    /**
     * Send a fresh magic-link to a user who requests one from the login form.
     */
    public static function send_magic_link( $user_id ) {
        $user      = get_userdata( $user_id );
        $magic_url = JB3_Magic_Link::generate_url( $user_id );

        $subject = sprintf(
            /* translators: %s: site name */
            __( '[JB3] Tu enlace de acceso a %s', 'jb3-members' ),
            get_bloginfo( 'name' )
        );

        $body = self::render( 'email-magic-link', [
            'user'      => $user,
            'magic_url' => $magic_url,
        ] );

        wp_mail( $user->user_email, $subject, $body, self::headers() );
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    private static function headers() {
        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];
    }

    /**
     * Render a PHP email template from /templates/ folder.
     * Variables are extracted into the template scope.
     */
    private static function render( $template, array $vars = [] ) {
        $file = JB3_DIR . 'templates/' . $template . '.php';
        if ( ! file_exists( $file ) ) {
            return '';
        }
        // phpcs:ignore WordPress.PHP.DontExtract
        extract( $vars, EXTR_SKIP );
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
