<?php
/**
 * Dynamic Staff Application Form library.
 * Runtime: field seed, render, save. Legacy column migration lives in
 * staffform_migration.php (optional; safe to delete after migrate+drop).
 */

if (!defined('STAFFFORMLIB')) {
    define('STAFFFORMLIB', true);

// Register staff_app form definition (hooks + computed resolvers)
$__staff_app_formdef = dirname(__DIR__) . '/formdefs/staff_app.php';
if (file_exists($__staff_app_formdef)) {
    include_once($__staff_app_formdef);
}
unset($__staff_app_formdef);

}
// Named form engine (hooks, storage, migration plans)
if (!defined('FORMENGINELIB')) {
    $__fe = __DIR__ . '/formenginelib.php';
    if (file_exists($__fe)) {
        include_once($__fe);
    }
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
    // Thin wrapper — prefer form_engine_protected_field_keys()
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    if (function_exists('form_engine_protected_field_keys')) {
        return form_engine_protected_field_keys(defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app', 0);
    }
    return [];
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
    $form_key = defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app';
    $sql = "SELECT * FROM form_fields
            WHERE form_key = ||form_key||
              AND (pageid = ||pageid|| OR pageid = 0)
            $where
            ORDER BY pageid DESC, sortorder ASC, fieldid ASC";
    // Prefer page-specific over global when both exist for same field_key
    $rows = get_db_result($sql, ['pageid' => (int)$pageid, 'form_key' => $form_key]);

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
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    form_engine_ensure_schema();
    // Staff storage columns on events_staff / archive (feature-owned, not form engine)
    staff_form_add_column_if_missing('events_staff', 'form_data', 'LONGTEXT DEFAULT NULL');
    staff_form_add_column_if_missing('events_staff_archive', 'form_data', 'LONGTEXT DEFAULT NULL');
    staff_form_ensure_longtext_column('events_staff', 'form_data');
    staff_form_ensure_longtext_column('events_staff_archive', 'form_data');
    foreach (['workerconsent', 'workerconsentdate', 'workerconsentsig',
              'parentalconsent', 'parentalconsentsig', 'bgcheck'] as $col) {
        staff_form_ensure_column_default('events_staff', $col);
        staff_form_ensure_column_default('events_staff_archive', $col);
    }
}


function staff_form_ensure_column_default($table, $column) {
    $row = get_db_row(
        "SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ||table||
           AND COLUMN_NAME = ||column||",
        ['table' => $table, 'column' => $column]
    );
    if (!$row) {
        return;
    }
    if ($row['COLUMN_DEFAULT'] !== null) {
        return; // already has a default
    }
    $ctype = $row['COLUMN_TYPE'];
    $dtype = strtolower($row['DATA_TYPE'] ?? '');
    if (in_array($dtype, ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'])) {
        execute_db_sql("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$ctype} NOT NULL DEFAULT 0");
    } else {
        execute_db_sql("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$ctype} NOT NULL DEFAULT ''");
    }
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
            "SELECT fieldid FROM form_fields
             WHERE form_key = ||fk|| AND pageid = ||pageid|| AND field_key = ||key||",
            [
                'fk' => defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app',
                'pageid' => $pageid,
                'key' => $f['field_key'],
            ]
        );
        if ($exists && !$force) {
            // Refresh only flags that must stay in sync with code defaults.
            // Do NOT overwrite extra_attrs / visibility / required_when / helptext / label —
            // those are editable in the UI and must persist across editor reloads.
            execute_db_sql(
                "UPDATE form_fields SET
                    is_system = ||is_system||, modified = ||modified||
                 WHERE fieldid = ||fieldid||",
                [
                    'is_system' => (int)($f['is_system'] ?? 0),
                    'modified' => $now,
                    'fieldid'  => $exists['fieldid'],
                ]
            );
            // One-time backfill: if visibility/required_when/extra_attrs empty in DB, copy from defaults
            $row = get_db_row("SELECT visibility, required_when, extra_attrs FROM form_fields WHERE fieldid=||id||", ['id' => $exists['fieldid']]);
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
                    "UPDATE form_fields SET " . implode(', ', $sets) . " WHERE fieldid = ||fieldid||",
                    $params
                );
            }
            continue;
        }
        $params = [
            'form_key'   => defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app',
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
                "UPDATE form_fields SET
                    form_key=||form_key||, label=||label||, type=||type||, options=||options||, required=||required||,
                    sortorder=||sortorder||, section=||section||, helptext=||helptext||,
                    validation=||validation||, is_system=||is_system||, active=||active||,
                    extra_attrs=||extra_attrs||, visibility=||visibility||, required_when=||required_when||,
                    modified=||modified||
                 WHERE fieldid = ||fieldid||",
                array_merge($params, ['fieldid' => $exists['fieldid']])
            );
        } else {
            execute_db_sql(
                "INSERT INTO form_fields
                    (form_key, pageid, field_key, label, type, options, required, sortorder, section, helptext, validation, is_system, active, extra_attrs, visibility, required_when, created, modified)
                 VALUES
                    (||form_key||, ||pageid||, ||field_key||, ||label||, ||type||, ||options||, ||required||, ||sortorder||, ||section||, ||helptext||, ||validation||, ||is_system||, ||active||, ||extra_attrs||, ||visibility||, ||required_when||, ||created||, ||modified||)",
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
/**
 * Normalize legacy checkbox / signature values to canonical "1" / "0".
 * Browsers historically posted "on" for checkboxes without an explicit value attribute.
 */
function staff_form_normalize_checkbox_value($val) {
    if ($val === null || $val === '') {
        return '0';
    }
    if (is_bool($val)) {
        return $val ? '1' : '0';
    }
    $s = strtolower(trim((string)$val));
    if ($s === '1' || $s === 'on' || $s === 'yes' || $s === 'true' || $s === 'checked') {
        return '1';
    }
    if ($s === '0' || $s === 'off' || $s === 'no' || $s === 'false') {
        return '0';
    }
    // Any other non-empty string treats as checked (legacy safety)
    return '1';
}


/**
 * Parse a calendar date string to a UTC-midnight unix timestamp.
 * Accepts m/d/Y, n/j/Y, and Y-m-d. Avoids timezone day-shifts for DOB-style fields.
 *
 * @param string $str
 * @return int Unix timestamp (UTC midnight) or 0
 */
function staff_form_parse_date_only($str) {
    $str = trim((string)$str);
    if ($str === '') {
        return 0;
    }
    // Already a pure integer timestamp
    if (is_numeric($str) && (string)(int)$str === (string)$str && (int)$str > 0) {
        // Normalize existing timestamps to UTC midnight of their UTC calendar day
        return staff_form_normalize_ts_to_utc_midnight((int)$str);
    }
    $tz = new DateTimeZone('UTC');
    foreach (['!m/d/Y', '!n/j/Y', '!Y-m-d'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $str, $tz);
        if ($dt instanceof DateTime) {
            $errs = DateTime::getLastErrors();
            if (is_array($errs) && (!empty($errs['warning_count']) || !empty($errs['error_count']))) {
                continue;
            }
            $dt->setTime(0, 0, 0);
            return (int)$dt->getTimestamp();
        }
    }
    return 0;
}

/**
 * Force a timestamp to UTC midnight on the same UTC calendar day.
 * Stabilizes legacy values that were stored at local midnights.
 *
 * @param int $ts
 * @return int
 */
function staff_form_normalize_ts_to_utc_midnight($ts) {
    $ts = (int)$ts;
    if ($ts <= 0) {
        return 0;
    }
    $day = gmdate('Y-m-d', $ts);
    $dt = DateTime::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));
    return $dt ? (int)$dt->getTimestamp() : 0;
}

