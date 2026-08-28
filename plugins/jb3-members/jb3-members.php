<?php
/**
 * Plugin Name: JB3 Members
 * Plugin URI:  https://jb3.tv
 * Description: Registro, aprobación y acceso de miembros con magic link para JB3.
 * Version:     1.0.1
 * Author:      JB3
 * Author URI:  https://jb3.tv
 * Text Domain: jb3-members
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:  7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'JB3_VERSION',  '1.0.1' );
define( 'JB3_DIR',      plugin_dir_path( __FILE__ ) );
define( 'JB3_URL',      plugin_dir_url( __FILE__ ) );
define( 'JB3_BASENAME', plugin_basename( __FILE__ ) );

$jb3_includes = [
    'includes/helpers.php',
    'includes/class-database.php',
    'includes/class-representatives.php',
    'includes/class-email.php',
    'includes/class-magic-link.php',
    'includes/class-registration.php',
    'includes/class-profile-update.php',
    'includes/class-login.php',
    'includes/class-auth-guard.php',
    'includes/class-cleanup.php',
    'includes/class-mailchimp.php',
    'includes/class-mailchimp-entry.php',
    'includes/class-sync.php',
    'includes/class-content-privacy.php',
    'includes/class-google-sheets.php',
    'includes/class-op-comerciales-tracking.php',
    'includes/class-recaptcha.php',
    'admin/class-admin.php',
];

foreach ( $jb3_includes as $file ) {
    require_once JB3_DIR . $file;
}

register_activation_hook( __FILE__, [ 'JB3_Database', 'install' ] );
register_activation_hook( __FILE__, [ 'JB3_Cleanup', 'schedule' ] );
register_deactivation_hook( __FILE__, [ 'JB3_Cleanup', 'deactivate' ] );
register_deactivation_hook( __FILE__, [ 'JB3_Sync', 'deactivate' ] );

add_action( 'plugins_loaded', 'jb3_members_boot' );

function jb3_members_boot() {
    JB3_Database::maybe_upgrade();

    JB3_Registration::init();
    JB3_Profile_Update::init();
    JB3_Login::init();
    JB3_Magic_Link::init();
    JB3_Auth_Guard::init();
    JB3_Cleanup::init();
    JB3_Mailchimp::init();
    JB3_Representatives::init();
    JB3_Content_Privacy::init();
    JB3_Op_Comerciales_Tracking::init();
    JB3_Recaptcha::init();

    if ( is_admin() ) {
        JB3_Sync::init();
        JB3_Admin::init();
    }
}
