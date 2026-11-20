<?php
/**
 * Plugin Name:       Paralela 45 – Cărți digitale
 * Description:       Platformă de cărți digitale pentru profesori: încarci PDF-ul, platforma îl transformă într-o carte care se răsfoiește, cu Mod Tablă, desen, semne de carte și note. Accesul se dă din comandă.
 * Version:           1.1.3
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Editura Paralela 45
 * License:           GPL-2.0-or-later
 * Text Domain:       p45-carti-digitale
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'P45CD_VERSION', '1.1.3' );
define( 'P45CD_FILE', __FILE__ );
define( 'P45CD_DIR', plugin_dir_path( __FILE__ ) );
define( 'P45CD_URL', plugin_dir_url( __FILE__ ) );
define( 'P45CD_DB_VERSION', '1' );

require_once P45CD_DIR . 'includes/functions.php';
require_once P45CD_DIR . 'includes/install.php';
require_once P45CD_DIR . 'includes/cpt.php';
require_once P45CD_DIR . 'includes/access.php';
require_once P45CD_DIR . 'includes/mail.php';
require_once P45CD_DIR . 'includes/routes.php';
require_once P45CD_DIR . 'includes/rest.php';
require_once P45CD_DIR . 'includes/migrare.php';
require_once P45CD_DIR . 'includes/api-crm.php';

if ( is_admin() ) {
	require_once P45CD_DIR . 'admin/admin.php';
}

register_activation_hook( __FILE__, 'p45cd_activate' );
register_deactivation_hook( __FILE__, 'p45cd_deactivate' );