/**
 * Format a date-only timestamp (or date string) as m/d/Y using the UTC calendar day.
 *
 * @param mixed $ts
 * @return string
 */
function staff_form_format_date_only($ts) {
    if ($ts === null || $ts === '' || $ts === false) {
        return '';
    }
    if (!is_numeric($ts)) {
        $s = trim((string)$ts);
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $s)) {
            return $s;
        }
        $ts = staff_form_parse_date_only($s);
    }
    $ts = (int)$ts;
    if ($ts <= 0) {
        return '';
    }
    return gmdate('m/d/Y', $ts);
}

/**
 * Propagate a corrected date of birth to all archive rows for this user on the page.
 * Also patches form_data JSON when it contains dateofbirth.
 *
 * @param int $userid
 * @param int $pageid
 * @param int $dob_ts UTC-midnight timestamp
 */
function staff_form_sync_dob_to_archives($userid, $pageid, $dob_ts) {
    $userid = (int)$userid;
    $pageid = (int)$pageid;
    $dob_ts = (int)$dob_ts;
    if ($userid <= 0 || $pageid <= 0 || $dob_ts <= 0) {
        return;
    }

    execute_db_sql(
        "UPDATE events_staff_archive
         SET dateofbirth = ||dob||
         WHERE userid = ||userid|| AND pageid = ||pageid||",
        ["dob" => $dob_ts, "userid" => $userid, "pageid" => $pageid]
    );

    // Keep form_data in sync when present
    $rows = get_db_result(
        "SELECT staffid, year, form_data FROM events_staff_archive
         WHERE userid = ||userid|| AND pageid = ||pageid||",
        ["userid" => $userid, "pageid" => $pageid]
    );
    if (!$rows) {
        return;
    }
    while ($row = fetch_row($rows)) {
        if (empty($row['form_data'])) {
            continue;
        }
        $json = is_string($row['form_data']) ? json_decode($row['form_data'], true) : $row['form_data'];
        if (!is_array($json)) {
            continue;
        }
        if (isset($json['dateofbirth']) && (int)$json['dateofbirth'] === $dob_ts) {
            continue;
        }
        $json['dateofbirth'] = $dob_ts;
        execute_db_sql(
            "UPDATE events_staff_archive
             SET form_data = ||form_data||
             WHERE staffid = ||staffid|| AND year = ||year|| AND pageid = ||pageid||",
            [
                "form_data" => json_encode($json, JSON_UNESCAPED_UNICODE),
                "staffid" => $row["staffid"],
                "year" => $row["year"],
                "pageid" => $pageid,
            ]
        );
    }
}

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
        'bgcheckpassdate',
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
    // Normalize dates for display (UTC calendar day — no TZ day-shift)
    if (!empty($vals['dateofbirth'])) {
        $vals['dateofbirth'] = staff_form_format_date_only($vals['dateofbirth']);
    }
    if (!empty($vals['workerconsentdate'])) {
        $vals['workerconsentdate'] = staff_form_format_date_only($vals['workerconsentdate']);
    }
    // Normalize signature / checkbox consents: legacy "on" → "1"
    foreach (['workerconsentsig', 'parentalconsentsig'] as $sig_key) {
        if (array_key_exists($sig_key, $vals)) {
            $vals[$sig_key] = staff_form_normalize_checkbox_value($vals[$sig_key]);
        }
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
        $url = (string)($opts['url'] ?? $opts['viewer_url'] ?? '');
        // Link text is generic — the field label is already shown as the row title in view-only.
        $reviewed = (!empty($val) && $val !== '0') ? 'Reviewed' : 'Not confirmed';
        $link = $url !== ''
            ? '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">View document</a>'
            : '';
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
    if ($type === 'date') {
        $formatted = staff_form_format_date_only($val);
        return $formatted !== '' ? htmlspecialchars($formatted) : '';
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
    // no_autofill: do not pre-fill from saved values (editable forms only)
    $no_autofill = !empty($attrs['no_autofill']);
    if ($no_autofill && !$viewonly) {
        $val = '';
    }

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
        if (in_array($ak, ['disabled', 'readonly', 'wrapper_id', 'locked', 'no_autofill'], true)) {
            continue;
        }
        $extra .= ' ' . $ak . '="' . htmlspecialchars((string)$av) . '"';
    }
    if ($no_autofill && !$viewonly) {
        $extra .= ' autocomplete="off"';
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
                $confirm_text = trim((string)($opts['confirm_text'] ?? ''));
                if ($confirm_text === '') {
                    $confirm_text = 'I have reviewed this document';
                }
                // Checkbox + text as siblings (not a flex <label>) so validation .error
                // labels are not trapped/squished inside the confirm row.
                $html .= '<div class="staff-file-confirm" style="margin-top:10px">';
                $html .= '<div class="staff-file-confirm-row">';
                $html .= '<input type="checkbox" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '" value="1"'
                       . ' style="width:45px;height:30px;margin:0"'
                       . $checked . $data_rules . $extra . ' />';
                $html .= '<label for="' . htmlspecialchars($key) . '" class="staff-file-confirm-text">'
                       . htmlspecialchars($confirm_text) . '</label>';
                $html .= '</div>';
                $html .= '</div>';
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
    global $USER;

    $pageid = get_pageid() ?: 0;
    seed_staff_form_fields(0);

    $fields = staff_form_fields_consent_last(get_staff_form_fields($pageid));
    $vals = get_staff_application_values($row);
    $vals = staff_form_prepare_application_values($vals, $row, $viewonly);

    $pages = staff_form_group_fields_by_section($fields);
    $page_count = count($pages);

    $parts = [];
    $parts[] = '<div id="staffapplication_form_div" class="' . ($viewonly ? 'staff-form-viewonly' : '') . '">';
    $parts[] = staff_form_application_styles();
    $parts[] = staff_form_application_header_html($vals, $viewonly);
    $parts[] = staff_form_application_form_open_html($vals, $viewonly);

    if ($page_count > 1 && !$viewonly) {
        $parts[] = staff_form_application_pager_html($pages);
    }

    foreach ($pages as $i => $page) {
        $parts[] = staff_form_application_page_html($page, $i, $vals, $viewonly);
    }

    $parts[] = staff_form_application_submit_nav_html($page_count, $viewonly);
    $parts[] = '</fieldset></form>';

    if (!$viewonly) {
        $parts[] = staff_form_rules_js($fields);
        if ($page_count > 1) {
            $parts[] = staff_form_pagination_js($page_count);
        }
    }
    $parts[] = keepalive();
    $parts[] = '</div>';

    return implode("\n", $parts);
}

/**
 * Keep fixed consent keys at the end of the field list (order within each group preserved).
 */
function staff_form_fields_consent_last(array $fields) {
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
    return array_merge($main, $consent);
}

/**
 * New-application name prefill and editable-form consent reset.
 */
function staff_form_prepare_application_values(array $vals, $row, $viewonly) {
    global $USER;
    if (empty($row) && isset($USER->fname)) {
        $vals['name'] = trim($USER->fname . ' ' . ($USER->lname ?? ''));
    }
    if (!$viewonly) {
        foreach ([
            'workerconsent', 'workerconsentdate', 'workerconsentsig',
            'parentalconsent', 'parentalconsentsig',
        ] as $key) {
            $vals[$key] = '';
        }
    }
    return $vals;
}

/**
 * Group form fields into pages by section (first-seen order).
 * @return array<int, array{title:string,fields:array}>
 */
function staff_form_group_fields_by_section(array $fields) {
    $pages = [];
    $index = [];
    foreach ($fields as $field) {
        $sec = trim((string)($field['section'] ?? ''));
        if ($sec === '') {
            $sec = 'General';
        }
        if (!isset($index[$sec])) {
            $index[$sec] = count($pages);
            $pages[] = ['title' => $sec, 'fields' => []];
        }
        $pages[$index[$sec]]['fields'][] = $field;
    }
    return $pages;
}

function staff_form_application_styles() {
    return <<<'CSS'
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
    .staff-file-confirm-row {
        display: grid;
        grid-template-columns: 45px 1fr;
        grid-template-areas: "check text" "error error";
        column-gap: 8px; row-gap: 4px; align-items: center;
    }
    .staff-file-confirm-row > input[type=checkbox] {
        grid-area: check;
        width: 45px !important; height: 30px !important; max-width: none !important;
        margin: 0;
    }
    .staff-file-confirm-row > label.staff-file-confirm-text {
        grid-area: text; font-weight: normal; margin: 0; cursor: pointer;
    }
    .staff-file-confirm-row > label.error,
    .staff-file-confirm > label.error {
        grid-area: error; display: block; width: 100%;
        color: #dc2626; font-weight: normal; margin: 0;
    }
    .staff-viewonly-row { margin-bottom: 8px; page-break-inside: avoid; }
    .staff-viewonly-value {
        font-weight: 500; color: #0f172a; padding: 2px 0 8px; border-bottom: 1px dotted #e2e8f0;
    }
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
    .staff-subsection-heading {
        margin: 1rem 0 .5rem; font-size: 1.05em; color: #1e293b;
        border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;
    }
    .staff-subsection { margin-top: .5rem; }
</style>
CSS;
}

function staff_form_application_header_html(array $vals, $viewonly) {
    $name = htmlspecialchars($vals['name'] ?? '');
    $title = $viewonly ? ($name . ' Application') : 'Staff Application';
    return '
    <div style="text-align:center;">
        <h2>' . $title . '</h2>
        <span style="font-weight:bold;font-size:.9em">
            If you are not ' . $name . ', please sign into your own account.
        </span>
    </div>
    <br />';
}

function staff_form_application_form_open_html(array $vals, $viewonly) {
    $staffid = empty($vals['staffid'])
        ? ''
        : '<input type="hidden" id="staffid" name="staffid" value="' . (int)$vals['staffid'] . '" />';
    $fs_style = $viewonly ? '' : 'style="max-width: 520px;margin-left: auto;margin-right: auto;"';
    return '
    <form name="staffapplication_form" id="staffapplication_form">
        ' . $staffid . '
        <fieldset class="formContainer" ' . $fs_style . '>';
}

function staff_form_application_pager_html(array $pages) {
    $n = count($pages);
    $steps = '';
    foreach ($pages as $i => $page) {
        $cls = 'sfp-step' . ($i === 0 ? ' active' : '');
        $steps .= '<button type="button" class="' . $cls . '" data-page="' . (int)$i . '">'
            . htmlspecialchars($page['title']) . '</button>';
    }
    return '
    <div class="staff-form-progress"><span id="sfp-progress-label">Step 1 of ' . (int)$n . '</span></div>
    <div class="staff-form-pager" id="staff-form-pager">' . $steps . '</div>';
}

/**
 * One multi-step (or view-only) section page of fields.
 */
function staff_form_application_page_html(array $page, $index, array $vals, $viewonly) {
    $active = ($viewonly || (int)$index === 0) ? ' active' : '';
    $title = htmlspecialchars($page['title']);

    $field_html = '';
    $inside_parental = false;
    foreach ($page['fields'] as $field) {
        $key = $field['field_key'];
        $val = $vals[$key] ?? '';

        // Parental consent section: wrapper toggled by under-18 rules
        if ($key === 'section_parental') {
            $par_style = $viewonly ? '' : ' style="display:none"';
            $inside_parental = true;
            $field_html .= '
                <div id="sub18" data-staff-section="section_parental"' . $par_style . '>
                    <h4 class="staff-subsection-heading">
                    ' . htmlspecialchars($field['label']) . '
                    </h4>';
            continue;
        }

        if ($inside_parental && $key !== 'parentalconsent' && $key !== 'parentalconsentsig') {
            $field_html .= '</div>';
            $inside_parental = false;
        }

        $field_html .= render_staff_form_field($field, $val, $viewonly);
    }

    if ($inside_parental) {
        $field_html .= '</div>';
    }

    return '
        <div class="staff-form-page' . $active . '" data-page="' . (int)$index . '" data-section="' . $title . '">
            <h3 style="margin-top:0">' . $title . '</h3>
            ' . $field_html . '
        </div>';
}

function staff_form_application_submit_nav_html($page_count, $viewonly) {
    if ($viewonly) {
        return '';
    }
    if ((int)$page_count > 1) {
        return '
            <div class="staff-form-nav">
                <button type="button" class="secondary" id="sfp-prev" disabled>Previous</button>
                <button type="button" id="sfp-next">Next</button>
                <input class="submit" id="sfp-submit" name="submit" type="submit" value="Submit Application" style="display:none" />
            </div>';
    }
    return '<input class="submit" name="submit" type="submit" onmouseover="this.focus();" value="Submit Application" />';
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
    function showPage(i, opts){
        if (typeof jQuery === "undefined") return;
        opts = opts || {};
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
        // Keep the form top in view only when it has scrolled out of the viewport.
        // Never force a document scroll on every step (that caused the page to creep downward).
        if (!opts.noScroll) {
            try {
                var el = document.getElementById("staffapplication_form_div");
                if (el && typeof el.getBoundingClientRect === "function") {
                    var rect = el.getBoundingClientRect();
                    // Only nudge if the form header is above the visible area
                    if (rect.top < 0) {
                        var y = window.pageYOffset + rect.top - 20;
                        if (y < 0) y = 0;
                        window.scrollTo({ top: y, behavior: "smooth" });
                    }
                }
            } catch(e) {}
        }
    }
    function validatePage(idx){
        if (typeof jQuery === "undefined") return true;
        var $page = pages().eq(idx);
        var ok = true;
        $page.find("[data-rule-required=true], [data-rule-required=\'true\']").each(function(){
            var $el = jQuery(this);
            // Treat as visible if its page is the one we are validating (even if temporarily hidden)
            var val = $el.is(":checkbox") ? ($el.is(":checked") ? "1" : "") : String($el.val() || "");
            if (val === "" || val === "Please select") {
                ok = false;
                $el.addClass("error");
            } else {
                $el.removeClass("error");
            }
        });
        return ok;
    }
    function validateCurrentPage(){
        var ok = validatePage(cur);
        if (!ok) {
            alert("Please complete the required fields on this step before continuing.");
        }
        return ok;
    }
    function bind(){
        if (typeof jQuery === "undefined") { setTimeout(bind, 50); return; }
        jQuery("#sfp-next").on("click", function(e){
            e.preventDefault();
            e.stopPropagation();
            if (!validateCurrentPage()) return;
            showPage(cur + 1);
        });
        jQuery("#sfp-prev").on("click", function(e){
            e.preventDefault();
            e.stopPropagation();
            showPage(cur - 1);
        });
        jQuery("#staff-form-pager").on("click", ".sfp-step", function(e){
            e.preventDefault();
            e.stopPropagation();
            var p = parseInt(jQuery(this).data("page"), 10);
            if (isNaN(p) || p === cur) return;
            if (p < cur) {
                showPage(p);
                return;
            }
            // Forward: require every step up to (but not including) the target to pass validation
            for (var i = cur; i < p; i++) {
                if (!validatePage(i)) {
                    showPage(i); // jump to the first incomplete step
                    alert("Please complete the required fields on this step before continuing.");
                    return;
                }
            }
            showPage(p);
        });
        // Initial paint — do not scroll the document on first load
        showPage(0, { noScroll: true });
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
                $raw = staff_form_parse_date_only($str);
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
 * Process hooks panel for the form editor (bindings for form_key staff_app).
 */

function staff_form_editor_ui($pageid) {
    global $CFG;
    if (!defined('FORMEDITORLIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formeditorlib.php');
    }
    $form_key = defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app';
    return form_editor_ui($form_key, $pageid);
}

function staff_form_hooks_panel_html($pageid) {
    global $CFG;
    if (!defined('FORMEDITORLIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formeditorlib.php');
    }
    $form_key = defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app';
    return form_editor_hooks_panel_html($form_key, $pageid);
}

function staff_form_resolve_sortorder($pageid, $fieldid, $place_after) {
    global $CFG;
    if (!defined("FORMEDITORLIB")) {
        include_once($CFG->dirroot . "/lib/formengine/formeditorlib.php");
    }
    $fk = defined("FORM_KEY_STAFF_APP") ? FORM_KEY_STAFF_APP : "staff_app";
    return form_editor_resolve_sortorder($fk, $pageid, $fieldid, $place_after);
}

/**
 * Persist staff application columns + form_data (Events storage adapter).
 */
function staff_app_save_application($pageid, $staffid, $col_params, $form_data, $userid) {
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

    // Date-only fields: normalize to UTC midnight so archives/export never drift by TZ
    if (!empty($params['dateofbirth'])) {
        $params['dateofbirth'] = staff_form_parse_date_only($params['dateofbirth']);
    } else {
        $params['dateofbirth'] = 0;
    }
    if (!empty($params['workerconsentdate'])) {
        $params['workerconsentdate'] = staff_form_parse_date_only($params['workerconsentdate']);
    } else {
        $params['workerconsentdate'] = 0;
    }

    // Keep form_data timestamps aligned with column values
    if (is_string($form_data)) {
        $fd = json_decode($form_data, true);
        if (!is_array($fd)) {
            $fd = [];
        }
    } else {
        $fd = is_array($form_data) ? $form_data : [];
    }
    if (!empty($params['dateofbirth'])) {
        $fd['dateofbirth'] = $params['dateofbirth'];
    }
    if (!empty($params['workerconsentdate'])) {
        $fd['workerconsentdate'] = $params['workerconsentdate'];
    }
    $params['form_data'] = json_encode($fd, JSON_UNESCAPED_UNICODE);

    $newid = false;
    $staff = null;
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

        // DOB does not change year-to-year: push corrected value to all archives for this user
        if (!empty($params['dateofbirth'])) {
            staff_form_sync_dob_to_archives((int)$userid, (int)$pageid, (int)$params['dateofbirth']);
        }
    }

    return ['staffid' => $staffid, 'new' => (bool)$newid, 'row' => $staff];
}
