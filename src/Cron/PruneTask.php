<?php
/**
 * Daily revision cleanup cron task.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Cron;

use PratikLondhe\PratiksOptionsRevisions\Service;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Periodically prunes expired or excess options revisions.
 */
class PruneTask implements Service {

	public const CRON_HOOK = 'pratiks_options_revisions_daily_cleanup';

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
	 * Registers service hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_cleanup' ) );
	}

	/**
	 * Schedules the cron event if not already scheduled.
	 */
	public static function maybe_schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Executes the pruning logic.
	 */
	public function run_cleanup(): void {
		/**
		 * Filters the retention period in days.
		 *
		 * @param int $days Number of days to retain revisions.
		 */
		$days = (int) apply_filters( 'pratiks_options_revisions_retention_days', 30 );

		/**
		 * Filters the maximum number of revisions stored per option.
		 *
		 * @param int $max_per_option Maximum count per option.
		 */
		$max_per_option = (int) apply_filters( 'pratiks_options_revisions_max_revisions_per_option', 30 );

		$deleted_old    = $this->repository->prune_older_than( $days );
		$deleted_excess = $this->repository->prune_excess( $max_per_option );

		$total = $deleted_old + $deleted_excess;

		/**
		 * Fires after revisions have been pruned by cron or command.
		 *
		 * @param int $total Total number of pruned records.
		 */
		do_action( 'pratiks_options_revisions_pruned', $total );
	}

	/**
	 * Clears the scheduled cron event.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
