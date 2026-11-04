<?php
/**
 * Removes everything Options Revisions stored. Runs when the plugin is deleted from wp-admin.
 *
 * @package PratikLondhe\OptionsRevisions
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// PSR-4 minimal bootstrap for uninstall.
$options_revisions_db_file = __DIR__ . '/src/Storage/Database.php';
if ( is_readable( $options_revisions_db_file ) ) {
	require_once $options_revisions_db_file;
}
$options_revisions_cron_file = __DIR__ . '/src/Cron/PruneTask.php';
if ( is_readable( $options_revisions_cron_file ) ) {
	require_once $options_revisions_cron_file;
}

/**
 * Removes plugin data for a single site.
 */
function options_revisions_uninstall_single_site(): void {
	if ( class_exists( 'PratikLondhe\\OptionsRevisions\\Storage\\Database' ) ) {
		\PratikLondhe\OptionsRevisions\Storage\Database::drop_table();
	}

	if ( class_exists( 'PratikLondhe\\OptionsRevisions\\Cron\\PruneTask' ) ) {
		\PratikLondhe\OptionsRevisions\Cron\PruneTask::unschedule();
	}

	delete_option( 'options_revisions_db_version' );
}

if ( is_multisite() ) {
	$options_revisions_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $options_revisions_sites as $options_revisions_site_id ) {
		switch_to_blog( $options_revisions_site_id );
		options_revisions_uninstall_single_site();
		restore_current_blog();
	}
} else {
	options_revisions_uninstall_single_site();
}
