=== Options Revisions ===
Contributors: pratiklondhe
Tags: options, settings, rollback, revisions, history
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track settings changes, compare unified diffs, and roll back any WordPress or plugin option with one click.

== Description ==

Options Revisions gives you the safety of post revisions for your site settings and options.

Whenever WordPress core or any plugin updates an option in `wp_options`, Options Revisions captures the previous and new values, who made the change, and the timestamp. When an update breaks layout, email delivery, or checkout, you can view a unified visual diff and roll back the setting with a single click.

Features:
* Automatic snapshot capture on option changes (`update_option`, `add_option`, `delete_option`).
* Built-in denylist excluding transient caches, cron schedules, and authentication secrets.
* Visual side-by-side and unified diffs rendered with WordPress core diffing tools.
* Smart formatting for serialized arrays and objects into readable JSON diffs.
* 1-click rollback with nonces, capability checks, and recursion loop guards.
* Full WP-CLI command suite (`wp options-revisions`).
* WordPress 6.9+ Abilities API integration for AI agents and MCP workflows.
* Automated daily pruning keeping the database lean.
* GDPR compliant personal data exporter and eraser.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/options-revisions` directory, or install through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Access revision logs and rollback options under Tools > Options Revisions.

== Frequently Asked Questions ==

= Does this track every option? =
It tracks options that carry meaningful site configuration while automatically ignoring high-churn caches (transients, cron) and authentication keys. You can further customize tracked options using the `options_revisions_should_ignore` filter.

= Can I roll back via command line? =
Yes, run `wp options-revisions rollback <id>` using WP-CLI.

== Screenshots ==

1. Revisions list table under Tools > Options Revisions.
2. Unified visual diff modal showing previous and new option values.

== Changelog ==

= 0.1.0 =
* First version.
