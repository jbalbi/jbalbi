<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bulk sync: WordPress (source of truth) → Mailchimp.
 *
 * The browser drives the chunk loop via repeated AJAX calls. No server-side
 * cron or hosting configuration required.
 *
 * State is persisted to wp_options after every chunk, so a network drop or
 * browser close only loses the in-flight chunk (≤500 users). On reconnect the
 * admin page offers "Reanudar" and picks up exactly where it left off.
 *
 * Phase 1 — WP → MC
 *   Iterates approved jb3_members in chunks of WP_CHUNK. Approved members
 *   with newsletter=1 are upserted; newsletter=0 are archived. Admin users are
 *   always subscribed (for test emails). Uses Mailchimp Batch API — one HTTP
 *   call per chunk, Mailchimp processes operations asynchronously.
 *   Optimization: update_meta_cache() loads all user meta in one SQL query
 *   per chunk (replaces N × 7 individual queries).
 *
 * Phase 2 — MC → Archive (solo si el admin la tilda, 'archivar_sin_cuenta')
 *   Fetches Mailchimp members in pages of MC_CHUNK. A single SQL IN() query
 *   per page checks which emails exist in WordPress. Those that don't are
 *   archived via Batch API DELETE (archive = recoverable, not permanent).
 *   Opcional desde 2026-11-02: archiva TODO contacto sin cuenta, incluidos los
 *   cargados a mano en Mailchimp, así que no corre sin pedirlo.
 */
class JB3_Sync {

    const OPTION    = 'jb3_sync_state';
    const CRON_HOOK = 'jb3_sync_process_chunk'; // kept only to clear any legacy scheduled events
    const WP_CHUNK  = 500;
    const MC_CHUNK  = 500;

