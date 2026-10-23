<?php
/***************************************************************************
 * formenginelib.php - Named form engine (hooks, answer storage, migration plans)
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
 * @param string $form_key Unique form identifier (e.g. staff_app)
 * @param array $def Form definition array
 * @return void
 */
function form_engine_register_form($form_key, array $def) {
    $GLOBALS['FORM_ENGINE_REGISTRY'][$form_key] = $def;
}

/**
 * Load consumer form definitions from features/{feature}/formdefs/*.php (once per request).
 * @return void
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
 * Return the registry of known forms (metadata, hooks, storage, migration).
 * @return array form_key => form definition
 */
function form_engine_registry() {
    form_engine_load_consumer_forms();
    return $GLOBALS['FORM_ENGINE_REGISTRY'] ?? [];
}

/**
 * First registered form_key, or empty string when none are registered.
 * @return string
 */
function form_engine_default_form_key() {
    $reg = form_engine_registry();
    if ($reg) {
        return (string)array_key_first($reg);
    }
    return '';
}

/**
 * Resolve and include a form-def library path (runtime_lib / migration_lib).
 * Missing file is a no-op so migration libs can be deleted after use.
 * @param string $form_key Form identifier
 * @param string $lib_key Def key for the path (runtime_lib or migration_lib)
 * @return bool False if the form is unknown or path cannot be resolved; true if included or not required
 */
function form_engine_include_def_lib($form_key, $lib_key) {
    global $CFG;
    form_engine_load_consumer_forms();
    $def = form_engine_def($form_key, false);
    if (!$def) {
        return false;
    }
    $lib = $def[$lib_key] ?? null;
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

/**
 * Get a single form definition from the registry.
 * @param string $form_key Form identifier
 * @return array|null Definition array or null if unknown
 */
function form_engine_get_form_def($form_key) {
    $reg = form_engine_registry();
    return $reg[$form_key] ?? null;
}

/**
 * Ensure a table column exists, adding it as nullable LONGTEXT when absent.
 * @param string $table
 * @param string $column
 * @param string|null $after
 * @return void
 */
function form_engine_add_column_if_missing($table, $column, $after = null) {
    foreach ([$table, $column] as $identifier) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$identifier)) {
            throw new InvalidArgumentException('Invalid database identifier.');
        }
    }
    if ($after !== null && !preg_match('/^[a-zA-Z0-9_]+$/', (string)$after)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }
    $row = get_db_row(
        "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = @@table@@
           AND COLUMN_NAME = @@column@@",
        ['table' => $table, 'column' => $column]
    );
    if ($row && (int)$row['cnt'] > 0) {
        return;
    }
    $after_clause = $after !== null ? " AFTER `{$after}`" : '';
    execute_db_sql("ALTER TABLE `{$table}` ADD COLUMN `{$column}` LONGTEXT DEFAULT NULL{$after_clause}");
}

/**
 * Ensure an existing column uses a text type suitable for JSON data.
 * @param string $table
 * @param string $column
 * @return void
 */
function form_engine_ensure_longtext_column($table, $column) {
    foreach ([$table, $column] as $identifier) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$identifier)) {
            throw new InvalidArgumentException('Invalid database identifier.');
        }
    }
    $row = get_db_row(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = @@table@@
           AND COLUMN_NAME = @@column@@",
        ['table' => $table, 'column' => $column]
    );
    if (!$row) {
        return;
    }
    $type = strtolower($row['DATA_TYPE'] ?? '');
    if (in_array($type, ['longtext', 'text', 'mediumtext', 'tinytext', 'varchar', 'char'], true)) {
        return;
    }
    execute_db_sql("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` LONGTEXT DEFAULT NULL");
}

/**
 * Ensure an existing column is NOT NULL and has a zero/empty default.
 * @param string $table
 * @param string $column
 * @return void
 */
function form_engine_ensure_column_default($table, $column) {
    foreach ([$table, $column] as $identifier) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$identifier)) {
            throw new InvalidArgumentException('Invalid database identifier.');
        }
    }
    $row = get_db_row(
        "SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = @@table@@
           AND COLUMN_NAME = @@column@@",
        ['table' => $table, 'column' => $column]
    );
    if (!$row || $row['COLUMN_DEFAULT'] !== null) {
        return;
    }
    $ctype = $row['COLUMN_TYPE'];
    $dtype = strtolower($row['DATA_TYPE'] ?? '');
    if (in_array($dtype, ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'], true)) {
        execute_db_sql("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$ctype} NOT NULL DEFAULT 0");
    } else {
        execute_db_sql("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$ctype} NOT NULL DEFAULT ''");
    }
}

/**
 * Get a form definition after optionally loading its runtime library.
 * @param string $form_key Form identifier
 * @param bool $load_runtime Include runtime_lib before returning the definition
 * @return array Definition array, or an empty array when the form is unknown
 */
function form_engine_def($form_key, $load_runtime = true) {
    if ($load_runtime) {
        form_engine_include_def_lib($form_key, 'runtime_lib');
    }
    return form_engine_get_form_def($form_key) ?: [];
}

/**
 * True if a hook can be bound to a form field in the editor.
 * Computed hooks (resolve callable, bindable=false) are not linkable.
 * @param array $h Hook definition row
 * @return bool
 */
