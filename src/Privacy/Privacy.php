<?php
/**
 * Privacy policy and personal data exporter/eraser.
 *
 * @package PratikLondhe\OptionsRevisions
 */

namespace PratikLondhe\OptionsRevisions\Privacy;

use PratikLondhe\OptionsRevisions\Service;
use PratikLondhe\OptionsRevisions\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Handles GDPR privacy text and personal data exporter/eraser.
 */
class Privacy implements Service {

	/**
	 * Registers service hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Suggests privacy policy content.
	 */
	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p>%s</p>',
			esc_html__(
				'This site records revisions of WordPress options and settings changes, including the user account ID and anonymized IP address of administrators who perform updates, to provide rollback and audit capabilities. Revision records are retained for a default of 30 days.',
				'options-revisions'
			)
		);

		wp_add_privacy_policy_content( __( 'Options Revisions', 'options-revisions' ), $content );
	}

	/**
	 * Registers the personal data exporter.
	 *
	 * @param array<string, array<string, string>> $exporters Exporters list.
	 * @return array<string, array<string, string>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['options-revisions'] = array(
			'exporter_friendly_name' => __( 'Options Revisions', 'options-revisions' ),
			'callback'               => array( $this, 'export_user_data' ),
		);
		return $exporters;
	}

	/**
	 * Exports personal data.
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          Page number.
	 * @return array{data: array<int, array{group_id: string, group_label: string, item_id: string, data: array<int, array{name: string, value: string}>}>, done: bool}
	 */
	public function export_user_data( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$table  = Database::table_name();
		$limit  = 50;
		$offset = ( $page - 1 ) * $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user->ID,
				$limit,
				$offset
			)
		);
		// phpcs:enable

		$export_items = array();

		if ( is_array( $records ) ) {
			foreach ( $records as $record ) {
				$export_items[] = array(
					'group_id'    => 'options_revisions',
					'group_label' => __( 'Options Revisions', 'options-revisions' ),
					'item_id'     => 'revision-' . $record->id,
					'data'        => array(
						array(
							'name'  => __( 'Option Name', 'options-revisions' ),
							'value' => (string) $record->option_name,
						),
						array(
							'name'  => __( 'Action', 'options-revisions' ),
							'value' => (string) $record->action_type,
						),
						array(
							'name'  => __( 'Date', 'options-revisions' ),
							'value' => (string) $record->created_at,
						),
					),
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => count( $export_items ) < $limit,
		);
	}

	/**
	 * Registers personal data eraser.
	 *
	 * @param array<string, array<string, string>> $erasers Erasers list.
	 * @return array<string, array<string, string>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['options-revisions'] = array(
			'eraser_friendly_name' => __( 'Options Revisions', 'options-revisions' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);
		return $erasers;
	}

	/**
	 * Erases/anonymizes personal data in revisions table.
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          Page number.
	 * @return array{items_removed: int, items_retained: int, messages: array<string>, done: bool}
	 */
	public function erase_user_data( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$table = Database::table_name();

		// Anonymize user_id and IP to 0 and empty string rather than deleting audit history.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET user_id = 0, user_ip = '' WHERE user_id = %d",
				$user->ID
			)
		);
		// phpcs:enable

		return array(
			'items_removed'  => false === $updated ? 0 : (int) $updated,
			'items_retained' => 0,
			'messages'       => array( __( 'Anonymized user attribution in options revisions.', 'options-revisions' ) ),
			'done'           => true,
		);
	}
}
