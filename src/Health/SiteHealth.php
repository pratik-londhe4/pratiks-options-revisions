<?php
/**
 * Site Health check integration.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Health;

use PratikLondhe\PratiksOptionsRevisions\Cron\PruneTask;
use PratikLondhe\PratiksOptionsRevisions\Service;
use PratikLondhe\PratiksOptionsRevisions\Storage\Database;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Site Health checks.
 */
class SiteHealth implements Service {

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
		add_filter( 'site_status_tests', array( $this, 'register_tests' ) );
	}

	/**
	 * Adds our custom status test.
	 *
	 * @param array<string, array<string, mixed>> $tests Status tests list.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_tests( array $tests ): array {
		$tests['direct']['pratiks_options_revisions_status'] = array(
			'label' => __( 'Pratiks Options Revisions Status', 'pratiks-options-revisions' ),
			'test'  => array( $this, 'run_test' ),
		);
		return $tests;
	}

	/**
	 * Performs the health check.
	 *
	 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
	 */
	public function run_test(): array {
		global $wpdb;

		$table_name = Database::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( ! $table_exists ) {
			return array(
				'label'       => __( 'Pratiks Options Revisions table is missing', 'pratiks-options-revisions' ),
				'status'      => 'critical',
				'badge'       => array(
					'label' => __( 'Database', 'pratiks-options-revisions' ),
					'color' => 'red',
				),
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__( 'The database table for tracking option revisions could not be found.', 'pratiks-options-revisions' )
				),
				'actions'     => '',
				'test'        => 'pratiks_options_revisions_status',
			);
		}

		$count          = $this->repository->count();
		$cron_scheduled = (bool) wp_next_scheduled( PruneTask::CRON_HOOK );

		return array(
			'label'       => sprintf(
				/* translators: %d total recorded revisions */
				__( 'Pratiks Options Revisions is active (%d revisions recorded)', 'pratiks-options-revisions' ),
				$count
			),
			'status'      => $cron_scheduled ? 'good' : 'recommended',
			'badge'       => array(
				'label' => __( 'Pratiks Options Revisions', 'pratiks-options-revisions' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s %s</p>',
				sprintf(
					/* translators: %d count */
					esc_html__( 'Currently tracking %d option revision records.', 'pratiks-options-revisions' ),
					$count
				),
				$cron_scheduled
					? esc_html__( 'Automated daily pruning is active.', 'pratiks-options-revisions' )
					: esc_html__( 'Automated pruning is not currently scheduled.', 'pratiks-options-revisions' )
			),
			'actions'     => '',
			'test'        => 'pratiks_options_revisions_status',
		);
	}
}