function form_engine_hook_is_bindable(array $h) {
    if (array_key_exists('bindable', $h)) {
        return !empty($h['bindable']);
    }
    // Declared field slots mean the editor can bind sources (even when resolve() computes a value)
    if (!empty($h['fields']) && is_array($h['fields'])) {
        return true;
    }
    // Legacy single field_key
    if (!empty($h['field_key'])) {
        return true;
    }
    // Pure computed: resolve only, no field slots
    if (!empty($h['resolve'])) {
        return false;
    }
    $meta = $h['meta'] ?? [];
    if (is_string($meta) && $meta !== '') {
        $meta = json_decode($meta, true) ?: [];
    }
    if (is_array($meta) && (!empty($meta['resolve']) || !empty($meta['computed']))) {
        return false;
    }
    return true;
}

/**
 * Normalize hook field slots from def + meta bindings.
 * Def shape: fields => [ [slot_name, type, amount], ... ]
 * @param array $h Hook definition row
 * @return array[] Each: slot (string), type (?string), amount (int), keys (string[])
 */
function form_engine_hook_fields_spec(array $h) {
    $meta = $h['meta'] ?? [];
    if (is_string($meta) && $meta !== '') {
        $meta = json_decode($meta, true) ?: [];
    }
    if (!is_array($meta)) {
        $meta = [];
    }
    $bindings = [];
    if (!empty($meta['bindings']) && is_array($meta['bindings'])) {
        foreach ($meta['bindings'] as $slot => $keys) {
            $bindings[(string)$slot] = array_values(array_filter(array_map('strval', (array)$keys)));
        }
    }

    $slots = [];
    if (!empty($h['fields']) && is_array($h['fields'])) {
        foreach ($h['fields'] as $row) {
            if (!is_array($row)) {
                $slot = (string)$row;
                $type = null;
                $amt = 1;
            } elseif (array_key_exists(0, $row) || array_key_exists(1, $row)) {
                $slot = (string)($row[0] ?? '');
                $type = $row[1] ?? null;
                $amt = array_key_exists(2, $row) ? $row[2] : 1;
            } else {
                $slot = (string)($row['slot'] ?? $row['key'] ?? $row['field_key'] ?? '');
                $type = $row['type'] ?? $row['fieldtype'] ?? null;
                $amt = array_key_exists('amount', $row) ? $row['amount'] : 1;
            }
            if ($slot === '') {
                continue;
            }
            if ($type === '') {
                $type = null;
            }
            $amt = ($amt === null || $amt === '') ? 0 : (int)$amt;
            $keys = $bindings[$slot] ?? [];
            $slots[] = [
                'slot' => $slot,
                'type' => $type,
                'amount' => $amt,
                'keys' => $keys,
            ];
        }
    }

    // Forms without declared slots can still use the single field_key column.
    if (!$slots) {
        if (!empty($h['field_key'])) {
            $slots[] = [
                'slot' => (string)$h['field_key'],
                'type' => null,
                'amount' => 1,
                'keys' => [(string)$h['field_key']],
            ];
        }
    }

    // Apply bindings onto slots that still have empty keys
    foreach ($slots as &$s) {
        if (empty($s['keys']) && !empty($bindings[$s['slot']])) {
            $s['keys'] = $bindings[$s['slot']];
        }
        // Default seed: for amount 1, if no keys yet, bind slot name as form field_key
        if (empty($s['keys']) && (int)$s['amount'] === 1) {
            // leave empty for editor — seed_hooks may set defaults
        }
    }
    unset($s);

    return $slots;
}

/**
 * All bound form field_keys across every slot on a hook.
 * @param array $h Hook definition row
 * @return string[]
 */
function form_engine_hook_field_keys(array $h) {
    $keys = [];
    foreach (form_engine_hook_fields_spec($h) as $s) {
        foreach ($s['keys'] as $k) {
            if ($k !== '') {
                $keys[] = $k;
            }
        }
    }
    return array_values(array_unique($keys));
}

/**
 * Resolve process-facing values from a hook + form values map.
 * Single slot amount 1 → [slot => value]; multi → [slot => [...], slot_1 => v1, ...]
 * @param array $h Hook definition row
 * @param array $values field_key => value
 * @return array
 */
function form_engine_hook_slot_values(array $h, array $values) {
    $out = [];
    foreach (form_engine_hook_fields_spec($h) as $s) {
        $slot = $s['slot'];
        $keys = $s['keys'];
        $amt = (int)$s['amount'];
        if ($amt === 1) {
            $fk = $keys[0] ?? '';
            $out[$slot] = ($fk !== '' && array_key_exists($fk, $values)) ? $values[$fk] : null;
        } else {
            $list = [];
            $i = 1;
            foreach ($keys as $fk) {
                $v = array_key_exists($fk, $values) ? $values[$fk] : null;
                $list[] = $v;
                $out[$slot . '_' . $i] = $v;
                $i++;
            }
            $out[$slot] = $list;
        }
    }
    return $out;
}

/**
 * Create/migrate core form_fields and form_hook_bindings tables (idempotent).
 * @return void
 */
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
 * Rename or copy legacy Events tables into core form_fields / form_hook_bindings.
 * @return void
 */
function form_engine_migrate_legacy_tables() {
    form_engine_migrate_one_table('events_staff_form_fields', 'form_fields');
    form_engine_migrate_one_table('events_form_hooks', 'form_hook_bindings');
}

