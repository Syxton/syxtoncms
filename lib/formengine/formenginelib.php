<?php
/***************************************************************************
 * formenginelib.php - Named form engine (hooks, storage profiles, migration plans)
 * -------------------------------------------------------------------------
 * Forms are identified by form_key (e.g. a feature-defined key). Callers declare process
 * hooks; the editor binds field_keys to those hooks. Storage adapters know
 * where values live (columns + optional JSON). Each form definition can
 * describe a migration plan from legacy static columns.
 ***************************************************************************/

if (!defined('FORMENGINELIB')) {
    define('FORMENGINELIB', true);
}

/** @var array form_key => form definition (registered by feature form definition files) */
$GLOBALS['FORM_ENGINE_REGISTRY'] = $GLOBALS['FORM_ENGINE_REGISTRY'] ?? [];

/**
 * Register a named form (hooks, storage, migration). Called by feature form definition files.
 */
function form_engine_register_form($form_key, array $def) {
    $GLOBALS['FORM_ENGINE_REGISTRY'][$form_key] = $def;
}

/**
 * Load consumer form definitions.
 * Discovers features/{feature}/formdefs/*.php (one structure for all form consumers).
 */
function form_engine_load_consumer_forms() {
    global $CFG;
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $root = $CFG->dirroot ?? '';
    if ($root === '') {
        return;
    }
    foreach (glob($root . '/features/*/formdefs/*.php') ?: [] as $path) {
        include_once($path);
    }
}

/**
 * Registry of known forms: metadata, hooks, storage profile id, migration plan.
 */
function form_engine_registry() {
    form_engine_load_consumer_forms();
    return $GLOBALS['FORM_ENGINE_REGISTRY'] ?? [];
}

function form_engine_default_form_key() {
    $reg = form_engine_registry();
    if ($reg) {
        return (string)array_key_first($reg);
    }
    return '';
}


/**
 * Include the form definition's runtime library (seed, migrate, storage helpers).
 */
function form_engine_load_form_runtime($form_key) {
    global $CFG;
    form_engine_load_consumer_forms();
    $def = form_engine_get_form_def($form_key);
    if (!$def) {
        return false;
    }
    $lib = $def['runtime_lib'] ?? null;
    if (!$lib) {
        return true;
    }
    $path = $lib;
    if ($path[0] !== '/' && empty($CFG->dirroot)) {
        return false;
    }
    if (isset($CFG->dirroot) && $path[0] === '/') {
        $path = $CFG->dirroot . $path;
    } elseif (isset($CFG->dirroot) && strpos($path, $CFG->dirroot) !== 0) {
        $path = $CFG->dirroot . '/' . ltrim($path, '/');
    }
    if (file_exists($path)) {
        include_once($path);
        return true;
    }
    return false;
}

function form_engine_get_form_def($form_key) {
    $reg = form_engine_registry();
    return $reg[$form_key] ?? null;
}

/**
 * True if a hook can be bound to a form field in the editor.
 * Computed hooks (resolve callable, bindable=false) are not linkable.
 */
function form_engine_hook_is_bindable(array $h) {
    if (array_key_exists('bindable', $h)) {
        return !empty($h['bindable']);
    }
    // Default: bindable unless it has a resolve callable
    if (!empty($h['resolve'])) {
        return false;
    }
    $meta = $h['meta'] ?? [];
    if (!empty($meta['resolve']) || !empty($meta['computed'])) {
        return false;
    }
    return true;
}

