<?php
/**
 * Dynamic Staff Application Form library.
 * Keeps full backward compatibility with existing columns while allowing
 * the form definition (and future custom questions) to live in the DB.
 */

if (!defined('STAFFFORMLIB')) {
    define('STAFFFORMLIB', true);
}

/**
 * Default form definition that exactly matches the current static form.
 * All fields that map to existing columns are marked is_system = 1.
 * This is used as fallback and as the seed for the DB table.
 */
function get_default_staff_form_fields() {
    $fields = [
        // ---- Personal Information ----
        [
            'field_key'   => 'name',
            'label'       => 'Name',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 10,
            'section'     => 'Personal Information',
            'helptext'    => 'input_full_name',
            'is_system'   => 1,
            'extra_attrs' => ['disabled' => true, 'readonly' => true],
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'dateofbirth',
            'label'       => 'Date of Birth',
            'type'        => 'date',
            'required'    => 1,
            'sortorder'   => 20,
            'section'     => 'Personal Information',
            'helptext'    => 'input_staff_dob',
            'is_system'   => 1,
            'validation'  => [
                'required' => true,
                'custom'   => '^[0-9]{2}/[0-9]{2}/[0-9]{4}$',
                'date'     => true,
            ],
            'extra_attrs' => [
                // JS that auto-sets agerange (kept for compatibility)
                'onblur' => "var d = new Date($(this).val()).getTime() / 1000; var now = Math.floor(Date.now()/1000); var ar = 2; if (now - d < 567648000) { ar = 0; } else if (now - d < 788400000) { ar = 1; } $('#agerange').val(ar); $('input[type=hidden][name=agerange]').val(String(ar)); $('#agerange').trigger('change'); if (window.staffFormApplyRules) { window.staffFormApplyRules(); }",
            ],
        ],
        [
            'field_key'   => 'phone',
            'label'       => 'Phone',
            'type'        => 'phone',
            'required'    => 1,
            'sortorder'   => 30,
            'section'     => 'Personal Information',
            'helptext'    => 'input_default_phone',
            'is_system'   => 1,
            'validation'  => ['required' => true, 'phone' => true],
        ],
        [
            'field_key'   => 'address',
            'label'       => 'Address',
            'type'        => 'textarea',
            'required'    => 1,
            'sortorder'   => 40,
            'section'     => 'Personal Information',
            'helptext'    => 'input_address',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        // address2 / city / state / zip exist as columns but are not currently collected in the form.
        // They remain is_system so managers/export still work; they can be added later via the editor.
        [
            'field_key'   => 'agerange',
            'label'       => 'Age Range',
            'type'        => 'select',
            'required'    => 1,
            'sortorder'   => 50,
            'section'     => 'Personal Information',
            'helptext'    => 'input_staff_agerange',
            'is_system'   => 0,
            'options'     => [
                ['value' => '0', 'label' => 'younger than 18'],
                ['value' => '1', 'label' => '18-25'],
                ['value' => '2', 'label' => '26 or older'],
            ],
            'validation'  => ['required' => true],
            'extra_attrs' => [],
        ],
        [
            'field_key'   => 'cocmember',
            'label'       => 'Are you a member of the church of Christ?',
            'type'        => 'yesno',
            'required'    => 1,
            'sortorder'   => 60,
            'section'     => 'Personal Information',
            'helptext'    => 'select_yesno',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'congregation',
            'label'       => 'Congregation Name',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 70,
            'section'     => 'Personal Information',
            'helptext'    => 'input_staff_congregation',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'priorwork',
            'label'       => 'Have you worked at Camp Wabashi as a staff member before?',
            'type'        => 'yesno',
            'required'    => 1,
            'sortorder'   => 80,
            'section'     => 'Personal Information',
            'helptext'    => 'select_yesno',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],

        // ---- Screening questions ----
        [
            'field_key'   => 'section_screening',
            'label'       => 'Have you at any time ever:',
            'type'        => 'section',
            'required'    => 0,
            'sortorder'   => 100,
            'section'     => 'Screening',
            'is_system'   => 0,
        ],
        [
            'field_key'   => 'q1_1',
            'label'       => 'Been arrested for any reason?',
            'type'        => 'yesno',
            'required'    => 1,
            'sortorder'   => 110,
            'section'     => 'Screening',
            'helptext'    => 'select_yesno',
            'is_system'   => 0,
            'validation'  => ['required' => true],
            'extra_attrs' => [
                'onchange' => "if (($('#q1_1').val() + $('#q1_2').val() + $('#q1_3').val() + $('#q2_1').val() + $('#q2_2').val()) > 0) { $('#q2_3').attr('data-rule-required', 'true'); } else { $('#q2_3').removeData('rule-required').removeAttr('data-rule-required'); }",
            ],
        ],
        [
            'field_key'   => 'q1_2',
            'label'       => 'Been convicted of, or pleaded guilty or no contest to, any crime?',
            'type'        => 'yesno',
            'required'    => 1,
            'sortorder'   => 120,
            'section'     => 'Screening',
            'helptext'    => 'select_yesno',
            'is_system'   => 0,
            'validation'  => ['required' => true],
            'extra_attrs' => [
                'onchange' => "if (($('#q1_1').val() + $('#q1_2').val() + $('#q1_3').val() + $('#q2_1').val() + $('#q2_2').val()) > 0) { $('#q2_3').attr('data-rule-required', 'true'); } else { $('#q2_3').removeData('rule-required').removeAttr('data-rule-required'); }",
            ],
        ],
        [
            'field_key'   => 'q1_3',
            'label'       => 'Engaged in, or been accused of, any child molestation, exploitation, or abuse?',
            'type'        => 'yesno',
            'required'    => 1,
            'sortorder'   => 130,
            'section'     => 'Screening',
            'helptext'    => 'select_yesno',
            'is_system'   => 0,
            'validation'  => ['required' => true],
            'extra_attrs' => [
                'onchange' => "if (($('#q1_1').val() + $('#q1_2').val() + $('#q1_3').val() + $('#q2_1').val() + $('#q2_2').val()) > 0) { $('#q2_3').attr('data-rule-required', 'true'); } else { $('#q2_3').removeData('rule-required').removeAttr('data-rule-required'); }",
            ],
        ],
        [
            'field_key'   => 'section_q2',
            'label'       => 'Have you ever been the subject of:',
            'type'        => 'section',
            'required'    => 0,
            'sortorder'   => 140,
            'section'     => 'Screening',
            'is_system'   => 0,
        ],
        [
            'field_key'   => 'q2_1',
            'label'       => 'Any investigation or allegation of child abuse or neglect?',
            'type'        => 'yesno',
            'required'    => 1,
            'sortorder'   => 150,
            'section'     => 'Screening',
            'helptext'    => 'select_yesno',
            'is_system'   => 0,
            'validation'  => ['required' => true],
            'extra_attrs' => [
                'onchange' => "if (($('#q1_1').val() + $('#q1_2').val() + $('#q1_3').val() + $('#q2_1').val() + $('#q2_2').val()) > 0) { $('#q2_3').attr('data-rule-required', 'true'); } else { $('#q2_3').removeData('rule-required').removeAttr('data-rule-required'); }",
            ],
        ],
        [
            'field_key'   => 'q2_2',
            'label'       => 'Any investigation or allegation of sexual misconduct?',
            'type'        => 'yesno',
            'required'    => 1,
            'sortorder'   => 160,
            'section'     => 'Screening',
            'helptext'    => 'select_yesno',
            'is_system'   => 0,
            'validation'  => ['required' => true],
            'extra_attrs' => [
                'onchange' => "if (($('#q1_1').val() + $('#q1_2').val() + $('#q1_3').val() + $('#q2_1').val() + $('#q2_2').val()) > 0) { $('#q2_3').attr('data-rule-required', 'true'); } else { $('#q2_3').removeData('rule-required').removeAttr('data-rule-required'); }",
            ],
        ],
        [
            'field_key'   => 'q2_3',
            'label'       => 'If you answered yes to any of the above, please explain:',
            'type'        => 'textarea',
            'required'    => 0,
            'sortorder'   => 170,
            'section'     => 'Screening',
            'helptext'    => 'input_explain',
            'is_system'   => 0,
            'required_when' => [
                'logic' => 'or',
                'conditions' => [
                    ['field' => 'q1_1', 'op' => 'eq', 'value' => '1'],
                    ['field' => 'q1_2', 'op' => 'eq', 'value' => '1'],
                    ['field' => 'q1_3', 'op' => 'eq', 'value' => '1'],
                    ['field' => 'q2_1', 'op' => 'eq', 'value' => '1'],
                    ['field' => 'q2_2', 'op' => 'eq', 'value' => '1'],
                ],
            ],
        ],

        // ---- References ----
        [
            'field_key'   => 'section_ref1',
            'label'       => 'References #1',
            'type'        => 'section',
            'required'    => 0,
            'sortorder'   => 200,
            'section'     => 'References',
            'is_system'   => 0,
        ],
        [
            'field_key'   => 'ref1name',
            'label'       => 'Name',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 210,
            'section'     => 'References',
            'helptext'    => 'input_staff_refname',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'ref1relationship',
            'label'       => 'Relationship',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 220,
            'section'     => 'References',
            'helptext'    => 'input_staff_refrelationship',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'ref1phone',
            'label'       => 'Phone',
            'type'        => 'phone',
            'required'    => 1,
            'sortorder'   => 230,
            'section'     => 'References',
            'helptext'    => 'input_default_phone',
            'is_system'   => 0,
            'validation'  => ['required' => true, 'phone' => true],
        ],
        [
            'field_key'   => 'section_ref2',
            'label'       => 'References #2',
            'type'        => 'section',
            'required'    => 0,
            'sortorder'   => 240,
            'section'     => 'References',
            'is_system'   => 0,
        ],
        [
            'field_key'   => 'ref2name',
            'label'       => 'Name',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 250,
            'section'     => 'References',
            'helptext'    => 'input_staff_refname',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'ref2relationship',
            'label'       => 'Relationship',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 260,
            'section'     => 'References',
            'helptext'    => 'input_staff_refrelationship',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'ref2phone',
            'label'       => 'Phone',
            'type'        => 'phone',
            'required'    => 1,
            'sortorder'   => 270,
            'section'     => 'References',
            'helptext'    => 'input_default_phone',
            'is_system'   => 0,
            'validation'  => ['required' => true, 'phone' => true],
        ],
        [
            'field_key'   => 'section_ref3',
            'label'       => 'References #3',
            'type'        => 'section',
            'required'    => 0,
            'sortorder'   => 280,
            'section'     => 'References',
            'is_system'   => 0,
        ],
        [
            'field_key'   => 'ref3name',
            'label'       => 'Name',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 290,
            'section'     => 'References',
            'helptext'    => 'input_staff_refname',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'ref3relationship',
            'label'       => 'Relationship',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 300,
            'section'     => 'References',
            'helptext'    => 'input_staff_refrelationship',
            'is_system'   => 0,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'ref3phone',
            'label'       => 'Phone',
            'type'        => 'phone',
            'required'    => 1,
            'sortorder'   => 310,
            'section'     => 'References',
            'helptext'    => 'input_default_phone',
            'is_system'   => 0,
            'validation'  => ['required' => true, 'phone' => true],
        ],

        // ---- Consents ----
        [
            'field_key'   => 'section_workerconsent',
            'label'       => 'Worker Renewal Work Verification and Release',
            'type'        => 'section',
            'required'    => 0,
            'sortorder'   => 400,
            'section'     => 'Consents',
            'is_system'   => 0,
        ],
        [
            'field_key'   => 'workerconsent',
            'label'       => 'Full Name',
            'type'        => 'text',
            'required'    => 1,
            'sortorder'   => 410,
            'section'     => 'Consents',
            'helptext'    => 'input_staff_workerconsent',
            'is_system'   => 1,
            'validation'  => ['required' => true],
        ],
        [
            'field_key'   => 'workerconsentdate',
            'label'       => 'Date',
            'type'        => 'date',
            'required'    => 1,
            'sortorder'   => 420,
            'section'     => 'Consents',
            'helptext'    => 'input_staff_workerconsentdate',
            'is_system'   => 1,
            'validation'  => ['required' => true, 'date' => true],
        ],
        [
            'field_key'   => 'workerconsentsig',
            'label'       => 'Signature',
            'type'        => 'checkbox',
            'required'    => 1,
            'sortorder'   => 430,
            'section'     => 'Consents',
            'helptext'    => 'input_staff_workerconsentsig',
            'is_system'   => 1,
            'validation'  => ['required' => true],
        ],
        // Parental consent block (shown only when agerange == 0)
        [
            'field_key'   => 'section_parental',
            'label'       => 'Parental / Guardian Consent (required if under 18)',
            'type'        => 'section',
            'required'    => 0,
            'sortorder'   => 450,
            'section'     => 'Consents',
            'is_system'   => 0,
            'extra_attrs' => ['wrapper_id' => 'sub18'],
            'visibility'  => [
                'action' => 'show',
                'logic'  => 'and',
                'conditions' => [
                    ['field' => 'agerange', 'op' => 'eq', 'value' => '0'],
                ],
            ],
        ],
        [
            'field_key'   => 'parentalconsent',
            'label'       => 'Parent or Guardian Full Name',
            'type'        => 'text',
            'required'    => 0, // made required dynamically when under 18
            'sortorder'   => 460,
            'section'     => 'Consents',
            'helptext'    => 'input_staff_parentalconsent',
                        'visibility'  => [
                'action' => 'show',
                'logic'  => 'and',
                'conditions' => [
                    ['field' => 'agerange', 'op' => 'eq', 'value' => '0'],
                ],
            ],
            'required_when' => [
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'agerange', 'op' => 'eq', 'value' => '0'],
                ],
            ],
            'is_system'   => 1,
        ],
        [
            'field_key'   => 'parentalconsentsig',
            'label'       => 'Parent or Guardian Signature',
            'type'        => 'checkbox',
            'required'    => 0,
            'sortorder'   => 470,
            'section'     => 'Consents',
            'helptext'    => 'input_staff_parentalconsentsig',
                        'visibility'  => [
                'action' => 'show',
                'logic'  => 'and',
                'conditions' => [
                    ['field' => 'agerange', 'op' => 'eq', 'value' => '0'],
                ],
            ],
            'required_when' => [
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'agerange', 'op' => 'eq', 'value' => '0'],
                ],
            ],
            'is_system'   => 1,
        ],
    ];

    return $fields;
}

