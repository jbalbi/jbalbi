<?php
defined( 'ABSPATH' ) || exit;

class JB3_Registration {

    public static function init() {
        add_shortcode( 'jb3_register', [ __CLASS__, 'shortcode' ] );
        add_action( 'init', [ __CLASS__, 'handle_approval_request' ], 5 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
    }

    // ─── Asset loading ────────────────────────────────────────────────────────

    public static function maybe_enqueue() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }
        if ( has_shortcode( $post->post_content, 'jb3_register' )
            || has_shortcode( $post->post_content, 'jb3_profile' )
            || has_shortcode( $post->post_content, 'jb3_login' ) ) {
            wp_enqueue_style(  'jb3-forms', JB3_URL . 'public/css/jb3-forms.css', [], JB3_VERSION );
            wp_enqueue_script( 'jb3-forms', JB3_URL . 'public/js/jb3-forms.js', [ 'jquery' ], JB3_VERSION, true );
        }
    }

    // ─── Shortcode ────────────────────────────────────────────────────────────

    public static function shortcode() {
        if ( is_user_logged_in() ) {
            return '<p class="jb3-notice jb3-info">'
                . esc_html__( 'Ya estás registrado.', 'jb3-members' )
                . '</p>';
        }

        $errors  = [];
        $success = false;

        if ( isset( $_POST['jb3_register_nonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jb3_register_nonce'] ) ), 'jb3_register' ) ) {
            [ $errors, $success ] = self::process();
        }

        ob_start();
        include JB3_DIR . 'public/views/registration-form.php';
        return ob_get_clean();
    }

    // ─── Form processing ──────────────────────────────────────────────────────

    private static function process() {
        $errors = [];

        // — Sanitise ——————————————————————————————————————————————
        $first_name  = sanitize_text_field( wp_unslash( $_POST['first_name']  ?? '' ) );
        $last_name   = sanitize_text_field( wp_unslash( $_POST['last_name']   ?? '' ) );
        $email       = sanitize_email(      wp_unslash( $_POST['email']       ?? '' ) );
        $company     = sanitize_text_field( wp_unslash( $_POST['company']     ?? '' ) );
        $job_title   = sanitize_text_field( wp_unslash( $_POST['job_title']   ?? '' ) );
        $phone       = jb3_sanitize_phone( wp_unslash( $_POST['phone']    ?? '' ) );
        $country     = strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ) );
        $sector      = sanitize_text_field( wp_unslash( $_POST['sector']      ?? '' ) );
        $newsletter  = ! empty( $_POST['newsletter'] ) ? 1 : 0;
        $raw         = isset( $_POST['interests'] ) && is_array( $_POST['interests'] )
                       ? $_POST['interests'] : [];
        $interests   = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw ) );

        // — Validate ——————————————————————————————————————————————
        if ( '' === $first_name ) {
            $errors[] = __( 'El nombre es obligatorio.', 'jb3-members' );
        }
        if ( '' === $last_name ) {
            $errors[] = __( 'El apellido es obligatorio.', 'jb3-members' );
        }
        if ( ! is_email( $email ) ) {
            $errors[] = __( 'El email no es válido.', 'jb3-members' );
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

        // Filter interests to only allowed keys.
        $valid_keys = array_keys( jb3_interests() );
        $interests  = array_values( array_filter( $interests, fn( $i ) => in_array( $i, $valid_keys, true ) ) );

        if ( email_exists( $email ) ) {
            $errors[] = __( 'Este email ya está registrado.', 'jb3-members' );
        }

        if ( ! empty( $errors ) ) {
            return [ $errors, false ];
        }

        // — Create user ——————————————————————————————————————————————
        $user_id = wp_insert_user( [
            'user_login'   => 'jb3_tmp_' . wp_generate_password( 8, false ),
            'user_email'   => $email,
            'user_pass'    => wp_generate_password( 24 ),
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
            'role'         => 'jb3_member',
        ] );

        if ( is_wp_error( $user_id ) ) {
            $errors[] = $user_id->get_error_message();
            return [ $errors, false ];
        }

        // Use the WordPress-assigned ID as the username.
        global $wpdb;
        $wpdb->update( $wpdb->users, [ 'user_login' => (string) $user_id ], [ 'ID' => $user_id ] );
        clean_user_cache( $user_id );
        update_user_meta( $user_id, 'nickname', (string) $user_id );

        // — Save meta ——————————————————————————————————————————————
        update_user_meta( $user_id, 'jb3_company',       $company );
        update_user_meta( $user_id, 'jb3_job_title',     $job_title );
        update_user_meta( $user_id, 'jb3_phone',         $phone );
        update_user_meta( $user_id, 'jb3_country',       $country );
        update_user_meta( $user_id, 'jb3_sector',        $sector );
        update_user_meta( $user_id, 'jb3_newsletter',    $newsletter );
        update_user_meta( $user_id, 'jb3_interests',     $interests );
        update_user_meta( $user_id, 'jb3_status',        'pending' );
        update_user_meta( $user_id, 'jb3_registered_at', current_time( 'mysql' ) );

        // — Approval token ——————————————————————————————————————————
        $token = wp_generate_password( 48, false );
        update_user_meta( $user_id, 'jb3_approval_token', $token );

        // — Notify representatives ——————————————————————————————————
        JB3_Email::send_approval_request( $user_id );

        return [ [], true ];
    }

    // ─── Representative approval handler ─────────────────────────────────────

    /**
     * Handles ?jb3_approve=1&uid=X&token=Y links sent to representatives.
     */
    public static function handle_approval_request() {
        if ( empty( $_GET['jb3_approve'] ) ) {
            return;
        }

        $user_id = absint( $_GET['uid'] ?? 0 );
        $token   = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );

        if ( ! $user_id || ! $token ) {
            wp_die(
                esc_html__( 'Enlace de aprobación inválido.', 'jb3-members' ),
                esc_html__( 'Error', 'jb3-members' ),
                [ 'response' => 400 ]
            );
        }

        $stored = get_user_meta( $user_id, 'jb3_approval_token', true );

        if ( ! $stored || ! hash_equals( $stored, $token ) ) {
            wp_die(
                esc_html__( 'Token de aprobación inválido o expirado.', 'jb3-members' ),
                esc_html__( 'Error', 'jb3-members' ),
                [ 'response' => 403 ]
            );
        }

        // Guard against double-approval (multiple reps receiving the same email).
        if ( jb3_is_approved( $user_id ) ) {
            wp_die(
                self::approval_page(
                    '#f57f17',
                    __( 'Usuario ya aprobado', 'jb3-members' ),
                    __( 'Este usuario ya fue aprobado anteriormente por otro representante.', 'jb3-members' )
                ),
                esc_html__( 'Ya aprobado', 'jb3-members' ),
                [ 'response' => 200 ]
            );
        }

        // — Approve ——————————————————————————————————————————————
        update_user_meta( $user_id, 'jb3_status',      'approved' );
        update_user_meta( $user_id, 'jb3_approved_at', current_time( 'mysql' ) );
        delete_user_meta( $user_id, 'jb3_approval_token' );

        // Send the user their magic-link welcome email.
        JB3_Email::send_user_approved( $user_id );
        do_action( 'jb3_sync_to_mailchimp', $user_id );

        $user = get_userdata( $user_id );
        wp_die(
            self::approval_page(
                '#2e7d32',
                __( 'Usuario aprobado', 'jb3-members' ),
                sprintf(
                    /* translators: %s: user display name */
                    __( '<strong>%s</strong> ha sido aprobado. Se le envió un correo con su enlace de acceso.', 'jb3-members' ),
                    esc_html( $user->display_name )
                )
            ),
            esc_html__( 'Usuario Aprobado', 'jb3-members' ),
            [ 'response' => 200 ]
        );
    }

    // ─── Inline HTML for approval result page ────────────────────────────────

    private static function approval_page( $color, $heading, $message ) {
        return '<div style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:40px;'
            . 'background:#fafafa;border-radius:10px;border-top:4px solid ' . esc_attr( $color ) . ';text-align:center;">'
            . '<h2 style="color:' . esc_attr( $color ) . ';">' . esc_html( $heading ) . '</h2>'
            . '<p style="font-size:1.1em;color:#333;">' . wp_kses( $message, [ 'strong' => [] ] ) . '</p>'
            . '</div>';
    }
}
