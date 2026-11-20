# User Activity Tracker

Logs selected user activities according to the actor's roles: authentication, entity changes, role and permission changes, files, and configuration operations.

## Why use User Activity Tracker with Database Logging?

Database Logging (`dblog`) stores and displays messages emitted by Drupal and its modules; it does not itself detect every user activity. User Activity Tracker complements it by generating activity messages with:

- **Selective tracking:** focus on relevant activities by choosing which actions to log for each actor role.
- **Consistent context across entity types:** identify the actor and affected entity without relying solely on each module's own logging.
- **Targeted change details:** see which roles were added to or removed from an account, and which permissions were added to or removed from a role.

Enable `dblog` to view these entries in Drupal's Recent log messages report, or use another Drupal logging backend. User Activity Tracker does not require `dblog` and does not provide its own log storage or viewer.

Some activities are already logged by Drupal core or other modules. User Activity Tracker does not suppress those messages, so related entries may coexist.

## Requirements

- Drupal 11.1 or later within Drupal 11 (`^11.1`).
- PHP 8.3 or later, subject to the installed Drupal version's requirements.
- Drupal's User module (`drupal:user`).

Configuration Manager (`config`) is not a required dependency.

## Installation

Place the module in `web/modules/custom/user_activity_tracker`. From the project root:

```bash
vendor/bin/drush en user_activity_tracker
vendor/bin/drush cr
```

For code updates requiring a cache rebuild, use `vendor/bin/drush cr`; do not uninstall/reinstall the module, as uninstalling can remove its configuration.

## Configuration

The settings page is `/admin/config/system/activity-tracker` and requires `administer site configuration`.

1. Enable **Track this role** for each role to monitor.
2. Select the actions to track.
3. Save the configuration.

Actions are always visible and editable. Selections for disabled roles are not saved; unchecking a role disables its tracking on save without requiring its actions to be unchecked first. No roles are tracked by default.

Settings are stored in `user_activity_tracker.settings`, under `track_role_actions`, indexed by role ID. An action is logged if at least one of the actor's roles enables it. Matching multiple roles does not produce duplicate entries.

## Logged Actions

Entries use the `user_activity_tracker` channel at `notice` level. `ActorId` identifies the account performing the action, not the owner of the affected entity. Context is formatted as `key: value | key: value`.

| Action IDs | Scope |
| --- | --- |
| `login`, `logout` | User login and logout. |
| `entity_insert`, `entity_update`, `entity_delete` | Content and configuration entity creation, update, and deletion, subject to the exclusions below. |
| `entity_publish`, `entity_unpublish` | Publication status transitions for entities implementing `EntityPublishedInterface`. |
| `role_create`, `role_update`, `role_delete` | Role creation, changes, and deletion. |
| `user_role_change` | Roles added to or removed from a user account. |
| `file_insert`, `file_delete` | File creation and deletion. |
| `config_save`, `config_delete`, `config_import` | Simple configuration saves/deletions and configuration import summaries. |

### Entities and publication

Generic entity entries contain `entity_type`, `entity_id`, `bundle`, and `label`, not a list of changed fields. Deletion covers nodes, media, comments, taxonomy terms, and other entity types.

- Role and file creation/deletion use their dedicated actions instead of generic entity entries.
- User deletion is handled by `hook_user_delete()` using `entity_delete`, with the account's ID, label, login name, and email, without a second generic deletion entry.
- Technical action entities using `user_add_role_action` or `user_remove_role_action` are excluded from both generic creation and deletion. Other action plugins remain tracked.

A publication transition produces `entity_publish` or `entity_unpublish` instead of `entity_update`, even if other fields change. Enable those actions explicitly: `entity_update` alone does not log these transitions. A new entity produces only its creation entry.

For translations, publication status is compared with the previous state of the same language. If the status is unchanged, the previous state is unavailable, or a translation is newly added to an existing entity, the action remains `entity_update`.

### Roles and files

`user_role_change` records `user_id`, `user_name`, `added_roles`, `removed_roles`, and `current_roles`. Role IDs are compared independently of order; `current_roles` is sorted alphabetically. The target user is distinct from the actor. A detected role change replaces that user's generic `entity_update` entry, even if other fields also change.

`role_update` ignores unchanged role saves and permission reordering. Permission changes include `added_permissions` and/or `removed_permissions`. Other role property changes are still tracked; if the previous state is unavailable, the save is logged.

File updates use `entity_update`, except for the technical temporary-to-permanent transition when only `status` and optionally `changed` differ. Changes to other stored fields are still logged, as are updates without an available previous state. A media entity referencing a file is tracked separately.

### Configuration

`config_save` and `config_delete` cover simple configuration in the default collection, excluding configuration entities. Saves are logged even if the data is unchanged, and do not distinguish creation from update.

The `config_import` summary includes `changelist_count`: the number of processed create, update, delete, and rename operations across all collections, including language overrides. This is not a count of unique configuration names. An import can also generate individual entity/configuration entries.

These actions use the current account's roles, including for Drush imports; an import is not unconditionally logged.

Saving the module's own settings also writes a separate `[TRACKER][CONFIG]` administrative entry describing the selected roles and actions, independently of the tracking filters.

## Implementation

- [UserActivityTrackerHooks](src/Hook/UserActivityTrackerHooks.php): hooks implemented with `#[Hook(...)]`, automatically discovered and autowired by Drupal.
- [UserActivityTrackerService](src/Service/UserActivityTrackerService.php): role/action filtering and logging.
- [ConfigTrackSubscriber](src/EventSubscriber/ConfigTrackSubscriber.php): configuration event handling.
- [UserActivityTrackerSettingsForm](src/Form/UserActivityTrackerSettingsForm.php): administration form.
- [TrackedAction](src/TrackedAction.php): action IDs and shared label definitions; `label()` resolves one label, while `options()` preserves lazy translation for form choices.

Previous entity states use `getOriginal()` where available, with a compatibility fallback to the original property for Drupal 11.1. The module does not implement procedural hooks.

French translations are provided in [user_activity_tracker.fr.po](translations/user_activity_tracker.fr.po). Changes to that file may require reimporting it through Drupal's interface translation UI; rebuilding caches alone does not guarantee reimport.

## Tests

The repository includes:

- [UserActivityTrackerServiceTest](tests/src/Unit/Service/UserActivityTrackerServiceTest.php): role-based filtering, account selection, and log context formatting.
- [HooksTest](tests/src/Unit/Hooks/HooksTest.php): selected authentication, entity, translation, role, and file tracking cases.
- [ConfigTrackSubscriberTest](tests/src/Unit/EventSubscriber/ConfigTrackSubscriberTest.php): save/delete context, configuration entity exclusions, and import counts.
- [UserActivityTrackerSettingsFormTest](tests/src/Unit/Form/UserActivityTrackerSettingsFormTest.php): action availability and disabling role tracking.
- [HookDiscoveryTest](tests/src/Kernel/HookDiscoveryTest.php): hook discovery, injection, and a single login invocation.
- [SettingsSchemaTest](tests/src/Kernel/SettingsSchemaTest.php): configuration schema with empty selections and arbitrary role IDs.

With PHPUnit and Drupal's development dependencies installed, run unit tests from the project root:

```bash
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/user_activity_tracker/tests/src/Unit
```

Kernel tests additionally require a dedicated test database configured through `SIMPLETEST_DB` or PHPUnit configuration. **Do not use the site's database.**

```bash
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/user_activity_tracker/tests/src/Kernel
```

The presence of these tests does not imply they have been executed or passed on every supported Drupal version.

