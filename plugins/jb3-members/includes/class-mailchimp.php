<?php
defined( 'ABSPATH' ) || exit;

class JB3_Mailchimp {

    // public: reutilizados por JB3_Mailchimp_Entry (validación por ?e=) para
    // no duplicar el parseo de server/list — mismo par de credenciales.
    public static function api_key() { return get_option( 'jb3_mc_api_key', '' ); }
    public static function list_id() { return get_option( 'jb3_mc_list_id', '' ); }
    public static function server() {
        $key = self::api_key();
        $pos = strrpos( $key, '-' );
        return $pos !== false ? substr( $key, $pos + 1 ) : 'us1';
    }

    // Construye ambos cachés en una sola llamada a la API.
    // Detecta automáticamente la categoría "Newsletters" por nombre.
    private static function build_all_caches() {
        $groups = self::fetch_interest_groups();
        if ( is_wp_error( $groups ) || empty( $groups ) ) {
            return;
        }
        $general = [];
        $nl      = [];
        foreach ( $groups as $cat ) {
            $is_nl = stripos( $cat['title'], 'newsletter' ) !== false;
            foreach ( $cat['interests'] as $interest ) {
                if ( $is_nl ) {
                    $nl[ $interest['id'] ] = $interest['name'];
                } else {
                    $general[ $interest['id'] ] = $interest['name'];
                }
            }
        }
        set_transient( 'jb3_mc_interests_cache',    $general, HOUR_IN_SECONDS );
        set_transient( 'jb3_mc_nl_interests_cache', $nl,      HOUR_IN_SECONDS );
    }

    public static function get_cached_interests() {
        if ( ! self::api_key() || ! self::list_id() ) {
            return [];
        }
        $cached = get_transient( 'jb3_mc_interests_cache' );
        if ( false !== $cached ) {
            return $cached;
        }
        self::build_all_caches();
        return get_transient( 'jb3_mc_interests_cache' ) ?: [];
    }

    public static function get_newsletter_interests() {
        if ( ! self::api_key() || ! self::list_id() ) {
            return [];
        }
        $cached = get_transient( 'jb3_mc_nl_interests_cache' );
        if ( false !== $cached ) {
            return $cached;
        }
        self::build_all_caches();
        return get_transient( 'jb3_mc_nl_interests_cache' ) ?: [];
    }

    // Auto-genera y persiste un secret para el webhook.
    public static function webhook_secret() {
        $secret = get_option( 'jb3_mc_webhook_secret', '' );
        if ( ! $secret ) {
            $secret = wp_generate_password( 32, false );
            update_option( 'jb3_mc_webhook_secret', $secret );
        }
        return $secret;
    }

    public static function webhook_url() {
        return add_query_arg( 'jb3_mc_hook', self::webhook_secret(), home_url( '/' ) );
    }

    // -------------------------------------------------------------------------
    // Fetch interest groups from Mailchimp (for admin UI)
    // -------------------------------------------------------------------------

