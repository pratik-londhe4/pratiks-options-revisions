<?php
/**
 * Option rollback execution handler.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Rollback;

use PratikLondhe\PratiksOptionsRevisions\Capture\OptionListener;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles executing option rollbacks safely.
 */
class RollbackHandler {

	/**
	 * Repository instance.
	 *
	 * @var RevisionRepository
	 */
	private RevisionRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param RevisionRepository|null $repository Optional repository.
	 */
	public function __construct( ?RevisionRepository $repository = null ) {
		$this->repository = $repository ?? new RevisionRepository();
	}

	/**
	 * Options that must never be rolled back directly because modifying them via update_option
	 * bypasses WordPress activation/deactivation hooks, theme switching routines, or role management.
	 *
	 * @var array<string>
	 */
	public const DISALLOWED_ROLLBACK_OPTIONS = array(
		'active_plugins',
		'active_sitewide_plugins',
		'template',
		'stylesheet',
		'current_theme',
		'theme_switched',
		'wp_user_roles',
		'recently_activated',
		'uninstall_plugins',
	);

	/**
	 * Checks whether an option is disallowed from rollback because it controls active plugins,
	 * themes, or user roles, which must be handled through their dedicated WordPress APIs.
	 *
	 * @param string $option_name Option name.
	 * @return bool True if disallowed, false otherwise.
	 */
	public static function is_disallowed_option( string $option_name ): bool {
		if ( in_array( $option_name, self::DISALLOWED_ROLLBACK_OPTIONS, true ) ) {
			return true;
		}

		if ( false !== strpos( $option_name, 'user_roles' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Maps sensitive option names to the WordPress capability required to modify them.
	 *
	 * @return array<string, string> Option name => required capability.
	 */
	private static function sensitive_option_capabilities(): array {
		return array(
			'sidebars_widgets' => 'edit_theme_options',
			'theme_mods_*'     => 'edit_theme_options',
			'widget_*'         => 'edit_theme_options',
		);
	}

	/**
	 * Returns the extra capability required for a given option, if any.
	 *
	 * @param string $option_name Option name.
	 * @return string|null The required capability, or null if manage_options is sufficient.
	 */
	private static function get_required_capability( string $option_name ): ?string {
		$map = self::sensitive_option_capabilities();

		// Check exact match first.
		if ( isset( $map[ $option_name ] ) ) {
			return $map[ $option_name ];
		}

		// Check wildcard prefixes (entries ending with *).
		foreach ( $map as $pattern => $capability ) {
			if ( '*' === substr( $pattern, -1 ) && 0 === strpos( $option_name, substr( $pattern, 0, -1 ) ) ) {
				return $capability;
			}
		}

		return null;
	}

	/**
	 * Checks whether the current user has permission to roll back a given option.
	 *
	 * Lifecycle-managed options (active plugins, active themes, user roles) are always
	 * disallowed from direct rollback because direct update_option calls bypass WordPress
	 * activation/deactivation hooks and theme switching routines.
	 *
	 * Requires manage_options for all options. Additionally requires option-specific capabilities
	 * mapped to sensitive options (e.g. edit_theme_options for sidebars_widgets or widgets).
	 * On multisite, requires manage_network_options for network site URLs.
	 *
	 * @param string $option_name Option name.
	 * @return bool True if allowed, false otherwise.
	 */
	public static function can_user_rollback_option( string $option_name ): bool {
		// Disallow lifecycle-managed options from direct rollback.
		if ( self::is_disallowed_option( $option_name ) ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check option-specific capability.
		$required_cap = self::get_required_capability( $option_name );
		if ( null !== $required_cap && ! current_user_can( $required_cap ) ) {
			return false;
		}

		// On multisite, network-wide URL options require super-admin.
		if ( is_multisite() ) {
			if ( in_array( $option_name, array( 'siteurl', 'home' ), true ) && ! current_user_can( 'manage_network_options' ) ) {
				return false;
			}
		}

		/**
		 * Filters whether the current user can roll back a specific option.
		 *
		 * @param bool   $can_rollback Whether the user can roll back.
		 * @param string $option_name  The option name.
		 */
		return (bool) apply_filters( 'pratiks_options_revisions_can_rollback_option', true, $option_name );
	}

	/**
	 * Checks whether the current user can roll back a specific revision by ID.
	 *
	 * @param int $revision_id Revision record ID.
	 * @return bool True if allowed, false otherwise.
	 */
	public function can_user_rollback_revision( int $revision_id ): bool {
		$record = $this->repository->get_by_id( $revision_id );
		if ( ! $record ) {
			return false;
		}

		return self::can_user_rollback_option( (string) $record->option_name );
	}

	/**
	 * Rolls back an option to its state prior to a specific revision.
	 *
	 * @param int $revision_id Revision record ID.
	 * @return bool True on success, false on failure.
	 */
	public function rollback( int $revision_id ): bool {
		$record = $this->repository->get_by_id( $revision_id );
		if ( ! $record ) {
			return false;
		}

		$option_name = (string) $record->option_name;

		// Never restore lifecycle-managed options (plugins, themes, roles) directly via update_option.
		if ( self::is_disallowed_option( $option_name ) || ! self::can_user_rollback_option( $option_name ) ) {
			return false;
		}

		$old_raw     = $record->old_value;
		$action_type = (string) $record->action_type;

		// Refuse to restore any revision that contains sensitive credentials.
		if ( ! is_null( $old_raw ) && OptionListener::contains_sensitive_data( maybe_unserialize( $old_raw ) ) ) {
			return false;
		}

		OptionListener::$is_restoring = true;

		try {
			if ( 'added' === $action_type ) {
				// Reverting an 'added' option means deleting it.
				$success        = delete_option( $option_name );
				$restored_value = null;
			} elseif ( 'deleted' === $action_type ) {
				// Reverting a 'deleted' option means restoring the previous value.
				if ( is_null( $old_raw ) ) {
					// Cannot restore without a recorded prior value.
					return false;
				}
				$restored_value = maybe_unserialize( $old_raw );
				$success        = update_option( $option_name, $restored_value );
			} elseif ( is_null( $old_raw ) ) {
				// Reverting an update with no previous value deletes it.
				$success        = delete_option( $option_name );
				$restored_value = null;
			} else {
				$restored_value = maybe_unserialize( $old_raw );
				$success        = update_option( $option_name, $restored_value );
			}
		} finally {
			OptionListener::$is_restoring = false;
		}

		if ( $success ) {
			/**
			 * Fires after an option has been successfully rolled back.
			 *
			 * @param string $option_name    The option name.
			 * @param mixed  $restored_value The value restored.
			 * @param int    $revision_id    The revision ID rolled back.
			 */
			do_action( 'pratiks_options_revisions_rolled_back', $option_name, $restored_value, $revision_id );
		}

		return (bool) $success;
	}
}
