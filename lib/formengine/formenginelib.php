<?php
/***************************************************************************
 * formenginelib.php - Named form engine (hooks, storage profiles, migration plans)
 * -------------------------------------------------------------------------
 * Forms are identified by form_key (e.g. staff_app). Callers declare process
 * hooks; the editor binds field_keys to those hooks. Storage adapters know
 * where values live (columns + optional JSON). Each form definition can
 * describe a migration plan from legacy static columns.
 ***************************************************************************/

if (!defined('FORMENGINELIB')) {
    define('FORMENGINELIB', true);
}

/** Canonical form key for the staff application consumer. */
define('FORM_KEY_STAFF_APP', 'staff_app');

/**
 * Registry of known forms: metadata, default hooks, storage profile id, migration plan.
 * Storage profile implementations live in code (v1); signup can register later.
 */
function form_engine_registry() {
    return [
        FORM_KEY_STAFF_APP => [
            'label' => 'Staff Application',
            'description' => 'Staff work application with consent, screening, and background-check process hooks.',
            'storage' => 'staff',
            'hooks' => form_engine_staff_app_hook_defs(),
            'migration' => form_engine_staff_app_migration_plan(),
        ],
        // Future: 'signup' => [ 'label' => 'Sign up', 'storage' => 'user', 'hooks' => [...], 'migration' => [...] ]
    ];
}

function form_engine_get_form_def($form_key) {
    $reg = form_engine_registry();
    return $reg[$form_key] ?? null;
}

/**
 * Default process hooks for staff_app (semantic slots the backend understands).
 * meta.field_keys: multi-source hooks (screening). meta.computed: derived values.
 */
function form_engine_staff_app_hook_defs() {
    return [
        [
            'hook_id' => 'identity.name',
            'label' => 'Full name',
            'required' => 1,
            'field_key' => 'name',
            'help' => 'Used in emails, manager lists, and export.',
        ],
        [
            'hook_id' => 'identity.phone',
            'label' => 'Phone',
            'required' => 1,
            'field_key' => 'phone',
            'help' => 'Contact phone on the application record.',
        ],
        [
            'hook_id' => 'identity.date_of_birth',
            'label' => 'Date of birth',
            'required' => 1,
            'field_key' => 'dateofbirth',
            'help' => 'Drives under-18 / parental consent and background-check eligibility.',
        ],
        [
            'hook_id' => 'consent.worker_name',
            'label' => 'Worker consent name',
            'required' => 1,
            'field_key' => 'workerconsent',
            'help' => 'Printed name on worker consent.',
        ],
        [
            'hook_id' => 'consent.worker_date',
            'label' => 'Worker consent date',
            'required' => 1,
            'field_key' => 'workerconsentdate',
            'help' => 'Determines whether the application is out of date.',
        ],
        [
            'hook_id' => 'consent.worker_sig',
            'label' => 'Worker signature',
            'required' => 1,
            'field_key' => 'workerconsentsig',
            'help' => 'Checkbox signature for worker consent.',
        ],
        [
            'hook_id' => 'consent.parental_name',
            'label' => 'Parental consent name',
            'required' => 0,
            'field_key' => 'parentalconsent',
            'help' => 'Required when applicant is under 18.',
        ],
        [
            'hook_id' => 'consent.parental_sig',
            'label' => 'Parental signature',
            'required' => 0,
            'field_key' => 'parentalconsentsig',
            'help' => 'Required when applicant is under 18.',
        ],
        [
            'hook_id' => 'screening.flag_sources',
            'label' => 'Screening risk questions',
            'required' => 0,
            'field_key' => null,
            'meta' => [
                'field_keys' => ['q1_1', 'q1_2', 'q1_3', 'q2_1', 'q2_2'],
                'aggregate' => 'any_truthy',
            ],
            'help' => 'Yes answers on these fields flag the application for review (server-side).',
        ],
        [
            'hook_id' => 'meta.under_18',
            'label' => 'Under 18 (computed)',
            'required' => 0,
            'field_key' => null,
            'meta' => [
                'computed' => 'under_18_from_dob',
                'from_hook' => 'identity.date_of_birth',
                // Optional legacy field still used by client rules until replaced
                'legacy_field_key' => 'agerange',
            ],
            'help' => 'Derived from date of birth on the server. Client may still use agerange for show/hide.',
        ],
    ];
}

