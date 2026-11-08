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
	 * Checks whether the current user has permission to roll back a given option.
	 *
	 * Requires manage_options for all options.
	 * Requires activate_plugins if rolling back active_plugins.
	 * On multisite, requires manage_network_plugins if rolling back network options.
	 *
	 * @param string $option_name Option name.
	 * @return bool True if allowed, false otherwise.
	 */
	public static function can_user_rollback_option( string $option_name ): bool {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( in_array( $option_name, array( 'active_plugins', 'active_sitewide_plugins' ), true ) ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return false;
			}

			if ( is_multisite() && 'active_sitewide_plugins' === $option_name && ! current_user_can( 'manage_network_plugins' ) ) {
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

		if ( ! self::can_user_rollback_option( $option_name ) ) {
			return false;
		}

		$old_raw     = $record->old_value;
		$action_type = (string) $record->action_type;

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
