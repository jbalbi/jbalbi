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
     * Manda el link de confirmación de baja de cuenta — pedido explícito
     * 2026-09-09, ver el comentario en JB3_Profile_Update sobre por qué el
     * botón del perfil ya no borra directo.
     */
    public static function send_delete_confirmation( $user_id ) {
        $user        = get_userdata( $user_id );
        $confirm_url = JB3_Profile_Update::generate_delete_confirm_url( $user_id );

        $subject = sprintf(
            /* translators: %s: site name */
            __( '[JB3] Confirmá la eliminación de tu cuenta en %s', 'jb3-members' ),
            get_bloginfo( 'name' )
        );

        $body = self::render( 'email-delete-confirmation', [
            'user'        => $user,
            'confirm_url' => $confirm_url,
        ] );

        wp_mail( $user->user_email, $subject, $body, self::headers() );
    }

    /**
     * Send a fresh magic-link to a user who requests one from the login form.
     *
     * @param int    $user_id
     * @param string $redirect_to Optional page to return the user to after login.
     */
    public static function send_magic_link( $user_id, $redirect_to = '' ) {
        $user      = get_userdata( $user_id );
        $magic_url = JB3_Magic_Link::generate_url( $user_id, $redirect_to );

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

    /**
     * Notify the representatives of the DOWNLOADING USER's country that
     * someone downloaded an Op. Comercial — so the right regional rep can
     * follow up directly. Mismo criterio que send_approval_request(): país
     * del usuario, no del contenido.
     */
    public static function send_op_comercial_descargado( array $reps, $user_id, $post_id ) {
        if ( ! $reps ) {
            return;
        }

        $user = get_userdata( $user_id );

        $subject = sprintf(
            /* translators: 1: user display name, 2: Op. Comercial title */
            __( '[JB3] %1$s descargó "%2$s"', 'jb3-members' ),
            $user->display_name,
            get_the_title( $post_id )
        );

        $body = self::render( 'email-op-comercial-descargado', [
            'user'    => $user,
            'user_id' => $user_id,
            'post_id' => $post_id,
        ] );

        foreach ( $reps as $rep ) {
            wp_mail( $rep->email, $subject, $body, self::headers() );
        }
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * Remitente configurable (Configuración → Email) — necesario porque el
     * admin_email de WordPress suele ser una casilla personal (Gmail/Outlook)
     * sin SPF/DKIM propio; Gmail en particular rechaza mails que digan venir
     * de "@gmail.com" pero no hayan salido de los servidores de Google. Cae
     * a admin_email/nombre del sitio solo si todavía no se cargó nada en
     * Configuración, para no romper en un sitio recién instalado.
     */
    private static function headers() {
        $from_email = get_option( 'jb3_email_from' ) ?: get_option( 'admin_email' );
        $from_name  = get_option( 'jb3_email_from_name' ) ?: get_bloginfo( 'name' );

        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
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
