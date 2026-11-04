<?php
/**
 * WP-CLI command integration.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Cli;

use PratikLondhe\PratiksOptionsRevisions\Diff\DiffEngine;
use PratikLondhe\PratiksOptionsRevisions\Rollback\RollbackHandler;
use PratikLondhe\PratiksOptionsRevisions\Service;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Manage and rollback options revisions via WP-CLI.
 */
class Command implements Service {

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
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'pratiks-options-revisions', $this );
		}
	}

	/**
	 * Lists option revisions.
	 *
	 * ## OPTIONS
	 *
	 * [--option=<name>]
	 * : Filter by option name.
	 *
	 * [--search=<text>]
	 * : Search option names.
	 *
	 * [--limit=<n>]
	 * : Number of records to return. Default 20.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv, yaml, ids, count). Default table.
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function list( array $args, array $assoc_args ): void {
		$query_args = array(
			'option_name' => $assoc_args['option'] ?? '',
			'search'      => $assoc_args['search'] ?? '',
			'limit'       => isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20,
			'order'       => 'DESC',
		);

		$format = $assoc_args['format'] ?? 'table';

		if ( 'count' === $format ) {
			\WP_CLI::line( (string) $this->repository->count( $query_args ) );
			return;
		}

		$items = $this->repository->query( $query_args );

		if ( 'ids' === $format ) {
			$ids = array_map( static fn( $item ) => $item->id, $items );
			\WP_CLI::line( implode( ' ', $ids ) );
			return;
		}

		$formatter = new \WP_CLI\Formatter(
			$assoc_args,
			array( 'id', 'option_name', 'action_type', 'user_id', 'created_at' )
		);
		$formatter->display_items( $items );
	}

	/**
	 * Displays the diff for a revision.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The revision ID.
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function diff( array $args, array $assoc_args ): void {
		$id     = absint( $args[0] );
		$record = $this->repository->get_by_id( $id );

		if ( ! $record ) {
			\WP_CLI::error( sprintf( 'Revision %d not found.', $id ) );
		}

		$old_text = DiffEngine::format_value( maybe_unserialize( $record->old_value ) );
		$new_text = DiffEngine::format_value( maybe_unserialize( $record->new_value ) );

		\WP_CLI::line( \WP_CLI::colorize( "%B=== Option: {$record->option_name} (Revision #{$id}) ===%n" ) );
		\WP_CLI::line( \WP_CLI::colorize( '%R--- Previous Value%n' ) );
		\WP_CLI::line( $old_text );
		\WP_CLI::line( \WP_CLI::colorize( '%G+++ New Value%n' ) );
		\WP_CLI::line( $new_text );
	}

	/**
	 * Rolls back an option to its state before a revision.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The revision ID.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function rollback( array $args, array $assoc_args ): void {
		$id     = absint( $args[0] );
		$record = $this->repository->get_by_id( $id );

		if ( ! $record ) {
			\WP_CLI::error( sprintf( 'Revision %d not found.', $id ) );
		}

		\WP_CLI::confirm(
			sprintf( "Are you sure you want to roll back '%s' to revision #%d?", $record->option_name, $id ),
			$assoc_args
		);

		$success = $this->rollback_handler->rollback( $id );

		if ( $success ) {
			\WP_CLI::success( sprintf( "Option '%s' rolled back successfully.", $record->option_name ) );
		} else {
			\WP_CLI::error( sprintf( "Failed to roll back option '%s'.", $record->option_name ) );
		}
	}

	/**
	 * Prunes revisions older than specified days.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<n>]
	 * : Number of days to keep. Default 30.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function purge( array $args, array $assoc_args ): void {
		$days = isset( $assoc_args['days'] ) ? absint( $assoc_args['days'] ) : 30;

		\WP_CLI::confirm(
			sprintf( 'Are you sure you want to delete revisions older than %d days?', $days ),
			$assoc_args
		);

		$deleted = $this->repository->prune_older_than( $days );
		\WP_CLI::success( sprintf( 'Purged %d revision records.', $deleted ) );
	}
}
