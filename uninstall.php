<?php
/**
 * Removes everything Pratiks Options Revisions stored. Runs when the plugin is deleted from wp-admin.
 *
 * @package PratikLondhe\PratiksOptionsRevisions
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// PSR-4 minimal bootstrap for uninstall.
$pratiks_options_revisions_db_file = __DIR__ . '/src/Storage/Database.php';
if ( is_readable( $pratiks_options_revisions_db_file ) ) {
	require_once $pratiks_options_revisions_db_file;
}
$pratiks_options_revisions_cron_file = __DIR__ . '/src/Cron/PruneTask.php';
if ( is_readable( $pratiks_options_revisions_cron_file ) ) {
	require_once $pratiks_options_revisions_cron_file;
}

/**
 * Removes plugin data for a single site.
 */
function pratiks_options_revisions_uninstall_single_site(): void {
	if ( class_exists( 'PratikLondhe\\PratiksOptionsRevisions\\Storage\\Database' ) ) {
		\PratikLondhe\PratiksOptionsRevisions\Storage\Database::drop_table();
	}

	if ( class_exists( 'PratikLondhe\\PratiksOptionsRevisions\\Cron\\PruneTask' ) ) {
		\PratikLondhe\PratiksOptionsRevisions\Cron\PruneTask::unschedule();
	}

	delete_option( 'pratiks_options_revisions_db_version' );
}

if ( is_multisite() ) {
	$pratiks_options_revisions_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $pratiks_options_revisions_sites as $pratiks_options_revisions_site_id ) {
		switch_to_blog( $pratiks_options_revisions_site_id );
		pratiks_options_revisions_uninstall_single_site();
		restore_current_blog();
	}
} else {
	pratiks_options_revisions_uninstall_single_site();
}
