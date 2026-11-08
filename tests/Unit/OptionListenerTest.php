<?php
/**
 * OptionListener unit tests.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery;
use PratikLondhe\PratiksOptionsRevisions\Capture\OptionListener;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

/**
 * Tests option change listener.
 */
class OptionListenerTest extends TestCase {

	public function test_should_ignore_returns_true_for_denylisted_keys(): void {
		$listener = new OptionListener();

		$this->assertTrue( $listener->should_ignore( 'cron' ) );
		$this->assertTrue( $listener->should_ignore( '_transient_feed_cache' ) );
		$this->assertTrue( $listener->should_ignore( '_site_transient_update_plugins' ) );
		$this->assertTrue( $listener->should_ignore( 'pratiks_options_revisions_db_version' ) );
		$this->assertTrue( $listener->should_ignore( 'auth_key' ) );
		$this->assertFalse( $listener->should_ignore( 'blogname' ) );
		$this->assertFalse( $listener->should_ignore( 'woocommerce_currency' ) );
	}

	public function test_on_updated_option_does_not_record_when_values_are_identical(): void {
		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldNotReceive( 'insert' );

		$listener = new OptionListener( $repository );
		$listener->on_updated_option( 'blogname', 'My Site', 'My Site' );
	}

	public function test_on_updated_option_records_change_and_fires_action(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		Functions\when( 'current_time' )->justReturn( '2026-11-04 12:00:00' );
		Functions\when( 'maybe_serialize' )->returnArg();

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'insert' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return 'blogname' === $data['option_name']
							&& 'Old Title' === $data['old_value']
							&& 'New Title' === $data['new_value']
							&& 'updated' === $data['action_type']
							&& 42 === $data['user_id'];
					}
				)
			)
			->andReturn( 101 );

		Actions\expectDone( 'pratiks_options_revisions_recorded' )
			->once()
			->with( 'blogname', 'Old Title', 'New Title', 42, 101 );

		$listener = new OptionListener( $repository );
		$listener->on_updated_option( 'blogname', 'Old Title', 'New Title' );
	}

	public function test_on_deleted_option_captures_pre_delete_value(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 10 );
		Functions\when( 'current_time' )->justReturn( '2026-11-08 12:00:00' );
		Functions\when( 'maybe_serialize' )->returnArg();

		update_option( 'deprecated_opt', 'Goodbye World' );

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'insert' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return 'deprecated_opt' === $data['option_name']
							&& 'Goodbye World' === $data['old_value']
							&& is_null( $data['new_value'] )
							&& 'deleted' === $data['action_type'];
					}
				)
			)
			->andReturn( 102 );

		Actions\expectDone( 'pratiks_options_revisions_recorded' )
			->once()
			->with( 'deprecated_opt', 'Goodbye World', null, 10, 102 );

		$listener = new OptionListener( $repository );
		$listener->on_delete_option( 'deprecated_opt' );
		delete_option( 'deprecated_opt' );
		$listener->on_deleted_option( 'deprecated_opt' );
	}

	public function test_on_updated_option_ignores_when_is_restoring(): void {
		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldNotReceive( 'insert' );

		OptionListener::$is_restoring = true;
		try {
			$listener = new OptionListener( $repository );
			$listener->on_updated_option( 'blogname', 'Old Title', 'New Title' );
		} finally {
			OptionListener::$is_restoring = false;
		}
	}
}