    // -------------------------------------------------------------------------
    // Boot & teardown
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'wp_ajax_jb3_sync_start',  [ __CLASS__, 'ajax_start'  ] );
        add_action( 'wp_ajax_jb3_sync_chunk',  [ __CLASS__, 'ajax_chunk'  ] );
        add_action( 'wp_ajax_jb3_sync_cancel', [ __CLASS__, 'ajax_cancel' ] );
    }

    /** Clears any legacy cron events left by older versions of this plugin. */
    public static function deactivate() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public static function get_state() {
        return get_option( self::OPTION );
    }

    // -------------------------------------------------------------------------
    // AJAX: start a new sync run
    // -------------------------------------------------------------------------

    public static function ajax_start() {
        check_ajax_referer( 'jb3_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado.' );
        }

        $member_ids = array_map( 'intval', get_users( [ 'role' => 'jb3_member', 'number' => -1, 'fields' => 'ID' ] ) );
        $wp_total   = count( $member_ids );
        // Exclude admins who are also jb3_members — the member loop handles them already.
        $all_admins = array_map( 'intval', get_users( [ 'role' => 'administrator', 'number' => -1, 'fields' => 'ID' ] ) );
        $admin_ids  = array_values( array_diff( $all_admins, $member_ids ) );

        $state = [
            'phase'      => 'wp_to_mc',
            'wp_offset'  => 0,
            'wp_total'   => $wp_total,
            'admins'     => $admin_ids,
            'admin_done' => false,
            'mc_offset'  => 0,
            'mc_total'   => -1,
            'archivar_sin_cuenta' => ! empty( $_POST['archivar_sin_cuenta'] ),
            'subscribed'   => 0,
            'unsubscribed' => 0,
            'archived'     => 0,
            'errors'     => [],
            'started_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];

        update_option( self::OPTION, $state, false );
        wp_send_json_success( $state );
    }

    // -------------------------------------------------------------------------
    // AJAX: process one chunk (called repeatedly by the browser until done)
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

        $state['updated_at'] = current_time( 'mysql' );
        update_option( self::OPTION, $state, false );
        wp_send_json_success( $state );
    }

    // -------------------------------------------------------------------------
    // AJAX: cancel and wipe state
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

        // --- Still have jb3_members to iterate ---
        if ( $state['wp_offset'] < $state['wp_total'] ) {
            // Fetch full user objects so WP caches wp_users rows (avoids N get_userdata queries).
            $users    = get_users( [
                'role'    => 'jb3_member',
                'number'  => self::WP_CHUNK,
                'offset'  => $state['wp_offset'],
                'orderby' => 'ID',
                'order'   => 'ASC',
            ] );
            $user_ids = array_map( fn( $u ) => $u->ID, $users );

            // One SQL query preloads all meta for these users (vs N×7 queries)
            update_meta_cache( 'user', $user_ids );

            $operations = [];
            foreach ( $users as $user ) {
                $op = self::build_op( $user, $list_id );
                if ( $op ) {
                    $operations[] = $op;
                }
            }

            if ( ! empty( $operations ) ) {
                $err = self::submit_batch( $operations, $api_key, $server );
                if ( $err ) {
                    $state['errors'][] = 'Lote miembros (offset ' . $state['wp_offset'] . '): ' . $err;
                } else {
                    foreach ( $operations as $op ) {
                        if ( 'PUT' === $op['method'] ) {
                            $state['subscribed']++;
                        } else {
                            $state['unsubscribed']++;
                        }
                    }
                }
            }

            $state['wp_offset'] += count( $users );
            return $state;
        }

        // --- Sync admin users (once, after all members) ---
        // Uses direct PUT (not Batch API) so archived contacts are resubscribed
        // immediately and errors are visible in the WP error log.
        if ( ! $state['admin_done'] ) {
            foreach ( $state['admins'] as $admin_id ) {
                $newsletter = (bool) get_user_meta( $admin_id, 'jb3_newsletter', true );
                if ( $newsletter ) {
                    JB3_Mailchimp::sync_user( $admin_id );
                    $state['subscribed']++;
                }
            }
            $state['admin_done'] = true;
            return $state;
        }

        // --- Transition to Phase 2 ---
        // ?? true: una sincronización a medias de antes de esta opción sigue como empezó.
        $state['phase'] = ( $state['archivar_sin_cuenta'] ?? true ) ? 'mc_to_wp' : 'done';
        return $state;
    }

    // =========================================================================
    // Phase 2: Mailchimp members → archive those without a WP account
    // =========================================================================

    private static function process_mc_chunk( array $state ): array {
        [ $api_key, $list_id, $server ] = self::mc_creds();
        if ( ! $api_key ) {
            $state['phase'] = 'done';
            return $state;
        }

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

        if ( ! empty( $members ) ) {
            // Single IN() query — one DB call for the whole page
            $mc_emails = array_map( fn( $m ) => strtolower( trim( $m['email_address'] ) ), $members );
            $in_wp     = self::emails_in_wp( $mc_emails );

            $to_archive = [];
            foreach ( $mc_emails as $email ) {
                if ( ! isset( $in_wp[ $email ] ) ) {
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

    private static function build_op( WP_User $user, string $list_id ): ?array {
        $uid      = $user->ID;
        $is_admin = user_can( $uid, 'manage_options' );

        if ( ! $is_admin && 'approved' !== jb3_get_status( $uid ) ) {
            return null;
        }

        $hash = md5( strtolower( trim( $user->user_email ) ) );

        if ( ! (bool) get_user_meta( $uid, 'jb3_newsletter', true ) ) {
            return [ 'method' => 'DELETE', 'path' => '/lists/' . $list_id . '/members/' . $hash ];
        }

        // Payload (merge_fields, interests) compartido con el sync individual
        // — ver JB3_Mailchimp::build_member_data().
        return [
            'method' => 'PUT',
            'path'   => '/lists/' . $list_id . '/members/' . $hash,
            'body'   => wp_json_encode( JB3_Mailchimp::build_member_data( $user, $is_admin ) ),
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns a hash-set of WP user emails found in the given list.
     * Single SQL IN() query — O(1) per lookup thereafter.
     *
     * @param  string[] $emails Lowercased email addresses.
     * @return array<string, true>
     */
    private static function emails_in_wp( array $emails ): array {
        if ( empty( $emails ) ) {
            return [];
        }
        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT LOWER(user_email) FROM {$wpdb->users} WHERE LOWER(user_email) IN ($placeholders)",
                ...$emails
            )
        );
        return array_flip( $rows );
    }

    /**
     * POST /3.0/batches — Mailchimp queues operations asynchronously.
     * Returns null on success, error string on failure.
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
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );
            return 'API error ' . $code . ' — ' . ( $body['detail'] ?? '' );
        }
        return null;
    }

    /** Returns [ api_key, list_id, server ] or [ '', '', '' ] if unconfigured. */
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