/**
 * True if a base table exists in the current database.
 * @param string $table Table name
 * @return bool
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
 * @param string $old Legacy table name
 * @param string $new Target table name
 * @return void
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
 * @param string $table Table name
 * @return string[]
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

/**
 * Ensure form_fields unique index on (form_key, pageid, field_key).
 * @return void
 */
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
 * @param string $table Table name
 * @param string $column Column name
 * @param int $len Maximum length
 * @param string $default_sql SQL default expression (e.g. "''")
 * @return void
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
 * Align form_hook_bindings with core schema after legacy rename (hookid → id, required columns).
 * @return void
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
 * Primary key column name for form_hook_bindings (id or legacy hookid).
 * @return string
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
 * Store hook field associations in the canonical meta.bindings structure.
 * @param mixed $meta Existing or submitted metadata
 * @param string|null $field_key Legacy single-key column value
 * @param array $hook Hook definition
 * @return array
 */
function form_engine_normalize_hook_meta($meta, $field_key, array $hook) {
    if (is_string($meta)) {
        $meta = json_decode($meta, true);
    }
    $meta = is_array($meta) ? $meta : [];
    $bindings = [];
    if (isset($meta['bindings']) && is_array($meta['bindings'])) {
        foreach ($meta['bindings'] as $slot => $keys) {
            $bindings[(string)$slot] = array_values(array_filter(array_map('strval', (array)$keys)));
        }
    } elseif (!empty($meta['field_keys']) && is_array($meta['field_keys'])) {
        $bindings['value'] = array_values(array_filter(array_map('strval', $meta['field_keys'])));
    } elseif (!empty($meta['fields']) && is_array($meta['fields'])) {
        foreach ($meta['fields'] as $row) {
            if (!is_array($row)) {
                $bindings['value'][] = (string)$row;
                continue;
            }
            $key = (string)($row[0] ?? $row['key'] ?? $row['field_key'] ?? '');
            $keys = $row['keys'] ?? null;
            if (is_array($keys)) {
                $slot = (string)($row['slot'] ?? $key);
                $bindings[$slot] = array_values(array_filter(array_map('strval', $keys)));
            } elseif ($key !== '') {
                $slot = (string)($row['slot'] ?? 'value');
                $bindings[$slot][] = $key;
            }
        }
    }
    if (!isset($meta['bindings']) && !$bindings && $field_key !== null && $field_key !== '') {
        $slots = form_engine_hook_fields_spec($hook);
        $slot = $slots ? $slots[0]['slot'] : 'value';
        $bindings[$slot] = [(string)$field_key];
    }
    foreach (($hook['default_bindings'] ?? []) as $slot => $keys) {
        if (!array_key_exists($slot, $bindings)) {
            $bindings[(string)$slot] = array_values(array_filter(array_map('strval', (array)$keys)));
        }
    }
    unset($meta['fields'], $meta['field_keys']);
    $meta['bindings'] = $bindings;
    return $meta;
}

/**
 * Seed default hook bindings for a form when missing (pageid 0 = global).
 * @param string $form_key Form identifier
 * @param int $pageid Page id (0 = global defaults)
 * @return int Number of rows inserted
 */