    public static function fetch_interest_groups() {
        if ( ! self::api_key() || ! self::list_id() ) {
            return new WP_Error( 'no_credentials', 'API Key o Audience ID no configurados.' );
        }

        $base    = 'https://' . self::server() . '.api.mailchimp.com/3.0/lists/' . self::list_id();
        $headers = [ 'Authorization' => 'Basic ' . base64_encode( 'jb3:' . self::api_key() ) ];

        $resp = wp_remote_get( $base . '/interest-categories?count=50', [ 'headers' => $headers, 'timeout' => 15 ] );
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code( $resp );
        if ( 200 !== $code ) {
            return new WP_Error( 'api_error', 'Error ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
        }

        $body       = json_decode( wp_remote_retrieve_body( $resp ), true );
        $categories = $body['categories'] ?? [];
        $result     = [];

        foreach ( $categories as $cat ) {
            $int_resp  = wp_remote_get( $base . '/interest-categories/' . $cat['id'] . '/interests?count=100', [ 'headers' => $headers, 'timeout' => 15 ] );
            $interests = [];
            if ( ! is_wp_error( $int_resp ) && 200 === wp_remote_retrieve_response_code( $int_resp ) ) {
                $int_body  = json_decode( wp_remote_retrieve_body( $int_resp ), true );
                $interests = array_map(
                    fn( $i ) => [ 'id' => $i['id'], 'name' => $i['name'] ],
                    $int_body['interests'] ?? []
                );
            }
            $result[] = [ 'id' => $cat['id'], 'title' => $cat['title'], 'interests' => $interests ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    public static function init() {
        // Sync desde el formulario de perfil frontend.
        add_action( 'jb3_after_profile_save', [ __CLASS__, 'sync_user' ] );

        // Sync desde cualquier otro punto del plugin via acción genérica.
        add_action( 'jb3_sync_to_mailchimp', [ __CLASS__, 'sync_user' ] );

        // Webhook entrante de Mailchimp (unsubscribe → actualiza WordPress).
        add_action( 'init', [ __CLASS__, 'handle_webhook' ], 1 );

        // Archivar en Mailchimp cuando se elimina un miembro.
        add_action( 'delete_user', [ __CLASS__, 'handle_user_delete' ] );
    }

    // -------------------------------------------------------------------------
    // Webhook: Mailchimp → WordPress
    // -------------------------------------------------------------------------

    public static function handle_webhook() {
        if ( empty( $_GET['jb3_mc_hook'] ) ) {
            return;
        }

        // Mailchimp hace un GET para validar la URL — responder 200 y salir.
        if ( 'GET' === $_SERVER['REQUEST_METHOD'] ) {
            status_header( 200 );
            exit;
        }

        // Verificar secret ANTES de cualquier otra cosa (incluido el log de
        // abajo) — auditoría de seguridad 2026-08-27: antes el error_log()
        // corría sin condición, en cada POST a esta URL, ANTES de validar
        // nada — cualquiera sin conocer el secret podía forzar escritura en
        // el log del servidor mandando POSTs en bucle con type/email
        // arbitrarios. hash_equals() en vez de !==, para no depender de una
        // comparación que no es a tiempo constante (esta sí importa: el
        // secret protege un endpoint de escritura de datos de usuarios).
        if ( ! hash_equals( self::webhook_secret(), (string) ( $_GET['jb3_mc_hook'] ?? '' ) ) ) {
            status_header( 403 );
            exit;
        }

        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[JB3 Webhook] POST recibido. type=' . ( $_POST['type'] ?? '—' ) . ' email=' . ( $_POST['data']['email'] ?? '—' ) );
        }

        $type  = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $email = sanitize_email( $_POST['data']['email'] ?? '' );

        if ( ! $email ) {
            error_log( '[JB3 Webhook] Email vacío.' );
            status_header( 200 );
            exit;
        }

        $user = get_user_by( 'email', $email );
        if ( ! $user || ! jb3_is_member( $user->ID ) ) {
            error_log( '[JB3 Webhook] Usuario no encontrado o no es miembro: ' . $email );
            status_header( 200 );
            exit;
        }

        if ( 'unsubscribe' === $type || 'cleaned' === $type ) {
            update_user_meta( $user->ID, 'jb3_newsletter', 0 );
            jb3_sync_newsletter_countries( $user->ID, false );
            self::archive_contact( $email );
        }

        if ( 'subscribe' === $type ) {
            update_user_meta( $user->ID, 'jb3_newsletter', 1 );
            jb3_sync_newsletter_countries( $user->ID, true );
        }

        if ( 'profile' === $type ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $merges = $_POST['data']['merges'] ?? [];

            $first_name = sanitize_text_field( wp_unslash( $merges['FNAME']   ?? '' ) );
            $last_name  = sanitize_text_field( wp_unslash( $merges['LNAME']   ?? '' ) );
            $phone      = jb3_sanitize_phone( wp_unslash( $merges['PHONE']    ?? '' ) );
            $company    = sanitize_text_field( wp_unslash( $merges['COMPANY'] ?? '' ) );
            $sector     = sanitize_text_field( wp_unslash( $merges['MMERGE5'] ?? '' ) );
            $job_title  = sanitize_text_field( wp_unslash( $merges['MMERGE6'] ?? '' ) );
            $country    = strtoupper( sanitize_text_field( wp_unslash( $merges['COUNTRY'] ?? '' ) ) );

            if ( $first_name || $last_name ) {
                wp_update_user( [
                    'ID'           => $user->ID,
                    'first_name'   => $first_name ?: $user->first_name,
                    'last_name'    => $last_name  ?: $user->last_name,
                    'display_name' => trim( ( $first_name ?: $user->first_name ) . ' ' . ( $last_name ?: $user->last_name ) ),
                ] );
            }

            if ( $phone )    update_user_meta( $user->ID, 'jb3_phone',     $phone );
            if ( $company )  update_user_meta( $user->ID, 'jb3_company',   $company );
            if ( $sector && in_array( $sector, jb3_sectors(), true ) ) {
                update_user_meta( $user->ID, 'jb3_sector', $sector );
            }
            if ( $job_title && in_array( $job_title, jb3_job_titles(), true ) ) {
                update_user_meta( $user->ID, 'jb3_job_title', $job_title );
            }
            if ( $country && array_key_exists( $country, jb3_countries() ) ) {
                update_user_meta( $user->ID, 'jb3_country', $country );
            }
        }

        status_header( 200 );
        exit;
    }

    // -------------------------------------------------------------------------
    // Archive: desuscribe y archiva el contacto en Mailchimp
    // -------------------------------------------------------------------------

    public static function archive_contact( $email ) {
        if ( ! self::api_key() || ! self::list_id() ) {
            return;
        }
        $hash = md5( strtolower( trim( $email ) ) );
        $url  = 'https://' . self::server() . '.api.mailchimp.com/3.0/lists/' . self::list_id() . '/members/' . $hash;
        $auth = [ 'Authorization' => 'Basic ' . base64_encode( 'jb3:' . self::api_key() ) ];

        // Desuscribir + archivar — fire-and-forget (blocking=false): no bloquea al usuario.
        wp_remote_request( $url, [
            'method'   => 'PATCH',
            'headers'  => array_merge( $auth, [ 'Content-Type' => 'application/json' ] ),
            'body'     => wp_json_encode( [ 'status' => 'unsubscribed' ] ),
            'timeout'  => 15,
            'blocking' => false,
        ] );

        wp_remote_request( $url, [
            'method'   => 'DELETE',
            'headers'  => $auth,
            'timeout'  => 15,
            'blocking' => false,
        ] );
    }

    // -------------------------------------------------------------------------
    // Hook delete_user: archiva en Mailchimp cuando se elimina un miembro
    // -------------------------------------------------------------------------

    public static function handle_user_delete( $user_id ) {
        if ( ! jb3_is_member( $user_id ) ) {
            return;
        }
        $user = get_userdata( $user_id );
        if ( $user ) {
            self::archive_contact( $user->user_email );
        }
    }

    // -------------------------------------------------------------------------
    // Sync: WordPress → Mailchimp
    // -------------------------------------------------------------------------

    public static function sync_user( $user_id ) {
        if ( ! self::api_key() || ! self::list_id() ) {
            return;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $is_admin = user_can( $user_id, 'manage_options' );
        $status   = jb3_get_status( $user_id );

        if ( ! $is_admin && 'approved' !== $status ) {
            return;
        }

        $newsletter = (bool) get_user_meta( $user_id, 'jb3_newsletter', true );

        // Sin newsletter: archivar miembros, pero simplemente saltear admins (no archivarlos).
        if ( ! $newsletter ) {
            if ( ! $is_admin ) {
                self::archive_contact( $user->user_email );
            }
            return;
        }

        $interests_raw = (array) get_user_meta( $user_id, 'jb3_interests', true );

        $interests = [];
        foreach ( array_keys( self::get_cached_interests() ) as $id ) {
            $interests[ $id ] = in_array( $id, $interests_raw, true );
        }

        $nl_ids      = (array) get_user_meta( $user_id, 'jb3_newsletter_countries', true );
        $nl_interest = self::get_newsletter_interests();
        foreach ( array_keys( $nl_interest ) as $id ) {
            $interests[ $id ] = in_array( $id, $nl_ids, true );
        }

        $data = [
            'email_address' => $user->user_email,
            'status_if_new' => 'subscribed',
            'status'        => 'subscribed',
            'merge_fields'  => [
                'FNAME'   => $user->first_name,
                'LNAME'   => $user->last_name,
                'PHONE'   => get_user_meta( $user_id, 'jb3_phone',     true ),
                'COMPANY' => get_user_meta( $user_id, 'jb3_company',   true ),
                'MMERGE5' => get_user_meta( $user_id, 'jb3_sector',    true ),
                'MMERGE6' => get_user_meta( $user_id, 'jb3_job_title', true ),
                'COUNTRY'     => get_user_meta( $user_id, 'jb3_country',             true ),

                'ESTADO'      => 'Aprobado',
            ],
            'interests' => $interests,
        ];

        $hash = md5( strtolower( trim( $user->user_email ) ) );
        $url  = 'https://' . self::server() . '.api.mailchimp.com/3.0/lists/' . self::list_id() . '/members/' . $hash;

        $response = wp_remote_request( $url, [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( 'jb3:' . self::api_key() ),
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $data ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[JB3 Mailchimp] Error sincronizando usuario ' . $user_id . ': ' . $response->get_error_message() );
        } elseif ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
            error_log( '[JB3 Mailchimp] API error ' . wp_remote_retrieve_response_code( $response ) . ' usuario ' . $user_id . ': ' . wp_remote_retrieve_body( $response ) );
        }
    }
}
