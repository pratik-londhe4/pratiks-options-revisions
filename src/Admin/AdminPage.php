<?php
/**
 * Admin management page and AJAX endpoints.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Admin;

use PratikLondhe\PratiksOptionsRevisions\Diff\DiffEngine;
use PratikLondhe\PratiksOptionsRevisions\Rollback\RollbackHandler;
use PratikLondhe\PratiksOptionsRevisions\Service;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the wp-admin screen and AJAX diff requests.
 */
class AdminPage implements Service {

	public const MENU_SLUG = 'pratiks-options-revisions';

	/**
	 * Repository instance.
	 *
	 * @var RevisionRepository
	 */
	private RevisionRepository $repository;

	/**
	 * Rollback handler.
	 *
	 * @var RollbackHandler
	 */
	private RollbackHandler $rollback_handler;

	/**
	 * Constructor.
	 *
	 * @param RevisionRepository|null $repository       Optional repository.
	 * @param RollbackHandler|null    $rollback_handler Optional rollback handler.
	 */
	public function __construct(
		?RevisionRepository $repository = null,
		?RollbackHandler $rollback_handler = null
	) {
		$this->repository       = $repository ?? new RevisionRepository();
		$this->rollback_handler = $rollback_handler ?? new RollbackHandler( $this->repository );
	}

	/**
	 * Registers service hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_rollback_action' ) );
		add_action( 'wp_ajax_pratiks_options_revisions_get_diff', array( $this, 'ajax_get_diff' ) );
	}

	/**
	 * Returns the required capability for viewing and rolling back options.
	 *
	 * @return string
	 */
	public static function capability(): string {
		/**
		 * Filters the required capability for managing options revisions.
		 *
		 * @param string $capability Default 'manage_options'.
		 */
		return (string) apply_filters( 'pratiks_options_revisions_capability', 'manage_options' );
	}

	/**
	 * Registers Tools > Options Revisions menu.
	 */
	public function add_menu_page(): void {
		add_management_page(
			__( 'Options Revisions', 'pratiks-options-revisions' ),
			__( 'Options Revisions', 'pratiks-options-revisions' ),
			self::capability(),
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues CSS and JS on the revisions admin page.
	 *
	 * @param string $hook_suffix The current admin screen hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'pratiks-options-revisions-admin',
			plugins_url( 'assets/css/admin.css', dirname( __DIR__ ) ),
			array(),
			\PratikLondhe\PratiksOptionsRevisions\VERSION
		);

		wp_enqueue_script(
			'pratiks-options-revisions-admin',
			plugins_url( 'assets/js/admin.js', dirname( __DIR__ ) ),
			array( 'jquery', 'wp-hooks' ),
			\PratikLondhe\PratiksOptionsRevisions\VERSION,
			true
		);

		wp_localize_script(
			'pratiks-options-revisions-admin',
			'pratiksOptionsRevisionsL10n',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'diffNonce' => wp_create_nonce( 'pratiks_options_revisions_diff_nonce' ),
				'loading'   => __( 'Loading differences...', 'pratiks-options-revisions' ),
				'error'     => __( 'Could not load diff. Please try again.', 'pratiks-options-revisions' ),
				'close'     => __( 'Close', 'pratiks-options-revisions' ),
			)
		);
	}

	/**
	 * Handles HTTP GET rollback requests.
	 */
	public function handle_rollback_action(): void {
		if ( ! isset( $_GET['page'] ) || self::MENU_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || 'rollback' !== $_GET['action'] ) {
			return;
		}

		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to roll back options.', 'pratiks-options-revisions' ) );
		}

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'pratiks_options_revisions_rollback_' . $id );

		$record = $this->repository->get_by_id( $id );
		if ( ! $record ) {
			wp_die( esc_html__( 'Revision not found.', 'pratiks-options-revisions' ) );
		}

		if ( ! RollbackHandler::can_user_rollback_option( (string) $record->option_name ) ) {
			wp_die( esc_html__( 'You do not have permission to roll back this option.', 'pratiks-options-revisions' ) );
		}

		$success = $this->rollback_handler->rollback( $id );

		$redirect_url = add_query_arg(
			array(
				'page'   => self::MENU_SLUG,
				'rolled' => $success ? '1' : '0',
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler for fetching diff HTML.
	 */
	public function ajax_get_diff(): void {
		check_ajax_referer( 'pratiks_options_revisions_diff_nonce', 'nonce' );

		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pratiks-options-revisions' ) ), 403 );
		}

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$record = $this->repository->get_by_id( $id );

		if ( ! $record ) {
			wp_send_json_error( array( 'message' => __( 'Revision not found.', 'pratiks-options-revisions' ) ), 404 );
		}

		$diff_html = DiffEngine::render_diff(
			maybe_unserialize( $record->old_value ),
			maybe_unserialize( $record->new_value )
		);

		wp_send_json_success(
			array(
				'option'    => esc_html( (string) $record->option_name ),
				'diff_html' => $diff_html,
			)
		);
	}

	/**
	 * Renders the management page HTML.
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pratiks-options-revisions' ) );
		}

		$table = new RevisionsListTable( $this->repository );
		$table->prepare_items();
		?>
		<div class="wrap optrev-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Options Revisions', 'pratiks-options-revisions' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Track settings changes, compare unified diffs, and roll back any WordPress or plugin option with one click.', 'pratiks-options-revisions' ); ?>
			</p>

			<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( isset( $_GET['rolled'] ) ) : ?>
				<?php if ( '1' === $_GET['rolled'] ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Option was successfully rolled back.', 'pratiks-options-revisions' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e( 'Failed to roll back option.', 'pratiks-options-revisions' ); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			<?php // phpcs:enable ?>

			<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
				<?php $table->search_box( __( 'Search Options', 'pratiks-options-revisions' ), 'options_search' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>

		<!-- Diff Modal -->
		<div id="optrev-diff-modal" class="optrev-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="optrev-modal-title">
			<div class="optrev-modal-overlay"></div>
			<div class="optrev-modal-content">
				<div class="optrev-modal-header">
					<h2 id="optrev-modal-title"><?php esc_html_e( 'Option Diff', 'pratiks-options-revisions' ); ?></h2>
					<button type="button" class="optrev-modal-close" aria-label="<?php esc_attr_e( 'Close', 'pratiks-options-revisions' ); ?>">&times;</button>
				</div>
				<div class="optrev-modal-body">
					<div class="optrev-modal-spinner"></div>
					<div class="optrev-modal-diff"></div>
				</div>
			</div>
		</div>
		<?php
	}
}
