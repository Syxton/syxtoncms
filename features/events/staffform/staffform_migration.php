<?php
/***************************************************************************
 * staffform_migration.php - Legacy static-column → form_data migration
 * -------------------------------------------------------------------------
 * OPTIONAL. Used only by Form Editor migration/drop actions and the
 * migration_run / drop_deprecated / migration_tools_html form-def callbacks.
 *
 * Safe to delete this file after staff_form_migration_status() reports
 * complete on every environment (form_data filled, deprecated columns gone).
 * Also remove migration_lib / migration_run / drop_deprecated /
 * migration_tools_html from features/events/formdefs/staff_app.php.
 *
 * Fresh installs never need this file: install.sql already uses retained
 * columns + form_data only.
 ***************************************************************************/

if (!defined('STAFFFORM_MIGRATION')) {
    define('STAFFFORM_MIGRATION', true);
}

// Runtime helpers: ensure_staff_form_tables, seed_staff_form_fields,
// get_default_staff_form_fields, form_engine_normalize_checkbox_value
if (!defined('STAFFFORMLIB')) {
    $__sf = __DIR__ . '/staffformlib.php';
    if (file_exists($__sf)) {
        include_once($__sf);
    }
}

/**
 * One-time / admin-triggered migration:
 * 1. Ensure tables/columns exist
 * 2. Seed default fields
 * 3. For every existing staff row, populate form_data from the classic columns
 */
/**
 * Build form_data JSON from a staff/archive row using all classic answer columns.
 * Existing form_data keys are kept; missing keys are filled from columns (including "0").
 */
function staff_form_row_to_form_data($row) {
    $data = [];
    if (!empty($row['form_data'])) {
        $existing = is_string($row['form_data']) ? json_decode($row['form_data'], true) : $row['form_data'];
        if (is_array($existing)) {
            $data = $existing;
        }
    }

    // Every field definition key + known deprecated columns
    $keys = [];
    foreach (get_default_staff_form_fields() as $f) {
        if (($f['type'] ?? '') === 'section') {
            continue;
        }
        $keys[$f['field_key']] = true;
    }
    foreach (get_staff_form_deprecated_columns() as $k) {
        $keys[$k] = true;
    }
    foreach (['name', 'phone', 'dateofbirth', 'parentalconsent', 'parentalconsentsig',
              'workerconsent', 'workerconsentsig', 'workerconsentdate'] as $k) {
        $keys[$k] = true;
    }

    foreach (array_keys($keys) as $k) {
        if (!array_key_exists($k, $row) || $row[$k] === null) {
            continue;
        }
        // Fill when form_data lacks the key (allow "0" and empty string from columns)
        if (!array_key_exists($k, $data)) {
            $data[$k] = $row[$k];
        }
    }

    // Normalize signature checkboxes: legacy "on" → "1" (both from columns and existing form_data)
    foreach (['workerconsentsig', 'parentalconsentsig'] as $sig_key) {
        if (array_key_exists($sig_key, $data)) {
            $data[$sig_key] = form_engine_normalize_checkbox_value($data[$sig_key]);
        }
    }

    return $data;
}

