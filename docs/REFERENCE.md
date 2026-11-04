# Reference

## Hooks

### Filters

#### `options_revisions_capability`
* **Type**: Filter
* **Arguments**: `(string $capability)`
* **Default**: `'manage_options'`
* **Description**: Sets the capability required to view options revisions, access diffs, and execute rollbacks.

```php
add_filter( 'options_revisions_capability', function() {
    return 'edit_theme_options';
} );
```

#### `options_revisions_should_ignore`
* **Type**: Filter
* **Arguments**: `(bool $ignore, string $option)`
* **Default**: `false`
* **Description**: Allows filtering whether a specific option should be excluded from revision capture.

```php
add_filter( 'options_revisions_should_ignore', function( $ignore, $option ) {
    if ( str_starts_with( $option, 'my_custom_cache_' ) ) {
        return true;
    }
    return $ignore;
}, 10, 2 );
```

#### `options_revisions_retention_days`
* **Type**: Filter
* **Arguments**: `(int $days)`
* **Default**: `30`
* **Description**: Sets how many days revisions are retained before automated pruning by daily cron.

#### `options_revisions_max_revisions_per_option`
* **Type**: Filter
* **Arguments**: `(int $max_per_option)`
* **Default**: `30`
* **Description**: Maximum number of revisions kept per unique option name.

#### `options_revisions_diff_output`
* **Type**: Filter
* **Arguments**: `(string $diff, mixed $old_value, mixed $new_value)`
* **Description**: Filters the final HTML diff rendered by the diff engine.

---

### Actions

#### `options_revisions_recorded`
* **Type**: Action
* **Arguments**: `(string $option, mixed $old_val, mixed $new_val, int $user_id, int $revision_id)`
* **Description**: Fires immediately after a new option revision is saved to the database.

#### `options_revisions_rolled_back`
* **Type**: Action
* **Arguments**: `(string $option_name, mixed $restored_value, int $revision_id)`
* **Description**: Fires after an option has been successfully rolled back to a previous revision.

#### `options_revisions_pruned`
* **Type**: Action
* **Arguments**: `(int $total_pruned)`
* **Description**: Fires when expired or excess revisions are pruned by the daily cron task.

---

### JavaScript Hooks (`wp.hooks`)

Namespace: `optionsRevisions`

* **`optionsRevisions.onDiffOpen`**: Action fired when the diff modal opens for an option.
* **`optionsRevisions.onDiffClose`**: Action fired when the diff modal closes.

---

## Abilities API (WordPress 6.9+ / MCP)

Category: `options-revisions`

* **`options-revisions/list-revisions`**:
  * Annotations: `readonly`, `idempotent`
  * Input: `{ "option": "string", "limit": 20 }`
  * Output: `{ "total": int, "items": [...] }`

* **`options-revisions/get-diff`**:
  * Annotations: `readonly`, `idempotent`
  * Input: `{ "id": int }`
  * Output: `{ "option_name": "string", "old_value": "string", "new_value": "string", "diff_html": "string" }`

* **`options-revisions/rollback-option`**:
  * Annotations: `destructive`
  * Input: `{ "id": int }`
  * Output: `{ "success": true, "option_name": "string", "message": "string" }`

---

## WP-CLI

```bash
# List recent option revisions
wp options-revisions list

# Filter by option name
wp options-revisions list --option=blogname

# View diff of a revision
wp options-revisions diff 12

# Rollback an option to revision 12
wp options-revisions rollback 12 --yes

# Purge revisions older than 14 days
wp options-revisions purge --days=14 --yes
```
