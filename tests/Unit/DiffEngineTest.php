<?php
/**
 * DiffEngine unit tests.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Tests\Unit;

use Brain\Monkey\Functions;
use PratikLondhe\PratiksOptionsRevisions\Diff\DiffEngine;

/**
 * Tests DiffEngine formatting and diff rendering.
 */
class DiffEngineTest extends TestCase {

	public function test_format_value_handles_scalars_and_null(): void {
		$this->assertSame( '(null)', DiffEngine::format_value( null ) );
		$this->assertSame( 'true', DiffEngine::format_value( true ) );
		$this->assertSame( 'false', DiffEngine::format_value( false ) );
		$this->assertSame( '123', DiffEngine::format_value( 123 ) );
		$this->assertSame( 'hello world', DiffEngine::format_value( 'hello world' ) );
	}

	public function test_format_value_formats_arrays_as_json(): void {
		Functions\when( 'wp_json_encode' )->alias(
			function ( $data, $options = 0 ) {
				return json_encode( $data, $options );
			}
		);

		$data = array( 'site_url' => 'https://example.com', 'enabled' => true );
		$formatted = DiffEngine::format_value( $data );

		$this->assertStringContainsString( '"site_url": "https://example.com"', $formatted );
		$this->assertStringContainsString( '"enabled": true', $formatted );
	}

	public function test_format_value_formats_serialized_strings(): void {
		Functions\when( 'is_serialized' )->justReturn( true );
		Functions\when( 'maybe_unserialize' )->alias(
			function ( $data ) {
				return is_string( $data ) ? @unserialize( $data ) : $data;
			}
		);
		Functions\when( 'wp_json_encode' )->alias(
			function ( $data, $options = 0 ) {
				return json_encode( $data, $options );
			}
		);

		$array = array( 'key' => 'value' );
		$serialized = serialize( $array );

		$formatted = DiffEngine::format_value( $serialized );
		$this->assertStringContainsString( '"key": "value"', $formatted );
	}

	public function test_render_diff_uses_wp_text_diff(): void {
		Functions\when( 'wp_text_diff' )->justReturn( '<table class="diff"><tr><td>old</td><td>new</td></tr></table>' );

		$diff = DiffEngine::render_diff( 'old value', 'new value' );
		$this->assertStringContainsString( '<table class="diff">', $diff );
	}
}