/**
 * Migration plan from original static staff columns → form_data + hooks.
 * Used by migrate tooling and documentation; executable steps call existing migrate helpers.
 */
function form_engine_staff_app_migration_plan() {
    return [
        'from' => 'static_events_staff_columns',
        'description' => 'Legacy events_staff / events_staff_archive answer columns → form_data JSON; process fields retained as columns.',
        'steps' => [
            [
                'id' => 'ensure_schema',
                'label' => 'Ensure form_data, form_key, and hooks tables exist',
            ],
            [
                'id' => 'seed_fields',
                'label' => 'Seed default field definitions (form_key=staff_app)',
            ],
            [
                'id' => 'seed_hooks',
                'label' => 'Seed default process hook bindings',
            ],
            [
                'id' => 'backfill_form_data',
                'label' => 'Copy classic columns into form_data for all staff and archive rows',
                'source_columns' => [
                    'address', 'address2', 'city', 'state', 'zip',
                    'agerange', 'cocmember', 'congregation', 'priorwork',
                    'q1_1', 'q1_2', 'q1_3', 'q2_1', 'q2_2', 'q2_3',
                    'ref1name', 'ref1relationship', 'ref1phone',
                    'ref2name', 'ref2relationship', 'ref2phone',
                    'ref3name', 'ref3relationship', 'ref3phone',
                    'name', 'phone', 'dateofbirth',
                    'parentalconsent', 'parentalconsentsig',
                    'workerconsent', 'workerconsentsig', 'workerconsentdate',
                ],
            ],
            [
                'id' => 'normalize_signatures',
                'label' => 'Normalize checkbox signatures on → 1',
            ],
            [
                'id' => 'drop_deprecated_columns',
                'label' => 'Drop classic answer columns after form_data is complete',
                'columns' => [
                    'address', 'address2', 'city', 'state', 'zip',
                    'agerange', 'cocmember', 'congregation', 'priorwork',
                    'q1_1', 'q1_2', 'q1_3', 'q2_1', 'q2_2', 'q2_3',
                    'ref1name', 'ref1relationship', 'ref1phone',
                    'ref2name', 'ref2relationship', 'ref2phone',
                    'ref3name', 'ref3relationship', 'ref3phone',
                    'bgcheckpass',
                ],
            ],
        ],
        'retained_columns' => [
            'staffid', 'userid', 'pageid', 'name', 'phone', 'dateofbirth',
            'parentalconsent', 'parentalconsentsig',
            'workerconsent', 'workerconsentsig', 'workerconsentdate',
            'bgcheckpassdate', 'form_data',
        ],
    ];
}

/**
 * Ensure form_key column + hooks table; backfill form_key; seed hook bindings.
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
                 ADD COLUMN form_key varchar(64) NOT NULL DEFAULT 'staff_app' AFTER fieldid"
            );
            @execute_db_sql(
                "UPDATE form_fields SET form_key = 'staff_app'
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
    form_engine_ensure_varchar_column('form_fields', 'form_key', 64, "'staff_app'");
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
        'form_key' => "varchar(64) NOT NULL DEFAULT 'staff_app'",
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
    form_engine_ensure_varchar_column('form_hook_bindings', 'form_key', 64, "'staff_app'");
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
            'hookid' => $b ? (int)($b['id'] ?? $b['hookid'] ?? 0) : 0,
        ];
        $seen[$hid] = true;
    }
    // Extra DB-only hooks
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
            'hookid' => (int)($b['id'] ?? $b['hookid'] ?? 0),
        ];
    }
    return $out;
}

/**
 * Resolve a single hook value from a flat values map (field_key => value).
 */