function migrate_staff_form_data($pageid = null) {
    ensure_staff_form_tables();
    $seeded = seed_staff_form_fields(0); // global defaults

    // Always migrate ALL rows (schema-wide). pageid filter only affects messaging if needed.
    // Archives from prior years must not be skipped when admin is on a different page.
    $rows = get_db_result("SELECT * FROM events_staff");
    $updated = 0;
    $skipped = 0;

    if ($rows) {
        while ($row = fetch_row($rows)) {
            $data = staff_form_row_to_form_data($row);
            // Always write — even if only zeros — so form_data is never NULL.
            // Also normalize legacy signature columns ("on" → "1") so classic columns match form_data.
            $wsig = form_engine_normalize_checkbox_value($data['workerconsentsig'] ?? ($row['workerconsentsig'] ?? ''));
            $psig = form_engine_normalize_checkbox_value($data['parentalconsentsig'] ?? ($row['parentalconsentsig'] ?? ''));
            $data['workerconsentsig'] = $wsig;
            $data['parentalconsentsig'] = $psig;
            execute_db_sql(
                "UPDATE events_staff SET form_data = @@fd@@, workerconsentsig = @@wsig@@, parentalconsentsig = @@psig@@ WHERE staffid = @@id@@",
                [
                    'fd' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'wsig' => $wsig,
                    'psig' => $psig,
                    'id' => $row['staffid'],
                ]
            );
            $updated++;
        }
    }

    $arows = get_db_result("SELECT * FROM events_staff_archive");
    $aupdated = 0;
    if ($arows) {
        while ($row = fetch_row($arows)) {
            $data = staff_form_row_to_form_data($row);
            $wsig = form_engine_normalize_checkbox_value($data['workerconsentsig'] ?? ($row['workerconsentsig'] ?? ''));
            $psig = form_engine_normalize_checkbox_value($data['parentalconsentsig'] ?? ($row['parentalconsentsig'] ?? ''));
            $data['workerconsentsig'] = $wsig;
            $data['parentalconsentsig'] = $psig;
            execute_db_sql(
                "UPDATE events_staff_archive SET form_data = @@fd@@, workerconsentsig = @@wsig@@, parentalconsentsig = @@psig@@ WHERE archiveid = @@id@@",
                [
                    'fd' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'wsig' => $wsig,
                    'psig' => $psig,
                    'id' => $row['archiveid'],
                ]
            );
            $aupdated++;
        }
    }

    // Count remaining empty for the message
    $miss = get_db_row("SELECT COUNT(*) AS cnt FROM events_staff WHERE form_data IS NULL OR form_data = ''");
    $amiss = get_db_row("SELECT COUNT(*) AS cnt FROM events_staff_archive WHERE form_data IS NULL OR form_data = ''");

    return [
        'seeded_fields' => $seeded,
        'staff_updated' => $updated,
        'archive_updated' => $aupdated,
        'staff_still_empty' => $miss ? (int)$miss['cnt'] : 0,
        'archive_still_empty' => $amiss ? (int)$amiss['cnt'] : 0,
    ];
}

/**
 * Columns retained after deprecation drop.
 */
function get_staff_form_retained_columns() {
    return [
        'staffid', 'userid', 'pageid',
        'name', 'phone', 'dateofbirth',
        'parentalconsent', 'parentalconsentsig',
        'workerconsent', 'workerconsentsig', 'workerconsentdate',
        'bgcheckpassdate',
        'form_data',
    ];
}

/**
 * Classic answer columns that live only in form_data after migration.
 */
function get_staff_form_deprecated_columns() {
    return [
        'address', 'address2', 'city', 'state', 'zip',
        'agerange', 'cocmember', 'congregation', 'priorwork',
        'q1_1', 'q1_2', 'q1_3', 'q2_1', 'q2_2', 'q2_3',
        'ref1name', 'ref1relationship', 'ref1phone',
        'ref2name', 'ref2relationship', 'ref2phone',
        'ref3name', 'ref3relationship', 'ref3phone',
        'bgcheckpass', // superseded by bgcheckpassdate alone
    ];
}

/**
 * Whether legacy migration tooling is still needed.
 * - needs_migration: any staff/archive row lacks form_data
 * - needs_drop: any deprecated classic column still exists on either table
 * - complete: neither of the above
 */
function staff_form_column_exists($table, $column) {
    $row = get_db_row(
        "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = @@t@@
           AND COLUMN_NAME = @@c@@",
        ['t' => $table, 'c' => $column]
    );
    return $row && (int)$row['cnt'] > 0;
}

function staff_form_migration_status() {
    ensure_staff_form_tables();

    $empty_cnt = 0;
    if (staff_form_column_exists('events_staff', 'form_data')) {
        $staff_empty = get_db_row(
            "SELECT COUNT(*) AS cnt FROM events_staff
             WHERE form_data IS NULL OR form_data = '' OR form_data = '{}'"
        );
        $empty_cnt += (int)($staff_empty['cnt'] ?? 0);
    } else {
        // Column still missing — treat as needing migration path
        $cnt = get_db_row("SELECT COUNT(*) AS cnt FROM events_staff");
        $empty_cnt += (int)($cnt['cnt'] ?? 0);
    }
    if (staff_form_column_exists('events_staff_archive', 'form_data')) {
        $arch_empty = get_db_row(
            "SELECT COUNT(*) AS cnt FROM events_staff_archive
             WHERE form_data IS NULL OR form_data = '' OR form_data = '{}'"
        );
        $empty_cnt += (int)($arch_empty['cnt'] ?? 0);
    } else {
        $cnt = get_db_row("SELECT COUNT(*) AS cnt FROM events_staff_archive");
        $empty_cnt += (int)($cnt['cnt'] ?? 0);
    }

    $deprecated = get_staff_form_deprecated_columns();
    $remaining_cols = [];
    foreach (['events_staff', 'events_staff_archive'] as $table) {
        foreach ($deprecated as $col) {
            $row = get_db_row(
                "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = @@t@@
                   AND COLUMN_NAME = @@c@@",
                ['t' => $table, 'c' => $col]
            );
            if ($row && (int)$row['cnt'] > 0) {
                $remaining_cols[$col] = true;
            }
        }
    }

    $needs_migration = $empty_cnt > 0;
    $needs_drop = !empty($remaining_cols);

    return [
        'needs_migration' => $needs_migration,
        'needs_drop' => $needs_drop,
        'empty_form_data_rows' => $empty_cnt,
        'remaining_columns' => array_keys($remaining_cols),
        'complete' => !$needs_migration && !$needs_drop,
    ];
}

