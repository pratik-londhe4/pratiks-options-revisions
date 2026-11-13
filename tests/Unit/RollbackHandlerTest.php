<?php
/**
 * RollbackHandler unit tests.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery;
use PratikLondhe\PratiksOptionsRevisions\Capture\OptionListener;
use PratikLondhe\PratiksOptionsRevisions\Rollback\RollbackHandler;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

/**
 * Tests option rollback execution.
 */
class RollbackHandlerTest extends TestCase {

	public function test_rollback_returns_false_when_record_not_found(): void {
		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 999 )->andReturn( null );

		$handler = new RollbackHandler( $repository );
		$this->assertFalse( $handler->rollback( 999 ) );
	}

	public function test_rollback_restores_old_value_and_fires_action(): void {
		Functions\when( 'maybe_unserialize' )->returnArg();

		$record              = new \stdClass();
		$record->id          = 5;
		$record->option_name = 'site_desc';
		$record->old_value   = 'Just another site';
		$record->new_value   = 'New tagline';
		$record->action_type = 'updated';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 5 )->andReturn( $record );

		Actions\expectDone( 'pratiks_options_revisions_rolled_back' )
			->once()
			->with( 'site_desc', 'Just another site', 5 );

		$handler = new RollbackHandler( $repository );
		$result  = $handler->rollback( 5 );

		$this->assertTrue( $result );
		$this->assertSame( 'Just another site', get_option( 'site_desc' ) );
		$this->assertFalse( OptionListener::$is_restoring, 'is_restoring flag should be reset to false' );
	}

	public function test_rollback_deletes_option_if_originally_added(): void {
		update_option( 'newly_added', 'some_val' );

		$record              = new \stdClass();
		$record->id          = 10;
		$record->option_name = 'newly_added';
		$record->old_value   = null;
		$record->new_value   = 'some_val';
		$record->action_type = 'added';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 10 )->andReturn( $record );

		Actions\expectDone( 'pratiks_options_revisions_rolled_back' )
			->once()
			->with( 'newly_added', null, 10 );

		$handler = new RollbackHandler( $repository );
		$result  = $handler->rollback( 10 );

		$this->assertTrue( $result );
		$this->assertFalse( get_option( 'newly_added' ) );
	}

	public function test_rollback_restores_option_if_originally_deleted(): void {
		Functions\when( 'maybe_unserialize' )->returnArg();

		$record              = new \stdClass();
		$record->id          = 15;
		$record->option_name = 'restored_key';
		$record->old_value   = 'saved_before_deletion';
		$record->new_value   = null;
		$record->action_type = 'deleted';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 15 )->andReturn( $record );

		Actions\expectDone( 'pratiks_options_revisions_rolled_back' )
			->once()
			->with( 'restored_key', 'saved_before_deletion', 15 );

		$handler = new RollbackHandler( $repository );
		$result  = $handler->rollback( 15 );

		$this->assertTrue( $result );
		$this->assertSame( 'saved_before_deletion', get_option( 'restored_key' ) );
	}

	public function test_rollback_fails_if_deleted_record_has_no_prior_value(): void {
		update_option( 'recreated_option', 'current_value' );

		$record              = new \stdClass();
		$record->id          = 16;
		$record->option_name = 'recreated_option';
		$record->old_value   = null;
		$record->new_value   = null;
		$record->action_type = 'deleted';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 16 )->andReturn( $record );

		$handler = new RollbackHandler( $repository );
		$result  = $handler->rollback( 16 );

		$this->assertFalse( $result );
		// Ensure it was NOT deleted!
		$this->assertSame( 'current_value', get_option( 'recreated_option' ) );
	}

	public function test_rollback_active_plugins_requires_activate_plugins_capability(): void {
		Functions\when( 'current_user_can' )->alias(
			function ( $cap ) {
				if ( 'activate_plugins' === $cap ) {
					return false;
				}
				return true;
			}
		);

		$record              = new \stdClass();
		$record->id          = 20;
		$record->option_name = 'active_plugins';
		$record->old_value   = serialize( array( 'akismet/akismet.php' ) );
		$record->new_value   = serialize( array() );
		$record->action_type = 'updated';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 20 )->andReturn( $record );

		$handler = new RollbackHandler( $repository );
		$result  = $handler->rollback( 20 );

		$this->assertFalse( $result );
	}

	public function test_rollback_stylesheet_requires_switch_themes_capability(): void {
		Functions\when( 'current_user_can' )->alias(
			function ( $cap ) {
				if ( 'switch_themes' === $cap ) {
					return false;
				}
				return true;
			}
		);

		$record              = new \stdClass();
		$record->id          = 21;
		$record->option_name = 'stylesheet';
		$record->old_value   = 'twentytwentythree';
		$record->new_value   = 'twentytwentyfour';
		$record->action_type = 'updated';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 21 )->andReturn( $record );

		$handler = new RollbackHandler( $repository );
		$this->assertFalse( $handler->rollback( 21 ) );
	}

	public function test_rollback_prefixed_user_roles_requires_promote_users_capability(): void {
		Functions\when( 'current_user_can' )->alias(
			function ( $cap ) {
				if ( 'promote_users' === $cap ) {
					return false;
				}
				return true;
			}
		);

		$record              = new \stdClass();
		$record->id          = 22;
		$record->option_name = 'wp_2_user_roles';
		$record->old_value   = serialize( array( 'administrator' => array() ) );
		$record->new_value   = serialize( array() );
		$record->action_type = 'updated';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 22 )->andReturn( $record );

		$handler = new RollbackHandler( $repository );
		$this->assertFalse( $handler->rollback( 22 ) );
	}

	public function test_rollback_refuses_to_restore_sensitive_credentials(): void {
		Functions\when( 'maybe_unserialize' )->returnArg();

		$record              = new \stdClass();
		$record->id          = 23;
		$record->option_name = 'custom_settings';
		$record->old_value   = array( 'api_key' => 'secret_123' );
		$record->new_value   = array( 'api_key' => 'secret_456' );
		$record->action_type = 'updated';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 23 )->andReturn( $record );

		$handler = new RollbackHandler( $repository );
		$this->assertFalse( $handler->rollback( 23 ) );
	}
}
