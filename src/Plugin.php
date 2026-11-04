<?php
/**
 * Plugin bootstrap.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions;

use PratikLondhe\PratiksOptionsRevisions\Admin\AdminPage;
use PratikLondhe\PratiksOptionsRevisions\Capture\OptionListener;
use PratikLondhe\PratiksOptionsRevisions\Cli\Command;
use PratikLondhe\PratiksOptionsRevisions\Cron\PruneTask;
use PratikLondhe\PratiksOptionsRevisions\Health\SiteHealth;
use PratikLondhe\PratiksOptionsRevisions\Privacy\Privacy;
use PratikLondhe\PratiksOptionsRevisions\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the services once and registers their hooks.
 */
final class Plugin {

	/**
	 * The running instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Starts the plugin. Runs on `plugins_loaded`.
	 */
	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
	}

	/**
	 * The running instance, for other plugins.
	 *
	 * @return Plugin|null
	 */
	public static function instance(): ?Plugin {
		return self::$instance;
	}

	/**
	 * The plugin's services: one per concern (settings screen, abilities, WP-CLI, ...).
	 *
	 * @return Service[]
	 */
	private function services(): array {
		return array(
			new Database(),
			new OptionListener(),
			new AdminPage(),
			new PruneTask(),
			new Command(),
			new Abilities(),
			new Privacy(),
			new SiteHealth(),
		);
	}

	/**
	 * Registers every service with WordPress.
	 */
	private function register(): void {
		foreach ( $this->services() as $service ) {
			$service->register();
		}
	}
}
