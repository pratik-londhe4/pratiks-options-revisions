<?php
/**
 * PruneTask unit tests.
 *
 * @package PratikLondhe\OptionsRevisions
 */

namespace PratikLondhe\OptionsRevisions\Tests\Unit;

use Brain\Monkey\Actions;
use Mockery;
use PratikLondhe\OptionsRevisions\Cron\PruneTask;
use PratikLondhe\OptionsRevisions\Storage\RevisionRepository;

/**
 * Tests daily cron cleanup.
 */
class PruneTaskTest extends TestCase {

	public function test_run_cleanup_prunes_records_and_fires_action(): void {
		$repository = Mockery::mock( RevisionRepository::class );
		$repository->shouldReceive( 'prune_older_than' )
			->once()
			->with( 30 )
			->andReturn( 12 );

		$repository->shouldReceive( 'prune_excess' )
			->once()
			->with( 30 )
			->andReturn( 8 );

		Actions\expectDone( 'options_revisions_pruned' )
			->once()
			->with( 20 );

		$task = new PruneTask( $repository );
		$task->run_cleanup();
	}
}
