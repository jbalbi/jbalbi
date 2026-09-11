<?php
defined( 'ABSPATH' ) || exit;

class JB3_Profile_Update {

    public static function init() {
        add_shortcode( 'jb3_profile', [ __CLASS__, 'shortcode' ] );
        add_shortcode( 'jb3_confirmar_baja', [ __CLASS__, 'shortcode_confirm_delete' ] );
        add_action( 'init', [ __CLASS__, 'handle_delete_account_request' ], 5 );
        add_action( 'init', [ __CLASS__, 'handle_confirm_delete_submit' ], 5 );
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

        $user_id          = get_current_user_id();
        $errors           = [];
        $success          = false;
        $redirect_to      = '';
        $delete_requested = isset( $_GET['baja_solicitada'] );

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
    // Account deletion — confirmación por email
    // -------------------------------------------------------------------------
    //
    // Pedido explícito 2026-09-09: un solo clic en el perfil (con un confirm()
    // del navegador de por medio) NO prueba que sea el dueño real de la cuenta
    // el que está operando esa sesión — alguien que se queda con la sesión
    // abierta (PC compartida, sesión olvidada) podía borrarla sin ser el
    // dueño. El botón del perfil ahora solo PIDE la baja (manda un mail); el
    // borrado real ocurre recién cuando se hace clic en el link de ESE mail,
    // que solo puede abrir quien tenga acceso a esa casilla — mismo criterio
    // de seguridad que ya usa JB3_Magic_Link para el login, con su propio
    // token independiente (no se reutiliza el de login: expira mucho antes,
    // 24hs en vez de 120 días, porque acá la ventana de uso normal es "lo
    // reviso en el momento", no "vuelvo en unos meses").

    const DELETE_TOKEN_META        = 'jb3_delete_token';
    const DELETE_TOKEN_EXPIRY_META = 'jb3_delete_token_expiry';
    const DELETE_TOKEN_VALID_HOURS = 24;

    /**
     * Paso 1: el usuario pide la baja desde su perfil. No borra nada todavía
     * — genera el token y le manda el mail de confirmación.
     */
    public static function handle_delete_account_request() {
        if ( ! is_user_logged_in() || empty( $_POST['jb3_request_delete_account'] ) ) {
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

        JB3_Email::send_delete_confirmation( $user_id );

        $profile_id = (int) get_option( 'jb3_profile_page_id' );
        $back       = ( $profile_id ? get_permalink( $profile_id ) : '' ) ?: home_url( '/' );
        wp_redirect( add_query_arg( 'baja_solicitada', '1', $back ) );
        exit;
    }

    /**
     * Paso 2a (GET — clic en el link del mail, o vuelta desde el POST):
     * shortcode [jb3_confirmar_baja], pensado para vivir en una página real
     * del sitio (con header/footer/estilos del theme) — configurable en
     * Configuración → "Página de Confirmar Baja de Cuenta".
     *
     * Solo LEE, nunca borra nada acá — a propósito: un GET no debe tener
     * efectos secundarios. Los escáneres de seguridad de email (Safe Links
     * de Microsoft, Mimecast, Proofpoint, etc.) VISITAN AUTOMÁTICAMENTE
     * cualquier link de un mail para revisarlo antes de que la persona real
     * lo abra, muchas veces simulando un navegador real a propósito para no
     * ser detectados como bot. Si el GET borrara directo, cualquiera de esos
     * escáneres podría eliminar la cuenta sin que el dueño hiciera nada. El
     * botón de este shortcode manda el POST real (ver
     * handle_confirm_delete_submit()) — eso sí requiere una persona haciendo
     * clic, ningún escáner "aprieta botones".
     *
     * No exige sesión activa a propósito: el link puede abrirse desde
     * cualquier dispositivo/navegador (la identidad la da el token, no la
     * sesión), y después de confirmar la baja el usuario queda deslogueado
     * — tiene que poder seguir viendo esta misma página como visitante
     * anónimo normal, sin que ningún guard de "sesión requerida" la bloquee.
     */
    public static function shortcode_confirm_delete() {
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return '';
        }

        if ( isset( $_GET['resultado_baja'] ) ) {
            $result = sanitize_text_field( wp_unslash( $_GET['resultado_baja'] ) );
            if ( 'done' === $result ) {
                return '<div class="jb3-confirmar-baja"><div class="jb3-notice jb3-success">' . esc_html__( 'Tu cuenta fue eliminada correctamente. Gracias por haber sido parte.', 'jb3-members' ) . '</div></div>';
            }
            return '<div class="jb3-confirmar-baja"><div class="jb3-notice jb3-error">' . esc_html__( 'Este enlace de confirmación ya no es válido — puede que ya se haya usado, que haya pasado más de 24hs, o que se haya pedido una baja más nueva. Si todavía querés eliminar tu cuenta, volvé a tu perfil y solicitalo de nuevo.', 'jb3-members' ) . '</div></div>';
        }

        $token = isset( $_GET['confirmar_baja'] ) ? sanitize_text_field( wp_unslash( $_GET['confirmar_baja'] ) ) : '';

        if ( '' === $token || ! self::validate_delete_token( $token ) ) {
            return '<div class="jb3-confirmar-baja"><div class="jb3-notice jb3-error">' . esc_html__( 'Este enlace de confirmación no es válido o ya venció. Si todavía querés eliminar tu cuenta, volvé a tu perfil y solicitalo de nuevo.', 'jb3-members' ) . '</div></div>';
        }

        ob_start();
        ?>
        <div class="jb3-confirmar-baja">
            <div class="jb3-notice jb3-warning">
                <?php esc_html_e( 'Esta acción no se puede deshacer: se van a borrar todos tus datos del sitio y vas a ser dado de baja de nuestras listas de correo.', 'jb3-members' ); ?>
            </div>
            <form method="post" class="jb3-form">
                <!-- Campo oculto, NO el name/value del botón — ver la nota en
                     update-form.php sobre por qué jb3-forms.js deshabilita el
                     botón de submit apenas se dispara 'submit' y eso lo excluye
                     del envío si la marca vive en su name/value. -->
                <input type="hidden" name="confirmar_baja_token" value="<?php echo esc_attr( $token ); ?>">
                <input type="hidden" name="confirmar_baja_submit" value="1">
                <button type="submit" class="jb3-btn" style="background:#c0392b;">
                    <?php esc_html_e( 'Sí, eliminar mi cuenta definitivamente', 'jb3-members' ); ?>
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Paso 2b (POST — clic en el botón del shortcode anterior): acá SÍ se
     * borra. No depende de haber una sesión activa (puede confirmarse desde
     * cualquier dispositivo/navegador, igual que un magic link de login) —
     * la identidad la da el token, no la sesión de quien hace clic.
     */
    public static function handle_confirm_delete_submit() {
        if ( empty( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['confirmar_baja_submit'] ) ) {
            return;
        }

        $confirm_page_id = (int) get_option( 'jb3_delete_confirm_page_id' );
        $back            = ( $confirm_page_id ? get_permalink( $confirm_page_id ) : '' ) ?: home_url( '/' );

        $token   = sanitize_text_field( wp_unslash( $_POST['confirmar_baja_token'] ?? '' ) );
        $user_id = self::validate_delete_token( $token );

        if ( ! $user_id ) {
            wp_redirect( add_query_arg( 'resultado_baja', 'invalid', $back ) );
            exit;
        }

        // Un solo uso — se borra apenas se valida, antes de tocar nada más.
        delete_user_meta( $user_id, self::DELETE_TOKEN_META );
        delete_user_meta( $user_id, self::DELETE_TOKEN_EXPIRY_META );

        if ( ! jb3_is_member( $user_id ) ) {
            // Ya no es miembro (rol cambiado entre el pedido y la confirmación)
            // — no hay nada que borrar por esta vía.
            wp_redirect( $back );
            exit;
        }

        // Eliminar de WordPress — el hook delete_user archiva automáticamente en Mailchimp.
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );
        wp_clear_auth_cookie(); // el usuario sigue navegando, pero ya como anónimo.

        wp_redirect( add_query_arg( 'resultado_baja', 'done', $back ) );
        exit;
    }

    /** Genera el token de borrado y devuelve la URL completa del mail — llamado por JB3_Email::send_delete_confirmation(). */
    public static function generate_delete_confirm_url( $user_id ) {
        $token  = wp_generate_password( 64, false );
        $expiry = time() + ( self::DELETE_TOKEN_VALID_HOURS * HOUR_IN_SECONDS );

        update_user_meta( $user_id, self::DELETE_TOKEN_META,        $token );
        update_user_meta( $user_id, self::DELETE_TOKEN_EXPIRY_META, $expiry );

        $page_id = (int) get_option( 'jb3_delete_confirm_page_id' );
        $base    = ( $page_id ? get_permalink( $page_id ) : '' ) ?: home_url( '/' );

        return add_query_arg( 'confirmar_baja', $token, $base );
    }

    /** @return int|false El user_id si el token es válido y no venció; false en cualquier otro caso. */
    private static function validate_delete_token( $token ) {
        global $wpdb;

        if ( empty( $token ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                self::DELETE_TOKEN_META,
                $token
            )
        );

        if ( empty( $user_ids ) ) {
            return false;
        }

        $user_id = (int) $user_ids[0];
        $expiry  = (int) get_user_meta( $user_id, self::DELETE_TOKEN_EXPIRY_META, true );

        if ( time() > $expiry ) {
            delete_user_meta( $user_id, self::DELETE_TOKEN_META );
            delete_user_meta( $user_id, self::DELETE_TOKEN_EXPIRY_META );
            return false;
        }

        return $user_id;
    }

}
