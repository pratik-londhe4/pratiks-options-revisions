// End-to-end tests for Options Revisions. Check every outcome in the database through WP-CLI as well as on screen.
const { test, expect } = require( '@playwright/test' );

test.describe( 'Options Revisions', () => {
	test( 'the plugin is active', async ( { page } ) => {
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page.locator( 'tr[data-slug="options-revisions"].active' ) ).toBeVisible();
	} );
} );
