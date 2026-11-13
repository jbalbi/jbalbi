<?php
defined( 'ABSPATH' ) || exit;

class JB3_Cleanup {

    const CRON_HOOK = 'jb3_cleanup_pending_users';

    public static function init() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Delete pending (unapproved) jb3_member accounts older than 30 days.
     */
    public static function run() {
        // wp_delete_user() vive en wp-admin/includes/user.php, que WP-Cron no
        // carga: sin esto la limpieza fallaba en el cron con la primera
        // cuenta a borrar (detectado 2026-11-04).
        require_once ABSPATH . 'wp-admin/includes/user.php';

        // jb3_registered_at se guarda con current_time('mysql'), en la hora
        // del sitio: el umbral, en la misma hora (antes gmdate(), en UTC).
        $threshold = wp_date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        $users = get_users( [
            'role'       => 'jb3_member',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'   => 'jb3_status',
                    'value' => 'pending',
                ],
                [
                    'key'     => 'jb3_registered_at',
                    'value'   => $threshold,
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ],
            ],
            'fields' => 'ID',
            'number' => 200,
        ] );

        foreach ( $users as $user_id ) {
            wp_delete_user( (int) $user_id );
        }
    }
}
