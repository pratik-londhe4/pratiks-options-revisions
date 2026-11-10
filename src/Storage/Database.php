<?php
/**
 * Database schema and table management.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Storage;

use PratikLondhe\PratiksOptionsRevisions\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the custom database table for options revisions.
 */
class Database implements Service {

	public const TABLE_NAME        = 'pratiks_options_revisions';
	public const DB_VERSION        = '1.0.0';
	public const DB_VERSION_OPTION = 'pratiks_options_revisions_db_version';

	/**
	 * Registers service hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Returns the full table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Checks whether the table schema is up to date and creates/upgrades if needed.
	 */
	public function maybe_upgrade(): void {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
		if ( version_compare( (string) $installed_version, self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Creates or updates the database table schema.
	 */
	public static function install(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			option_name varchar(191) NOT NULL,
			old_value longtext DEFAULT NULL,
			new_value longtext DEFAULT NULL,
			action_type varchar(20) NOT NULL DEFAULT 'updated',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_ip varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY option_name (option_name),
			KEY created_at (created_at),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Drops the table upon uninstall.
	 */
	public static function drop_table(): void {
		global $wpdb;
		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		delete_option( self::DB_VERSION_OPTION );
	}
}
