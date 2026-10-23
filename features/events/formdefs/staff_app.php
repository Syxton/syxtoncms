<?php
/***************************************************************************
 * formdefs/staff_app.php - Staff Application form definition
 * -------------------------------------------------------------------------
 * Form-engine registration only: hooks, resolvers, seed/migrate callbacks.
 * Runtime storage/save/status lives in features/events/staffform/.
 ***************************************************************************/

if (!defined('FORMENGINELIB')) {
    global $CFG;
    include_once(($CFG->dirroot ?? dirname(__DIR__, 3)) . '/lib/formengine/formenginelib.php');
}

function staff_app_hook_defs() {
    return [
        // fields: [ [slot_name, type, amount], ... ]
        // slot_name is the process-facing name (value available as that key; multi → slot_1, slot_2, …)
        // amount 1 = one form field; 0 = unlimited form fields of that type
        [
            'hook_id' => 'identity.name',
            'label' => 'Full name',
            'required' => 1,
            'fields' => [['name', 'text', 1]],
            'help' => 'Used in emails, manager lists, and export.',
        ],
        [
            'hook_id' => 'identity.phone',
            'label' => 'Phone',
            'required' => 1,
            'fields' => [['phone', 'phone', 1]],
            'help' => 'Contact phone on the application record.',
        ],
        [
            'hook_id' => 'identity.date_of_birth',
            'label' => 'Date of birth',
            'required' => 1,
            'fields' => [['dateofbirth', 'date', 1]],
            'help' => 'Drives under-18 / parental consent and background-check eligibility.',
        ],
        [
            'hook_id' => 'consent.worker_name',
            'label' => 'Worker consent name',
            'required' => 1,
            'fields' => [['workerconsent', 'text', 1]],
            'help' => 'Printed name on worker consent.',
        ],
        [
            'hook_id' => 'consent.worker_date',
            'label' => 'Worker consent date',
            'required' => 1,
            'fields' => [['workerconsentdate', 'date', 1]],
            'help' => 'Determines whether the application is out of date.',
        ],
        [
            'hook_id' => 'consent.worker_sig',
            'label' => 'Worker signature',
            'required' => 1,
            'fields' => [['workerconsentsig', 'checkbox', 1]],
            'help' => 'Checkbox signature for worker consent.',
        ],
        [
            'hook_id' => 'consent.parental_name',
            'label' => 'Parental consent name',
            'required' => 0,
            'fields' => [['parentalconsent', 'text', 1]],
            'help' => 'Required when applicant is under 18.',
        ],
        [
            'hook_id' => 'consent.parental_sig',
            'label' => 'Parental signature',
            'required' => 0,
            'fields' => [['parentalconsentsig', 'checkbox', 1]],
            'help' => 'Required when applicant is under 18.',
        ],
        [
            'hook_id' => 'meta.under_18',
            'label' => 'Under 18 (computed)',
            'required' => 0,
            'bindable' => true,
            'fields' => [['dob', 'date', 1]],
            'default_bindings' => ['dob' => ['dateofbirth']],
            'resolve' => 'staff_app_resolve_under_18',
            'help' => 'Computed from the bound date-of-birth field (slot "dob").',
        ],
        [
            'hook_id' => 'screening.flagged',
            'label' => 'Screening flagged',
            'required' => 0,
            'bindable' => true,
            'fields' => [['risk', 'yesno', 0]],
            'default_bindings' => [
                'risk' => ['q1_1', 'q1_2', 'q1_3', 'q2_1', 'q2_2'],
            ],
            'resolve' => 'staff_app_resolve_flagged',
            'help' => 'True if any bound risk answer is truthy. Bind any number of yes/no fields to slot "risk".',
        ],
    ];
}

/**
 * Computed: under 18 from identity.date_of_birth (or dateofbirth fallback).
 *
 * @param array $values Flat field_key => value map
 * @param array $hook   Hook definition
 * @param string $form_key
 * @return string '1' or '0'
 */