/**
 * Return active form fields for a page (DB preferred, fallback to defaults).
 * @param int $pageid  0 = global
 * @param bool $include_inactive
 * @return array
 */

/**
 * Field keys that always appear at the bottom of the form and are hidden from the editor.
 * These are required consent blocks, not editable questions.
 */
function get_staff_form_fixed_consent_keys() {
    // Forced to the bottom of the live form (signature / consent blocks)
    return [
        'section_workerconsent',
        'workerconsent',
        'workerconsentdate',
        'workerconsentsig',
        'section_parental',
        'parentalconsent',
        'parentalconsentsig',
    ];
}

/** Fields that cannot be deleted (consent set + date of birth). */
function get_staff_form_protected_keys() {
    return array_merge(['dateofbirth'], get_staff_form_fixed_consent_keys());
}

function get_staff_form_fields($pageid = 0, $include_inactive = false) {
    global $CFG;

    // Ensure table exists (safe no-op if already present)
    static $table_checked = false;
    if (!$table_checked) {
        ensure_staff_form_tables();
        $table_checked = true;
    }

    $where = $include_inactive ? '' : ' AND active = 1';
    $sql = "SELECT * FROM events_staff_form_fields
            WHERE (pageid = ||pageid|| OR pageid = 0)
            $where
            ORDER BY pageid DESC, sortorder ASC, fieldid ASC";
    // Prefer page-specific over global when both exist for same field_key
    $rows = get_db_result($sql, ['pageid' => (int)$pageid]);

    $by_key = [];
    if ($rows) {
        while ($r = fetch_row($rows)) {
            // Decode JSON columns – always end up as arrays (empty string / invalid JSON => [])
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
            // Page-specific overrides global
            if (!isset($by_key[$r['field_key']]) || (int)$r['pageid'] === (int)$pageid) {
                $by_key[$r['field_key']] = $r;
            }
        }
    }

    if (empty($by_key)) {
        // Fallback to hard-coded defaults (and optionally seed)
        $defaults = get_default_staff_form_fields();
        foreach ($defaults as $i => $f) {
            $f['fieldid'] = 0;
            $f['pageid']  = 0;
            $f['active']  = 1;
            $by_key[$f['field_key']] = $f;
        }
    }

    // Re-sort by sortorder
    $list = array_values($by_key);
    usort($list, function ($a, $b) {
        return ((int)$a['sortorder'] <=> (int)$b['sortorder']) ?: ((int)($a['fieldid'] ?? 0) <=> (int)($b['fieldid'] ?? 0));
    });
    return $list;
}

/**
 * Ensure the new tables / columns exist (idempotent, compatible with MySQL 5.7+/8.x).
 */
function ensure_staff_form_tables() {
    global $CFG;

    // Create definition table (CREATE TABLE IF NOT EXISTS is widely supported)
    $sqlfile = $CFG->dirroot . '/features/events/staffform/dbsql/staffform.sql';
    if (file_exists($sqlfile)) {
        $sql = file_get_contents($sqlfile);
        if (preg_match('/CREATE TABLE IF NOT EXISTS `events_staff_form_fields`.*?ENGINE=InnoDB[^;]*;/s', $sql, $m)) {
            @execute_db_sql($m[0]);
        }
    }

    // If the table was previously created with JSON columns, convert them to LONGTEXT
    // so empty-string values are accepted.
    staff_form_ensure_longtext_column('events_staff_form_fields', 'options');
    staff_form_ensure_longtext_column('events_staff_form_fields', 'validation');
    staff_form_ensure_longtext_column('events_staff_form_fields', 'extra_attrs');
    staff_form_add_column_if_missing('events_staff_form_fields', 'visibility', 'LONGTEXT DEFAULT NULL');
    staff_form_add_column_if_missing('events_staff_form_fields', 'required_when', 'LONGTEXT DEFAULT NULL');

    // Add form_data columns only when missing
    staff_form_add_column_if_missing('events_staff', 'form_data', 'LONGTEXT DEFAULT NULL', 'bgcheckpassdate');
    staff_form_add_column_if_missing('events_staff_archive', 'form_data', 'LONGTEXT DEFAULT NULL', 'bgcheckpassdate');
}

/**
 * If a column exists and is JSON (or anything other than longtext/text), convert it to LONGTEXT.
 */
function staff_form_ensure_longtext_column($table, $column) {
    $row = get_db_row(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ||table||
           AND COLUMN_NAME = ||column||",
        ['table' => $table, 'column' => $column]
    );
    if (!$row) {
        return; // column does not exist yet (CREATE will handle it)
    }
    $type = strtolower($row['DATA_TYPE'] ?? '');
    if (in_array($type, ['longtext', 'text', 'mediumtext', 'tinytext', 'varchar', 'char'])) {
        return; // already a text type
    }
    // Convert JSON (or other) to LONGTEXT
    execute_db_sql("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` LONGTEXT DEFAULT NULL");
}

/**
 * Safely add a column if it does not already exist.
 * Uses information_schema so it works on MySQL versions that lack "ADD COLUMN IF NOT EXISTS".
 * Uses LONGTEXT (universally supported) to store JSON-encoded form data.
 */
function staff_form_add_column_if_missing($table, $column, $definition = 'LONGTEXT DEFAULT NULL', $after = null) {
    $params = ['table' => $table, 'column' => $column];
    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ||table||
              AND COLUMN_NAME = ||column||";
    $row = get_db_row($sql, $params);
    if ($row && (int)$row['cnt'] > 0) {
        return; // already exists
    }
    $after_clause = $after ? " AFTER `{$after}`" : '';
    // LONGTEXT is safe on all MySQL/MariaDB versions and stores JSON just fine
    execute_db_sql("ALTER TABLE `{$table}` ADD COLUMN `{$column}` LONGTEXT DEFAULT NULL{$after_clause}");
}

/**
 * Seed the global form definition from defaults (only inserts missing field_keys).
 */
function seed_staff_form_fields($pageid = 0, $force = false) {
    $defaults = get_default_staff_form_fields();
    $now = time();
    $inserted = 0;
    foreach ($defaults as $f) {
        $exists = get_db_row(
            "SELECT fieldid FROM events_staff_form_fields WHERE pageid = ||pageid|| AND field_key = ||key||",
            ['pageid' => $pageid, 'key' => $f['field_key']]
        );
        if ($exists && !$force) {
            // Refresh only flags that must stay in sync with code defaults.
            // Do NOT overwrite extra_attrs / visibility / required_when / helptext / label —
            // those are editable in the UI and must persist across editor reloads.
            execute_db_sql(
                "UPDATE events_staff_form_fields SET
                    is_system = ||is_system||, modified = ||modified||
                 WHERE fieldid = ||fieldid||",
                [
                    'is_system' => (int)($f['is_system'] ?? 0),
                    'modified' => $now,
                    'fieldid'  => $exists['fieldid'],
                ]
            );
            // One-time backfill: if visibility/required_when/extra_attrs empty in DB, copy from defaults
            $row = get_db_row("SELECT visibility, required_when, extra_attrs FROM events_staff_form_fields WHERE fieldid=||id||", ['id' => $exists['fieldid']]);
            $sets = [];
            $params = ['fieldid' => $exists['fieldid']];
            if ($row && ($row['visibility'] === null || $row['visibility'] === '') && !empty($f['visibility'])) {
                $sets[] = 'visibility = ||visibility||';
                $params['visibility'] = json_encode($f['visibility']);
            }
            if ($row && ($row['required_when'] === null || $row['required_when'] === '') && !empty($f['required_when'])) {
                $sets[] = 'required_when = ||required_when||';
                $params['required_when'] = json_encode($f['required_when']);
            }
            if ($row && ($row['extra_attrs'] === null || $row['extra_attrs'] === '') && !empty($f['extra_attrs'])) {
                $sets[] = 'extra_attrs = ||extra_attrs||';
                $params['extra_attrs'] = json_encode($f['extra_attrs']);
            }
            if ($sets) {
                execute_db_sql(
                    "UPDATE events_staff_form_fields SET " . implode(', ', $sets) . " WHERE fieldid = ||fieldid||",
                    $params
                );
            }
            continue;
        }
        $params = [
            'pageid'     => $pageid,
            'field_key'  => $f['field_key'],
            'label'      => $f['label'],
            'type'       => $f['type'],
            // Use empty string instead of null – this CMS DB layer rejects null bound values
            'options'    => !empty($f['options']) ? json_encode($f['options']) : '',
            'required'   => (int)($f['required'] ?? 0),
            'sortorder'  => (int)$f['sortorder'],
            'section'    => $f['section'] ?? '',
            'helptext'   => $f['helptext'] ?? '',
            'validation' => !empty($f['validation']) ? json_encode($f['validation']) : '',
            'is_system'  => (int)($f['is_system'] ?? 0),
            'active'     => 1,
            'extra_attrs'=> !empty($f['extra_attrs']) ? json_encode($f['extra_attrs']) : '',
            'visibility' => !empty($f['visibility']) ? json_encode($f['visibility']) : '',
            'required_when' => !empty($f['required_when']) ? json_encode($f['required_when']) : '',
            'created'    => $now,
            'modified'   => $now,
        ];
        if ($exists && $force) {
            execute_db_sql(
                "UPDATE events_staff_form_fields SET
                    label=||label||, type=||type||, options=||options||, required=||required||,
                    sortorder=||sortorder||, section=||section||, helptext=||helptext||,
                    validation=||validation||, is_system=||is_system||, active=||active||,
                    extra_attrs=||extra_attrs||, visibility=||visibility||, required_when=||required_when||,
                    modified=||modified||
                 WHERE fieldid = ||fieldid||",
                array_merge($params, ['fieldid' => $exists['fieldid']])
            );
        } else {
            execute_db_sql(
                "INSERT INTO events_staff_form_fields
                    (pageid, field_key, label, type, options, required, sortorder, section, helptext, validation, is_system, active, extra_attrs, visibility, required_when, created, modified)
                 VALUES
                    (||pageid||, ||field_key||, ||label||, ||type||, ||options||, ||required||, ||sortorder||, ||section||, ||helptext||, ||validation||, ||is_system||, ||active||, ||extra_attrs||, ||visibility||, ||required_when||, ||created||, ||modified||)",
                $params
            );
            $inserted++;
        }
    }
    return $inserted;
}

