<?php
/**
 * Site Health check integration.
 *
 * @package PratikLondhe\OptionsRevisions
 */

namespace PratikLondhe\OptionsRevisions\Health;

use PratikLondhe\OptionsRevisions\Cron\PruneTask;
use PratikLondhe\OptionsRevisions\Service;
use PratikLondhe\OptionsRevisions\Storage\Database;
use PratikLondhe\OptionsRevisions\Storage\RevisionRepository;

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
		$tests['direct']['options_revisions_status'] = array(
			'label' => __( 'Options Revisions Status', 'options-revisions' ),
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
				'label'       => __( 'Options Revisions table is missing', 'options-revisions' ),
				'status'      => 'critical',
				'badge'       => array(
					'label' => __( 'Database', 'options-revisions' ),
					'color' => 'red',
				),
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__( 'The database table for tracking option revisions could not be found.', 'options-revisions' )
				),
				'actions'     => '',
				'test'        => 'options_revisions_status',
			);
		}

		$count          = $this->repository->count();
		$cron_scheduled = (bool) wp_next_scheduled( PruneTask::CRON_HOOK );

		return array(
			'label'       => sprintf(
				/* translators: %d total recorded revisions */
				__( 'Options Revisions is active (%d revisions recorded)', 'options-revisions' ),
				$count
			),
			'status'      => $cron_scheduled ? 'good' : 'recommended',
			'badge'       => array(
				'label' => __( 'Options Revisions', 'options-revisions' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s %s</p>',
				sprintf(
					/* translators: %d count */
					esc_html__( 'Currently tracking %d option revision records.', 'options-revisions' ),
					$count
				),
				$cron_scheduled
					? esc_html__( 'Automated daily pruning is active.', 'options-revisions' )
					: esc_html__( 'Automated pruning is not currently scheduled.', 'options-revisions' )
			),
			'actions'     => '',
			'test'        => 'options_revisions_status',
		);
	}
}
