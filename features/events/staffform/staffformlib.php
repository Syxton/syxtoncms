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
// Named form engine (hooks, storage, migration plans) + shared field renderer
if (!defined('FORMENGINELIB')) {
    global $CFG;
    $__fe = null;
    if (!empty($CFG->dirroot) && file_exists($CFG->dirroot . '/lib/formengine/formenginelib.php')) {
        $__fe = $CFG->dirroot . '/lib/formengine/formenginelib.php';
    } elseif (file_exists(__DIR__ . '/formenginelib.php')) {
        $__fe = __DIR__ . '/formenginelib.php';
    }
    if ($__fe) {
        include_once($__fe);
    }
    unset($__fe);
}
if (!defined('FORMRENDERLIB')) {
    global $CFG;
    $__fr = null;
    if (!empty($CFG->dirroot) && file_exists($CFG->dirroot . '/lib/formengine/formrenderlib.php')) {
        $__fr = $CFG->dirroot . '/lib/formengine/formrenderlib.php';
    } elseif (file_exists(dirname(__DIR__, 3) . '/lib/formengine/formrenderlib.php')) {
        $__fr = dirname(__DIR__, 3) . '/lib/formengine/formrenderlib.php';
    }
    if ($__fr) {
        include_once($__fr);
    }
    unset($__fr);
}