/**
 * Migration toolbar HTML for the form editor.
 * Hidden entirely when form_data is filled and deprecated columns are gone.
 */
function staff_form_migration_tools_html() {
    $status = staff_form_migration_status();
    if (!empty($status['complete'])) {
        return ''; // Fully migrated — no tooling needed
    }

    $html = '<div id="form_editor_migration_tools" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid #e2e8f0">';

    if (!empty($status['needs_migration'])) {
        $empty = (int)($status['empty_form_data_rows'] ?? 0);
        $html .= '<p class="sfe-hint" style="margin:0 0 8px">
            Legacy data still needs migration'
            . ($empty > 0 ? ' (' . $empty . ' row(s) without form_data)' : '')
            . '.
        </p>
        <button type="button" class="btn-secondary" id="form_engine_migrate_btn" onclick="formEngineRunMigrate()">Run Data Migration</button>
        <span id="form_engine_migrate_result" style="margin-left:10px"></span>';
    } elseif (!empty($status['needs_drop'])) {
        $cols = implode(', ', array_slice($status['remaining_columns'] ?? [], 0, 8));
        $more = count($status['remaining_columns'] ?? []) > 8 ? '…' : '';
        $html .= '<p class="sfe-hint" style="margin:0 0 8px">
            Data is migrated. Deprecated DB columns can be removed'
            . ($cols !== '' ? ' (' . htmlspecialchars($cols) . $more . ')' : '')
            . '.
        </p>
        <button type="button" id="form_editor_drop_cols_btn" class="btn-secondary" onclick="formEngineDropDeprecated()">Remove deprecated DB columns</button>
        <span id="form_engine_migrate_result" style="margin-left:10px"></span>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Drop deprecated classic columns from events_staff and events_staff_archive
 * after form_data migration is complete.
 */
function drop_deprecated_staff_columns() {
    $deprecated = get_staff_form_deprecated_columns();

    // Safety: refuse if any staff row still lacks form_data
    $missing = get_db_row(
        "SELECT COUNT(*) AS cnt FROM events_staff
         WHERE form_data IS NULL OR form_data = '' OR form_data = '{}'"
    );
    $missing_cnt = $missing ? (int)$missing['cnt'] : 0;
    if ($missing_cnt > 0) {
        return [
            'ok' => false,
            'message' => "Cannot drop columns: {$missing_cnt} staff row(s) still have empty form_data. Run Data Migration first.",
            'dropped' => [],
        ];
    }

    $arch_missing = get_db_row(
        "SELECT COUNT(*) AS cnt FROM events_staff_archive
         WHERE form_data IS NULL OR form_data = '' OR form_data = '{}'"
    );
    $arch_cnt = $arch_missing ? (int)$arch_missing['cnt'] : 0;
    if ($arch_cnt > 0) {
        return [
            'ok' => false,
            'message' => "Cannot drop columns: {$arch_cnt} archive row(s) still have empty form_data. Run Data Migration first.",
            'dropped' => [],
        ];
    }

    $dropped = [];
    foreach (['events_staff', 'events_staff_archive'] as $table) {
        foreach ($deprecated as $col) {
            $row = get_db_row(
                "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = @@table@@
                   AND COLUMN_NAME = @@column@@",
                ['table' => $table, 'column' => $col]
            );
            if ($row && (int)$row['cnt'] > 0) {
                execute_db_sql("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
                $dropped[] = "{$table}.{$col}";
            }
        }
    }

    return [
        'ok' => true,
        'message' => empty($dropped)
            ? 'No deprecated columns remained to drop.'
            : ('Dropped ' . count($dropped) . ' column(s): ' . implode(', ', $dropped)),
        'dropped' => $dropped,
    ];
}
