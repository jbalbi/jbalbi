<?php
defined( 'ABSPATH' ) || exit;

class JB3_Database {

    const TABLE           = 'jb3_representatives';
    const TABLE_COUNTRIES = 'jb3_representative_countries';
    const DB_VER          = '1.2';

    /**
     * Runs on every admin request (cheap: one autoloaded option compare).
     * Needed because install() only fires on activation — a schema bump on
     * an already-active plugin (like the 1.0 -> 1.1 country_code -> join
     * table migration) has to be picked up some other way.
     */
    public static function maybe_upgrade() {
        if ( get_option( 'jb3_db_version' ) !== self::DB_VER ) {
            self::install();
        }
    }

    public static function install() {
        global $wpdb;

        $table           = $wpdb->prefix . self::TABLE;
        $table_countries = $wpdb->prefix . self::TABLE_COUNTRIES;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name       VARCHAR(100)        NOT NULL DEFAULT '',
            last_name        VARCHAR(100)        NOT NULL DEFAULT '',
            email            VARCHAR(150)        NOT NULL DEFAULT '',
            phone            VARCHAR(50)         NOT NULL DEFAULT '',
            whatsapp         VARCHAR(50)         NOT NULL DEFAULT '',
            photo_id         BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            is_default       TINYINT(1)          NOT NULL DEFAULT 0,
            visible          TINYINT(1)          NOT NULL DEFAULT 1,
            notify_downloads TINYINT(1)          NOT NULL DEFAULT 1,
            created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        // Un representante puede cubrir varios países (checkboxes en el
        // admin) — de ahí la tabla aparte en vez de una sola columna.
        $sql_countries = "CREATE TABLE {$table_countries} (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            representative_id BIGINT(20) UNSIGNED NOT NULL,
            country_code      VARCHAR(2)          NOT NULL,
            PRIMARY KEY (id),
            KEY idx_rep (representative_id),
            KEY idx_country (country_code)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        dbDelta( $sql_countries );

        self::migrate_country_code_column();

        // Seed the default representative once.
        if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_default = 1" ) ) {
            $wpdb->insert(
                $table,
                [
                    'first_name'       => 'juan jose',
                    'last_name'        => 'balbi',
                    'email'            => 'juanjose@jb3.tv',
                    'phone'            => '1111111111',
                    'is_default'       => 1,
                    'visible'          => 1,
                    'notify_downloads' => 1,
                ],
                [ '%s', '%s', '%s', '%s', '%d', '%d', '%d' ]
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

    /**
     * v1.0 stored a single country per representative in a `country_code`
     * column. v1.1 moves to the join table above so one rep can cover
     * several countries. Runs once per site: copies any existing values
     * across, then drops the old column. Safe to call on every install()
     * — no-ops instantly once the column is gone.
     */
    private static function migrate_country_code_column() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_column = $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $wpdb->dbname,
            $table,
            'country_code'
        ) );

        if ( ! $has_column ) {
            return;
        }

        $table_countries = $wpdb->prefix . self::TABLE_COUNTRIES;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results( "SELECT id, country_code FROM {$table} WHERE country_code != ''" );
        foreach ( $rows as $row ) {
            $wpdb->insert(
                $table_countries,
                [
                    'representative_id' => (int) $row->id,
                    'country_code'      => strtoupper( $row->country_code ),
                ],
                [ '%d', '%s' ]
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( "ALTER TABLE {$table} DROP COLUMN country_code" );
    }
}
