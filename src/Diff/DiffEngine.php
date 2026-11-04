<?php
/**
 * Diff generation engine.
 *
 * @package PratikLondhe\OptionsRevisions
 */

namespace PratikLondhe\OptionsRevisions\Diff;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes values and produces visual diffs for option revisions.
 */
class DiffEngine {

	/**
	 * Formats a raw value (string, serialized, or array) into readable text suitable for diffing.
	 *
	 * @param mixed $value The option value.
	 * @return string
	 */
	public static function format_value( $value ): string {
		if ( is_null( $value ) ) {
			return '(null)';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_scalar( $value ) ) {
			// Check if string is serialized PHP.
			if ( is_string( $value ) && is_serialized( $value ) ) {
				$unserialized = maybe_unserialize( $value );
				if ( $unserialized !== $value ) {
					$encoded = wp_json_encode( $unserialized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
					if ( is_string( $encoded ) ) {
						return $encoded;
					}
				}
			}
			return (string) $value;
		}

		// Array or Object.
		$json = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $json ) ? $json : (string) print_r( $value, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Fallback representation.
	}

	/**
	 * Generates an HTML diff table comparing two values using WordPress core's wp_text_diff().
	 *
	 * @param mixed                $old_value The original value.
	 * @param mixed                $new_value The revised value.
	 * @param array<string, mixed> $args Options passed to wp_text_diff.
	 * @return string HTML diff table.
	 */
	public static function render_diff( $old_value, $new_value, array $args = array() ): string {
		if ( ! function_exists( 'wp_text_diff' ) ) {
			require_once ABSPATH . WPINC . '/pluggable.php';
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}

		$old_text = self::format_value( $old_value );
		$new_text = self::format_value( $new_value );

		$defaults = array(
			'title'       => __( 'Differences', 'options-revisions' ),
			'title_left'  => __( 'Previous Value', 'options-revisions' ),
			'title_right' => __( 'New Value', 'options-revisions' ),
		);

		$diff_args = wp_parse_args( $args, $defaults );

		/**
		 * Filters the arguments passed into wp_text_diff.
		 *
		 * @param array<string, mixed> $diff_args
		 * @param mixed                $old_value
		 * @param mixed                $new_value
		 */
		$diff_args = apply_filters( 'options_revisions_diff_args', $diff_args, $old_value, $new_value );

		$diff = wp_text_diff( $old_text, $new_text, $diff_args );

		if ( empty( $diff ) ) {
			$diff = '<p class="optrev-no-diff">' . esc_html__( 'No visual differences detected.', 'options-revisions' ) . '</p>';
		}

		/**
		 * Filters the final diff HTML output.
		 *
		 * @param string $diff
		 * @param mixed  $old_value
		 * @param mixed  $new_value
		 */
		return (string) apply_filters( 'options_revisions_diff_output', $diff, $old_value, $new_value );
	}
}