function form_engine_ensure_schema() {
    global $CFG;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // 1) Migrate legacy tables first (rename before CREATE so we keep data + column order)
    form_engine_migrate_legacy_tables();

    // 2) Create core tables if still missing
    $sqlfile = $CFG->dirroot . '/lib/formengine/dbsql/formengine.sql';
    if (file_exists($sqlfile)) {
        $sql = file_get_contents($sqlfile);
        if (preg_match('/CREATE TABLE IF NOT EXISTS `form_fields`.*?ENGINE=InnoDB[^;]*;/s', $sql, $m)) {
            @execute_db_sql($m[0]);
        }
        if (preg_match('/CREATE TABLE IF NOT EXISTS `form_hook_bindings`.*?ENGINE=InnoDB[^;]*;/s', $sql, $m)) {
            @execute_db_sql($m[0]);
        }
    }

    // 3) Column / index upgrades on form_fields
    if (form_engine_table_exists('form_fields')) {
        $cols = get_db_result("SHOW COLUMNS FROM `form_fields` LIKE 'form_key'");
        if (!$cols || !count_db_result($cols)) {
            @execute_db_sql(
                "ALTER TABLE form_fields
                 ADD COLUMN form_key varchar(64) NOT NULL DEFAULT '' AFTER fieldid"
            );
            @execute_db_sql(
                "UPDATE form_fields SET form_key = ''
                 WHERE form_key = '' OR form_key IS NULL"
            );
        }
        form_engine_ensure_form_page_field_index();
    }
    form_engine_normalize_hook_bindings_schema();
}

/**
 * Legacy → core:
 *   events_staff_form_fields → form_fields
 *   events_form_hooks        → form_hook_bindings
 */
function form_engine_migrate_legacy_tables() {
    form_engine_migrate_one_table('events_staff_form_fields', 'form_fields');
    form_engine_migrate_one_table('events_form_hooks', 'form_hook_bindings');
}

/**
 * True if a base table exists in the current database.
 */
function form_engine_table_exists($table) {
    $table = preg_replace('/[^a-z0-9_]/i', '', (string)$table);
    if ($table === '') {
        return false;
    }
    // SHOW TABLES does not work reliably with prepared placeholders — interpolate safely.
    $r = get_db_result("SHOW TABLES LIKE '" . $table . "'");
    return $r && count_db_result($r) > 0;
}

/**
 * Rename or column-safe copy of one legacy table into a core table name.
 * Never uses INSERT SELECT * (column order may differ).
 */
