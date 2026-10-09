# Core Form Engine (`lib/formengine`)

Named dynamic forms shared by the whole CMS — **not** owned by Events.

## Core tables (neutral names)

| Table | Purpose |
|-------|---------|
| `form_fields` | Field definitions for any `form_key` |
| `form_hook_bindings` | Which `field_key` satisfies which process `hook_id` |

Declared in `dbsql/formengine.sql`. Created/migrated by `form_engine_ensure_schema()`.

Legacy tables `events_staff_form_fields` / `events_form_hooks` are renamed or copied automatically on first ensure.

## What Features provide

| Concern | Owner |
|---------|--------|
| Required hooks for a form | Feature PHP (e.g. Events `staff_app` hook catalog) |
| Default field seed | Feature seed helper |
| Storage of answers | Feature tables (`events_staff.form_data`, etc.) |
| Process logic (status, flagging) | Feature, reading hooks via `form_hook_value()` |

## Editor

- Admin panel → System → Form Editor
- AJAX: `/ajax/formengine_ajax.php`
