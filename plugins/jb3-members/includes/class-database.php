<?php
defined( 'ABSPATH' ) || exit;

class JB3_Database {

    const TABLE   = 'jb3_representatives';
    const DB_VER  = '1.0';

    public static function install() {
        global $wpdb;

        $table           = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            country_code VARCHAR(2)          NOT NULL DEFAULT '',
            first_name   VARCHAR(100)        NOT NULL DEFAULT '',
            last_name    VARCHAR(100)        NOT NULL DEFAULT '',
            email        VARCHAR(150)        NOT NULL DEFAULT '',
            phone        VARCHAR(50)         NOT NULL DEFAULT '',
            is_default   TINYINT(1)          NOT NULL DEFAULT 0,
            created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_country (country_code)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Seed the default representative once.
        if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_default = 1" ) ) {
            $wpdb->insert(
                $table,
                [
                    'country_code' => '',
                    'first_name'   => 'juan jose',
                    'last_name'    => 'balbi',
                    'email'        => 'juanjose@jb3.tv',
                    'phone'        => '1111111111',
                    'is_default'   => 1,
                ],
                [ '%s', '%s', '%s', '%s', '%s', '%d' ]
            );
        }

        // Register custom WordPress role (ignored silently if already exists).
        if ( ! get_role( 'jb3_member' ) ) {
            add_role(
                'jb3_member',
                __( 'JB3 Member', 'jb3-members' ),
                [ 'read' => true ]
            );
        }

        update_option( 'jb3_db_version', self::DB_VER );
    }
}
