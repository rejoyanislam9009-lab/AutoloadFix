<?php
/**
 * Plugin Name:       AutoloadFix
 * Plugin URI:        https://github.com/rejoyanislam9009-lab/AutoloadFix
 * Description:       Audit autoloaded options, diagnose cache and page problems, generate supported optimization profiles, verify fixes, and restore autoload changes from snapshots.
 * Version:           1.4.0
 * Requires at least: 6.6
 * Requires PHP:      7.2
 * Author:            Rejoyan Islam
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       autoloadfix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AUTOLOADFIX_VERSION', '1.4.0' );
define( 'AUTOLOADFIX_FILE', __FILE__ );
define( 'AUTOLOADFIX_PATH', plugin_dir_path( __FILE__ ) );
define( 'AUTOLOADFIX_URL', plugin_dir_url( __FILE__ ) );

require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-scanner.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-snapshot.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-audit.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-admin.php';
require_once AUTOLOADFIX_PATH . 'includes/trait-autoloadfix-advanced-render.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-advanced-admin.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-cache-advisor.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-site-scanner.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-scanner-refinements.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-optimization-profiles.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-site-health.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-cli.php';
require_once AUTOLOADFIX_PATH . 'includes/class-autoloadfix-plugin.php';

register_activation_hook( __FILE__, array( 'AutoloadFix_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AutoloadFix_Plugin', 'deactivate' ) );

AutoloadFix_Plugin::instance();