if (!defined('FORMCLIENTLIB')) {
    global $CFG;
    $__fc = null;
    if (!empty($CFG->dirroot) && file_exists($CFG->dirroot . '/lib/formengine/formclientlib.php')) {
        $__fc = $CFG->dirroot . '/lib/formengine/formclientlib.php';
    }
    if ($__fc) {
        include_once($__fc);
    }
    unset($__fc);
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
    // Ensure Events legacy columns / tables (safe no-op if already present)
    static $table_checked = false;
    if (!$table_checked) {
        ensure_staff_form_tables();
        $table_checked = true;
    }

    $form_key = defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app';
    if (!defined('FORMENGINELIB')) {
        global $CFG;
        if (!empty($CFG->dirroot)) {
            include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
        }
    }
    $list = form_engine_get_fields($form_key, $pageid, $include_inactive);
    if (!empty($list)) {
        return $list;
    }

    // Fallback to hard-coded defaults when DB has no rows yet
    $by_key = [];
    $defaults = get_default_staff_form_fields();
    foreach ($defaults as $f) {
        $f['fieldid'] = 0;
        $f['pageid']  = 0;
        $f['active']  = 1;
        $by_key[$f['field_key']] = $f;
    }
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


/**
 * Parse a calendar date string to a UTC-midnight unix timestamp.
 * Accepts m/d/Y, n/j/Y, and Y-m-d. Avoids timezone day-shifts for DOB-style fields.
 *
 * @param string $str
 * @return int Unix timestamp (UTC midnight) or 0
 */

/**
 * Force a timestamp to UTC midnight on the same UTC calendar day.
 * Stabilizes legacy values that were stored at local midnights.
 *
 * @param int $ts
 * @return int
 */

/**
 * Format a date-only timestamp (or date string) as m/d/Y using the UTC calendar day.
 *
 * @param mixed $ts
 * @return string
 */

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
        $vals['dateofbirth'] = form_engine_format_date_only($vals['dateofbirth']);
    }
    if (!empty($vals['workerconsentdate'])) {
        $vals['workerconsentdate'] = form_engine_format_date_only($vals['workerconsentdate']);
    }
    // Normalize signature / checkbox consents: legacy "on" → "1"
    foreach (['workerconsentsig', 'parentalconsentsig'] as $sig_key) {
        if (array_key_exists($sig_key, $vals)) {
            $vals[$sig_key] = form_engine_normalize_checkbox_value($vals[$sig_key]);
        }
    }
    return $vals;
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

    $pages = form_engine_group_fields_by_section($fields);
    $page_count = count($pages);

    $parts = [];
    $parts[] = '<div id="staffapplication_form_div" class="' . ($viewonly ? 'staff-form-viewonly' : '') . '">';
    $parts[] = staff_form_application_styles();
    $parts[] = staff_form_application_header_html($vals, $viewonly);
    $parts[] = staff_form_application_form_open_html($vals, $viewonly);

    if ($page_count > 1 && !$viewonly) {
        $parts[] = form_engine_pager_html($pages);
    }

    foreach ($pages as $i => $page) {
        $parts[] = staff_form_application_page_html($page, $i, $vals, $viewonly);
    }

    $parts[] = staff_form_application_submit_nav_html($page_count, $viewonly);
    $parts[] = '</fieldset></form>';

    if (!$viewonly) {
        $parts[] = form_engine_rules_js($fields);
        if ($page_count > 1) {
            $parts[] = form_engine_multipage_js($page_count);
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


/**
 * Staff-specific field wrapper: parental consent block (#sub18).
 */
function staff_form_wrap_field_html($field, $html, &$ctx, $viewonly) {
    $key = $field['field_key'];
    if ($key === 'section_parental') {
        $par_style = $viewonly ? '' : ' style="display:none"';
        $ctx['inside_wrapper'] = 'parental';
        return '
                <div id="sub18" data-staff-section="section_parental"' . $par_style . '>
                    <h4 class="staff-subsection-heading">
                    ' . htmlspecialchars($field['label']) . '
                    </h4>';
    }
    if (!empty($ctx['inside_wrapper']) && $ctx['inside_wrapper'] === 'parental') {
        if ($key !== 'parentalconsent' && $key !== 'parentalconsentsig') {
            $ctx['inside_wrapper'] = null;
            return '</div>' . $html;
        }
    }
    return $html;
}

function staff_form_application_page_html(array $page, $index, array $vals, $viewonly) {
    return form_engine_page_html($page, $index, $vals, [
        'viewonly' => $viewonly,
        'wrap_field' => 'staff_form_wrap_field_html',
        'render_field' => function ($field, $value, $vo) {
            return form_engine_render_field($field, $value, ['viewonly' => $vo]);
        },
    ]);
}

function staff_form_application_submit_nav_html($page_count, $viewonly) {
    return form_engine_submit_nav_html($page_count, [
        'viewonly' => $viewonly,
        'submit_value' => 'Submit Application',
    ]);
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
                $raw = form_engine_parse_date_only($str);
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
        $params['dateofbirth'] = form_engine_parse_date_only($params['dateofbirth']);
    } else {
        $params['dateofbirth'] = 0;
    }
    if (!empty($params['workerconsentdate'])) {
        $params['workerconsentdate'] = form_engine_parse_date_only($params['workerconsentdate']);
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
        // Carry forward the latest non-zero background-check date so yearly archives
        // keep the last known check until a new one is recorded on the live row.
        $bg = (int)($staff['bgcheckpassdate'] ?? 0);
        if ($bg <= 0) {
            $prev = get_db_row(
                "SELECT bgcheckpassdate FROM events_staff_archive
                 WHERE staffid = ||staffid|| AND pageid = ||pageid|| AND bgcheckpassdate > 0
                 ORDER BY year DESC LIMIT 1",
                ['staffid' => $staffid, 'pageid' => $pageid]
            );
            if ($prev && (int)($prev['bgcheckpassdate'] ?? 0) > 0) {
                $bg = (int)$prev['bgcheckpassdate'];
                // Heal live row so future saves and UI see the carried value
                execute_db_sql(
                    "UPDATE events_staff SET bgcheckpassdate = ||bg|| WHERE staffid = ||staffid|| AND pageid = ||pageid||",
                    ['bg' => $bg, 'staffid' => $staffid, 'pageid' => $pageid]
                );
            }
        }
        $params['bgcheckpassdate'] = $bg;
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
