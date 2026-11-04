<?php
/**
 * Revision storage repository.
 *
 * @package PratikLondhe\OptionsRevisions
 */

namespace PratikLondhe\OptionsRevisions\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Repository for reading and writing options revision records.
 */
class RevisionRepository {

	/**
	 * Inserts a new revision record into the database.
	 *
	 * @param array<string, mixed> $data Revision data.
	 * @return int The inserted record ID, or 0 on failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$table = Database::table_name();

		$defaults = array(
			'option_name' => '',
			'old_value'   => null,
			'new_value'   => null,
			'action_type' => 'updated',
			'user_id'     => 0,
			'user_ip'     => '',
			'created_at'  => current_time( 'mysql', true ),
		);

		$row = wp_parse_args( $data, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'option_name' => sanitize_text_field( (string) $row['option_name'] ),
				'old_value'   => is_null( $row['old_value'] ) ? null : (string) $row['old_value'],
				'new_value'   => is_null( $row['new_value'] ) ? null : (string) $row['new_value'],
				'action_type' => sanitize_key( (string) $row['action_type'] ),
				'user_id'     => absint( $row['user_id'] ),
				'user_ip'     => sanitize_text_field( (string) $row['user_ip'] ),
				'created_at'  => (string) $row['created_at'],
			),
			array(
				'%s',
				is_null( $row['old_value'] ) ? null : '%s',
				is_null( $row['new_value'] ) ? null : '%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Retrieves a single revision record by its ID.
	 *
	 * @param int $id Revision ID.
	 * @return object|null Record object or null if not found.
	 */
	public function get_by_id( int $id ): ?object {
		global $wpdb;

		$table = Database::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );

		return is_object( $record ) ? $record : null;
	}

	/**
	 * Queries revisions matching optional criteria.
	 *
	 * @param array<string, mixed> $args Query arguments (option_name, search, user_id, limit, offset, order).
	 * @return array<int, object>
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'option_name' => '',
			'search'      => '',
			'user_id'     => null,
			'limit'       => 20,
			'offset'      => 0,
			'order'       => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$table  = Database::table_name();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['option_name'] ) ) {
			$where[]  = 'option_name = %s';
			$params[] = sanitize_text_field( (string) $args['option_name'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'option_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
		}

		if ( ! is_null( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = absint( $args['user_id'] );
		}

		$order  = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$limit  = max( 1, absint( $args['limit'] ) );
		$offset = max( 0, absint( $args['offset'] ) );

		$where_clause = implode( ' AND ', $where );
		$sql          = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY id {$order} LIMIT %d OFFSET %d";
		$params[]     = $limit;
		$params[]     = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Counts total revisions matching criteria.
	 *
	 * @param array<string, mixed> $args Criteria (option_name, search, user_id).
	 * @return int
	 */
	public function count( array $args = array() ): int {
		global $wpdb;

		$table  = Database::table_name();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['option_name'] ) ) {
			$where[]  = 'option_name = %s';
			$params[] = sanitize_text_field( (string) $args['option_name'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'option_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = absint( $args['user_id'] );
		}

		$where_clause = implode( ' AND ', $where );
		$sql          = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $params ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		} else {
			$total = (int) $wpdb->get_var( $sql );
		}
		// phpcs:enable

		return $total;
	}

	/**
	 * Prunes revisions older than a given number of days.
	 *
	 * @param int $days Number of retention days.
	 * @return int Number of deleted rows.
	 */
	public function prune_older_than( int $days ): int {
		global $wpdb;

		$days   = max( 1, $days );
		$table  = Database::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Prunes revisions exceeding the maximum count per option.
	 *
	 * @param int $max_per_option Maximum revisions to keep per option.
	 * @return int Number of deleted rows.
	 */
	public function prune_excess( int $max_per_option ): int {
		global $wpdb;

		$max_per_option = max( 1, $max_per_option );
		$table          = Database::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$options = $wpdb->get_col( "SELECT DISTINCT option_name FROM {$table}" );
		if ( empty( $options ) ) {
			return 0;
		}

		$total_deleted = 0;

		foreach ( $options as $option_name ) {
			// Find IDs beyond the most recent $max_per_option for this option.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids_to_delete = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE option_name = %s ORDER BY id DESC LIMIT 1000 OFFSET %d",
					$option_name,
					$max_per_option
				)
			);

			if ( ! empty( $ids_to_delete ) ) {
				$format_ids = implode( ',', array_map( 'absint', $ids_to_delete ) );
				$deleted    = $wpdb->query( "DELETE FROM {$table} WHERE id IN ({$format_ids})" );
				if ( false !== $deleted ) {
					$total_deleted += (int) $deleted;
				}
			}
			// phpcs:enable
		}

		return $total_deleted;
	}
}
