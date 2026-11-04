<?php
/**
 * Plugin Name:       Pratiks Options Revisions
 * Plugin URI:        https://wordpress.org/plugins/pratiks-options-revisions/
 * Description:       Track settings changes, compare unified diffs, and roll back any WordPress or plugin option with one click.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Pratik Londhe
 * Author URI:        https://profiles.wordpress.org/pratiklondhe/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pratiks-options-revisions
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;

// PSR-4: PratikLondhe\PratiksOptionsRevisions\Foo\Bar lives in src/Foo/Bar.php.
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook(
	PLUGIN_FILE,
	static function () {
		Storage\Database::install();
		Cron\PruneTask::maybe_schedule_cron();
	}
);

register_deactivation_hook(
	PLUGIN_FILE,
	static function () {
		Cron\PruneTask::unschedule();
	}
);

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
