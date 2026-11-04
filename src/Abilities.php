<?php
/**
 * Abilities API integration for WordPress 6.9+ and MCP.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions;

use PratikLondhe\PratiksOptionsRevisions\Admin\AdminPage;
use PratikLondhe\PratiksOptionsRevisions\Diff\DiffEngine;
use PratikLondhe\PratiksOptionsRevisions\Rollback\RollbackHandler;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Abilities API tools for AI agents and REST clients.
 */
class Abilities implements Service {

	public const CATEGORY = 'pratiks-options-revisions';

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
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers the pratiks-options-revisions ability category.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Pratiks Options Revisions', 'pratiks-options-revisions' ),
				'description' => __( 'Manage and rollback settings and option revisions.', 'pratiks-options-revisions' ),
			)
		);
	}

	/**
	 * Registers the plugin's abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// 1. List revisions.
		wp_register_ability(
			'pratiks-options-revisions/list-revisions',
			array(
				'category'            => self::CATEGORY,
				'label'               => __( 'List Option Revisions', 'pratiks-options-revisions' ),
				'description'         => __( 'Lists recorded revisions for WordPress options.', 'pratiks-options-revisions' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'execute_callback'    => array( $this, 'execute_list' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option' => array(
							'type'        => 'string',
							'description' => 'Optional option name to filter by.',
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Maximum number of items to return.',
							'default'     => 20,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total' => array( 'type' => 'integer' ),
						'items' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly', 'idempotent' ),
					'show_in_rest' => true,
					'public'       => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);

		// 2. Get diff.
		wp_register_ability(
			'pratiks-options-revisions/get-diff',
			array(
				'category'            => self::CATEGORY,
				'label'               => __( 'Get Option Revision Diff', 'pratiks-options-revisions' ),
				'description'         => __( 'Returns the text and visual diff for a given revision ID.', 'pratiks-options-revisions' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'execute_callback'    => array( $this, 'execute_diff' ),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Revision record ID.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'old_value'   => array( 'type' => 'string' ),
						'new_value'   => array( 'type' => 'string' ),
						'diff_html'   => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly', 'idempotent' ),
					'show_in_rest' => true,
					'public'       => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);

		// 3. Rollback option.
		wp_register_ability(
			'pratiks-options-revisions/rollback-option',
			array(
				'category'            => self::CATEGORY,
				'label'               => __( 'Rollback Option Revision', 'pratiks-options-revisions' ),
				'description'         => __( 'Restores an option to its state before a specific revision was recorded.', 'pratiks-options-revisions' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'execute_callback'    => array( $this, 'execute_rollback' ),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Revision record ID to roll back.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'option_name' => array( 'type' => 'string' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'destructive' ),
					'show_in_rest' => true,
					'public'       => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);
	}

	/**
	 * Permission check matching admin capability.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( AdminPage::capability() );
	}

	/**
	 * Executes list revisions ability.
	 *
	 * @param array<string, mixed> $input Input arguments.
	 * @return array<string, mixed>
	 */
	public function execute_list( array $input ): array {
		$args = array(
			'option_name' => $input['option'] ?? '',
			'limit'       => isset( $input['limit'] ) ? absint( $input['limit'] ) : 20,
			'order'       => 'DESC',
		);

		return array(
			'total' => $this->repository->count( $args ),
			'items' => $this->repository->query( $args ),
		);
	}

	/**
	 * Executes diff ability.
	 *
	 * @param array<string, mixed> $input Input arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_diff( array $input ) {
		$id     = absint( $input['id'] );
		$record = $this->repository->get_by_id( $id );

		if ( ! $record ) {
			return new \WP_Error( 'not_found', __( 'Revision not found.', 'pratiks-options-revisions' ), array( 'status' => 404 ) );
		}

		$old_text = DiffEngine::format_value( maybe_unserialize( $record->old_value ) );
		$new_text = DiffEngine::format_value( maybe_unserialize( $record->new_value ) );

		return array(
			'option_name' => (string) $record->option_name,
			'old_value'   => $old_text,
			'new_value'   => $new_text,
			'diff_html'   => DiffEngine::render_diff(
				maybe_unserialize( $record->old_value ),
				maybe_unserialize( $record->new_value )
			),
		);
	}

	/**
	 * Executes rollback ability.
	 *
	 * @param array<string, mixed> $input Input arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_rollback( array $input ) {
		$id     = absint( $input['id'] );
		$record = $this->repository->get_by_id( $id );

		if ( ! $record ) {
			return new \WP_Error( 'not_found', __( 'Revision not found.', 'pratiks-options-revisions' ), array( 'status' => 404 ) );
		}

		$success = $this->rollback_handler->rollback( $id );

		if ( ! $success ) {
			return new \WP_Error( 'rollback_failed', __( 'Failed to roll back option.', 'pratiks-options-revisions' ), array( 'status' => 500 ) );
		}

		return array(
			'success'     => true,
			'option_name' => (string) $record->option_name,
			'message'     => sprintf(
				/* translators: %s option name */
				__( "Option '%s' rolled back successfully.", 'pratiks-options-revisions' ),
				$record->option_name
			),
		);
	}
}