function form_engine_seed_hooks($form_key, $pageid = 0) {
    form_engine_ensure_schema();
    $def = form_engine_def($form_key);
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
            "SELECT * FROM form_hook_bindings
             WHERE form_key=@@fk@@ AND pageid=@@p@@ AND hook_id=@@hid@@",
            ['fk' => $form_key, 'p' => (int)$pageid, 'hid' => $hook_id]
        );
        if ($exists) {
            $existing_meta = is_string($exists['meta'] ?? null)
                ? json_decode($exists['meta'], true)
                : ($exists['meta'] ?? []);
            if (!is_array($existing_meta)
                || !isset($existing_meta['bindings'])
                || !is_array($existing_meta['bindings'])
                || isset($existing_meta['fields'])
                || isset($existing_meta['field_keys'])) {
                $normalized = form_engine_normalize_hook_meta(
                    $exists['meta'] ?? [],
                    $exists['field_key'] ?? '',
                    $h
                );
                $keys = form_engine_hook_field_keys(['fields' => $h['fields'] ?? [], 'meta' => $normalized]);
                form_engine_save_hook_binding(
                    $form_key,
                    $pageid,
                    $hook_id,
                    $keys ? $keys[0] : '',
                    (int)($exists['required'] ?? 0),
                    $normalized
                );
            }
            continue;
        }
        $fields_spec = form_engine_hook_fields_spec($h);
        $bindings = [];
        if (!empty($h['default_bindings']) && is_array($h['default_bindings'])) {
            foreach ($h['default_bindings'] as $slot => $keys) {
                $bindings[(string)$slot] = array_values(array_filter(array_map('strval', (array)$keys)));
            }
        }
        // amount 1 with no default: seed form field_key = slot name (legacy identity.*)
        foreach ($fields_spec as $s) {
            $slot = $s['slot'];
            if (empty($bindings[$slot]) && (int)$s['amount'] === 1) {
                $bindings[$slot] = [$slot];
            }
        }
        $keys = [];
        foreach ($bindings as $list) {
            foreach ($list as $k) {
                $keys[] = $k;
            }
        }
        $field = $keys ? $keys[0] : '';
        $meta_arr = is_array($h['meta'] ?? null) ? $h['meta'] : [];
        $meta_arr['bindings'] = $bindings;
        $meta = json_encode($meta_arr, JSON_UNESCAPED_UNICODE);
        execute_db_sql(
            "INSERT INTO form_hook_bindings
             (form_key, pageid, hook_id, field_key, required, meta, modified)
             VALUES (@@fk@@, @@p@@, @@hid@@, @@field@@, @@req@@, @@meta@@, @@m@@)",
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
 * @param string $form_key Form identifier
 * @param int $pageid Page id for page-specific overlay
 * @return array List of hook rows
 */
function form_engine_get_hooks($form_key, $pageid = 0) {
    form_engine_ensure_schema();
    form_engine_seed_hooks($form_key, 0);

    $def = form_engine_def($form_key);
    $catalog = [];
    if ($def && !empty($def['hooks'])) {
        foreach ($def['hooks'] as $h) {
            $catalog[$h['hook_id']] = $h;
        }
    }

    // DB bindings: global (pageid 0) then page-specific overlay
    $bindings = [];
    $rows = get_db_result(
        "SELECT * FROM form_hook_bindings WHERE form_key=@@fk@@ AND pageid=0",
        ['fk' => $form_key]
    );
    if ($rows) {
        while ($r = fetch_row($rows)) {
            $bindings[$r['hook_id']] = $r;
        }
    }
    if ((int)$pageid > 0) {
        $rows = get_db_result(
            "SELECT * FROM form_hook_bindings WHERE form_key=@@fk@@ AND pageid=@@p@@",
            ['fk' => $form_key, 'p' => (int)$pageid]
        );
        if ($rows) {
            while ($r = fetch_row($rows)) {
                $bindings[$r['hook_id']] = $r;
            }
        }
    }

    $out = [];
    foreach ($catalog as $hid => $h) {
        $b = $bindings[$hid] ?? null;
        $meta = $h['meta'] ?? [];
        if ($b && !empty($b['meta'])) {
            $decoded = is_string($b['meta']) ? json_decode($b['meta'], true) : $b['meta'];
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        // Merge def + DB bindings into meta.bindings[slot] = [form_field_keys...]
        $merged = $h;
        $has_meta_bindings = isset($meta['bindings']) && is_array($meta['bindings']);
        $meta_bindings = $has_meta_bindings
            ? $meta['bindings']
            : [];
        if (!$has_meta_bindings && $b && !empty($b['field_key'])) {
            $def_slots = form_engine_hook_fields_spec($h);
            $slot = $def_slots ? $def_slots[0]['slot'] : (string)$b['field_key'];
            $meta_bindings[$slot] = [(string)$b['field_key']];
        }
        // Default bindings from def when DB has none for a slot
        if (!empty($h['default_bindings']) && is_array($h['default_bindings'])) {
            foreach ($h['default_bindings'] as $slot => $keys) {
                if (!array_key_exists($slot, $meta_bindings)) {
                    $meta_bindings[$slot] = array_values(array_filter(array_map('strval', (array)$keys)));
                }
            }
        }
        $meta['bindings'] = $meta_bindings;
        $merged['meta'] = $meta;
        if ($b) {
            $merged['field_key'] = $b['field_key'] ?? null;
        }
        $fields_spec = form_engine_hook_fields_spec($merged);
        $keys = form_engine_hook_field_keys($merged);
        $out[] = [
            'hook_id' => $hid,
            'label' => $h['label'] ?? $hid,
            'help' => $h['help'] ?? '',
            'required' => $b ? (int)$b['required'] : (int)(!empty($h['required'])),
            'fields' => $fields_spec,
            'field_key' => $keys ? $keys[0] : null, // first key for legacy consumers
            'meta' => $meta,
            'bindable' => form_engine_hook_is_bindable($h),
            'resolve' => $h['resolve'] ?? ($meta['resolve'] ?? null),
            'hookid' => $b ? (int)($b['id'] ?? $b['hookid'] ?? 0) : 0,
        ];
    }
    return $out;
}

/**
 * Hooks that can be bound to a form field (excludes computed / resolve-only).
 * @param string $form_key Form identifier
 * @param int $pageid Page id
 * @return array
 */
function form_engine_get_bindable_hooks($form_key, $pageid = 0) {
    $all = form_engine_get_hooks($form_key, $pageid);
    return array_values(array_filter($all, function ($h) {
        return !empty($h['bindable']);
    }));
}

/**
 * Resolve a single hook value from a flat values map (field_key => value).
 * @param string $form_key Form identifier
 * @param string $hook_id Process hook id
 * @param array $values field_key => value
 * @return mixed|null
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

    // Standard: first process slot value (or first bound field)
    $slot_vals = form_engine_hook_slot_values($hook, $values);
    if ($slot_vals) {
        $first = reset($slot_vals);
        // multi slots return arrays — expose first element for simple consumers
        if (is_array($first)) {
            return $first ? reset($first) : null;
        }
        return $first;
    }
    $keys = form_engine_hook_field_keys($hook);
    if (!$keys) {
        return null;
    }
    $fk = $keys[0];
    return array_key_exists($fk, $values) ? $values[$fk] : null;
}

/**
 * Flatten a storage row to field_key => value.
 * Uses form def prepare_db_array_fn when set; otherwise merges form_data JSON over the row.
 * @param string $form_key Form identifier
 * @param array $storage_row DB row (may include form_data JSON)
 * @return array field_key => value
 */
function form_engine_prepare_db_array($form_key, $storage_row) {
    $resolved = form_engine_call_hook($form_key, 'prepare_db_array_fn', [$storage_row], ['resolve_values']);
    if ($resolved !== null) {
        return $resolved;
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

/**
 * Hooks that currently bind the given form field_key.
 * @param string $form_key Form identifier
 * @param string $field_key Field key
 * @param int $pageid Page id
 * @return array List of [hook_id, label, required]
 */
function form_engine_hooks_for_field($form_key, $field_key, $pageid = 0) {
    if ($field_key === '' || $field_key === null) {
        return [];
    }
    $hooks = form_engine_get_hooks($form_key, $pageid);
    $out = [];
    foreach ($hooks as $h) {
        $keys = form_engine_hook_field_keys($h);
        if (in_array((string)$field_key, $keys, true)) {
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
 * @param string $form_key Form identifier
 * @param string $field_key Field key to unbind
 * @param int $pageid Page id (also clears global pageid 0)
 * @return bool
 */
function form_engine_unbind_field($form_key, $field_key, $pageid = 0) {
    if ($field_key === '' || $field_key === null) {
        return 0;
    }
    form_engine_ensure_schema();
    $def = form_engine_def($form_key);
    $hook_defs = [];
    foreach (($def['hooks'] ?? []) as $hook) {
        $hook_defs[$hook['hook_id']] = $hook;
    }
    $rows = get_db_result(
        "SELECT pageid, hook_id, field_key, required, meta
         FROM form_hook_bindings
         WHERE form_key=@@fk@@ AND (pageid=0 OR pageid=@@p@@)",
        ['fk' => $form_key, 'p' => (int)$pageid]
    );
    if ($rows) {
        while ($row = fetch_row($rows)) {
            $hook = $hook_defs[$row['hook_id']] ?? [];
            $meta = form_engine_normalize_hook_meta(
                $row['meta'] ?? [],
                $row['field_key'] ?? '',
                $hook
            );
            $changed = false;
            foreach ($meta['bindings'] as $slot => $keys) {
                $filtered = array_values(array_filter($keys, function ($key) use ($field_key) {
                    return $key !== (string)$field_key;
                }));
                if (count($filtered) !== count($keys)) {
                    $meta['bindings'][$slot] = $filtered;
                    $changed = true;
                }
            }
            if ($changed) {
                $keys = form_engine_hook_field_keys([
                    'fields' => $hook['fields'] ?? [],
                    'meta' => $meta,
                ]);
                form_engine_save_hook_binding(
                    $form_key,
                    (int)$row['pageid'],
                    $row['hook_id'],
                    $keys ? $keys[0] : '',
                    (int)$row['required'],
                    $meta
                );
            }
        }
    }
    return true;
}

/**
 * Save a hook binding (pageid 0 global by default).
 * @param string $form_key Form identifier
 * @param int $pageid Page id
 * @param string $hook_id Process hook id
 * @param string|null $field_key Bound field key (empty to unbind)
 * @param int|null $required Required flag (null keeps existing)
 * @param array|string|null $meta Optional meta JSON/array
 * @return int Binding row id
 */
function form_engine_save_hook_binding($form_key, $pageid, $hook_id, $field_key, $required = null, $meta = null) {
    form_engine_ensure_schema();
    $now = time();
    $pageid = (int)$pageid;
    $pk = form_engine_hooks_pk();
    $existing = get_db_row(
        "SELECT * FROM form_hook_bindings
         WHERE form_key=@@fk@@ AND pageid=@@p@@ AND hook_id=@@hid@@",
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

    $source_meta = $meta;
    if ($source_meta === null && $existing) {
        $source_meta = $existing['meta'] ?? [];
    }
    $def = form_engine_def($form_key);
    $hook = [];
    foreach (($def['hooks'] ?? []) as $candidate) {
        if (($candidate['hook_id'] ?? '') === $hook_id) {
            $hook = $candidate;
            break;
        }
    }
    $normalized_meta = form_engine_normalize_hook_meta($source_meta, $fk_val, $hook);
    if ($fk_val === '' && !empty($normalized_meta['bindings'])) {
        foreach ($normalized_meta['bindings'] as $keys) {
            if (!empty($keys)) {
                $fk_val = (string)reset($keys);
                break;
            }
        }
    }
    $meta_json = json_encode($normalized_meta, JSON_UNESCAPED_UNICODE);

    if ($existing) {
        $id = (int)($existing[$pk] ?? $existing['id'] ?? $existing['hookid'] ?? 0);
        $sql = "UPDATE form_hook_bindings SET field_key=@@field@@, required=@@req@@, meta=@@meta@@, modified=@@m@@ WHERE `$pk`=@@id@@";
        execute_db_sql($sql, [
            'field' => $fk_val,
            'req' => (int)$req,
            'meta' => (string)$meta_json,
            'm' => $now,
            'id' => $id,
        ]);
        return $id;
    }
    return execute_db_sql(
        "INSERT INTO form_hook_bindings (form_key, pageid, hook_id, field_key, required, meta, modified)
         VALUES (@@fk@@, @@p@@, @@hid@@, @@field@@, @@req@@, @@meta@@, @@m@@)",
        [
            'fk' => $form_key,
            'p' => $pageid,
            'hid' => $hook_id,
            'field' => $fk_val,
            'req' => (int)$req,
            'meta' => (string)$meta_json,
            'm' => $now,
        ]
    );
}

/**
 * Run form migration via form def migration_run callback.
 * @param string $form_key Form identifier
 * @param int|null $pageid Optional page context for the runner
 * @return array Result with ok, message, and runner payload
 */
function form_engine_run_migration($form_key, $pageid = null) {
    form_engine_include_def_lib($form_key, 'runtime_lib');
    form_engine_include_def_lib($form_key, 'migration_lib');
    form_engine_ensure_schema();
    form_engine_seed_hooks($form_key, 0);
    form_engine_prune_stale_hooks($form_key);
    $def = form_engine_def($form_key, false);
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
 * Remove DB hook bindings no longer in the form definition catalog (or non-bindable).
 * @param string $form_key Form identifier
 * @return int Number of rows deleted
 */
function form_engine_prune_stale_hooks($form_key) {
    form_engine_ensure_schema();
    $def = form_engine_def($form_key);
    $keep = [];
    if ($def && !empty($def['hooks'])) {
        foreach ($def['hooks'] as $h) {
            if (form_engine_hook_is_bindable($h)) {
                $keep[$h['hook_id']] = true;
            }
        }
    }
    $rows = get_db_result(
        "SELECT hook_id FROM form_hook_bindings WHERE form_key=@@fk@@",
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
            "DELETE FROM form_hook_bindings WHERE form_key=@@fk@@ AND hook_id=@@hid@@",
            ['fk' => $form_key, 'hid' => $hid]
        );
        $removed++;
    }
    return $removed;
}

/**
 * Load field definition rows from the database for a form_key.
 * @param string $form_key Form identifier
 * @param int $pageid Page id (page-specific overlays pageid 0)
 * @param bool $include_inactive Include inactive fields
 * @return array List of field rows
 */
function form_engine_get_fields($form_key, $pageid = 0, $include_inactive = false) {
    form_engine_ensure_schema();
    $where = $include_inactive ? '' : ' AND active = 1';
    $sql = "SELECT * FROM form_fields
            WHERE form_key = @@form_key@@
              AND (pageid = @@pageid@@ OR pageid = 0)
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
                } elseif (!is_array($r[$json_col])) {
                    $r[$json_col] = [];
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
 * In-memory default field definitions from the form def (no DB write).
 * @param string $form_key Form identifier
 * @return array List of field rows
 */
function form_engine_default_fields($form_key) {
    $def = form_engine_def($form_key);
    if (!$def) {
        return [];
    }
    if (!empty($def['default_fields']) && is_array($def['default_fields'])) {
        return $def['default_fields'];
    }
    $out = form_engine_call_hook($form_key, 'get_field_defaults_fn', [], ['default_fields_fn']);
    return is_array($out) ? $out : [];
}

/**
 * Normalize a default/seed field row for runtime use when DB has no rows yet.
 * @param array $f Field row
 * @param int $pageid Default pageid when missing
 * @return array
 */
function form_engine_normalize_default_field(array $f, $pageid = 0) {
    if (!isset($f['fieldid'])) {
        $f['fieldid'] = 0;
    }
    if (!isset($f['pageid'])) {
        $f['pageid'] = (int)$pageid;
    }
    if (!isset($f['active'])) {
        $f['active'] = 1;
    }
    foreach (['options', 'validation', 'extra_attrs', 'visibility', 'required_when'] as $json_col) {
        if (!isset($f[$json_col]) || $f[$json_col] === '' || $f[$json_col] === null) {
            $f[$json_col] = [];
        } elseif (is_string($f[$json_col])) {
            $decoded = json_decode($f[$json_col], true);
            $f[$json_col] = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($f[$json_col])) {
            $f[$json_col] = [];
        }
    }
    return $f;
}

/**
 * Full field-load pipeline: meet_db_requirement_fn, DB rows, defaults, finalize_field_list_fn.
 * @param string $form_key Form identifier
 * @param int $pageid Page id
 * @param bool $include_inactive Include inactive fields
 * @return array List of field rows
 */
function form_engine_fields($form_key, $pageid = 0, $include_inactive = false) {
    form_engine_ensure_schema();

    // 2. Feature bootstrap (idempotent)
    form_engine_call_hook($form_key, 'meet_db_requirement_fn', [], ['ensure', 'ensure_fn']);

    // 3. DB rows
    $list = form_engine_get_fields($form_key, $pageid, $include_inactive);

    // 4. In-memory defaults when DB empty
    if (empty($list)) {
        $defaults = form_engine_default_fields($form_key);
        $by_key = [];
        foreach ($defaults as $f) {
            if (!is_array($f) || empty($f['field_key'])) {
                continue;
            }
            if (!$include_inactive && isset($f['active']) && !(int)$f['active']) {
                continue;
            }
            $f = form_engine_normalize_default_field($f, 0);
            $by_key[$f['field_key']] = $f;
        }
        $list = array_values($by_key);
        usort($list, function ($a, $b) {
            $sa = (int)($a['sortorder'] ?? 0);
            $sb = (int)($b['sortorder'] ?? 0);
            if ($sa !== $sb) {
                return $sa - $sb;
            }
            return ((int)($a['fieldid'] ?? 0)) - ((int)($b['fieldid'] ?? 0));
        });
    }

    // 5. Post-process hook
    $out = form_engine_call_hook(
        $form_key,
        'finalize_field_list_fn',
        [$list, $pageid, $include_inactive],
        ['post_process_fields']
    );
    if (is_array($out)) {
        $list = $out;
    }

    return $list;
}

/**
 * Sync default field definitions via form def sync_field_defs_fn callback.
 * @param string $form_key Form identifier
 * @param int $pageid Page id
 * @param bool $force Force re-seed when supported by the callback
 * @return int|mixed Callback result (typically count seeded)
 */
function form_engine_sync_field_defs($form_key, $pageid = 0, $force = false) {
    return form_engine_call_hook($form_key, 'sync_field_defs_fn', [$pageid, $force], ['seed_fields']) ?? 0;
}

/**
 * Optional fixed field keys declared by the form definition.
 * @param string $form_key Form identifier
 * @return string[]
 */
function form_engine_fixed_keys($form_key) {
    $def = form_engine_def($form_key);
    if (!$def) {
        return [];
    }
    if (!empty($def['fixed_keys']) && is_array($def['fixed_keys'])) {
        return $def['fixed_keys'];
    }
    $keys = form_engine_call_hook($form_key, 'fixed_keys_fn');
    return is_array($keys) ? $keys : [];
}


// Field rendering / display (optional module; same directory)
if (!defined('FORMRENDERLIB')) {
    $__fr = dirname(__FILE__) . '/formrenderlib.php';
    if (file_exists($__fr)) {
        include_once($__fr);
    }
    unset($__fr);
}

// Client helpers: rules JS, multipage (optional module)
if (!defined('FORMCLIENTLIB')) {
    $__fc = dirname(__FILE__) . '/formclientlib.php';
    if (file_exists($__fc)) {
        include_once($__fc);
    }
    unset($__fc);
}


// ---------------------------------------------------------------------------
// Facade: simple entry points for consumers
// ---------------------------------------------------------------------------

/**
 * Invoke a form-def callable/string hook if present.
 * @param string $form_key Form identifier
 * @param string $hook_name Key on the form definition
 * @param array $args Arguments passed to the callable
 * @param string[] $aliases Fallback definition keys
 * @return mixed|null Null if the hook is not set
 */
function form_engine_call_hook($form_key, $hook_name, array $args = [], array $aliases = []) {
    $def = form_engine_def($form_key);
    foreach (array_merge([$hook_name], $aliases) as $key) {
        $fn = $def[$key] ?? null;
        if (is_string($fn) && function_exists($fn)) {
            return call_user_func_array($fn, $args);
        }
        if (is_callable($fn)) {
            return call_user_func_array($fn, $args);
        }
    }
    return null;
}

/**
 * Collect POST into [columns, form_data] using field types + storage.columns / json_column.
 * @param string $form_key Form identifier
 * @param int $pageid Page id for field list
 * @return array{0: array, 1: array} [columns, form_data]
 */
function form_engine_collect($form_key, $pageid = 0) {
    $def = form_engine_def($form_key);
    $storage = $def['storage'] ?? [];
    if (!is_array($storage)) {
        $storage = [];
    }
    $column_keys = [];
    if (!empty($storage['columns']) && is_array($storage['columns'])) {
        foreach ($storage['columns'] as $ck) {
            $column_keys[(string)$ck] = true;
        }
    }

    $fields = form_engine_fields($form_key, $pageid, false);
    $columns = [];
    $form_data = [];

    foreach ($fields as $f) {
        if (($f['type'] ?? '') === 'section') {
            continue;
        }
        $key = $f['field_key'];
        $raw = null;
        $type = $f['type'] ?? 'text';

        switch ($type) {
            case 'checkbox':
                $raw = (function_exists('clean_myvar_opt') ? clean_myvar_opt($key, 'string', '') : ($_POST[$key] ?? '')) ? '1' : '0';
                break;
            case 'yesno':
            case 'select':
                $raw = function_exists('clean_myvar_opt')
                    ? clean_myvar_opt($key, 'string', '0')
                    : (string)($_POST[$key] ?? '0');
                break;
            case 'date':
                $str = function_exists('clean_myvar_opt')
                    ? clean_myvar_opt($key, 'string', '')
                    : (string)($_POST[$key] ?? '');
                $raw = function_exists('form_engine_parse_date_only')
                    ? form_engine_parse_date_only($str)
                    : $str;
                break;
            case 'number':
                $str = function_exists('clean_myvar_opt')
                    ? clean_myvar_opt($key, 'string', '')
                    : (string)($_POST[$key] ?? '');
                $raw = ($str === '' || $str === null) ? '' : $str;
                break;
            case 'phone':
                $str = function_exists('clean_myvar_opt')
                    ? clean_myvar_opt($key, 'string', '')
                    : (string)($_POST[$key] ?? '');
                $raw = function_exists('format_phone') ? format_phone($str) : $str;
                break;
            case 'textarea':
            case 'text':
            default:
                $raw = function_exists('clean_myvar_opt')
                    ? clean_myvar_opt($key, 'string', '')
                    : (string)($_POST[$key] ?? '');
                if (($key === 'name' || (strpos($key, 'ref') === 0 && strpos($key, 'name') !== false))
                    && function_exists('nameize')) {
                    $raw = nameize($raw);
                }
                break;
        }

        if (!empty($f['is_system']) || isset($column_keys[$key])) {
            $columns[$key] = $raw;
        }
        $form_data[$key] = $raw;
    }

    return [$columns, $form_data];
}

/**
 * Persist via form def save adapter.
 * @param string $form_key Form identifier
 * @param array $ctx Context (pageid, id, columns, form_data, userid, ...)
 * @return mixed Adapter result, or false if no adapter
 */
function form_engine_save($form_key, array $ctx) {
    $def = form_engine_def($form_key);
    $fn = $def['save'] ?? $def['save_fn'] ?? null;
    if (!is_callable($fn)) {
        return false;
    }
    $reflection = new ReflectionFunction(Closure::fromCallable($fn));
    $args = $reflection->getNumberOfParameters() <= 1 ? [$ctx] : [$form_key, $ctx];
    return form_engine_call_hook($form_key, 'save', $args, ['save_fn']);
}

/**
 * Invoke a callable stored in a render_layout entry.
 * @param array $layout
 * @param string $key
 * @param array $args
 * @return mixed|null
 */
function form_engine_call_layout(array $layout, $key, array $args = []) {
    $fn = $layout[$key] ?? null;
    if (is_string($fn) && function_exists($fn)) {
        return call_user_func_array($fn, $args);
    }
    if (is_callable($fn)) {
        return call_user_func_array($fn, $args);
    }
    return null;
}

/**
 * Render a full form: layout shell + pages + fields + client JS.
 * @param string $form_key Form identifier
 * @param array $values Storage row or flat field map
 * @param array $opts viewonly, pageid, seed, include_inactive, client, markup
 * @return string HTML
 */
function form_engine_render($form_key, $values = [], $opts = []) {
    if (!function_exists('form_engine_render_field')) {
        $dir = dirname(__FILE__);
        if (file_exists($dir . '/formrenderlib.php')) {
            include_once($dir . '/formrenderlib.php');
        }
    }
    if (!function_exists('form_engine_rules_js')) {
        $dir = dirname(__FILE__);
        if (file_exists($dir . '/formclientlib.php')) {
            include_once($dir . '/formclientlib.php');
        }
    }

    $def = form_engine_def($form_key);
    $viewonly = !empty($opts['viewonly']);
    $pageid = isset($opts['pageid']) ? (int)$opts['pageid'] : (function_exists('get_pageid') ? (int)(get_pageid() ?: 0) : 0);
    $seed = !array_key_exists('seed', $opts) || !empty($opts['seed']);
    $include_inactive = !empty($opts['include_inactive']);

    if ($seed) {
        form_engine_sync_field_defs($form_key, 0, false);
    }

    $fields = form_engine_fields($form_key, $pageid, $include_inactive);

    $row = is_array($values) ? $values : [];
    $vals = form_engine_prepare_db_array($form_key, $row);
    if (!is_array($vals)) {
        $vals = [];
    }
    $prepared = form_engine_call_hook(
        $form_key,
        'prepare_display_values_fn',
        [$vals, $row, $viewonly],
        ['prepare_values']
    );
    if (is_array($prepared)) {
        $vals = $prepared;
    }

    $layout = $def['render_layout'] ?? [];
    if (!is_array($layout)) {
        $layout = [];
    }
    // submit_value: prefer render_layout, then client, then default (declare once)
    $client_base = is_array($def['client'] ?? null) ? $def['client'] : [];
    $client_override = is_array($opts['client'] ?? null) ? $opts['client'] : [];
    $submit_value = $layout['submit_value']
        ?? $client_override['submit_value']
        ?? $client_base['submit_value']
        ?? (form_engine_default_client_opts()['submit_value'] ?? 'Submit');
    unset($client_base['submit_value'], $client_override['submit_value']);
    $client_opts = form_engine_merge_client_opts(array_merge(
        $client_base,
        $client_override,
        [
            'viewonly' => $viewonly,
            'submit_value' => $submit_value,
        ]
    ));
    if (!empty($def['wrap_field'])) {
        $client_opts['wrap_field'] = $def['wrap_field'];
    }
    if (!empty($opts['wrap_field'])) {
        $client_opts['wrap_field'] = $opts['wrap_field'];
    }
    $client_opts['render_field'] = function ($field, $value, $vo) use ($opts) {
        $ro = ['viewonly' => $vo];
        if (!empty($opts['markup']) && is_array($opts['markup'])) {
            $ro['markup'] = $opts['markup'];
        }
        return form_engine_render_field($field, $value, $ro);
    };

    $pages = form_engine_group_fields_by_section($fields, $client_opts['default_section'] ?? 'General');
    $page_count = count($pages);

    $wrapper_id = $layout['wrapper_id'] ?? ('form_engine_' . preg_replace('/[^a-z0-9_]+/i', '_', $form_key));
    $wrapper_class = $layout['wrapper_class'] ?? '';
    if ($viewonly && !empty($layout['wrapper_class_viewonly'])) {
        $wrapper_class = trim($wrapper_class . ' ' . $layout['wrapper_class_viewonly']);
    }

    $parts = [];
    $parts[] = '<div id="' . htmlspecialchars($wrapper_id) . '"'
        . ($wrapper_class !== '' ? ' class="' . htmlspecialchars($wrapper_class) . '"' : '')
        . '>';

    foreach (['styles' => [], 'header' => [$vals, $viewonly]] as $key => $args) {
        $html = form_engine_call_layout($layout, $key, $args);
        if (is_string($html) && $html !== '') {
            $parts[] = $html;
        }
    }

    $form_open = form_engine_call_layout($layout, 'form_open', [$vals, $viewonly]);
    if ($form_open === null) {
        $form_id = ltrim($client_opts['form_selector'] ?? '#form_engine_form', '#');
        $form_open = '<form name="' . htmlspecialchars($form_id) . '" id="' . htmlspecialchars($form_id) . '"><fieldset class="formContainer">';
    }
    $parts[] = $form_open;

    if ($page_count > 1 && !$viewonly) {
        $parts[] = form_engine_pager_html($pages, $client_opts);
    }

    foreach ($pages as $i => $page) {
        $parts[] = form_engine_page_html($page, $i, $vals, $client_opts);
    }

    $parts[] = form_engine_submit_nav_html($page_count, $client_opts);

    $form_close = form_engine_call_layout($layout, 'form_close', [$vals, $viewonly]);
    if ($form_close === null) {
        $form_close = '</fieldset></form>';
    }
    $parts[] = $form_close;

    if (!$viewonly) {
        $parts[] = form_engine_rules_js($fields, $client_opts);
        if ($page_count > 1) {
            $parts[] = form_engine_multipage_js($page_count, $client_opts);
        }
    }

    if (!empty($layout['keepalive']) && function_exists('keepalive')) {
        $parts[] = keepalive();
    }

    $parts[] = '</div>';
    return implode("\n", $parts);
}
