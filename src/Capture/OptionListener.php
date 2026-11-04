<?php
/**
 * Option change listener and snapshot capture.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Capture;

use PratikLondhe\PratiksOptionsRevisions\Service;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Listens to core option hooks and records revisions.
 */
class OptionListener implements Service {

	/**
	 * Flag indicating whether an active rollback is taking place.
	 *
	 * @var bool
	 */
	public static bool $is_restoring = false;

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
	 * Registers hook listeners.
	 */
	public function register(): void {
		add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );
		add_action( 'added_option', array( $this, 'on_added_option' ), 10, 2 );
		add_action( 'deleted_option', array( $this, 'on_deleted_option' ), 10, 1 );
	}

	/**
	 * Handles updated_option action.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old option value.
	 * @param mixed  $value     New option value.
	 */
	public function on_updated_option( string $option, $old_value, $value ): void {
		if ( self::$is_restoring || $this->should_ignore( $option ) ) {
			return;
		}

		// Don't record if values are identical.
		if ( $old_value === $value ) {
			return;
		}

		$this->record_change( $option, $old_value, $value, 'updated' );
	}

	/**
	 * Handles added_option action.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New option value.
	 */
	public function on_added_option( string $option, $value ): void {
		if ( self::$is_restoring || $this->should_ignore( $option ) ) {
			return;
		}

		$this->record_change( $option, null, $value, 'added' );
	}

	/**
	 * Handles deleted_option action.
	 *
	 * @param string $option Option name.
	 */
	public function on_deleted_option( string $option ): void {
		if ( self::$is_restoring || $this->should_ignore( $option ) ) {
			return;
		}

		$this->record_change( $option, null, null, 'deleted' );
	}

	/**
	 * Checks whether an option should be ignored from revisions tracking.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	public function should_ignore( string $option ): bool {
		$ignored_exact = array(
			'cron',
			'pratiks_options_revisions_db_version',
			'auth_key',
			'secure_auth_key',
			'logged_in_key',
			'nonce_key',
			'auth_salt',
			'secure_auth_salt',
			'logged_in_salt',
			'nonce_salt',
			'session_tokens',
			'auto_updater.lock',
			'core_updater.lock',
			'wp_user_roles',
		);

		if ( in_array( $option, $ignored_exact, true ) ) {
			return true;
		}

		// Check wildcards / prefixes.
		$wildcard_prefixes = array(
			'_transient_',
			'_site_transient_',
			'pratiks_options_revisions_',
			'action_scheduler_',
			'recovery_keys_',
		);

		foreach ( $wildcard_prefixes as $prefix ) {
			if ( 0 === strpos( $option, $prefix ) ) {
				return true;
			}
		}

		/**
		 * Filters whether a given option should be excluded from revision capture.
		 *
		 * @param bool   $ignore Whether to ignore tracking.
		 * @param string $option The option name.
		 */
		return (bool) apply_filters( 'pratiks_options_revisions_should_ignore', false, $option );
	}

	/**
	 * Serializes and writes a revision record.
	 *
	 * @param string $option      Option name.
	 * @param mixed  $old_val     Previous value.
	 * @param mixed  $new_val     New value.
	 * @param string $action_type Action type (updated, added, deleted).
	 */
	private function record_change( string $option, $old_val, $new_val, string $action_type ): void {
		$old_serialized = is_null( $old_val ) ? null : maybe_serialize( $old_val );
		$new_serialized = is_null( $new_val ) ? null : maybe_serialize( $new_val );

		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$user_ip = '';

		// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw_ip    = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$validated = filter_var( $raw_ip, FILTER_VALIDATE_IP );
			if ( false !== $validated ) {
				$user_ip = function_exists( 'wp_privacy_anonymize_ip' ) ? wp_privacy_anonymize_ip( $validated ) : $validated;
			}
		}
		// phpcs:enable

		$data = array(
			'option_name' => $option,
			'old_value'   => $old_serialized,
			'new_value'   => $new_serialized,
			'action_type' => $action_type,
			'user_id'     => $user_id,
			'user_ip'     => $user_ip,
			'created_at'  => current_time( 'mysql', true ),
		);

		$revision_id = $this->repository->insert( $data );

		if ( $revision_id > 0 ) {
			/**
			 * Fires after an option revision has been recorded.
			 *
			 * @param string $option      Option name.
			 * @param mixed  $old_val     Original value.
			 * @param mixed  $new_val     New value.
			 * @param int    $user_id     Actor user ID.
			 * @param int    $revision_id Inserted revision database ID.
			 */
			do_action( 'pratiks_options_revisions_recorded', $option, $old_val, $new_val, $user_id, $revision_id );
		}
	}
}