/**
 * Merge a DB row (columns) with form_data JSON into a single values array keyed by field_key.
 */
function get_staff_application_values($row) {
    if (empty($row)) {
        return [];
    }
    // form_data is the source of truth for dynamic answers; columns supply static fields + legacy fallback
    $vals = [];
    if (!empty($row['form_data'])) {
        $json = is_string($row['form_data']) ? json_decode($row['form_data'], true) : $row['form_data'];
        if (is_array($json)) {
            $vals = $json;
        }
    }
    // Overlay static / identity columns (these win over form_data for consent + bgcheck + identity)
    $static_keys = [
        'staffid', 'userid', 'pageid', 'name', 'phone', 'dateofbirth',
        'workerconsent', 'workerconsentdate', 'workerconsentsig',
        'parentalconsent', 'parentalconsentsig',
        'bgcheckpass', 'bgcheckpassdate',
    ];
    foreach ($static_keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
            $vals[$k] = $row[$k];
        }
    }
    // Legacy column fallback for older rows that have not been fully migrated into form_data
    foreach ($row as $k => $v) {
        if ($k === 'form_data') continue;
        if (!array_key_exists($k, $vals) && $v !== null && $v !== '') {
            $vals[$k] = $v;
        }
    }
    // Normalize dates for display
    if (!empty($vals['dateofbirth']) && is_numeric($vals['dateofbirth'])) {
        $vals['dateofbirth'] = date('m/d/Y', $vals['dateofbirth']);
    }
    if (!empty($vals['workerconsentdate']) && is_numeric($vals['workerconsentdate'])) {
        $vals['workerconsentdate'] = date('m/d/Y', $vals['workerconsentdate']);
    }
    return $vals;
}

/**
 * Plain-text display for a field value (view-only / print).
 */
function staff_form_display_value($field, $value) {
    $type = $field['type'] ?? 'text';
    $val = $value ?? '';
    if ($type === 'file_viewer') {
        $opts = $field['options'] ?? [];
        if (is_string($opts) && $opts !== '') {
            $opts = json_decode($opts, true) ?: [];
        }
        if (!is_array($opts)) {
            $opts = [];
        }
        $url = (string)($opts['url'] ?? '');
        $reviewed = (!empty($val) && $val !== '0') ? 'Reviewed' : 'Not confirmed';
        $link = $url !== '' ? '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars($url) . '</a>' : '';
        return ($link ? $link . '<br />' : '') . htmlspecialchars($reviewed);
    }
    if ($type === 'yesno') {
        if ((string)$val === '1') {
            return 'Yes';
        }
        if ((string)$val === '0') {
            return 'No';
        }
        return htmlspecialchars((string)$val);
    }
    if ($type === 'checkbox') {
        return (!empty($val) && $val !== '0') ? 'Yes' : 'No';
    }
    if ($type === 'select') {
        $options = $field['options'] ?? [];
        if (is_string($options) && $options !== '') {
            $options = json_decode($options, true) ?: [];
        }
        if (!is_array($options)) {
            $options = [];
        }
        foreach ($options as $opt) {
            $ov = is_array($opt) ? (string)($opt['value'] ?? '') : (string)$opt;
            $ol = is_array($opt) ? (string)($opt['label'] ?? $ov) : (string)$opt;
            if ((string)$val === $ov) {
                return htmlspecialchars($ol);
            }
        }
        return htmlspecialchars((string)$val);
    }
    if ($type === 'date' && is_numeric($val) && (int)$val > 0) {
        return htmlspecialchars(date('m/d/Y', (int)$val));
    }
    if ($type === 'textarea') {
        return nl2br(htmlspecialchars((string)$val));
    }
    return htmlspecialchars((string)$val);
}

/**
 * Render a single field as HTML matching the existing visual style.
 */
function render_staff_form_field($field, $value, $viewonly = false) {
    $key   = $field['field_key'];
    $type  = $field['type'];
    $label = $field['label'];
    $req   = !empty($field['required']);
    $help  = $field['helptext'] ?? '';
    $attrs = $field['extra_attrs'] ?? [];
    if (!is_array($attrs)) {
        $attrs = (is_string($attrs) && $attrs !== '') ? (json_decode($attrs, true) ?: []) : [];
    }
    $val   = $value ?? '';
    $locked = !empty($attrs['locked']) || !empty($attrs['disabled']) || !empty($attrs['readonly']);
    $disabled = $viewonly || $locked;

    // Section type = subsection heading on the current form page
    if ($type === 'section') {
        $vis_attr = '';
        if (!empty($field['visibility']) && !$viewonly) {
            $vis_attr = ' style="display:none"';
        }
        if (!empty($attrs['wrapper_id'])) {
            return '<div id="' . htmlspecialchars($attrs['wrapper_id']) . '" data-staff-section="' . htmlspecialchars($key) . '"' . $vis_attr . '>
                        <h4 class="staff-subsection-heading">' . htmlspecialchars($label) . '</h4>';
        }
        return '<div class="staff-subsection" data-staff-section="' . htmlspecialchars($key) . '"' . $vis_attr . '>'
             . '<h4 class="staff-subsection-heading">' . htmlspecialchars($label) . '</h4>'
             . '</div>';
    }

    // View-only / print: show answers as text, not form controls
    if ($viewonly) {
        $display = staff_form_display_value($field, $val);
        $html = '<div class="rowContainer staff-viewonly-row" data-staff-field-key="' . htmlspecialchars($key) . '">';
        $html .= '<label class="rowTitle">' . htmlspecialchars($label) . '</label>';
        $html .= '<div class="staff-viewonly-value">' . $display . '</div>';
        $html .= '</div>';
        return $html;
    }

    // Close parental wrapper after its last field (handled by caller or by a special end marker if needed)
    $html = '<div class="rowContainer" data-staff-field-key="' . htmlspecialchars($key) . '">';
    $html .= '<label class="rowTitle" for="' . htmlspecialchars($key) . '">' . htmlspecialchars($label) . '</label>';

    $data_rules = '';
    $validation = $field['validation'] ?? [];
    if (!is_array($validation)) {
        $validation = (is_string($validation) && $validation !== '') ? (json_decode($validation, true) ?: []) : [];
    }
    if ($req || !empty($validation['required'])) {
        $data_rules .= ' data-rule-required="true"';
        $data_rules .= ' data-msg-required="' . htmlspecialchars(getlang('input_required')) . '"';
    }
    if (!empty($validation['phone'])) {
        $data_rules .= ' data-rule-phone="true" data-msg-phone="' . htmlspecialchars(getlang('invalid_phone')) . '"';
    }
    if (!empty($validation['date'])) {
        $data_rules .= ' data-rule-date="true"';
    }
    if (!empty($validation['custom'])) {
        $data_rules .= ' data-rule-custom="' . htmlspecialchars($validation['custom']) . '"';
        $data_rules .= ' data-msg-custom="' . htmlspecialchars(getlang('input_default_date_slashes')) . '"';
    }

    $extra = '';
    foreach ($attrs as $ak => $av) {
        if (in_array($ak, ['disabled', 'readonly', 'wrapper_id'])) continue;
        $extra .= ' ' . $ak . '="' . htmlspecialchars($av) . '"';
    }
    if ($disabled) {
        // Locked (not full view-only): prefer readonly so text values still POST
        if ($locked && !$viewonly && in_array($type, ['text', 'phone', 'date', 'textarea'], true)) {
            $extra .= ' readonly="readonly"';
        } else {
            $extra .= ' disabled="disabled"';
        }
    }

    switch ($type) {
        case 'text':
        case 'phone':
        case 'date':
            $html .= '<input type="text" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '"
                        value="' . htmlspecialchars($val) . '"' . $data_rules . $extra . ' />';
            break;

        case 'textarea':
            $html .= '<textarea rows="3" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '"'
                   . $data_rules . $extra . '>' . htmlspecialchars($val) . '</textarea>';
            break;

        case 'yesno':
            $sel0 = ((string)$val === '0') ? ' selected' : '';
            $sel1 = ((string)$val === '1') ? ' selected' : '';
            $name_attr = ($locked && !$viewonly) ? '' : ' name="' . htmlspecialchars($key) . '"';
            $html .= '<select style="width:80px" id="' . htmlspecialchars($key) . '"' . $name_attr
                   . $data_rules . $extra . '>
                        <option value="0"' . $sel0 . '>No</option>
                        <option value="1"' . $sel1 . '>Yes</option>
                      </select>';
            if ($locked && !$viewonly) {
                $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars((string)$val) . '" />';
            }
            break;

        case 'select':
            $options = $field['options'] ?? [];
            if (!is_array($options)) {
                $options = (is_string($options) && $options !== '') ? (json_decode($options, true) ?: []) : [];
            }
            $name_attr = ($locked && !$viewonly) ? '' : ' name="' . htmlspecialchars($key) . '"';
            $html .= '<select id="' . htmlspecialchars($key) . '"' . $name_attr
                   . $data_rules . $extra . '><option>Please select</option>';
            foreach ($options as $opt) {
                $ov = is_array($opt) ? ($opt['value'] ?? '') : $opt;
                $ol = is_array($opt) ? ($opt['label'] ?? $ov) : $opt;
                $sel = ((string)$val === (string)$ov) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($ov) . '"' . $sel . '>' . htmlspecialchars($ol) . '</option>';
            }
            $html .= '</select>';
            if ($locked && !$viewonly) {
                $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars((string)$val) . '" />';
            }
            break;

        case 'checkbox':
            $checked = (!empty($val) && $val !== '0') ? ' checked="checked"' : '';
            $name_attr = ($locked && !$viewonly) ? '' : ' name="' . htmlspecialchars($key) . '"';
            $html .= '<input type="checkbox" id="' . htmlspecialchars($key) . '"' . $name_attr . ' value="1"'
                   . $checked . $data_rules . $extra . ' />';
            if ($locked && !$viewonly) {
                $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars((!empty($val) && $val !== '0') ? '1' : '0') . '" />';
            }
            break;

        case 'hidden':
            $html = '<input type="hidden" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '" />';
            return $html;

        case 'file_viewer':
            $opts = $field['options'] ?? [];
            if (is_string($opts) && $opts !== '') {
                $opts = json_decode($opts, true) ?: [];
            }
            if (!is_array($opts)) {
                $opts = [];
            }
            $url = (string)($opts['url'] ?? $opts['viewer_url'] ?? '');
            $height = (int)($opts['height'] ?? $opts['viewer_height'] ?? 480);
            if ($height < 200) {
                $height = 200;
            }
            if ($height > 1200) {
                $height = 1200;
            }
            $need_confirm = !isset($opts['confirm']) || !empty($opts['confirm']);
            if ($url !== '') {
                $safe_url = htmlspecialchars($url);
                $html .= '<div class="staff-file-viewer" style="margin:8px 0;border:1px solid #cbd5e1;border-radius:6px;overflow:hidden;background:#f8fafc">';
                $html .= '<div style="padding:6px 10px;font-size:.85em;background:#e2e8f0;color:#334155"><a href="' . $safe_url . '" target="_blank" rel="noopener">Open in new tab</a></div>';
                $html .= '<iframe src="' . $safe_url . '" title="' . htmlspecialchars($label) . '" style="width:100%;height:' . $height . 'px;border:0;display:block" loading="lazy"></iframe>';
                $html .= '</div>';
            } else {
                $html .= '<div class="tooltipContainer info">No document URL configured.</div>';
            }
            if ($need_confirm && !$viewonly) {
                $checked = (!empty($val) && $val !== '0') ? ' checked="checked"' : '';
                $html .= '<label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-weight:normal">';
                $html .= '<input type="checkbox" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '" value="1"'
                       . $checked . $data_rules . $extra . ' />';
                $html .= '<span>I have reviewed this document</span></label>';
            } elseif ($viewonly) {
                $html .= '<div>' . ((!empty($val) && $val !== '0') ? 'Reviewed' : 'Not confirmed') . '</div>';
            }
            break;

        default:
            $html .= '<input type="text" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '"
                        value="' . htmlspecialchars($val) . '"' . $data_rules . $extra . ' />';
    }

    if ($help !== '' && $help !== null) {
        // Resolve lang keys: try feature path first, then global
        if (preg_match('/^(input_|select_|help_)/', $help)) {
            $helptext = getlang($help, '/features/events');
            if ($helptext === '' || $helptext === $help) {
                $helptext = getlang($help); // global fallback
            }
            // If still unresolved, show nothing rather than the raw key
            if ($helptext === '' || $helptext === $help) {
                $helptext = '';
            }
        } else {
            $helptext = $help; // plain text help
        }
        if ($helptext !== '') {
            $html .= '<div class="tooltipContainer info">' . $helptext . '</div>';
        }
    }
    $html .= '<div class="spacer" style="clear: both;"></div></div>';
    return $html;
}

