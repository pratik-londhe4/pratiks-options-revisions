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
}
