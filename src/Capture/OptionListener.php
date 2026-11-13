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
	 * Temporary store for option values prior to deletion.
	 *
	 * @var array<string, mixed>
	 */
	private array $pre_delete_cache = array();

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
		add_action( 'delete_option', array( $this, 'on_delete_option' ), 10, 1 );
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
		if ( self::$is_restoring || $this->should_ignore( $option, $value ) ) {
			return;
		}

		// Don't record if values are identical.
		if ( $old_value === $value ) {
			return;
		}

		// Don't record if either old or new value contains sensitive credentials.
		if ( self::contains_sensitive_data( $old_value ) || self::contains_sensitive_data( $value ) ) {
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
		if ( self::$is_restoring || $this->should_ignore( $option, $value ) ) {
			return;
		}

		// Don't record if value contains sensitive credentials.
		if ( self::contains_sensitive_data( $value ) ) {
			return;
		}

		$this->record_change( $option, null, $value, 'added' );
	}

	/**
	 * Handles delete_option action before an option is removed.
	 *
	 * Captures the current value so that a deletion event retains the previous state for rollback,
	 * provided the value does not contain embedded credentials or secrets.
	 *
	 * @param string $option Option name.
	 */
	public function on_delete_option( string $option ): void {
		if ( self::$is_restoring || $this->should_ignore( $option ) ) {
			return;
		}

		$value = get_option( $option );

		// Do not retain deleted options that contain credentials embedded in settings arrays.
		if ( self::contains_sensitive_data( $value ) ) {
			return;
		}

		$this->pre_delete_cache[ $option ] = $value;
	}

	/**
	 * Handles deleted_option action once deletion has completed.
	 *
	 * @param string $option Option name.
	 */
	public function on_deleted_option( string $option ): void {
		if ( self::$is_restoring || $this->should_ignore( $option ) ) {
			unset( $this->pre_delete_cache[ $option ] );
			return;
		}

		if ( ! array_key_exists( $option, $this->pre_delete_cache ) ) {
			return;
		}

		$old_val = $this->pre_delete_cache[ $option ];
		unset( $this->pre_delete_cache[ $option ] );

		// Do not retain deleted options with embedded credentials in revision history.
		if ( self::contains_sensitive_data( $old_val ) ) {
			return;
		}

		$this->record_change( $option, $old_val, null, 'deleted' );
	}

	/**
	 * Checks whether an option should be ignored from revisions tracking.
	 *
	 * @param string     $option Option name.
	 * @param mixed|null $value  Optional option value to inspect for embedded credentials.
	 * @return bool
	 */
	public function should_ignore( string $option, $value = null ): bool {
		if ( function_exists( 'wp_installing' ) && wp_installing() ) {
			return true;
		}

		if ( ! is_null( $value ) && self::contains_sensitive_data( $value ) ) {
			return true;
		}

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

		// Exclude user roles (single site and multisite prefixed: e.g. wp_user_roles, wp_2_user_roles).
		if ( false !== strpos( $option, 'user_roles' ) ) {
			return true;
		}

		// Exclude options containing secrets, tokens, credentials, and API keys.
		$sensitive_patterns = array(
			'api_key',
			'apikey',
			'secret',
			'password',
			'token',
			'private_key',
			'license_key',
			'credential',
			'auth_key',
			'salt',
			'ai_connector',
		);

		$lower = strtolower( $option );
		foreach ( $sensitive_patterns as $pattern ) {
			if ( false !== strpos( $lower, $pattern ) ) {
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
	 * Checks whether an array key or object property name denotes a credential or secret.
	 *
	 * @param string $key   The key or property name.
	 * @param mixed  $value The value associated with the key (optional).
	 * @return bool True if the key represents sensitive credential data.
	 */
	public static function is_sensitive_key( string $key, $value = null ): bool {
		$lower = strtolower( trim( $key ) );

		// Skip non-string or boolean flags (e.g. 'pass' => true).
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return false;
		}

		$patterns = array(
			'api_key',
			'apikey',
			'api_secret',
			'secret',
			'secret_key',
			'client_secret',
			'password',
			'pwd',
			'token',
			'access_token',
			'refresh_token',
			'auth_token',
			'auth_key',
			'private_key',
			'license_key',
			'credential',
			'credentials',
			'salt',
			'webhook_secret',
			'signing_secret',
			'consumer_key',
			'consumer_secret',
			'smtp_pass',
			'smtp_password',
			'encryption_key',
			'security_key',
			'app_password',
			'ai_connector',
		);

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $lower, $pattern ) ) {
				return true;
			}
		}

		// Exact match for 'pass' if value is a string (e.g. password).
		if ( 'pass' === $lower && is_string( $value ) && '' !== $value ) {
			return true;
		}

		return false;
	}

	/**
	 * Recursively checks whether an option value (scalar, array, or object)
	 * contains embedded credentials, API keys, tokens, or secrets.
	 *
	 * @param mixed $value The option value to inspect.
	 * @return bool True if sensitive credentials are found.
	 */
	public static function contains_sensitive_data( $value ): bool {
		if ( is_null( $value ) || is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return false;
		}

		if ( is_string( $value ) ) {
			// Check for private key certificates.
			if ( false !== strpos( $value, 'PRIVATE KEY' ) ) {
				return true;
			}

			// Check if serialized PHP string.
			if ( is_serialized( $value ) ) {
				$unserialized = maybe_unserialize( $value );
				if ( $unserialized !== $value ) {
					return self::contains_sensitive_data( $unserialized );
				}
			}

			// Check if JSON-encoded string containing sensitive data.
			$trimmed = trim( $value );
			if ( 0 === strpos( $trimmed, '{' ) || 0 === strpos( $trimmed, '[' ) ) {
				$decoded = json_decode( $trimmed, true );
				if ( is_array( $decoded ) && json_last_error() === JSON_ERROR_NONE ) {
					return self::contains_sensitive_data( $decoded );
				}
			}

			return false;
		}

		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				if ( is_string( $k ) && self::is_sensitive_key( $k, $v ) ) {
					if ( ! empty( $v ) ) {
						return true;
					}
				}

				if ( self::contains_sensitive_data( $v ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Recursively scrubs and redacts any sensitive credential keys in an array or object.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed Sanitized value with credentials replaced by '[REDACTED]'.
	 */
	public static function redact_sensitive_data( $value ) {
		if ( is_object( $value ) ) {
			$vars = get_object_vars( $value );
			foreach ( $vars as $k => $v ) {
				if ( is_string( $k ) && self::is_sensitive_key( $k, $v ) ) {
					$value->$k = '[REDACTED]';
				} else {
					$value->$k = self::redact_sensitive_data( $v );
				}
			}
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				if ( is_string( $k ) && self::is_sensitive_key( $k, $v ) ) {
					$value[ $k ] = '[REDACTED]';
				} else {
					$value[ $k ] = self::redact_sensitive_data( $v );
				}
			}
			return $value;
		}

		return $value;
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
		// Defense in depth: Never record any change containing sensitive credentials.
		if ( self::contains_sensitive_data( $old_val ) || self::contains_sensitive_data( $new_val ) ) {
			return;
		}

		$old_sanitized  = is_null( $old_val ) ? null : self::redact_sensitive_data( $old_val );
		$new_sanitized  = is_null( $new_val ) ? null : self::redact_sensitive_data( $new_val );
		$old_serialized = is_null( $old_sanitized ) ? null : maybe_serialize( $old_sanitized );
		$new_serialized = is_null( $new_sanitized ) ? null : maybe_serialize( $new_sanitized );

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
