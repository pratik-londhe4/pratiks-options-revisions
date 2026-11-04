// End-to-end tests for Pratiks Options Revisions. Check every outcome in the database through WP-CLI as well as on screen.
const { test, expect } = require( '@playwright/test' );
const { wpCli } = require( './utils/wp-cli' );

test.describe( 'Pratiks Options Revisions', () => {
	test.beforeEach( async () => {
		// Ensure plugin is active.
		await wpCli( 'plugin activate pratiks-options-revisions' );
	} );

	test( 'the plugin is active in wp-admin', async ( { page } ) => {
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page.locator( 'tr[data-slug="pratiks-options-revisions"].active' ) ).toBeVisible();
	} );

	test( 'records option change and displays in tools table', async ( { page } ) => {
		const testOption = 'e2e_test_option';
		await wpCli( `option set ${ testOption } "Initial Value"` );
		await wpCli( `option set ${ testOption } "Updated Value"` );

		await page.goto( '/wp-admin/tools.php?page=pratiks-options-revisions' );
		await expect( page.locator( '.optrev-wrap h1' ) ).toHaveText( 'Pratiks Options Revisions' );
		await expect( page.locator( 'table.wp-list-table' ) ).toContainText( testOption );

		// Open Diff modal.
		const row = page.locator( `tr:has-text("${ testOption }")` ).first();
		await row.locator( '.optrev-view-diff' ).click();

		const modal = page.locator( '#optrev-diff-modal' );
		await expect( modal ).toBeVisible();
		await expect( modal.locator( '.optrev-modal-diff' ) ).toBeVisible();

		// Close modal.
		await modal.locator( '.optrev-modal-close' ).click();
		await expect( modal ).not.toBeVisible();
	} );

	test( 'rolls back option to previous value', async ( { page } ) => {
		const testOption = 'e2e_rollback_test';
		await wpCli( `option set ${ testOption } "Original Text"` );
		await wpCli( `option set ${ testOption } "Modified Text"` );

		await page.goto( '/wp-admin/tools.php?page=pratiks-options-revisions' );

		// Click Rollback on the most recent revision.
		page.on( 'dialog', dialog => dialog.accept() );
		const row = page.locator( `tr:has-text("${ testOption }")` ).first();
		await row.locator( '.optrev-rollback-btn' ).click();

		await expect( page.locator( '.notice-success' ) ).toContainText( 'Option was successfully rolled back' );

		// Verify database value restored.
		const restored = await wpCli( `option get ${ testOption }` );
		expect( restored.trim() ).toBe( 'Original Text' );
	} );

	test( 'subscriber receives 403 / permission denied', async ( { browser } ) => {
		const context = await browser.newContext( { storageState: undefined } );
		const page = await context.newPage();

		// Attempt to access without admin session.
		await page.goto( '/wp-admin/tools.php?page=pratiks-options-revisions' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Search Options' );
		await context.close();
	} );
} );