/**
 * Dynamic replacement for the old staff_application_form().
 * Renders from the field definition while preserving all special JS behaviour.
 */
function staff_application_form_dynamic($row, $viewonly = false) {
    global $USER, $CFG;

    $pageid = get_pageid() ?: 0;
    // Ensure default visibility/required_when exist for seeded fields that predate those columns
    seed_staff_form_fields(0);
    $fields = get_staff_form_fields($pageid);
    // Always keep consent blocks at the bottom, regardless of sortorder edits
    $consent_keys = get_staff_form_fixed_consent_keys();
    $main = [];
    $consent = [];
    foreach ($fields as $f) {
        if (in_array($f['field_key'], $consent_keys, true)) {
            $consent[] = $f;
        } else {
            $main[] = $f;
        }
    }
    $fields = array_merge($main, $consent);
    $vals   = get_staff_application_values($row);

    // Pre-fill name for new applications
    if (empty($row) && isset($USER->fname)) {
        $vals['name'] = trim($USER->fname . ' ' . $USER->lname);
    }

    // When the applicant is filling/renewing the form (not admin view-only review),
    // always require fresh consent + signature each time — never pre-fill them.
    if (!$viewonly) {
        foreach ([
            'workerconsent',
            'workerconsentdate',
            'workerconsentsig',
            'parentalconsent',
            'parentalconsentsig',
        ] as $consent_key) {
            $vals[$consent_key] = '';
        }
    }

    // Age-range selection helpers for select options (already handled inside render)
    // Determine whether parental block should be visible
    $agerange = $vals['agerange'] ?? '';
    $show_parental = ((string)$agerange === '0');

    // Group fields into pages by section name (order of first appearance)
    $pages = []; // [ ['title' => ..., 'fields' => [...] ], ... ]
    $page_index = [];
    foreach ($fields as $field) {
        $sec = trim((string)($field['section'] ?? ''));
        if ($sec === '') {
            $sec = 'General';
        }
        if (!isset($page_index[$sec])) {
            $page_index[$sec] = count($pages);
            $pages[] = ['title' => $sec, 'fields' => []];
        }
        $pages[$page_index[$sec]]['fields'][] = $field;
    }
    $page_count = count($pages);

    $html = '
        <div id="staffapplication_form_div" class="' . ($viewonly ? 'staff-form-viewonly' : '') . '">
            <style>
                .staff-form-pager { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; margin: 0 0 14px; }
                .staff-form-pager .sfp-step {
                    border:1px solid #cbd5e1; background:#f8fafc; color:#475569;
                    border-radius:999px; padding:4px 10px; font-size:.8em; cursor:pointer;
                }
                .staff-form-pager .sfp-step.active { background:#2563eb; border-color:#2563eb; color:#fff; }
                .staff-form-pager .sfp-step.done { background:#dbeafe; border-color:#93c5fd; color:#1e40af; }
                .staff-form-page { display:none; }
                .staff-form-page.active { display:block; }
                .staff-form-viewonly .staff-form-page { display:block !important; }
                .staff-viewonly-row { margin-bottom: 8px; page-break-inside: avoid; }
                .staff-viewonly-value { font-weight: 500; color: #0f172a; padding: 2px 0 8px; border-bottom: 1px dotted #e2e8f0; }
                @media print {
                    .staff-form-viewonly .staff-form-page { display:block !important; }
                    .staff-form-pager, .staff-form-nav, .staff-form-progress { display:none !important; }
                }
                .staff-form-nav {
                    display:flex; justify-content:space-between; gap:10px; margin-top:16px; flex-wrap:wrap;
                }
                .staff-form-nav button {
                    background:#2563eb; color:#fff; border:none; border-radius:6px; padding:8px 16px; cursor:pointer;
                }
                .staff-form-nav button.secondary { background:#64748b; }
                .staff-form-nav button:disabled { opacity:.5; cursor:default; }
                .staff-form-progress { text-align:center; font-size:.85em; color:#64748b; margin-bottom:8px; }
                .staff-subsection-heading { margin: 1rem 0 .5rem; font-size: 1.05em; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
                .staff-subsection { margin-top: .5rem; }
            </style>
            <div style="text-align:center;">
                <h2>' . (!$viewonly ? 'Staff Application' : htmlspecialchars($vals['name'] ?? '') . ' Application') . '</h2>
                <span style="font-weight:bold;font-size:.9em">
                    If you are not ' . htmlspecialchars($vals['name'] ?? '') . ', please sign into your own account.
                </span>
            </div>
            <br />
            <form name="staffapplication_form" id="staffapplication_form">
                ' . (empty($vals['staffid']) ? '' : '<input type="hidden" id="staffid" name="staffid" value="' . (int)$vals['staffid'] . '" />') . '
                <fieldset class="formContainer" ' . ($viewonly ? '' : 'style="max-width: 520px;margin-left: auto;margin-right: auto;"') . '>';

    if ($page_count > 1 && !$viewonly) {
        $html .= '<div class="staff-form-progress"><span id="sfp-progress-label">Step 1 of ' . $page_count . '</span></div>';
        $html .= '<div class="staff-form-pager" id="staff-form-pager">';
        foreach ($pages as $i => $page) {
            $html .= '<button type="button" class="sfp-step' . ($i === 0 ? ' active' : '') . '" data-page="' . $i . '">'
                   . htmlspecialchars($page['title']) . '</button>';
        }
        $html .= '</div>';
    }

    foreach ($pages as $i => $page) {
        $active = ($viewonly || $i === 0) ? ' active' : '';
        $html .= '<div class="staff-form-page' . $active . '" data-page="' . $i . '" data-section="' . htmlspecialchars($page['title']) . '">';
        $html .= '<h3 style="margin-top:0">' . htmlspecialchars($page['title']) . '</h3>';

        $inside_parental = false;
        foreach ($page['fields'] as $field) {
            $key = $field['field_key'];
            $val = $vals[$key] ?? '';

            // Section-type fields render as subsection headings via render_staff_form_field()
            if ($key === 'section_parental') {
                $par_style = $viewonly ? '' : ' style="display:none"';
                $html .= '<div id="sub18" data-staff-section="section_parental"' . $par_style . '>';
                $html .= '<h4 class="staff-subsection-heading">' . htmlspecialchars($field['label']) . '</h4>';
                $inside_parental = true;
                continue;
            }

            if ($inside_parental && $key !== 'parentalconsent' && $key !== 'parentalconsentsig') {
                $html .= '</div>';
                $inside_parental = false;
            }

            if ($viewonly && strpos($key, 'section_ref') === 0 && $key === 'section_ref1') {
                $html .= '<div style="text-align:center"><h2>' . htmlspecialchars($vals['name'] ?? '') . ' References</h2></div>';
            }

            $html .= render_staff_form_field($field, $val, $viewonly);
        }
        if ($inside_parental) {
            $html .= '</div>';
        }
        $html .= '</div>'; // page
    }

    if ($page_count > 1 && !$viewonly) {
        $html .= '
            <div class="staff-form-nav">
                <button type="button" class="secondary" id="sfp-prev" disabled>Previous</button>
                <button type="button" id="sfp-next">Next</button>
                <input class="submit" id="sfp-submit" name="submit" type="submit" value="Submit Application" style="display:none" />
            </div>';
    } else {
        $html .= ($viewonly ? '' : '<input class="submit" name="submit" type="submit" onmouseover="this.focus();" value="Submit Application" />');
    }

    $html .= '
                </fieldset>
            </form>
            ' . (!$viewonly ? staff_form_rules_js($fields) : '') . '
            ' . (!$viewonly && $page_count > 1 ? staff_form_pagination_js($page_count) : '') . '
            ' . keepalive() . '
        </div>';

    return $html;
}

/**
 * Multi-step section pagination for the staff application form.
 */
function staff_form_pagination_js($page_count) {
    $page_count = (int)$page_count;
    return '
<script type="text/javascript">
(function(){
    var total = ' . $page_count . ';
    var cur = 0;
    function pages(){ return jQuery("#staffapplication_form .staff-form-page"); }
    function showPage(i){
        if (typeof jQuery === "undefined") return;
        cur = Math.max(0, Math.min(total - 1, i));
        pages().removeClass("active").eq(cur).addClass("active");
        jQuery("#staff-form-pager .sfp-step").removeClass("active").each(function(){
            var p = parseInt(jQuery(this).data("page"), 10);
            jQuery(this).toggleClass("active", p === cur);
            jQuery(this).toggleClass("done", p < cur);
        });
        jQuery("#sfp-progress-label").text("Step " + (cur + 1) + " of " + total);
        jQuery("#sfp-prev").prop("disabled", cur === 0);
        if (cur >= total - 1) {
            jQuery("#sfp-next").hide();
            jQuery("#sfp-submit").show();
        } else {
            jQuery("#sfp-next").show();
            jQuery("#sfp-submit").hide();
        }
        if (window.staffFormApplyRules) window.staffFormApplyRules();
        try {
            var top = jQuery("#staffapplication_form_div").offset();
            if (top) jQuery("html, body").animate({scrollTop: top.top - 20}, 200);
        } catch(e) {}
    }
    function validateCurrentPage(){
        if (typeof jQuery === "undefined") return true;
        var $page = pages().eq(cur);
        var ok = true;
        $page.find("[data-rule-required=true], [data-rule-required=\'true\']").each(function(){
            var $el = jQuery(this);
            if (!$el.is(":visible")) return;
            var val = $el.is(":checkbox") ? ($el.is(":checked") ? "1" : "") : String($el.val() || "");
            if (val === "" || val === "Please select") {
                ok = false;
                $el.addClass("error");
            } else {
                $el.removeClass("error");
            }
        });
        if (!ok) {
            alert("Please complete the required fields on this step before continuing.");
        }
        return ok;
    }
    function bind(){
        if (typeof jQuery === "undefined") { setTimeout(bind, 50); return; }
        jQuery("#sfp-next").on("click", function(){
            if (!validateCurrentPage()) return;
            showPage(cur + 1);
        });
        jQuery("#sfp-prev").on("click", function(){ showPage(cur - 1); });
        jQuery("#staff-form-pager").on("click", ".sfp-step", function(){
            var p = parseInt(jQuery(this).data("page"), 10);
            if (p < cur) { showPage(p); return; }
            // only allow forward if current page validates
            if (p > cur) {
                if (!validateCurrentPage()) return;
                // validate intermediate pages loosely by walking forward
                while (cur < p) {
                    if (!validateCurrentPage()) return;
                    cur++;
                    pages().removeClass("active").eq(cur).addClass("active");
                }
                showPage(p);
            }
        });
        showPage(0);
    }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", bind);
    else bind();
})();
</script>';
}


function staff_form_rules_js($fields) {
    $rules = [];
    foreach ($fields as $f) {
        $key = $f['field_key'];
        $vis = $f['visibility'] ?? null;
        $req = $f['required_when'] ?? null;
        if (is_string($vis) && $vis !== '') {
            $vis = json_decode($vis, true);
        }
        if (is_string($req) && $req !== '') {
            $req = json_decode($req, true);
        }
        $entry = ['key' => $key, 'type' => $f['type'] ?? 'text'];
        if (is_array($vis) && !empty($vis['conditions'])) {
            $entry['visibility'] = $vis;
        }
        if (is_array($req) && !empty($req['conditions'])) {
            $entry['required_when'] = $req;
        }
        if (isset($entry['visibility']) || isset($entry['required_when'])) {
            $rules[] = $entry;
        }
    }
    if (empty($rules)) {
        return '<!-- staff form: no visibility/required rules -->';
    }
    $json = json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    // Escape for safe embedding inside a script tag
    $json = str_replace(['</', '<!--'], ['<\/', '<\!--'], $json);

    return '
<script type="text/javascript">
(function(){
    var RULES = ' . $json . ';
    function valOf(name) {
        // Prefer element by id (works for locked/disabled selects that lost their name)
        var $byId = jQuery("#" + name); // field keys are safe identifiers
        if ($byId.length && !$byId.is("input[type=hidden]")) {
            if ($byId.is(":checkbox")) return $byId.is(":checked") ? "1" : "0";
            var vid = $byId.val();
            if (vid != null && String(vid) !== "") return String(vid);
            // disabled select with value 0 is valid
            if ($byId.is("select") && vid != null) return String(vid);
        }
        // All matching names (hidden + visible); prefer non-hidden
        var $named = jQuery("#staffapplication_form [name=\'" + name + "\']");
        if (!$named.length) $named = jQuery("[name=\'" + name + "\']");
        var $visible = $named.filter(":not(input[type=hidden])");
        var $el = $visible.length ? $visible.first() : $named.first();
        if (!$el.length) return "";
        if ($el.is(":checkbox")) return $el.is(":checked") ? "1" : "0";
        var v = $el.val();
        return String(v == null ? "" : v);
    }
    function matchCond(c) {
        if (!c || !c.field) return false;
        var v = valOf(c.field);
        var t = String(c.value == null ? "" : c.value);
        switch (c.op) {
            case "neq": return v !== t;
            case "in": return (Array.isArray(c.value) ? c.value : [c.value]).map(String).indexOf(v) >= 0;
            case "gt": return parseFloat(v) > parseFloat(t);
            case "lt": return parseFloat(v) < parseFloat(t);
            case "eq":
            default: return v === t;
        }
    }
    function evalLogic(rule) {
        if (!rule) return true;
        var conds = rule.conditions || [];
        if (!conds.length) return true;
        var logic = String(rule.logic || "and").toLowerCase();
        if (logic === "or") {
            for (var i = 0; i < conds.length; i++) if (matchCond(conds[i])) return true;
            return false;
        }
        for (var j = 0; j < conds.length; j++) if (!matchCond(conds[j])) return false;
        return true;
    }
    function targetFor(r) {
        var $t = jQuery();
        // Section blocks
        $t = $t.add(jQuery("[data-staff-section=\'" + r.key + "\']"));
        // Field rows
        $t = $t.add(jQuery("[data-staff-field-key=\'" + r.key + "\']"));
        // Fallback: closest row to named input
        var $el = jQuery("#staffapplication_form [name=\'" + r.key + "\']");
        if ($el.length) $t = $t.add($el.closest(".rowContainer"));
        // Parental section key also toggles #sub18
        if (r.key === "section_parental") $t = $t.add(jQuery("#sub18"));
        return $t;
    }
    function applyRules() {
        if (typeof jQuery === "undefined") return;
        RULES.forEach(function(r) {
            var $row = targetFor(r);
            if (r.visibility) {
                var show = evalLogic(r.visibility);
                if ((r.visibility.action || "show") === "hide") show = !show;
                if (show) {
                    $row.show();
                } else {
                    $row.hide();
                    var $el = jQuery("#staffapplication_form [name=\'" + r.key + "\']");
                    if ($el.is(":checkbox")) $el.prop("checked", false);
                    else if ($el.is("select")) {
                        // keep value for agerange etc — only clear non-driver fields
                        if (r.key !== "agerange") $el.val($el.find("option:first").val());
                    } else if ($el.length && r.key !== "agerange" && r.key !== "dateofbirth") {
                        $el.val("");
                    }
                }
            }
            if (r.required_when) {
                var need = evalLogic(r.required_when);
                var $el2 = jQuery("#staffapplication_form [name=\'" + r.key + "\']");
                if (need) {
                    $el2.attr("data-rule-required", "true");
                } else {
                    $el2.removeAttr("data-rule-required").removeData("rule-required");
                }
            }
        });
    }
    window.staffFormApplyRules = applyRules;
    function bind() {
        if (typeof jQuery === "undefined") { setTimeout(bind, 50); return; }
        jQuery(document).off("change.staffRules keyup.staffRules", "#staffapplication_form input, #staffapplication_form select, #staffapplication_form textarea")
            .on("change.staffRules keyup.staffRules", "#staffapplication_form input, #staffapplication_form select, #staffapplication_form textarea", applyRules);
        applyRules();
        // Re-run after DOB onblur may have set agerange
        setTimeout(applyRules, 100);
        setTimeout(applyRules, 500);
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bind);
    } else {
        bind();
    }
})();
</script>';
}

function collect_staff_application_post($pageid = 0) {
    $fields = get_staff_form_fields($pageid);
    $columns = [];
    $form_data = [];

    // Always capture staffid if present
    $staffid = clean_myvar_opt('staffid', 'int', false);
    if ($staffid) {
        $columns['staffid'] = $staffid;
    }

    foreach ($fields as $f) {
        if ($f['type'] === 'section') {
            continue;
        }
        $key = $f['field_key'];
        $raw = null;

        switch ($f['type']) {
            case 'checkbox':
                $raw = clean_myvar_opt($key, 'string', '') ? '1' : '0';
                break;
            case 'yesno':
            case 'select':
                $raw = clean_myvar_opt($key, 'string', '0');
                break;
            case 'date':
                $str = clean_myvar_opt($key, 'string', '');
                $raw = $str ? strtotime($str) : 0;
                break;
            case 'phone':
                $raw = format_phone(clean_myvar_opt($key, 'string', ''));
                break;
            case 'textarea':
            case 'text':
            default:
                $raw = clean_myvar_opt($key, 'string', '');
                if ($key === 'name' || strpos($key, 'ref') === 0 && strpos($key, 'name') !== false) {
                    $raw = nameize($raw);
                }
                break;
        }

        // System fields also go into the classic columns
        if (!empty($f['is_system'])) {
            $columns[$key] = $raw;
        }
        // Everything goes into form_data for the future-proof store
        $form_data[$key] = $raw;
    }

    // Preserve bgcheck metadata (never part of the public form)
    // Caller is responsible for copying existing bgcheck values on update.

    return [$columns, $form_data];
}

/**
 * One-time / admin-triggered migration:
 * 1. Ensure tables/columns exist
 * 2. Seed default fields
 * 3. For every existing staff row, populate form_data from the classic columns
 */
function migrate_staff_form_data($pageid = null) {
    ensure_staff_form_tables();
    $seeded = seed_staff_form_fields(0); // global defaults

    $where = $pageid !== null ? ' WHERE pageid = ' . (int)$pageid : '';
    $rows = get_db_result("SELECT * FROM events_staff $where");
    $updated = 0;
    $system_keys = [];
    foreach (get_default_staff_form_fields() as $f) {
        if (!empty($f['is_system'])) {
            $system_keys[] = $f['field_key'];
        }
    }

    if ($rows) {
        while ($row = fetch_row($rows)) {
            $data = [];
            foreach ($system_keys as $k) {
                if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                    $data[$k] = $row[$k];
                }
            }
            // Keep any already-present form_data
            if (!empty($row['form_data'])) {
                $existing = is_string($row['form_data']) ? json_decode($row['form_data'], true) : $row['form_data'];
                if (is_array($existing)) {
                    $data = array_merge($data, $existing);
                }
            }
            execute_db_sql(
                "UPDATE events_staff SET form_data = ||fd|| WHERE staffid = ||id||",
                ['fd' => json_encode($data), 'id' => $row['staffid']]
            );
            $updated++;
        }
    }

    // Same for archive
    $arows = get_db_result("SELECT * FROM events_staff_archive $where");
    $aupdated = 0;
    if ($arows) {
        while ($row = fetch_row($arows)) {
            $data = [];
            foreach ($system_keys as $k) {
                if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                    $data[$k] = $row[$k];
                }
            }
            if (!empty($row['form_data'])) {
                $existing = is_string($row['form_data']) ? json_decode($row['form_data'], true) : $row['form_data'];
                if (is_array($existing)) {
                    $data = array_merge($data, $existing);
                }
            }
            execute_db_sql(
                "UPDATE events_staff_archive SET form_data = ||fd|| WHERE archiveid = ||id||",
                ['fd' => json_encode($data), 'id' => $row['archiveid']]
            );
            $aupdated++;
        }
    }

    return [
        'seeded_fields' => $seeded,
        'staff_updated' => $updated,
        'archive_updated' => $aupdated,
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
        'bgcheckpass', 'bgcheckpassdate',
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
    ];
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
                   AND TABLE_NAME = ||table||
                   AND COLUMN_NAME = ||column||",
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


function staff_form_editor_ui($pageid) {
    if (!defined('STAFFFORMLIB')) {
        global $CFG;
        include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
    }
    seed_staff_form_fields(0);

    $fields = get_staff_form_fields($pageid, true);
    $consent_keys = get_staff_form_fixed_consent_keys();
    $protected_keys = get_staff_form_protected_keys();

    $key_opts = '';
    $section_names = [];
    foreach ($fields as $f) {
        if (($f['type'] ?? '') === 'section') {
            continue;
        }
        $key_opts .= '<option value="' . htmlspecialchars($f['field_key']) . '">' . htmlspecialchars($f['field_key']) . '</option>';
    }
    foreach ($fields as $f) {
        $sec = trim((string)($f['section'] ?? ''));
        if ($sec !== '') {
            $section_names[$sec] = true;
        }
    }
    $section_opts = '';
    foreach (array_keys($section_names) as $sec) {
        $section_opts .= '<option value="' . htmlspecialchars($sec) . '">';
    }

    $edit_map = [];
    $rows = '';
    foreach ($fields as $f) {
        $fid = (int)($f['fieldid'] ?? 0);
        $is_consent = in_array($f['field_key'], $consent_keys, true);
        $is_protected = in_array($f['field_key'], $protected_keys, true);
        $active = !empty($f['active']) ? '<span style="color:#0a0">Active</span>' : '<span style="color:#999">Inactive</span>';

        $visibility = $f['visibility'] ?? [];
        if (is_string($visibility) && $visibility !== '') {
            $visibility = json_decode($visibility, true) ?: [];
        }
        if (!is_array($visibility)) {
            $visibility = [];
        }
        $vis = !empty($visibility['conditions']) ? 'Yes (' . count($visibility['conditions']) . ')' : '—';

        $required_when = $f['required_when'] ?? [];
        if (is_string($required_when) && $required_when !== '') {
            $required_when = json_decode($required_when, true) ?: [];
        }
        if (!is_array($required_when)) {
            $required_when = [];
        }
        $reqw = !empty($required_when['conditions']) ? 'Yes (' . count($required_when['conditions']) . ')' : '—';

        $attrs = $f['extra_attrs'] ?? [];
        if (is_string($attrs) && $attrs !== '') {
            $attrs = json_decode($attrs, true) ?: [];
        }
        if (!is_array($attrs)) {
            $attrs = [];
        }

        $options = $f['options'] ?? [];
        if (is_string($options) && $options !== '') {
            $options = json_decode($options, true) ?: [];
        }
        if (!is_array($options)) {
            $options = [];
        }
        // Normalize to [{value,label}, ...]
        $norm_opts = [];
        $viewer_url = '';
        $viewer_height = '480';
        $viewer_confirm = '1';
        if (($f['type'] ?? '') === 'file_viewer' && is_array($options)) {
            $viewer_url = (string)($options['url'] ?? $options['viewer_url'] ?? '');
            $viewer_height = (string)($options['height'] ?? $options['viewer_height'] ?? '480');
            $viewer_confirm = !empty($options['confirm']) || !isset($options['confirm']) ? '1' : '0';
            if (isset($options['confirm'])) {
                $viewer_confirm = $options['confirm'] ? '1' : '0';
            }
        } else {
            foreach ($options as $opt) {
                if (is_array($opt)) {
                    $norm_opts[] = [
                        'value' => (string)($opt['value'] ?? ''),
                        'label' => (string)($opt['label'] ?? $opt['value'] ?? ''),
                    ];
                } else {
                    $norm_opts[] = ['value' => (string)$opt, 'label' => (string)$opt];
                }
            }
        }

        $badge = $is_protected ? ' <span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:4px;font-size:.75em">protected</span>' : '';
        $edit_icon = function_exists('icon') ? icon([['icon' => 'pen-to-square']]) : 'Edit';
        $del_icon = function_exists('icon') ? icon([['icon' => 'trash']]) : 'Delete';
        $del = ($fid === 0 || $is_protected)
            ? ''
            : '<button type="button" class="btn-danger" title="Delete" onclick="if(confirm(\'Delete this field?\')) staff_form_delete_field(' . $fid . ')">' . $del_icon . '</button>';

        $map_key = $fid > 0 ? (string)$fid : ('k_' . $f['field_key']);
        $edit_map[$map_key] = [
            'fieldid' => $fid,
            'field_key' => $f['field_key'],
            'label' => $f['label'],
            'type' => $f['type'],
            'section' => $f['section'] ?? '',
            'sortorder' => (int)$f['sortorder'],
            'required' => (int)($f['required'] ?? 0),
            'active' => (int)($f['active'] ?? 1),
            'helptext' => $f['helptext'] ?? '',
            'vis_action' => $visibility['action'] ?? '',
            'vis_logic' => $visibility['logic'] ?? 'and',
            'vis_conditions' => $visibility['conditions'] ?? [],
            'reqw_enable' => !empty($required_when['conditions']) ? '1' : '0',
            'reqw_logic' => $required_when['logic'] ?? 'and',
            'reqw_conditions' => $required_when['conditions'] ?? [],
            'attr_onblur' => $attrs['onblur'] ?? '',
            'attr_onchange' => $attrs['onchange'] ?? '',
            'locked' => !empty($attrs['locked']) ? '1' : '0',
            'options' => $norm_opts,
            'viewer_url' => $viewer_url,
            'viewer_height' => $viewer_height,
            'viewer_confirm' => $viewer_confirm,
            'is_consent' => $is_consent ? 1 : 0,
            'is_core' => $is_protected ? 1 : 0,
        ];

        $edit_js_id = $fid > 0 ? $fid : ("'" . $map_key . "'");
        $rows .= '<tr draggable="true" data-fieldid="' . (int)$fid . '" class="sfe-field-row">
            <td class="sfe-drag-handle" title="Drag to reorder">&#8942;&#8942;</td>
            <td style="text-align:center" class="sfe-sort-display">' . (int)$f['sortorder'] . '</td>
            <td><code>' . htmlspecialchars($f['field_key']) . '</code>' . $badge . '</td>
            <td>' . htmlspecialchars($f['label']) . '</td>
            <td>' . htmlspecialchars($f['type']) . '</td>
            <td>' . htmlspecialchars($f['section'] ?? '') . '</td>
            <td style="text-align:center">' . $vis . '</td>
            <td style="text-align:center">' . $reqw . '</td>
            <td>' . $active . '</td>
            <td style="white-space:nowrap">
                <button type="button" title="Edit" onclick="staffFormEditField(' . $edit_js_id . ')">' . $edit_icon . '</button>
                ' . $del . '
            </td>
        </tr>';
    }

    $edit_json = json_encode($edit_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    $key_opts_js = json_encode(array_values(array_filter(array_map(function ($f) {
        return (($f['type'] ?? '') === 'section') ? null : $f['field_key'];
    }, $fields))), JSON_UNESCAPED_UNICODE);

    return '
    <style>
        .sfe-wrap { max-width: 1100px; margin: 0 auto; font-family: system-ui, sans-serif; }
        .sfe-wrap h2 { margin-bottom: .25rem; }
        .sfe-wrap .sfe-note { color: #555; margin-bottom: 1rem; font-size: .95em; }
        .sfe-wrap table.sfe-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .sfe-wrap table.sfe-table th { background: #2c3e50; color: #fff; padding: 8px 10px; text-align: left; font-weight: 600; font-size: .85em; }
        .sfe-wrap table.sfe-table td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: .9em; vertical-align: middle; }
        .sfe-wrap table.sfe-table tr:hover td { background: #f7fafc; }
        .sfe-wrap .sfe-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-top: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .sfe-wrap .sfe-card h3 { margin-top: 0; }
        .sfe-wrap .sfe-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
        .sfe-wrap .sfe-grid label { display: block; font-weight: 600; font-size: .85em; margin-bottom: 4px; color: #334155; }
        .sfe-wrap .sfe-grid input, .sfe-wrap .sfe-grid select, .sfe-wrap .sfe-grid textarea {
            width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;
        }
        .sfe-wrap .sfe-actions { margin-top: 14px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .sfe-wrap button { background: #2563eb; color: #fff; border: none; border-radius: 6px; padding: 8px 14px; cursor: pointer; font-size: .9em; }
        .sfe-wrap button:hover { background: #1d4ed8; }
        .sfe-wrap button.btn-danger { background: #dc2626; color: #fff !important; }
        .sfe-wrap button.btn-danger * { color: #fff !important; }
        .sfe-wrap button.btn-secondary { background: #64748b; }
        .sfe-wrap button.btn-small { padding: 4px 10px; font-size: .8em; }
        .sfe-wrap .sfe-rules { background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 6px; padding: 12px; margin-top: 8px; }
        .sfe-wrap .sfe-rules h4 { margin: 0 0 8px; font-size: .9em; color: #475569; }
        .sfe-wrap code { background: #f1f5f9; padding: 1px 5px; border-radius: 3px; font-size: .85em; }
        .sfe-wrap .sfe-hint { font-size: .85em; color: #64748b; margin: .35rem 0 8px; }
        .sfe-wrap .sfe-cond-row { display: grid; grid-template-columns: 2fr 1fr 1.5fr auto; gap: 8px; align-items: end; margin-bottom: 8px; }
        .sfe-wrap .sfe-cond-row label { font-size: .75em; font-weight: 600; color: #64748b; display: block; margin-bottom: 2px; }
        .sfe-wrap tr.sfe-dragging { opacity: .5; background: #e0f2fe !important; }
        .sfe-wrap tr.sfe-drag-over { box-shadow: inset 0 3px 0 #2563eb; }
        .sfe-wrap .sfe-drag-handle { cursor: grab; color: #94a3b8; user-select: none; font-size: 1.1em; padding: 0 6px; }
        .sfe-wrap .sfe-drag-handle:active { cursor: grabbing; }
        .sfe-wrap .sfe-dnd-hint { font-size: .85em; color: #64748b; margin: 0 0 8px; }
        .sfe-wrap details.sfe-collapse { background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 6px; padding: 0; margin-top: 8px; }
        .sfe-wrap details.sfe-collapse > summary {
            cursor: pointer; font-weight: 600; font-size: .9em; color: #475569;
            padding: 10px 12px; list-style: none; user-select: none;
        }
        .sfe-wrap details.sfe-collapse > summary::-webkit-details-marker { display: none; }
        .sfe-wrap details.sfe-collapse > summary::before { content: "▸ "; color: #94a3b8; }
        .sfe-wrap details.sfe-collapse[open] > summary::before { content: "▾ "; }
        .sfe-wrap details.sfe-collapse .sfe-collapse-body { padding: 0 12px 12px; }
        .sfe-wrap details.sfe-collapse.has-content > summary { color: #1e40af; }
    </style>
    <div class="sfe-wrap" id="staff_form_editor_container">
        <h2>Staff Application Form Editor</h2>
        <p class="sfe-note">
            Add, edit, reorder, and configure questions for the staff application form.
            Use field types, show/hide and required-when rules, and JavaScript attributes as needed.
        </p>
        <p class="sfe-dnd-hint">Drag the <strong>⋮⋮</strong> handle to reorder fields. Order is saved automatically.</p>
        <table class="sfe-table" id="sfe-fields-table">
            <thead>
                <tr>
                    <th style="width:28px"></th>
                    <th>Order</th><th>Key</th><th>Label</th><th>Type</th><th>Section</th>
                    <th>Show/Hide</th><th>Req. When</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>

        <p style="margin-top:1rem">
            <button type="button" onclick="staffFormShowNew()">+ New Field</button>
        </p>
        <div class="sfe-card" id="sfe-edit-card" style="display:none">
            <h3 id="sfe-form-title">Add Field</h3>
            <form id="staff_field_form">
                <input type="hidden" name="fieldid" id="edit_fieldid" value="0" />
                <input type="hidden" name="vis_conditions_json" id="edit_vis_conditions_json" value="[]" />
                <input type="hidden" name="reqw_conditions_json" id="edit_reqw_conditions_json" value="[]" />
                <div class="sfe-grid">
                    <div>
                        <label>Field Key</label>
                        <input type="text" name="field_key" id="edit_field_key" required />
                    </div>
                    <div>
                        <label>Label</label>
                        <input type="text" name="label" id="edit_label" required />
                    </div>
                    <div>
                        <label>Type</label>
                        <select name="type" id="edit_type">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="yesno">Yes/No</option>
                            <option value="select">Select</option>
                            <option value="date">Date</option>
                            <option value="phone">Phone</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="section">Section Header</option>
                            <option value="file_viewer">File / Document Viewer</option>
                        </select>
                    </div>
                    <div>
                        <label>Section</label>
                        <input type="text" name="section" id="edit_section" list="sfe-section-list" autocomplete="off" placeholder="Start typing..." />
                        <datalist id="sfe-section-list">' . $section_opts . '</datalist>
                    </div>
                    <div>
                        <label>Sort Order</label>
                        <input type="number" name="sortorder" id="edit_sortorder" value="500" />
                    </div>
                    <div>
                        <label>Required</label>
                        <select name="required" id="edit_required"><option value="0">No</option><option value="1">Yes</option></select>
                    </div>
                    <div>
                        <label>Active</label>
                        <select name="active" id="edit_active"><option value="1">Yes</option><option value="0">No</option></select>
                    </div>
                    <div>
                        <label>Locked (read-only on form)</label>
                        <select name="locked" id="edit_locked"><option value="0">No</option><option value="1">Yes — show but cannot change</option></select>
                    </div>
                    <div>
                        <label>Help / Tooltip</label>
                        <input type="text" name="helptext" id="edit_helptext" />
                    </div>
                </div>

                <div class="sfe-rules" id="sfe-options-panel" style="display:none">
                    <h4>Select options</h4>
                    <p class="sfe-hint">Each option needs a value (stored) and a label (shown). Drag not required — use Add / Remove.</p>
                    <div id="edit_select_options"></div>
                    <button type="button" class="btn-secondary btn-small" onclick="sfeAddOption()">+ Add option</button>
                    <input type="hidden" name="options_json" id="edit_options_json" value="[]" />
                </div>

                <div class="sfe-rules" id="sfe-viewer-panel" style="display:none">
                    <h4>File / Document viewer</h4>
                    <p class="sfe-hint">URL of a PDF or page to embed for the applicant to review. Optional confirmation checkbox uses the field label.</p>
                    <div class="sfe-grid">
                        <div style="grid-column:1/-1">
                            <label>Document URL</label>
                            <input type="text" name="viewer_url" id="edit_viewer_url" placeholder="https://example.com/policy.pdf" />
                        </div>
                        <div>
                            <label>Embed height (px)</label>
                            <input type="number" name="viewer_height" id="edit_viewer_height" value="480" min="200" max="1200" />
                        </div>
                        <div>
                            <label>Require confirmation checkbox</label>
                            <select name="viewer_confirm" id="edit_viewer_confirm">
                                <option value="1">Yes</option>
                                <option value="0">No (display only)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <details class="sfe-collapse" id="sfe-panel-js">
                    <summary>JavaScript attributes</summary>
                    <div class="sfe-collapse-body">
                        <p class="sfe-hint">DOB uses onblur to set Age Range, which then drives show/hide on other fields.</p>
                        <div class="sfe-grid">
                            <div style="grid-column:1/-1">
                                <label>onblur</label>
                                <textarea name="attr_onblur" id="edit_attr_onblur" rows="2"></textarea>
                            </div>
                            <div style="grid-column:1/-1">
                                <label>onchange</label>
                                <textarea name="attr_onchange" id="edit_attr_onchange" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </details>

                <details class="sfe-collapse" id="sfe-panel-vis">
                    <summary>Show / Hide rules</summary>
                    <div class="sfe-collapse-body">
                    <div class="sfe-grid" style="margin-bottom:10px">
                        <div>
                            <label>Action</label>
                            <select name="vis_action" id="edit_vis_action">
                                <option value="">— none —</option>
                                <option value="show">Show when conditions match</option>
                                <option value="hide">Hide when conditions match</option>
                            </select>
                        </div>
                        <div>
                            <label>Combine conditions with</label>
                            <select name="vis_logic" id="edit_vis_logic">
                                <option value="and">AND (all must match)</option>
                                <option value="or">OR (any may match)</option>
                            </select>
                        </div>
                    </div>
                    <div id="edit_vis_conditions"></div>
                    <button type="button" class="btn-secondary btn-small" onclick="sfeAddCond(\'vis\')">+ Add condition</button>
                    </div>
                </details>

                <details class="sfe-collapse" id="sfe-panel-reqw">
                    <summary>Required when</summary>
                    <div class="sfe-collapse-body">
                    <div class="sfe-grid" style="margin-bottom:10px">
                        <div>
                            <label>Enable</label>
                            <select name="reqw_enable" id="edit_reqw_enable">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div>
                            <label>Combine conditions with</label>
                            <select name="reqw_logic" id="edit_reqw_logic">
                                <option value="and">AND (all must match)</option>
                                <option value="or">OR (any may match)</option>
                            </select>
                        </div>
                    </div>
                    <div id="edit_reqw_conditions"></div>
                    <button type="button" class="btn-secondary btn-small" onclick="sfeAddCond(\'reqw\')">+ Add condition</button>
                    </div>
                </details>

                <div class="sfe-actions">
                    <button type="submit" onclick="return sfeSaveField();">Save Field</button>
                    <button type="button" class="btn-secondary" onclick="staffFormHideForm()">Cancel</button>
                </div>
            </form>
        </div>
        <p style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid #e2e8f0">
            <button type="button" class="btn-secondary" id="staff_form_migrate_btn" onclick="staffFormRunMigrate()">Run Data Migration</button>
            <span id="staff_form_migrate_result" style="margin-left:10px"></span>
        </p>
    </div>
    <script>
    window.STAFF_FORM_EDIT_MAP = ' . $edit_json . ';
    window.STAFF_FORM_FIELD_KEYS = ' . $key_opts_js . ';

    function sfeFieldOptions(selected) {
        var html = "<option value=\\"\\">—</option>";
        (window.STAFF_FORM_FIELD_KEYS || []).forEach(function(k) {
            html += "<option value=\\"" + k + "\\"" + (k === selected ? " selected" : "") + ">" + k + "</option>";
        });
        return html;
    }

    function sfeAddCond(kind, cond) {
        cond = cond || { field: "", op: "eq", value: "" };
        var box = document.getElementById(kind === "vis" ? "edit_vis_conditions" : "edit_reqw_conditions");
        var row = document.createElement("div");
        row.className = "sfe-cond-row";
        row.innerHTML =
            "<div><label>Watch field</label><select class=\\"sfe-c-field\\">" + sfeFieldOptions(cond.field || "") + "</select></div>" +
            "<div><label>Operator</label><select class=\\"sfe-c-op\\">" +
                "<option value=\\"eq\\"" + (cond.op === "eq" ? " selected" : "") + ">equals</option>" +
                "<option value=\\"neq\\"" + (cond.op === "neq" ? " selected" : "") + ">not equals</option>" +
            "</select></div>" +
            "<div><label>Value</label><input type=\\"text\\" class=\\"sfe-c-value\\" value=\\"" + (cond.value != null ? String(cond.value).replace(/"/g, "&quot;") : "") + "\\" /></div>" +
            "<div><button type=\\"button\\" class=\\"btn-danger btn-small\\" onclick=\\"this.closest(\".sfe-cond-row\").remove()\\">Remove</button></div>";
        box.appendChild(row);
    }

    function sfeCollectConds(kind) {
        var box = document.getElementById(kind === "vis" ? "edit_vis_conditions" : "edit_reqw_conditions");
        var out = [];
        box.querySelectorAll(".sfe-cond-row").forEach(function(row) {
            var field = row.querySelector(".sfe-c-field").value;
            if (!field) return;
            out.push({
                field: field,
                op: row.querySelector(".sfe-c-op").value || "eq",
                value: row.querySelector(".sfe-c-value").value
            });
        });
        return out;
    }

    function sfeAddOption(opt) {
        opt = opt || { value: "", label: "" };
        var box = document.getElementById("edit_select_options");
        if (!box) return;
        var row = document.createElement("div");
        row.className = "sfe-cond-row";
        var v = String(opt.value || "").replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/</g,"&lt;");
        var l = String(opt.label || "").replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/</g,"&lt;");
        row.innerHTML = "";
        var d1 = document.createElement("div");
        d1.innerHTML = "<label>Value</label>";
        var i1 = document.createElement("input");
        i1.type = "text"; i1.className = "sfe-opt-value"; i1.value = opt.value || "";
        d1.appendChild(i1);
        var d2 = document.createElement("div");
        d2.innerHTML = "<label>Label</label>";
        var i2 = document.createElement("input");
        i2.type = "text"; i2.className = "sfe-opt-label"; i2.value = opt.label || "";
        d2.appendChild(i2);
        var d3 = document.createElement("div");
        var d4 = document.createElement("div");
        var btn = document.createElement("button");
        btn.type = "button"; btn.className = "btn-danger btn-small"; btn.textContent = "Remove";
        btn.onclick = function(){ row.remove(); };
        d4.appendChild(btn);
        row.appendChild(d1); row.appendChild(d2); row.appendChild(d3); row.appendChild(d4);
        box.appendChild(row);
    }

    function sfeCollectOptions() {
        var box = document.getElementById("edit_select_options");
        var out = [];
        if (!box) return out;
        box.querySelectorAll(".sfe-cond-row").forEach(function(row) {
            var v = row.querySelector(".sfe-opt-value").value;
            var l = row.querySelector(".sfe-opt-label").value;
            if (v === "" && l === "") return;
            out.push({ value: v, label: l || v });
        });
        return out;
    }

    function sfeToggleOptionsPanel() {
        var type = document.getElementById("edit_type").value;
        var panel = document.getElementById("sfe-options-panel");
        var vpanel = document.getElementById("sfe-viewer-panel");
        if (panel) panel.style.display = (type === "select") ? "" : "none";
        if (vpanel) vpanel.style.display = (type === "file_viewer") ? "" : "none";
    }

    function sfeSaveField() {
        document.getElementById("edit_vis_conditions_json").value = JSON.stringify(sfeCollectConds("vis"));
        document.getElementById("edit_reqw_conditions_json").value = JSON.stringify(sfeCollectConds("reqw"));
        document.getElementById("edit_options_json").value = JSON.stringify(sfeCollectOptions());
        // Ensure textareas are not skipped: copy into hidden fields as well
        var ob = document.getElementById("edit_attr_onblur");
        var oc = document.getElementById("edit_attr_onchange");
        if (ob) { ob.setAttribute("name", "attr_onblur"); ob.name = "attr_onblur"; }
        if (oc) { oc.setAttribute("name", "attr_onchange"); oc.name = "attr_onchange"; }
        staff_form_save_field();
        return false;
    }

    function sfeSyncCollapsePanels(f) {
        f = f || {};
        var jsPanel = document.getElementById("sfe-panel-js");
        var visPanel = document.getElementById("sfe-panel-vis");
        var reqPanel = document.getElementById("sfe-panel-reqw");
        var hasJs = !!(f.attr_onblur || f.attr_onchange);
        var hasVis = !!(f.vis_action && (f.vis_conditions || []).length);
        var hasReq = (f.reqw_enable == "1" && (f.reqw_conditions || []).length);
        function setPanel(el, open, has) {
            if (!el) return;
            el.open = !!open;
            if (has) el.classList.add("has-content");
            else el.classList.remove("has-content");
        }
        setPanel(jsPanel, hasJs, hasJs);
        setPanel(visPanel, hasVis, hasVis);
        setPanel(reqPanel, hasReq, hasReq);
    }

    function staffFormEditField(id) {
        var f = window.STAFF_FORM_EDIT_MAP[id] || window.STAFF_FORM_EDIT_MAP[String(id)];
        if (!f) { alert("Field data not found"); return; }
        $("#edit_fieldid").val(f.fieldid);
        $("#edit_field_key").val(f.field_key);
        if (f.is_core == 1 || f.is_consent == 1) {
            $("#edit_field_key").prop("readonly", true).css("background", "#f1f5f9");
        } else {
            $("#edit_field_key").prop("readonly", false).css("background", "");
        }
        $("#edit_label").val(f.label);
        $("#edit_type").val(f.type);
        $("#edit_section").val(f.section);
        $("#edit_sortorder").val(f.sortorder);
        $("#edit_required").val(String(f.required));
        $("#edit_active").val(String(f.active));
        $("#edit_locked").val(f.locked || "0");
        $("#edit_helptext").val(f.helptext || "");
        $("#edit_attr_onblur").val(f.attr_onblur || "");
        $("#edit_attr_onchange").val(f.attr_onchange || "");
        document.getElementById("edit_select_options").innerHTML = "";
        (f.options || []).forEach(function(o) { sfeAddOption(o); });
        if (f.type === "select" && !(f.options || []).length) {
            sfeAddOption();
        }
        $("#edit_viewer_url").val(f.viewer_url || "");
        $("#edit_viewer_height").val(f.viewer_height || "480");
        $("#edit_viewer_confirm").val(f.viewer_confirm || "1");
        sfeToggleOptionsPanel();
        $("#edit_vis_action").val(f.vis_action || "");
        $("#edit_vis_logic").val(f.vis_logic || "and");
        $("#edit_reqw_enable").val(f.reqw_enable || "0");
        $("#edit_reqw_logic").val(f.reqw_logic || "and");
        document.getElementById("edit_vis_conditions").innerHTML = "";
        document.getElementById("edit_reqw_conditions").innerHTML = "";
        (f.vis_conditions || []).forEach(function(c) { sfeAddCond("vis", c); });
        (f.reqw_conditions || []).forEach(function(c) { sfeAddCond("reqw", c); });
        if (!(f.vis_conditions || []).length && f.vis_action) {
            sfeAddCond("vis");
        }
        if (f.reqw_enable == "1" && !(f.reqw_conditions || []).length) {
            sfeAddCond("reqw");
        }
        sfeSyncCollapsePanels(f);
        $("#sfe-form-title").text("Edit Field: " + f.field_key);
        var card = document.getElementById("sfe-edit-card");
        if (card) {
            card.style.display = "";
            card.scrollIntoView({behavior:"smooth"});
        }
    }

    function staffFormShowNew() {
        staffFormResetEdit();
        sfeSyncCollapsePanels({});
        var card = document.getElementById("sfe-edit-card");
        if (card) {
            card.style.display = "";
            card.scrollIntoView({behavior:"smooth"});
        }
    }

    function staffFormHideForm() {
        var card = document.getElementById("sfe-edit-card");
        if (card) card.style.display = "none";
        staffFormResetEdit();
    }

    function staffFormRunMigrate() {
        var btn = document.getElementById("staff_form_migrate_btn");
        var out = document.getElementById("staff_form_migrate_result");
        if (btn) {
            if (btn.disabled) return;
            btn.disabled = true;
            btn.setAttribute("data-label", btn.innerHTML);
            btn.innerHTML = "Migrating…";
        }
        if (out) out.innerHTML = "<em>Working… please wait.</em>";
        if (typeof staff_form_migrate === "function") {
            staff_form_migrate();
        }
        // Re-enable after response paints (ajax replaces #staff_form_migrate_result)
        setTimeout(function() {
            var b = document.getElementById("staff_form_migrate_btn");
            if (b) {
                b.disabled = false;
                if (b.getAttribute("data-label")) b.innerHTML = b.getAttribute("data-label");
            }
        }, 8000);
    }

    function staffFormDropDeprecated() {
        if (!confirm("Permanently drop deprecated answer columns from events_staff and events_staff_archive? This cannot be undone. Only proceed after a successful data migration.")) {
            return;
        }
        var btn = document.getElementById("staff_form_drop_cols_btn");
        if (btn) {
            if (btn.disabled) return;
            btn.disabled = true;
            btn.innerHTML = "Dropping columns…";
        }
        if (typeof staff_form_drop_deprecated_columns === "function") {
            staff_form_drop_deprecated_columns();
        }
    }

    function staffFormResetEdit() {
        $("#staff_field_form")[0].reset();
        $("#edit_fieldid").val(0);
        $("#edit_field_key").prop("readonly", false).css("background", "");
        $("#edit_attr_onblur").val("");
        $("#edit_attr_onchange").val("");
        document.getElementById("edit_select_options").innerHTML = "";
        $("#edit_options_json").val("[]");
        $("#edit_viewer_url").val("");
        $("#edit_viewer_height").val("480");
        $("#edit_viewer_confirm").val("1");
        sfeToggleOptionsPanel();
        document.getElementById("edit_vis_conditions").innerHTML = "";
        document.getElementById("edit_reqw_conditions").innerHTML = "";
        $("#edit_vis_conditions_json").val("[]");
        $("#edit_reqw_conditions_json").val("[]");
        $("#sfe-form-title").text("Add Field");
    }

    $("#edit_type").on("change", sfeToggleOptionsPanel);
    sfeToggleOptionsPanel();

    // --- Drag and drop reorder ---
    (function initStaffFormDnD() {
        var tbody = document.querySelector("#sfe-fields-table tbody");
        if (!tbody) return;
        var dragRow = null;

        tbody.addEventListener("dragstart", function(e) {
            var tr = e.target.closest("tr.sfe-field-row");
            if (!tr || !tr.getAttribute("data-fieldid")) { e.preventDefault(); return; }
            // Only start from handle or row
            dragRow = tr;
            tr.classList.add("sfe-dragging");
            try {
                e.dataTransfer.effectAllowed = "move";
                e.dataTransfer.setData("text/plain", tr.getAttribute("data-fieldid"));
            } catch (err) {}
        });

        tbody.addEventListener("dragend", function(e) {
            if (dragRow) dragRow.classList.remove("sfe-dragging");
            tbody.querySelectorAll("tr.sfe-drag-over").forEach(function(r) { r.classList.remove("sfe-drag-over"); });
            dragRow = null;
        });

        tbody.addEventListener("dragover", function(e) {
            e.preventDefault();
            var tr = e.target.closest("tr.sfe-field-row");
            if (!tr || tr === dragRow) return;
            tbody.querySelectorAll("tr.sfe-drag-over").forEach(function(r) {
                if (r !== tr) r.classList.remove("sfe-drag-over");
            });
            tr.classList.add("sfe-drag-over");
            try { e.dataTransfer.dropEffect = "move"; } catch (err) {}
        });

        tbody.addEventListener("dragleave", function(e) {
            var tr = e.target.closest("tr.sfe-field-row");
            if (tr) tr.classList.remove("sfe-drag-over");
        });

        tbody.addEventListener("drop", function(e) {
            e.preventDefault();
            var tr = e.target.closest("tr.sfe-field-row");
            tbody.querySelectorAll("tr.sfe-drag-over").forEach(function(r) { r.classList.remove("sfe-drag-over"); });
            if (!dragRow || !tr || dragRow === tr) return;

            var rect = tr.getBoundingClientRect();
            var before = (e.clientY - rect.top) < (rect.height / 2);
            if (before) {
                tbody.insertBefore(dragRow, tr);
            } else {
                tbody.insertBefore(dragRow, tr.nextSibling);
            }

            // Build new order of fieldids
            var ids = [];
            tbody.querySelectorAll("tr.sfe-field-row").forEach(function(row, idx) {
                var id = parseInt(row.getAttribute("data-fieldid"), 10);
                if (id > 0) ids.push(id);
                var disp = row.querySelector(".sfe-sort-display");
                if (disp) disp.textContent = String((idx + 1) * 10);
            });
            if (!ids.length) return;

            // Persist via existing ajaxapi helper
            if (typeof staff_form_reorder === "function") {
                // ajaxapi uses js||order||js — set global for the binding
                window.order = ids.join(",");
                staff_form_reorder();
            } else if (typeof jQuery !== "undefined") {
                jQuery.post("/features/events/events_ajax.php", {
                    action: "staff_form_reorder",
                    order: ids.join(",")
                }, function(html) {
                    var $box = jQuery("#staff_form_editor_container");
                    if ($box.length && html) {
                        // response may be full container html
                        if (html.indexOf("staff_form_editor_container") >= 0) {
                            $box.replaceWith(html);
                        } else {
                            $box.html(html);
                        }
                    }
                });
            }
        });
    })();
    </script>';
}
