<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bulk sync: WordPress (source of truth) → Mailchimp.
 *
 * Phase 1 — WP → MC:  upsert every approved jb3_member + every admin.
 *                      Members with newsletter=0 are archived instead.
 * Phase 2 — MC → WP:  contacts in Mailchimp that have no WP account are
 *                      archived (DELETE = archive, not permanent delete).
 *
 * Uses Mailchimp Batch API so each AJAX chunk submits up to WP_CHUNK/MC_CHUNK
 * operations in a single HTTP call; Mailchimp processes them asynchronously.
 * Progress tracks "submitted to Mailchimp", not "confirmed by Mailchimp".
 */
class JB3_Sync {

    const OPTION   = 'jb3_sync_state';
    const WP_CHUNK = 100;   // WP users per batch submission
    const MC_CHUNK = 500;   // MC members per fetch page

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'wp_ajax_jb3_sync_start',  [ __CLASS__, 'ajax_start'  ] );
        add_action( 'wp_ajax_jb3_sync_chunk',  [ __CLASS__, 'ajax_chunk'  ] );
        add_action( 'wp_ajax_jb3_sync_cancel', [ __CLASS__, 'ajax_cancel' ] );
    }

    public static function get_state() {
        return get_option( self::OPTION );
    }

    // -------------------------------------------------------------------------
    // AJAX: initialize a new sync run
    // -------------------------------------------------------------------------

    public static function ajax_start() {
        check_ajax_referer( 'jb3_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado.' );
        }

        $wp_total  = count( get_users( [ 'role' => 'jb3_member', 'number' => -1, 'fields' => 'ID' ] ) );
        $admin_ids = array_map( 'intval', get_users( [ 'role' => 'administrator', 'number' => -1, 'fields' => 'ID' ] ) );

        $state = [
            'phase'      => 'wp_to_mc',
            'wp_offset'  => 0,
            'wp_total'   => $wp_total,
            'admins'     => $admin_ids,
            'admin_done' => false,
            'mc_offset'  => 0,
            'mc_total'   => -1,
            'upserted'   => 0,
            'archived'   => 0,
            'errors'     => [],
            'started_at' => current_time( 'mysql' ),
        ];

        update_option( self::OPTION, $state, false );
        wp_send_json_success( $state );
    }

    // -------------------------------------------------------------------------
    // AJAX: process one chunk (advance state by one step)
    // -------------------------------------------------------------------------

    public static function ajax_chunk() {
        check_ajax_referer( 'jb3_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado.' );
        }

        $state = get_option( self::OPTION );
        if ( ! is_array( $state ) ) {
            wp_send_json_error( 'No hay sincronización activa. Iniciá de nuevo.' );
        }

        if ( 'wp_to_mc' === $state['phase'] ) {
            $state = self::process_wp_chunk( $state );
        } elseif ( 'mc_to_wp' === $state['phase'] ) {
            $state = self::process_mc_chunk( $state );
        }

        update_option( self::OPTION, $state, false );
        wp_send_json_success( $state );
    }

    // -------------------------------------------------------------------------
    // AJAX: cancel and clear state
    // -------------------------------------------------------------------------

    public static function ajax_cancel() {
        check_ajax_referer( 'jb3_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado.' );
        }
        delete_option( self::OPTION );
        wp_send_json_success();
    }

    // =========================================================================
    // Phase 1: WP members → Mailchimp
    // =========================================================================

    private static function process_wp_chunk( array $state ): array {
        [ $api_key, $list_id, $server ] = self::mc_creds();
        if ( ! $api_key ) {
            $state['errors'][] = 'Mailchimp no configurado (API Key o Audience ID faltante).';
            $state['phase']    = 'done';
            return $state;
        }

        // --- Still have jb3_members to process ---
        if ( $state['wp_offset'] < $state['wp_total'] ) {
            $user_ids = get_users( [
                'role'    => 'jb3_member',
                'number'  => self::WP_CHUNK,
                'offset'  => $state['wp_offset'],
                'fields'  => 'ID',
                'orderby' => 'ID',
                'order'   => 'ASC',
            ] );

            $operations = [];
            foreach ( $user_ids as $uid ) {
                $op = self::build_member_op( (int) $uid, $list_id );
                if ( $op ) {
                    $operations[] = $op;
                }
            }

            if ( ! empty( $operations ) ) {
                $err = self::submit_batch( $operations, $api_key, $server );
                if ( $err ) {
                    $state['errors'][] = 'Lote miembros (offset ' . $state['wp_offset'] . '): ' . $err;
                } else {
                    $state['upserted'] += count( $operations );
                }
            }

            $state['wp_offset'] += count( $user_ids );
            return $state;
        }

        // --- Sync admin users (once) ---
        if ( ! $state['admin_done'] ) {
            $operations = [];
            foreach ( $state['admins'] as $admin_id ) {
                $op = self::build_admin_op( $admin_id, $list_id );
                if ( $op ) {
                    $operations[] = $op;
                }
            }

            if ( ! empty( $operations ) ) {
                $err = self::submit_batch( $operations, $api_key, $server );
                if ( $err ) {
                    $state['errors'][] = 'Lote admins: ' . $err;
                } else {
                    $state['upserted'] += count( $operations );
                }
            }

            $state['admin_done'] = true;
            return $state;
        }

        // --- Transition to Phase 2 ---
        $state['phase'] = 'mc_to_wp';
        return $state;
    }

    // =========================================================================
    // Phase 2: fetch MC members → archive those without a WP account
    // =========================================================================

    private static function process_mc_chunk( array $state ): array {
        [ $api_key, $list_id, $server ] = self::mc_creds();
        if ( ! $api_key ) {
            $state['phase'] = 'done';
            return $state;
        }

        // Fetch one page of MC members (any non-archived status)
        $url = 'https://' . $server . '.api.mailchimp.com/3.0/lists/' . $list_id . '/members?' . http_build_query( [
            'count'  => self::MC_CHUNK,
            'offset' => $state['mc_offset'],
            'fields' => 'members.email_address,total_items',
        ] );

        $resp = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Basic ' . base64_encode( 'jb3:' . $api_key ) ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $resp ) ) {
            $state['errors'][] = 'Error al obtener miembros de Mailchimp: ' . $resp->get_error_message();
            $state['phase']    = 'done';
            return $state;
        }

        $code = wp_remote_retrieve_response_code( $resp );
        if ( 200 !== $code ) {
            $state['errors'][] = 'Mailchimp API error ' . $code . ' al obtener miembros.';
            $state['phase']    = 'done';
            return $state;
        }

        $body    = json_decode( wp_remote_retrieve_body( $resp ), true );
        $members = $body['members']     ?? [];
        $total   = (int) ( $body['total_items'] ?? 0 );

        if ( $state['mc_total'] < 0 ) {
            $state['mc_total'] = $total;
        }

        // Build archive ops for contacts that have no WP account
        $to_archive = [];
        foreach ( $members as $mc_member ) {
            $email   = strtolower( trim( $mc_member['email_address'] ) );
            $wp_user = get_user_by( 'email', $email );
            if ( ! $wp_user ) {
                $to_archive[] = [
                    'method' => 'DELETE',
                    'path'   => '/lists/' . $list_id . '/members/' . md5( $email ),
                ];
            }
        }

        if ( ! empty( $to_archive ) ) {
            $err = self::submit_batch( $to_archive, $api_key, $server );
            if ( $err ) {
                $state['errors'][] = 'Lote archivo (offset ' . $state['mc_offset'] . '): ' . $err;
            } else {
                $state['archived'] += count( $to_archive );
            }
        }

        $state['mc_offset'] += count( $members );

        if ( empty( $members ) || $state['mc_offset'] >= $state['mc_total'] ) {
            $state['phase'] = 'done';
        }

        return $state;
    }

    // =========================================================================
    // Operation builders
    // =========================================================================

    /**
     * Build a PUT (subscribe) or DELETE (archive) batch operation for a jb3_member.
     * Returns null for pending members and invalid users.
     */
    private static function build_member_op( int $user_id, string $list_id ): ?array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return null;
        }

        // Only sync approved members
        if ( 'approved' !== jb3_get_status( $user_id ) ) {
            return null;
        }

        $email = strtolower( trim( $user->user_email ) );
        $hash  = md5( $email );

        // Opted out of newsletter → archive in MC (mirrors sync_user behavior)
        if ( ! (bool) get_user_meta( $user_id, 'jb3_newsletter', true ) ) {
            return [
                'method' => 'DELETE',
                'path'   => '/lists/' . $list_id . '/members/' . $hash,
            ];
        }

        // Build interest map (general + newsletter-countries)
        $interests_raw = (array) get_user_meta( $user_id, 'jb3_interests', true );
        $interests     = [];
        foreach ( array_keys( JB3_Mailchimp::get_cached_interests() ) as $id ) {
            $interests[ $id ] = in_array( $id, $interests_raw, true );
        }
        $nl_ids = (array) get_user_meta( $user_id, 'jb3_newsletter_countries', true );
        foreach ( array_keys( JB3_Mailchimp::get_newsletter_interests() ) as $id ) {
            $interests[ $id ] = in_array( $id, $nl_ids, true );
        }

        return [
            'method' => 'PUT',
            'path'   => '/lists/' . $list_id . '/members/' . $hash,
            'body'   => wp_json_encode( [
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
                    'COUNTRY' => get_user_meta( $user_id, 'jb3_country',   true ),
                    'ESTADO'  => 'Aprobado',
                ],
                'interests' => $interests,
            ] ),
        ];
    }

    /**
     * Build a PUT (subscribe) batch operation for an administrator.
     * Admins are always subscribed so they can receive test emails.
     */
    private static function build_admin_op( int $user_id, string $list_id ): ?array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return null;
        }

        $hash = md5( strtolower( trim( $user->user_email ) ) );
        return [
            'method' => 'PUT',
            'path'   => '/lists/' . $list_id . '/members/' . $hash,
            'body'   => wp_json_encode( [
                'email_address' => $user->user_email,
                'status_if_new' => 'subscribed',
                'status'        => 'subscribed',
                'merge_fields'  => [
                    'FNAME'   => $user->first_name,
                    'LNAME'   => $user->last_name,
                    'PHONE'   => get_user_meta( $user_id, 'jb3_phone',     true ) ?: '',
                    'COMPANY' => get_user_meta( $user_id, 'jb3_company',   true ) ?: '',
                    'MMERGE5' => get_user_meta( $user_id, 'jb3_sector',    true ) ?: '',
                    'MMERGE6' => get_user_meta( $user_id, 'jb3_job_title', true ) ?: '',
                    'COUNTRY' => get_user_meta( $user_id, 'jb3_country',   true ) ?: '',
                    'ESTADO'  => 'Admin',
                ],
            ] ),
        ];
    }

    // =========================================================================
    // Mailchimp Batch API helper
    // =========================================================================

    /**
     * POST /3.0/batches — Mailchimp processes ops asynchronously.
     * Returns null on success, or an error string.
     */
    private static function submit_batch( array $operations, string $api_key, string $server ): ?string {
        $resp = wp_remote_post(
            'https://' . $server . '.api.mailchimp.com/3.0/batches',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode( 'jb3:' . $api_key ),
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [ 'operations' => $operations ] ),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $resp ) ) {
            return $resp->get_error_message();
        }

        $code = wp_remote_retrieve_response_code( $resp );
        if ( $code < 200 || $code >= 300 ) {
            return 'API error ' . $code . ': ' . wp_remote_retrieve_body( $resp );
        }

        return null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Returns [ api_key, list_id, server ] or [ '', '', '' ] if not configured. */
    private static function mc_creds(): array {
        $api_key = get_option( 'jb3_mc_api_key', '' );
        $list_id = get_option( 'jb3_mc_list_id', '' );
        if ( ! $api_key || ! $list_id ) {
            return [ '', '', '' ];
        }
        $pos    = strrpos( $api_key, '-' );
        $server = $pos !== false ? substr( $api_key, $pos + 1 ) : 'us1';
        return [ $api_key, $list_id, $server ];
    }
}