function form_hook_value($form_key, $hook_id, $values) {
    if (!is_array($values)) {
        $values = [];
    }
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

    // Computed under_18
    if (($meta['computed'] ?? '') === 'under_18_from_dob') {
        $dob = null;
        $from = $meta['from_hook'] ?? 'identity.date_of_birth';
        // Prefer DOB from identity hook field
        foreach ($hooks as $h2) {
            if ($h2['hook_id'] === $from && !empty($h2['field_key'])) {
                $dob = $values[$h2['field_key']] ?? null;
                break;
            }
        }
        if ($dob === null || $dob === '') {
            $dob = $values['dateofbirth'] ?? null;
        }
        return form_engine_is_under_18($dob) ? '1' : '0';
    }

    // Multi-field aggregate (screening)
    if (!empty($meta['field_keys']) && is_array($meta['field_keys'])) {
        $agg = $meta['aggregate'] ?? 'any_truthy';
        if ($agg === 'any_truthy') {
            foreach ($meta['field_keys'] as $fk) {
                $v = $values[$fk] ?? null;
                if ($v === true || $v === 1 || $v === '1' || $v === 'yes' || $v === 'on') {
                    return '1';
                }
                if (is_numeric($v) && (float)$v > 0) {
                    return '1';
                }
            }
            return '0';
        }
    }

    $fk = $hook['field_key'] ?? null;
    if ($fk === null || $fk === '') {
        return null;
    }
    return array_key_exists($fk, $values) ? $values[$fk] : null;
}

/**
 * Whether DOB indicates under 18 (accepts unix timestamp or m/d/Y string).
 */
function form_engine_is_under_18($dob) {
    if ($dob === null || $dob === '' || $dob === false) {
        return false;
    }
    if (!is_numeric($dob)) {
        $ts = strtotime((string)$dob);
    } else {
        $ts = (int)$dob;
    }
    if ($ts <= 0) {
        return false;
    }
    $eighteen = 18 * 365 * 24 * 60 * 60;
    return (time() - $ts) < $eighteen;
}

/**
 * Screening flagged? Uses screening.flag_sources hook.
 */
function form_engine_staff_is_flagged($values, $form_key = FORM_KEY_STAFF_APP) {
    $v = form_hook_value($form_key, 'screening.flag_sources', $values);
    return $v === '1' || $v === 1 || $v === true;
}

/**
 * Resolve flat values from a storage row using staff merge rules.
 * (Generic name; staff implementation delegates to get_staff_application_values.)
 */
function resolve_form_values($form_key, $storage_row) {
    global $CFG;
    if ($form_key === FORM_KEY_STAFF_APP) {
        if (!function_exists('get_staff_application_values')) {
            include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
        }
        return get_staff_application_values($storage_row);
    }
    // Generic fallback: form_data JSON only
    if (!empty($storage_row['form_data'])) {
        $json = is_string($storage_row['form_data']) ? json_decode($storage_row['form_data'], true) : $storage_row['form_data'];
        if (is_array($json)) {
            return $json;
        }
    }
    return is_array($storage_row) ? $storage_row : [];
}

/**
 * Field keys that satisfy any required hook (delete protection).
 */

