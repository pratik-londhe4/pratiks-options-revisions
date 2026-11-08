<?php
/**
 * Abilities unit tests.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

namespace PratikLondhe\PratiksOptionsRevisions\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PratikLondhe\PratiksOptionsRevisions\Abilities;
use PratikLondhe\PratiksOptionsRevisions\Rollback\RollbackHandler;
use PratikLondhe\PratiksOptionsRevisions\Storage\RevisionRepository;

/**
 * Tests Abilities API execution callbacks.
 */
class AbilitiesTest extends TestCase {

	public function test_execute_list_returns_total_and_items(): void {
		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'count' )->once()->andReturn( 2 );
		$repository->shouldReceive( 'query' )->once()->andReturn( array( (object) array( 'id' => 1 ) ) );

		$abilities = new Abilities( $repository );
		$result    = $abilities->execute_list( array( 'option' => 'siteurl' ) );

		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 1, $result['items'] );
	}

	public function test_execute_diff_returns_error_if_not_found(): void {
		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 99 )->andReturn( null );

		$abilities = new Abilities( $repository );
		$result    = $abilities->execute_diff( array( 'id' => 99 ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_execute_rollback_triggers_handler(): void {
		$record              = new \stdClass();
		$record->id          = 7;
		$record->option_name = 'active_plugins';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 7 )->andReturn( $record );

		$handler = Mockery::mock( RollbackHandler::class );
		$handler->shouldReceive( 'rollback' )->with( 7 )->andReturn( true );

		$abilities = new Abilities( $repository, $handler );
		$result    = $abilities->execute_rollback( array( 'id' => 7 ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'active_plugins', $result['option_name'] );
	}

	public function test_can_rollback_denies_active_plugins_without_permission(): void {
		Functions\when( 'current_user_can' )->alias(
			function ( $cap ) {
				if ( 'activate_plugins' === $cap ) {
					return false;
				}
				return true;
			}
		);

		$record              = new \stdClass();
		$record->id          = 7;
		$record->option_name = 'active_plugins';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 7 )->andReturn( $record );

		$abilities = new Abilities( $repository );
		$this->assertFalse( $abilities->can_rollback( array( 'id' => 7 ) ) );
	}

	public function test_can_rollback_allows_regular_option(): void {
		$record              = new \stdClass();
		$record->id          = 8;
		$record->option_name = 'blogname';

		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'get_by_id' )->with( 8 )->andReturn( $record );

		$abilities = new Abilities( $repository );
		$this->assertTrue( $abilities->can_rollback( array( 'id' => 8 ) ) );
	}
}
