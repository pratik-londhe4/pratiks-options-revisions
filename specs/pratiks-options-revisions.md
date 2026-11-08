# Pratiks Options Revisions (`pratiks-options-revisions`)

Namespace `PratikLondhe\PratiksOptionsRevisions`, prefix `pratiks_options_revisions_`, CSS prefix `optrev-`. E2E port 8912.
Requires at least 6.6 and PHP 7.4.

## Problem

WordPress core provides native revisions for posts and pages, but none for site settings (`wp_options`). When an administrator, a plugin, or an automated process updates core settings (General, Reading, Permalinks) or critical plugin settings (WooCommerce, SEO, Mail SMTP, Caching) and something breaks, there is no audit log or undo button. Existing plugins either only rollback code versions (WP Rollback) or log plain activity strings without snapshots or rollback functionality (Simple History).

## v1.0.0 scope

1. **Snapshot Engine**:
   - Hooks into `update_option`, `add_option`, and `delete_option`.
   - Denylist filtering: Ignores high-churn keys (`_transient_*`, `cron`, `site_transient_*`), session tokens, and security secrets (`auth_key`, passwords, nonces).
   - Human-readable serialization: Pretty-prints serialized arrays/objects into formatted JSON before diffing, allowing clean line-by-line diffs for complex plugin settings.
   - Storage: Stored in a dedicated lightweight table `{$wpdb->prefix}pratiks_options_revisions` (`id`, `option_name`, `old_value`, `new_value`, `user_id`, `created_at`, `ip_address`).
   - Automatic Pruning: Daily cron job keeps the latest 30 revisions per option or 30 days of retention (both filterable).
   - Loop Guard: Prevents recursive snapshot loops when rolling back an option.

2. **Admin Interface (`Tools > Pratiks Options Revisions`)**:
   - Standard WordPress `WP_List_Table` displaying: Option Name, Modified Date, Modified By, Actions (*View Diff*, *Rollback*).
   - Search filter by option name and date range.
   - Visual Diff Modal: Built on WordPress core's native `wp_text_diff()` showing red/green side-by-side and unified differences.
   - One-click Rollback with confirmation modal and capability verification (`manage_options`).

3. **Multisite Support**:
   - Works per sub-site on standard options; integrates with Network Admin for `wp_sitemeta` / `update_site_option`.

4. **Privacy & Cleanup**:
   - Privacy policy suggested text via `wp_add_privacy_policy_content`.
   - Personal data exporter and eraser registered for user IDs and IP addresses.
   - `uninstall.php`: Drops custom table, deletes plugin options, unschedules cron.

## Hooks

Filters:
- `pratiks_options_revisions_capability` (default `manage_options`)
- `pratiks_options_revisions_ignored_options` (add/remove options from tracking)
- `pratiks_options_revisions_max_revisions_per_option` (default 30)
- `pratiks_options_revisions_retention_days` (default 30)
- `pratiks_options_revisions_diff_output` (customize diff rendering)

Actions:
- `pratiks_options_revisions_recorded` (`$option_name`, `$old_value`, `$new_value`, `$user_id`)
- `pratiks_options_revisions_rolled_back` (`$option_name`, `$restored_value`, `$revision_id`)
- `pratiks_options_revisions_pruned` (`$deleted_count`)

JS (`wp.hooks`, namespace `pratiksOptionsRevisions`):
- `pratiksOptionsRevisions.onDiffOpen`, `pratiksOptionsRevisions.onDiffClose`

## Abilities

- `pratiks-options-revisions/list-revisions` (readonly)
- `pratiks-options-revisions/get-diff` (readonly)
- `pratiks-options-revisions/rollback-option` (destructive)

## WP-CLI

- `wp pratiks-options-revisions list [--option=<name>] [--format=table|json|csv]`
- `wp pratiks-options-revisions diff <revision-id>`
- `wp pratiks-options-revisions rollback <revision-id> [--yes]`
- `wp pratiks-options-revisions purge [--days=<n>] [--yes]`

## E2E ideas

1. Modify core setting (`blogname` or `permalink_structure`), verify revision row created in database via WP-CLI.
2. Verify visual diff renders correctly in the admin modal.
3. Perform rollback via admin UI, verify database value reverts immediately.
4. Verify WP-CLI rollback command restores serialized arrays accurately.
5. Verify transient options and blacklisted keys are ignored.
6. Verify permission denials for non-administrator roles (subscriber / editor receives 403).