/**
 * Hook rows bound to a given field_key (for editor labels / delete warnings).
 * @return array list of [hook_id, label, required]
 */
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
    $keys = [];
    foreach (form_engine_get_hooks($form_key, $pageid) as $h) {
        if (empty($h['required'])) {
            continue;
        }
        if (!empty($h['field_key'])) {
            $keys[$h['field_key']] = true;
        }
        $meta = $h['meta'] ?? [];
        if (!empty($meta['field_keys']) && is_array($meta['field_keys'])) {
            foreach ($meta['field_keys'] as $fk) {
                // multi-source screening not individually required for delete
            }
        }
    }
    // Always protect DOB-related and consent section keys for staff
    if ($form_key === FORM_KEY_STAFF_APP && function_exists('get_staff_form_protected_keys')) {
        foreach (get_staff_form_protected_keys() as $k) {
            $keys[$k] = true;
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


function form_engine_staff_save_application($pageid, $staffid, $col_params, $form_data, $userid) {
    global $CFG;
    if (!function_exists('fetch_template')) {
        // CMS core assumed available
    }
    $params = array_merge([
        'userid' => $userid,
        'pageid' => $pageid,
        'name' => '',
        'phone' => '',
        'dateofbirth' => 0,
        'parentalconsent' => '',
        'parentalconsentsig' => '',
        'workerconsent' => '',
        'workerconsentsig' => '',
        'workerconsentdate' => 0,
        'bgcheckpassdate' => 0,
    ], $col_params);
    $params['form_data'] = is_string($form_data) ? $form_data : json_encode($form_data, JSON_UNESCAPED_UNICODE);

    $newid = false;
    if ($staffid) {
        $params['staffid'] = $staffid;
        execute_db_sql(fetch_template('dbsql/events.sql', 'update_staff_app', 'events'), $params);
    } else {
        $newid = execute_db_sql(fetch_template('dbsql/events.sql', 'insert_staff_app', 'events'), $params);
        $staffid = $newid ? $newid : false;
    }

    if ($staffid) {
        $staff = get_db_row(fetch_template('dbsql/events.sql', 'get_staff_app', 'events'), ['staffid' => $staffid]);
        $params['bgcheckpassdate'] = $staff['bgcheckpassdate'] ?? 0;
        $params['year'] = date('Y');
        $params['staffid'] = $staffid;
        if (get_db_row(fetch_template('dbsql/events.sql', 'get_staff_by_year', 'events'), [
            'staffid' => $staffid, 'pageid' => $pageid, 'year' => $params['year'],
        ])) {
            $SQL = fetch_template('dbsql/events.sql', 'update_staff_app_archive', 'events');
        } else {
            $SQL = fetch_template('dbsql/events.sql', 'insert_staff_app_archive', 'events');
        }
        execute_db_sql($SQL, $params);
    }

    return ['staffid' => $staffid, 'new' => (bool)$newid, 'row' => $staff ?? null];
}

/**
 * Run form migration plan executable steps for staff_app (wraps existing migrate).
 */
function form_engine_run_migration($form_key, $pageid = null) {
    global $CFG;
    if ($form_key !== FORM_KEY_STAFF_APP) {
        return ['ok' => false, 'message' => 'No migration plan implemented for ' . $form_key];
    }
    form_engine_ensure_schema();
    form_engine_seed_hooks($form_key, 0);
    if (!function_exists('migrate_staff_form_data')) {
        include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
    }
    $result = migrate_staff_form_data($pageid);
    $result['hooks_seeded'] = true;
    $result['form_key'] = $form_key;
    $result['plan'] = form_engine_staff_app_migration_plan()['id'] ?? 'staff_app_legacy';
    return $result;
}


/**
 * Load field definitions for a form_key (page-specific overrides global pageid=0).
 */
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
 * Seed default fields for a form_key (staff_app uses events seed helper).
 */
function form_engine_seed_fields($form_key, $pageid = 0, $force = false) {
    global $CFG;
    if ($form_key === (defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app')) {
        if (!function_exists('seed_staff_form_fields')) {
            include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
        }
        return seed_staff_form_fields($pageid, $force);
    }
    form_engine_seed_hooks($form_key, 0);
    return 0;
}

/**
 * Keys forced to form bottom (consent blocks) — form-specific.
 */
function form_engine_fixed_keys($form_key) {
    global $CFG;
    if ($form_key === (defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app')) {
        if (!function_exists('get_staff_form_fixed_consent_keys')) {
            include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
        }
        return get_staff_form_fixed_consent_keys();
    }
    return [];
}

/**
 * Migration tools HTML for editor (delegates to form plan / staff tools).
 */
function form_editor_migration_tools_html($form_key) {
    global $CFG;
    if ($form_key === (defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app')) {
        if (!function_exists('staff_form_migration_tools_html')) {
            include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
        }
        // Rewrite button onclick to core JS names if present
        $html = staff_form_migration_tools_html();
        $html = str_replace('staffFormRunMigrate', 'formEngineRunMigrate', $html);
        $html = str_replace('staffFormDropDeprecated', 'formEngineDropDeprecated', $html);
        $html = str_replace('staff_form_migrate_btn', 'form_engine_migrate_btn', $html);
        $html = str_replace('staff_form_migrate_result', 'form_engine_migrate_result', $html);
        return $html;
    }
    return '';
}
