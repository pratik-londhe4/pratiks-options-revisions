// Regenerates .wordpress-org/screenshot-N.png (readme.txt "== Screenshots ==" and README.md).
// Run on demand: npm run screenshots
const { test, expect } = require( '@playwright/test' );
const { wp } = require( './utils/wp-cli' );
const { prepareSite, shot } = require( './utils/media' );

test.describe( 'Screenshots', () => {
	test.use( { viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2 } );

	test.beforeAll( async () => {
		prepareSite( 'Production Site' );
		wp( 'option', 'update', 'blogname', 'My Production Store' );
		wp( 'option', 'update', 'blogname', 'My Production Store - Spring Sale' );
		wp( 'option', 'update', 'default_role', 'subscriber' );
		wp( 'option', 'update', 'default_role', 'customer' );
	} );

	test( '1: revisions list table under Tools > Pratiks Options Revisions', async ( { page } ) => {
		await page.goto( '/wp-admin/tools.php?page=pratiks-options-revisions' );
		await expect( page.locator( '.optrev-wrap h1' ) ).toHaveText( 'Pratiks Options Revisions' );
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
		await shot( page, 1 );
	} );

	test( '2: unified visual diff modal showing previous and new option values', async ( { page } ) => {
		await page.goto( '/wp-admin/tools.php?page=pratiks-options-revisions' );
		const row = page.locator( 'tr:has-text("blogname")' ).first();
		await row.locator( '.optrev-view-diff' ).click();

		const modal = page.locator( '#optrev-diff-modal' );
		await expect( modal ).toBeVisible();
		await expect( modal.locator( '.optrev-modal-diff' ) ).toBeVisible();
		await shot( page, 2, { keepHover: true } );
	} );
} );