function form_engine_migrate_one_table($old, $new) {
    $has_old = form_engine_table_exists($old);
    $has_new = form_engine_table_exists($new);

    if (!$has_old) {
        return;
    }

    // Prefer rename: drop empty target first if it was created empty
    if ($has_new) {
        $cnt_new = 0;
        try {
            $row = get_db_row("SELECT COUNT(*) AS c FROM `$new`");
            $cnt_new = (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            $cnt_new = -1;
        }
        if ($cnt_new === 0) {
            @execute_db_sql("DROP TABLE IF EXISTS `$new`");
            $has_new = false;
        }
    }

    if (!$has_new) {
        @execute_db_sql("RENAME TABLE `$old` TO `$new`");
        return;
    }

    // Both have data: copy by intersecting column names only
    $old_cols = form_engine_table_columns($old);
    $new_cols = form_engine_table_columns($new);
    if (!$old_cols || !$new_cols) {
        return;
    }
    $shared = array_values(array_intersect($old_cols, $new_cols));
    // Never copy auto-increment PK if it would collide — include fieldid/id if present
    if (empty($shared)) {
        return;
    }
    $col_list = '`' . implode('`,`', $shared) . '`';
    try {
        @execute_db_sql(
            "INSERT IGNORE INTO `$new` ($col_list) SELECT $col_list FROM `$old`"
        );
    } catch (\Throwable $e) {
        // leave old table in place on failure
        return;
    }
    @execute_db_sql("DROP TABLE IF EXISTS `$old`");
}

/**
 * Ordered list of column names for a table.
 */
function form_engine_table_columns($table) {
    $table = preg_replace('/[^a-z0-9_]/i', '', (string)$table);
    if ($table === '') {
        return [];
    }
    $r = get_db_result("SHOW COLUMNS FROM `$table`");
    if (!$r) {
        return [];
    }
    $cols = [];
    while ($row = fetch_row($r)) {
        if (!empty($row['Field'])) {
            $cols[] = $row['Field'];
        }
    }
    return $cols;
}


function form_engine_ensure_form_page_field_index() {
    // Normalize key columns to VARCHAR so unique indexes are valid (legacy may be TEXT)
    form_engine_ensure_varchar_column('form_fields', 'form_key', 64, "''");
    form_engine_ensure_varchar_column('form_fields', 'field_key', 64, "''");

    $idx = get_db_result(
        "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_fields'
           AND INDEX_NAME IN ('page_field', 'form_page_field', 'uq_form_page_key')"
    );
    $have_old = false;
    $have_new = false;
    if ($idx) {
        while ($r = fetch_row($idx)) {
            $name = $r['INDEX_NAME'] ?? '';
            if ($name === 'page_field') {
                $have_old = true;
            }
            if ($name === 'form_page_field' || $name === 'uq_form_page_key') {
                $have_new = true;
            }
        }
    }
    if ($have_old) {
        @execute_db_sql("ALTER TABLE `form_fields` DROP INDEX `page_field`");
    }
    if (!$have_new) {
        // Prefix lengths keep this valid even if a column is still TEXT briefly
        @execute_db_sql(
            "ALTER TABLE `form_fields`
             ADD UNIQUE KEY `uq_form_page_key` (`form_key`(64), `pageid`, `field_key`(64))"
        );
    }
}

/**
 * Force a column to VARCHAR(n) if it exists as TEXT/BLOB or wrong type.
 */
function form_engine_ensure_varchar_column($table, $column, $len = 64, $default_sql = "''") {
    $table = preg_replace('/[^a-z0-9_]/i', '', (string)$table);
    $column = preg_replace('/[^a-z0-9_]/i', '', (string)$column);
    $len = (int)$len;
    if ($table === '' || $column === '' || $len < 1) {
        return;
    }
    $r = get_db_result("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$r || !count_db_result($r)) {
        return;
    }
    $row = fetch_row($r);
    $type = strtolower((string)($row['Type'] ?? ''));
    // Already a short varchar/char — leave alone
    if (preg_match('/^(var)?char\((\d+)\)/', $type, $m) && (int)$m[2] <= 255 && (int)$m[2] >= $len) {
        return;
    }
    if (strpos($type, 'varchar') === 0 || strpos($type, 'char') === 0) {
        // widen/narrow to requested length
        @execute_db_sql(
            "ALTER TABLE `$table` MODIFY COLUMN `$column` varchar($len) NOT NULL DEFAULT $default_sql"
        );
        return;
    }
    // TEXT/BLOB or other — convert
    @execute_db_sql(
        "ALTER TABLE `$table` MODIFY COLUMN `$column` varchar($len) NOT NULL DEFAULT $default_sql"
    );
}


/**
 * Align form_hook_bindings with core schema after legacy rename.
 * Legacy used PK `hookid`; core uses `id`.
 */
function form_engine_normalize_hook_bindings_schema() {
    if (!form_engine_table_exists('form_hook_bindings')) {
        return;
    }
    $cols = form_engine_table_columns('form_hook_bindings');
    if (!$cols) {
        return;
    }
    // Rename hookid → id if needed
    if (in_array('hookid', $cols, true) && !in_array('id', $cols, true)) {
        @execute_db_sql("ALTER TABLE `form_hook_bindings` CHANGE `hookid` `id` int NOT NULL AUTO_INCREMENT");
        $cols = form_engine_table_columns('form_hook_bindings');
    }
    // Ensure required columns exist
    $need = [
        'form_key' => "varchar(64) NOT NULL DEFAULT ''",
        'pageid' => "int NOT NULL DEFAULT 0",
        'hook_id' => "varchar(96) NOT NULL DEFAULT ''",
        'field_key' => "varchar(64) DEFAULT NULL",
        'required' => "tinyint(1) NOT NULL DEFAULT 1",
        'meta' => "longtext",
        'modified' => "int DEFAULT NULL",
    ];
    foreach ($need as $col => $def) {
        if (!in_array($col, $cols, true)) {
            @execute_db_sql("ALTER TABLE `form_hook_bindings` ADD COLUMN `$col` $def");
        }
    }
    form_engine_ensure_varchar_column('form_hook_bindings', 'form_key', 64, "''");
    form_engine_ensure_varchar_column('form_hook_bindings', 'hook_id', 96, "''");
    form_engine_ensure_varchar_column('form_hook_bindings', 'field_key', 64, "''");
}

/**
 * Primary key column name for form_hook_bindings (`id` or legacy `hookid`).
 */
function form_engine_hooks_pk() {
    static $pk = null;
    if ($pk !== null) {
        return $pk;
    }
    $cols = form_engine_table_columns('form_hook_bindings');
    if (in_array('id', $cols, true)) {
        $pk = 'id';
    } elseif (in_array('hookid', $cols, true)) {
        $pk = 'hookid';
    } else {
        $pk = 'id';
    }
    return $pk;
}

/**
 * Seed default hook bindings for a form when missing (pageid 0 = global).
 */
function form_engine_seed_hooks($form_key, $pageid = 0) {
    form_engine_ensure_schema();
    $def = form_engine_get_form_def($form_key);
    if (!$def || empty($def['hooks'])) {
        return 0;
    }
    $now = time();
    $seeded = 0;
    foreach ($def['hooks'] as $h) {
        // Computed hooks are not stored as field bindings
        if (!form_engine_hook_is_bindable($h)) {
            continue;
        }
        $hook_id = $h['hook_id'];
        $exists = get_db_row(
            "SELECT hook_id FROM form_hook_bindings
             WHERE form_key=||fk|| AND pageid=||p|| AND hook_id=||hid||",
            ['fk' => $form_key, 'p' => (int)$pageid, 'hid' => $hook_id]
        );
        if ($exists) {
            continue;
        }
        $meta = '';
        if (!empty($h['meta'])) {
            $meta = json_encode($h['meta'], JSON_UNESCAPED_UNICODE);
        }
        // Never pass null into ||placeholders|| (isset(null) fails in dblib)
        $field = $h['field_key'] ?? '';
        if ($field === null) {
            $field = '';
        }
        execute_db_sql(
            "INSERT INTO form_hook_bindings
             (form_key, pageid, hook_id, field_key, required, meta, modified)
             VALUES (||fk||, ||p||, ||hid||, ||field||, ||req||, ||meta||, ||m||)",
            [
                'fk' => $form_key,
                'p' => (int)$pageid,
                'hid' => $hook_id,
                'field' => (string)$field,
                'req' => !empty($h['required']) ? 1 : 0,
                'meta' => $meta === null ? '' : (string)$meta,
                'm' => $now,
            ]
        );
        $seeded++;
    }
    form_engine_prune_stale_hooks($form_key);
    return $seeded;
}

/**
 * Hook catalog for editor (defs merged with DB bindings).
 * Returns list of [hook_id, label, required, field_key, help, meta, bound].
 */
function form_engine_get_hooks($form_key, $pageid = 0) {
    form_engine_ensure_schema();
    form_engine_seed_hooks($form_key, 0);

    $def = form_engine_get_form_def($form_key);
    $catalog = [];
    if ($def && !empty($def['hooks'])) {
        foreach ($def['hooks'] as $h) {
            $catalog[$h['hook_id']] = $h;
        }
    }

    // DB bindings: global (pageid 0) then page-specific overlay
    $bindings = [];
    $rows = get_db_result(
        "SELECT * FROM form_hook_bindings WHERE form_key=||fk|| AND pageid=0",
        ['fk' => $form_key]
    );
    if ($rows) {
        while ($r = fetch_row($rows)) {
            $bindings[$r['hook_id']] = $r;
        }
    }
    if ((int)$pageid > 0) {
        $rows = get_db_result(
            "SELECT * FROM form_hook_bindings WHERE form_key=||fk|| AND pageid=||p||",
            ['fk' => $form_key, 'p' => (int)$pageid]
        );
        if ($rows) {
            while ($r = fetch_row($rows)) {
                $bindings[$r['hook_id']] = $r;
            }
        }
    }

    $out = [];
    $seen = [];
    foreach ($catalog as $hid => $h) {
        $b = $bindings[$hid] ?? null;
        $meta = $h['meta'] ?? [];
        if ($b && !empty($b['meta'])) {
            $decoded = is_string($b['meta']) ? json_decode($b['meta'], true) : $b['meta'];
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $out[] = [
            'hook_id' => $hid,
            'label' => $h['label'] ?? $hid,
            'help' => $h['help'] ?? '',
            'required' => $b ? (int)$b['required'] : (int)(!empty($h['required'])),
            'field_key' => $b ? ($b['field_key'] ?? null) : ($h['field_key'] ?? null),
            'meta' => $meta,
            'bindable' => form_engine_hook_is_bindable($h),
            'resolve' => $h['resolve'] ?? ($meta['resolve'] ?? null),
            'hookid' => $b ? (int)($b['id'] ?? $b['hookid'] ?? 0) : 0,
        ];
        $seen[$hid] = true;
    }
    // Extra DB-only hooks (always treated as bindable field bindings)
    foreach ($bindings as $hid => $b) {
        if (!empty($seen[$hid])) {
            continue;
        }
        $meta = [];
        if (!empty($b['meta'])) {
            $meta = is_string($b['meta']) ? (json_decode($b['meta'], true) ?: []) : (array)$b['meta'];
        }
        $out[] = [
            'hook_id' => $hid,
            'label' => $hid,
            'help' => '',
            'required' => (int)$b['required'],
            'field_key' => $b['field_key'] ?? null,
            'meta' => $meta,
            'bindable' => true,
            'resolve' => null,
            'hookid' => (int)($b['id'] ?? $b['hookid'] ?? 0),
        ];
    }
    return $out;
}

/**
 * Hooks that can be bound to a form field (excludes computed / resolve-only).
 */
function form_engine_get_bindable_hooks($form_key, $pageid = 0) {
    $all = form_engine_get_hooks($form_key, $pageid);
    return array_values(array_filter($all, function ($h) {
        return !empty($h['bindable']);
    }));
}

/**
 * Resolve a single hook value from a flat values map (field_key => value).
 */
function form_hook_value($form_key, $hook_id, $values) {
    if (!is_array($values)) {
        $values = [];
    }
    // Ensure consumer form defs (and resolve callables) are loaded
    form_engine_load_consumer_forms();

    $hooks = form_engine_get_hooks($form_key, 0);
    $hook = null;
    foreach ($hooks as $h) {
        if ($h['hook_id'] === $hook_id) {
            $hook = $h;
            break;
        }
    }
    if (!$hook) {
        return null;
    }

    $meta = $hook['meta'] ?? [];
    $resolve = $hook['resolve'] ?? ($meta['resolve'] ?? null);

    // Computed / feature-defined resolvers (e.g. feature resolve callable)
    if ($resolve) {
        if (is_string($resolve) && function_exists($resolve)) {
            return call_user_func($resolve, $values, $hook, $form_key);
        }
        if (is_callable($resolve)) {
            return call_user_func($resolve, $values, $hook, $form_key);
        }
    }

    // Standard: value of the bound field_key
    $fk = $hook['field_key'] ?? null;
    if ($fk === null || $fk === '') {
        return null;
    }
    return array_key_exists($fk, $values) ? $values[$fk] : null;
}

/**
 * Resolve flat values from a storage row using staff merge rules.
 * Flatten a storage row to field values (uses form def resolve_values callback if set).
 */
function resolve_form_values($form_key, $storage_row) {
    form_engine_load_consumer_forms();
    $def = form_engine_get_form_def($form_key);
    $resolver = $def['resolve_values'] ?? null;
    if ($resolver) {
        if (is_string($resolver) && function_exists($resolver)) {
            return call_user_func($resolver, $storage_row);
        }
        if (is_callable($resolver)) {
            return call_user_func($resolver, $storage_row);
        }
    }
    // Generic fallback: form_data JSON merged over row
    $values = is_array($storage_row) ? $storage_row : [];
    if (!empty($storage_row['form_data'])) {
        $json = is_string($storage_row['form_data'])
            ? json_decode($storage_row['form_data'], true)
            : $storage_row['form_data'];
        if (is_array($json)) {
            $values = array_merge($values, $json);
        }
    }
    return $values;
}


function form_engine_hooks_for_field($form_key, $field_key, $pageid = 0) {
    if ($field_key === '' || $field_key === null) {
        return [];
    }
    $hooks = form_engine_get_hooks($form_key, $pageid);
    $out = [];
    foreach ($hooks as $h) {
        if (($h['field_key'] ?? '') === $field_key) {
            $out[] = [
                'hook_id' => $h['hook_id'],
                'label' => $h['label'] ?? $h['hook_id'],
                'required' => !empty($h['required']),
            ];
        }
    }
    return $out;
}

/**
 * Clear all hook bindings that point at a field_key (after field delete or key change).
 */
function form_engine_unbind_field($form_key, $field_key, $pageid = 0) {
    if ($field_key === '' || $field_key === null) {
        return 0;
    }
    form_engine_ensure_schema();
    $now = time();
    // Unbind global + page-specific rows
    execute_db_sql(
        "UPDATE form_hook_bindings SET field_key='', modified=||m||
         WHERE form_key=||fk|| AND field_key=||field|| AND (pageid=0 OR pageid=||p||)",
        ['m' => $now, 'fk' => $form_key, 'field' => (string)$field_key, 'p' => (int)$pageid]
    );
    return true;
}

function form_engine_protected_field_keys($form_key, $pageid = 0) {
    // Required bindable hooks that currently have a field_key (hook-derived only)
    $keys = [];
    foreach (form_engine_get_bindable_hooks($form_key, $pageid) as $h) {
        if (empty($h['required'])) {
            continue;
        }
        if (!empty($h['field_key'])) {
            $keys[$h['field_key']] = true;
        }
    }
    return array_keys($keys);
}

/**
 * Save a hook binding (pageid 0 global by default).
 */
function form_engine_save_hook_binding($form_key, $pageid, $hook_id, $field_key, $required = null) {
    form_engine_ensure_schema();
    $now = time();
    $pageid = (int)$pageid;
    $pk = form_engine_hooks_pk();
    $existing = get_db_row(
        "SELECT * FROM form_hook_bindings
         WHERE form_key=||fk|| AND pageid=||p|| AND hook_id=||hid||",
        ['fk' => $form_key, 'p' => $pageid, 'hid' => $hook_id]
    );
    $req = $required;
    if ($req === null && $existing) {
        $req = (int)$existing['required'];
    }
    if ($req === null) {
        $req = 1;
    }
    $fk_val = ($field_key === null || $field_key === '') ? '' : (string)$field_key;
    if ($existing) {
        $id = (int)($existing[$pk] ?? $existing['id'] ?? $existing['hookid'] ?? 0);
        if ($pk === 'hookid') {
            execute_db_sql(
                "UPDATE form_hook_bindings SET field_key=||field||, required=||req||, modified=||m|| WHERE hookid=||id||",
                ['field' => $fk_val, 'req' => (int)$req, 'm' => $now, 'id' => $id]
            );
        } else {
            execute_db_sql(
                "UPDATE form_hook_bindings SET field_key=||field||, required=||req||, modified=||m|| WHERE id=||id||",
                ['field' => $fk_val, 'req' => (int)$req, 'm' => $now, 'id' => $id]
            );
        }
        return $id;
    }
    return execute_db_sql(
        "INSERT INTO form_hook_bindings (form_key, pageid, hook_id, field_key, required, meta, modified)
         VALUES (||fk||, ||p||, ||hid||, ||field||, ||req||, ||meta||, ||m||)",
        [
            'fk' => $form_key,
            'p' => $pageid,
            'hid' => $hook_id,
            'field' => $fk_val,
            'req' => (int)$req,
            'meta' => '',
            'm' => $now,
        ]
    );
}



/**
 * Run form migration via form def migration_run / migration.run callback.
 */
function form_engine_run_migration($form_key, $pageid = null) {
    form_engine_load_form_runtime($form_key);
    form_engine_ensure_schema();
    form_engine_seed_hooks($form_key, 0);
    form_engine_prune_stale_hooks($form_key);
    $def = form_engine_get_form_def($form_key);
    if (!$def) {
        return ['ok' => false, 'message' => 'Unknown form_key: ' . $form_key];
    }
    $runner = $def['migration_run'] ?? ($def['migration']['run'] ?? null);
    $result = ['ok' => true, 'form_key' => $form_key, 'hooks_seeded' => true];
    if ($runner) {
        if (is_string($runner) && function_exists($runner)) {
            $result = array_merge($result, (array)call_user_func($runner, $pageid));
        } elseif (is_callable($runner)) {
            $result = array_merge($result, (array)call_user_func($runner, $pageid));
        }
    }
    $plan = $def['migration'] ?? [];
    $result['plan'] = is_array($plan) ? ($plan['from'] ?? $plan['id'] ?? $form_key) : $form_key;
    return $result;
}

/**
 * Remove DB hook bindings that are no longer in the form definition catalog
 * (or are non-bindable computed hooks). Cleans leftovers like renamed hooks.
 */
function form_engine_prune_stale_hooks($form_key) {
    form_engine_ensure_schema();
    $def = form_engine_get_form_def($form_key);
    $keep = [];
    if ($def && !empty($def['hooks'])) {
        foreach ($def['hooks'] as $h) {
            if (form_engine_hook_is_bindable($h)) {
                $keep[$h['hook_id']] = true;
            }
        }
    }
    $rows = get_db_result(
        "SELECT hook_id FROM form_hook_bindings WHERE form_key=||fk||",
        ['fk' => $form_key]
    );
    if (!$rows) {
        return 0;
    }
    $removed = 0;
    while ($r = fetch_row($rows)) {
        $hid = $r['hook_id'] ?? '';
        if ($hid === '' || isset($keep[$hid])) {
            continue;
        }
        execute_db_sql(
            "DELETE FROM form_hook_bindings WHERE form_key=||fk|| AND hook_id=||hid||",
            ['fk' => $form_key, 'hid' => $hid]
        );
        $removed++;
    }
    return $removed;
}


function form_engine_get_fields($form_key, $pageid = 0, $include_inactive = false) {
    form_engine_ensure_schema();
    $where = $include_inactive ? '' : ' AND active = 1';
    $sql = "SELECT * FROM form_fields
            WHERE form_key = ||form_key||
              AND (pageid = ||pageid|| OR pageid = 0)
            $where
            ORDER BY pageid DESC, sortorder ASC, fieldid ASC";
    $rows = get_db_result($sql, ['pageid' => (int)$pageid, 'form_key' => $form_key]);
    $by_key = [];
    if ($rows) {
        while ($r = fetch_row($rows)) {
            foreach (['options', 'validation', 'extra_attrs', 'visibility', 'required_when'] as $json_col) {
                if (!isset($r[$json_col]) || $r[$json_col] === '' || $r[$json_col] === null) {
                    $r[$json_col] = [];
                } elseif (is_string($r[$json_col])) {
                    $decoded = json_decode($r[$json_col], true);
                    $r[$json_col] = is_array($decoded) ? $decoded : [];
                }
            }
            $k = $r['field_key'];
            if (!isset($by_key[$k]) || (int)$r['pageid'] === (int)$pageid) {
                $by_key[$k] = $r;
            }
        }
    }
    $fields = array_values($by_key);
    usort($fields, function ($a, $b) {
        $sa = (int)($a['sortorder'] ?? 0);
        $sb = (int)($b['sortorder'] ?? 0);
        if ($sa !== $sb) return $sa - $sb;
        return ((int)($a['fieldid'] ?? 0)) - ((int)($b['fieldid'] ?? 0));
    });
    return $fields;
}

/**
 * Seed default fields via form def seed_fields callback.
 */
function form_engine_seed_fields($form_key, $pageid = 0, $force = false) {
    form_engine_load_form_runtime($form_key);
    $def = form_engine_get_form_def($form_key);
    $seeder = $def['seed_fields'] ?? null;
    if ($seeder && is_string($seeder) && function_exists($seeder)) {
        return call_user_func($seeder, $pageid, $force);
    }
    if ($seeder && is_callable($seeder)) {
        return call_user_func($seeder, $pageid, $force);
    }
    return 0;
}

/**
 * Optional fixed field keys declared by the form definition (not core knowledge).
 */
function form_engine_fixed_keys($form_key) {
    form_engine_load_consumer_forms();
    $def = form_engine_get_form_def($form_key);
    if (!$def) {
        return [];
    }
    if (!empty($def['fixed_keys']) && is_array($def['fixed_keys'])) {
        return $def['fixed_keys'];
    }
    $fk = $def['fixed_keys_fn'] ?? null;
    if ($fk && is_string($fk) && function_exists($fk)) {
        return (array)call_user_func($fk);
    }
    return [];
}
