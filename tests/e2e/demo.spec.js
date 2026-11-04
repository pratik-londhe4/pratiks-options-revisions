// Records .github/media/demo.gif for the README. Run on demand: npm run demo
const { test, expect } = require( '@playwright/test' );
const { wp } = require( './utils/wp-cli' );
const { prepareSite, recordDemo } = require( './utils/media' );

test( 'demo: update an option, view visual diff, and roll back in Tools', async ( { browser } ) => {
	test.setTimeout( 180_000 );
	prepareSite( 'Production Store' );

	// Seed an initial option state.
	wp( 'option', 'update', 'blogname', 'My Production Store' );
	wp( 'option', 'update', 'blogname', 'My Production Store - Spring Sale' );

	await recordDemo(
		browser,
		async ( page, demo ) => {
			// Start at the Settings page to show a setting being modified.
			await page.goto( '/wp-admin/options-general.php' );
			const titleInput = page.locator( '#blogname' );
			await expect( titleInput ).toBeVisible();
			await demo.start();
			await demo.pause( 800 );

			// Make an edit that we will later roll back.
			await demo.type( titleInput, ' - Flash Promo (Unintended)' );
			await demo.click( page.locator( '#submit' ), 1000 );

			// Navigate to Tools > Pratiks Options Revisions.
			await demo.click( page.getByRole( 'link', { name: 'Tools', exact: true } ), 500 );
			await demo.click( page.getByRole( 'link', { name: 'Pratiks Options Revisions' } ), 1000 );

			// Inspect the revisions table.
			const table = page.locator( 'table.wp-list-table' );
			await expect( table ).toBeVisible();
			await demo.pause( 1000 );

			// Open the visual diff modal.
			const latestRow = page.locator( 'tr:has-text("blogname")' ).first();
			await demo.click( latestRow.locator( '.optrev-view-diff' ), 600 );

			const modal = page.locator( '#optrev-diff-modal' );
			await expect( modal ).toBeVisible();
			await demo.pause( 2200 );

			// Close the diff modal.
			await demo.click( modal.locator( '.optrev-modal-close' ), 500 );
			await expect( modal ).not.toBeVisible();
			await demo.pause( 600 );

			// Click Rollback.
			await demo.click( latestRow.locator( '.optrev-rollback-btn' ), 1500 );

			// Success notice is visible.
			await expect( page.locator( '.notice-success' ) ).toBeVisible();
			await demo.pause( 2500 );
		},
		{ viewport: { width: 1280, height: 760 }, width: 960, fps: 10 }
	);
} );