function staff_app_resolve_under_18($values, $hook = [], $form_key = 'staff_app') {
    $dob = null;
    // Prefer this hook's bound "dob" slot (or any bound keys on $hook)
    if ($hook && function_exists('form_engine_hook_field_keys')) {
        foreach (form_engine_hook_field_keys($hook) as $fk) {
            if ($fk !== '' && array_key_exists($fk, $values) && $values[$fk] !== '' && $values[$fk] !== null) {
                $dob = $values[$fk];
                break;
            }
        }
    }
    // Fall back to identity.date_of_birth binding
    if ($dob === null || $dob === '') {
        $hooks = function_exists('form_engine_get_hooks') ? form_engine_get_hooks($form_key, 0) : [];
        foreach ($hooks as $h) {
            if (($h['hook_id'] ?? '') !== 'identity.date_of_birth') {
                continue;
            }
            if (!function_exists('form_engine_hook_field_keys')) {
                break;
            }
            foreach (form_engine_hook_field_keys($h) as $fk) {
                if ($fk !== '' && array_key_exists($fk, $values) && $values[$fk] !== '' && $values[$fk] !== null) {
                    $dob = $values[$fk];
                    break 2;
                }
            }
        }
    }
    if ($dob === null || $dob === '') {
        $dob = $values['dateofbirth'] ?? null;
    }
    return staff_app_is_under_18($dob) ? '1' : '0';
}

/**
 * Computed: screening flagged from risk question field keys.
 *
 * @param array $values
 * @param array $hook
 * @param string $form_key
 * @return string '1' or '0'
 */
function staff_app_resolve_flagged($values, $hook = [], $form_key = 'staff_app') {
    $keys = function_exists('form_engine_hook_field_keys')
        ? form_engine_hook_field_keys($hook)
        : [];
    if (!$keys) {
        $keys = ['q1_1', 'q1_2', 'q1_3', 'q2_1', 'q2_2'];
    }
    foreach ($keys as $fk) {
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

/**
 * Whether DOB indicates under 18 (unix timestamp or date string).
 */
function staff_app_is_under_18($dob) {
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
    $eighteen = 18 * 365.25 * 24 * 60 * 60;
    return (time() - $ts) < $eighteen;
}

/**
 * Screening flagged? Uses computed hook screening.flagged.
 */
function staff_app_is_flagged($values, $form_key = 'staff_app') {
    if (function_exists('form_hook_value')) {
        $v = form_hook_value($form_key, 'screening.flagged', $values);
        return $v === '1' || $v === 1 || $v === true;
    }
    return staff_app_resolve_flagged($values) === '1';
}

/**
 * Migration plan from original static staff columns → form_data + hooks.
 */
function staff_app_migration_plan() {
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

// Register with core form engine
if (function_exists('form_engine_register_form')) {
    form_engine_register_form('staff_app', [
        'label' => 'Staff Application',
        'description' => 'Staff work application with consent, screening, and background-check process hooks.',
        'storage' => [
            'json_column' => 'form_data',
            // Promoted identity/consent columns (also is_system on field defs)
            'columns' => [
                'name', 'phone', 'dateofbirth',
                'workerconsent', 'workerconsentdate', 'workerconsentsig',
                'parentalconsent', 'parentalconsentsig',
            ],
        ],
        'hooks' => staff_app_hook_defs(),
        'migration' => staff_app_migration_plan(),
        'migration_run' => 'migrate_staff_form_data',
        'sync_field_defs_fn' => 'seed_staff_form_fields',
        'prepare_db_array_fn' => 'get_staff_application_values',
        'prepare_display_values_fn' => 'staff_form_prepare_application_values',
        'finalize_field_list_fn' => 'staff_form_fields_consent_last',
        'migration_tools_html' => 'staff_form_migration_tools_html',
        'drop_deprecated' => 'drop_deprecated_staff_columns',
        'runtime_lib' => '/features/events/staffform/staffformlib.php',
        'migration_lib' => '/features/events/staffform/staffform_migration.php',
        'meet_db_requirement_fn' => 'ensure_staff_form_tables',
        'get_field_defaults_fn' => 'get_default_staff_form_fields',
        'wrap_field' => 'staff_form_wrap_field_html',
        'save' => 'staff_app_save_application',
        'client' => [
            'form_selector' => '#staffapplication_form',
            'apply_rules_global' => 'staffFormApplyRules',
            'extra_targets' => ['section_parental' => '#sub18'],
            'preserve_keys_on_hide' => ['agerange', 'dateofbirth'],
        ],
        'render_layout' => [
            'wrapper_id' => 'staffapplication_form_div',
            'wrapper_class_viewonly' => 'staff-form-viewonly',
            'styles' => 'staff_form_application_styles',
            'header' => 'staff_form_application_header_html',
            'form_open' => 'staff_form_application_form_open_html',
            'keepalive' => true,
            'submit_value' => 'Submit Application',
        ],
    ]);
}
