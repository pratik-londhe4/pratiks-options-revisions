<?php
/**
 * WP_List_Table implementation for options revisions.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Admin;

use PratikLondhe\PratiksOptionsRevisions\Rollback\RollbackHandler;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the revisions table in wp-admin.
 */
class RevisionsListTable extends \WP_List_Table {

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
		parent::__construct(
			array(
				'singular' => 'option_revision',
				'plural'   => 'option_revisions',
				'ajax'     => false,
			)
		);
		$this->repository = $repository ?? new RevisionRepository();
	}

	/**
	 * Defines table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'option_name' => __( 'Option Name', 'pratiks-options-revisions' ),
			'action_type' => __( 'Event', 'pratiks-options-revisions' ),
			'changed_by'  => __( 'Modified By', 'pratiks-options-revisions' ),
			'created_at'  => __( 'Date & Time', 'pratiks-options-revisions' ),
			'actions'     => __( 'Actions', 'pratiks-options-revisions' ),
		);
	}

	/**
	 * Defines sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return array(
			'option_name' => array( 'option_name', false ),
			'created_at'  => array( 'created_at', true ),
		);
	}

	/**
	 * Prepares data items for display.
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'search' => $search,
			'limit'  => $per_page,
			'offset' => ( $current_page - 1 ) * $per_page,
			'order'  => 'DESC',
		);

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$total_items = $this->repository->count( array( 'search' => $search ) );
		$this->items = $this->repository->query( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param object $item        Record row object.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
	}

	/**
	 * Renders option_name column with row actions.
	 *
	 * @param object $item Record row object.
	 * @return string
	 */
	public function column_option_name( $item ): string {
		$name = esc_html( (string) $item->option_name );
		return sprintf( '<strong>%s</strong>', $name );
	}

	/**
	 * Renders action_type column.
	 *
	 * @param object $item Record row object.
	 * @return string
	 */
	public function column_action_type( $item ): string {
		$type  = (string) $item->action_type;
		$label = ucfirst( $type );
		$class = 'optrev-badge optrev-badge-' . sanitize_html_class( $type );
		return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), esc_html( $label ) );
	}

	/**
	 * Renders changed_by column.
	 *
	 * @param object $item Record row object.
	 * @return string
	 */
	public function column_changed_by( $item ): string {
		$user_id = absint( $item->user_id );
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				return esc_html( $user->display_name );
			}
			return sprintf( /* translators: %d user ID */ esc_html__( 'User #%d', 'pratiks-options-revisions' ), $user_id );
		}

		return esc_html__( 'System / CLI / Guest', 'pratiks-options-revisions' );
	}

	/**
	 * Renders created_at column.
	 *
	 * @param object $item Record row object.
	 * @return string
	 */
	public function column_created_at( $item ): string {
		$timestamp = strtotime( (string) $item->created_at );
		if ( ! $timestamp ) {
			return esc_html( (string) $item->created_at );
		}

		$date_format = (string) get_option( 'date_format', 'Y-m-d' );
		$time_format = (string) get_option( 'time_format', 'H:i' );
		return esc_html( wp_date( $date_format . ' ' . $time_format, $timestamp ) );
	}

	/**
	 * Renders actions column (View Diff, Rollback).
	 *
	 * @param object $item Record row object.
	 * @return string
	 */
	public function column_actions( $item ): string {
		$id = absint( $item->id );

		$diff_btn = sprintf(
			'<button type="button" class="button button-small optrev-view-diff" data-id="%d">%s</button>',
			$id,
			esc_html__( 'View Diff', 'pratiks-options-revisions' )
		);

		if ( ! RollbackHandler::can_user_rollback_option( (string) $item->option_name ) ) {
			return $diff_btn;
		}

		$rollback_nonce = wp_create_nonce( 'pratiks_options_revisions_rollback_' . $id );
		$rollback_url   = add_query_arg(
			array(
				'page'     => 'pratiks-options-revisions',
				'action'   => 'rollback',
				'id'       => $id,
				'_wpnonce' => $rollback_nonce,
			),
			admin_url( 'tools.php' )
		);

		$rollback_btn = sprintf(
			'<a href="%s" class="button button-small optrev-rollback-btn" data-id="%d" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( $rollback_url ),
			$id,
			esc_js( __( 'Are you sure you want to revert this option to its prior value?', 'pratiks-options-revisions' ) ),
			esc_html__( 'Rollback', 'pratiks-options-revisions' )
		);

		return $diff_btn . ' ' . $rollback_btn;
	}
}
