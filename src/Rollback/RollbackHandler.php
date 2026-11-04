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
		$old_raw     = $record->old_value;
		$action_type = (string) $record->action_type;

		OptionListener::$is_restoring = true;

		try {
			if ( 'added' === $action_type || is_null( $old_raw ) ) {
				// Reverting an 'added' option means deleting it.
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
