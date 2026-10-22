<?php
/***************************************************************************
 * formengine_ajax.php - Core form engine editor AJAX
 ***************************************************************************/
$sub = '';
while (!file_exists($sub . 'lib/header.php')) {
    $sub = $sub == '' ? '../' : $sub . '../';
}
include($sub . 'lib/header.php');

if (!defined('FORMENGINELIB')) {
    include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
}
if (!defined('FORMEDITORLIB')) {
    include_once($CFG->dirroot . '/lib/formengine/formeditorlib.php');
}

/** form_key from request, or first registered form, or empty. */
function form_engine_request_form_key() {
    form_engine_load_consumer_forms();
    $fk = clean_myvar_opt("form_key", "string", "");
    if ($fk !== "") {
        return $fk;
    }
    $reg = form_engine_registry();
    if ($reg) {
        return (string)array_key_first($reg);
    }
    return "";
}

function form_engine_save_field() {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    if (!defined('FORMEDITORLIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formeditorlib.php');
    }
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $form_key = form_engine_request_form_key();
    $fieldid = clean_myvar_opt("fieldid", "int", 0);
    $field_key = clean_myvar_req("field_key", "string");
    $label = clean_myvar_req("label", "string");
    $type = clean_myvar_req("type", "string");
    $section = clean_myvar_opt("section", "string", "");
    $place_after = clean_myvar_opt("place_after", "string", "end");
    $sortorder = form_editor_resolve_sortorder($form_key, $pageid, $fieldid, $place_after);
    $required = clean_myvar_opt("required", "int", 0);
    $active = clean_myvar_opt("active", "int", 1);
    $helptext = clean_myvar_opt("helptext", "string", "");
    $now = time();

    // JS attributes — read raw POST (clean_myvar "string" can strip JS punctuation)
    $extra_attrs = [];
    $fieldid_tmp = clean_myvar_opt("fieldid", "int", 0);
    if ($fieldid_tmp) {
        $existing = get_db_row("SELECT extra_attrs FROM form_fields WHERE fieldid=@@id@@", ['id' => $fieldid_tmp]);
        if ($existing && !empty($existing['extra_attrs'])) {
            $prev = is_string($existing['extra_attrs']) ? json_decode($existing['extra_attrs'], true) : $existing['extra_attrs'];
            if (is_array($prev)) {
                $extra_attrs = $prev;
            }
        }
    }
    // Always apply posted onblur/onchange (including empty to clear).
    // Check common request bags used by this CMS ajax layer.
    $onblur = null;
    $onchange = null;
    foreach ([$_POST, $_REQUEST, $_GET] as $bag) {
        if ($onblur === null && array_key_exists('attr_onblur', $bag)) {
            $onblur = (string)$bag['attr_onblur'];
        }
        if ($onchange === null && array_key_exists('attr_onchange', $bag)) {
            $onchange = (string)$bag['attr_onchange'];
        }
    }
    // Some ajax wrappers nest form fields under a parent key
    if ($onblur === null || $onchange === null) {
        foreach ([$_POST, $_REQUEST] as $bag) {
            foreach ($bag as $k => $v) {
                if (!is_array($v)) continue;
                if ($onblur === null && array_key_exists('attr_onblur', $v)) {
                    $onblur = (string)$v['attr_onblur'];
                }
                if ($onchange === null && array_key_exists('attr_onchange', $v)) {
                    $onchange = (string)$v['attr_onchange'];
                }
            }
        }
    }
    if ($onblur !== null) {
        $onblur = str_replace("\0", '', $onblur);
        if (strlen($onblur) > 4000) {
            $onblur = substr($onblur, 0, 4000);
        }
        if ($onblur === '') {
            unset($extra_attrs['onblur']);
        } else {
            $extra_attrs['onblur'] = $onblur;
        }
    }
    if ($onchange !== null) {
        $onchange = str_replace("\0", '', $onchange);
        if (strlen($onchange) > 4000) {
            $onchange = substr($onchange, 0, 4000);
        }
        if ($onchange === '') {
            unset($extra_attrs['onchange']);
        } else {
            $extra_attrs['onchange'] = $onchange;
        }
    }
    // Locked / read-only on the live form
    $locked = clean_myvar_opt("locked", "int", 0);
    if ($locked) {
        $extra_attrs['locked'] = 1;
    } else {
        unset($extra_attrs['locked']);
        // Do not leave stale disabled/readonly from older saves unless explicitly set elsewhere
        unset($extra_attrs['disabled'], $extra_attrs['readonly']);
    }
    // Disable pre-fill from saved application values
    $no_autofill = clean_myvar_opt("no_autofill", "int", 0);
    if ($no_autofill === '' && isset($_REQUEST['no_autofill'])) {
        $no_autofill = (int)$_REQUEST['no_autofill'];
    }
    $no_autofill = (int)$no_autofill;
    if ($no_autofill) {
        $extra_attrs['no_autofill'] = 1;
    } else {
        unset($extra_attrs['no_autofill']);
    }
    $extra_attrs_json = !empty($extra_attrs) ? json_encode($extra_attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

    // Visibility rule — multiple conditions from JSON
    $visibility = '';
    $vis_action = clean_myvar_opt("vis_action", "string", "");
    $vis_conds_raw = clean_myvar_opt("vis_conditions_json", "string", "[]");
    $vis_conds = json_decode($vis_conds_raw, true);
    if (!is_array($vis_conds)) {
        $vis_conds = [];
    }
    $vis_conds = array_values(array_filter($vis_conds, function ($c) {
        return is_array($c) && !empty($c['field']);
    }));
    if ($vis_action && !empty($vis_conds)) {
        $visibility = json_encode([
            'action' => $vis_action,
            'logic' => clean_myvar_opt("vis_logic", "string", "and"),
            'conditions' => $vis_conds,
        ]);
    }

    // Required-when — multiple conditions from JSON
    $required_when = '';
    $reqw_conds_raw = clean_myvar_opt("reqw_conditions_json", "string", "[]");
    $reqw_conds = json_decode($reqw_conds_raw, true);
    if (!is_array($reqw_conds)) {
        $reqw_conds = [];
    }
    $reqw_conds = array_values(array_filter($reqw_conds, function ($c) {
        return is_array($c) && !empty($c['field']);
    }));
    if (clean_myvar_opt("reqw_enable", "int", 0) && !empty($reqw_conds)) {
        $required_when = json_encode([
            'logic' => clean_myvar_opt("reqw_logic", "string", "and"),
            'conditions' => $reqw_conds,
        ]);
    }

    // Select options
    $options_json = '';
    $opts_raw = clean_myvar_opt("options_json", "string", "[]");
    if (isset($_REQUEST['options_json'])) {
        $opts_raw = (string)$_REQUEST['options_json'];
    }
    $opts = json_decode($opts_raw, true);
    if (!is_array($opts)) {
        $opts = [];
    }
    $opts = array_values(array_filter($opts, function ($o) {
        return is_array($o) && (isset($o['value']) || isset($o['label']));
    }));
    if ($type === 'select' && !empty($opts)) {
        $options_json = json_encode($opts, JSON_UNESCAPED_UNICODE);
    } elseif ($type === 'file_viewer') {
        $vurl = clean_myvar_opt('viewer_url', 'string', '');
        if (isset($_REQUEST['viewer_url'])) {
            $vurl = (string)$_REQUEST['viewer_url'];
        }
        $vheight = clean_myvar_opt('viewer_height', 'int', 480);
        $vconfirm = clean_myvar_opt('viewer_confirm', 'int', 1);
        $vconfirm_text = clean_myvar_opt('viewer_confirm_text', 'string', '');
        if (isset($_REQUEST['viewer_confirm_text'])) {
            $vconfirm_text = (string)$_REQUEST['viewer_confirm_text'];
        }
        $options_json = json_encode([
            'url' => $vurl,
            'height' => $vheight,
            'confirm' => $vconfirm ? 1 : 0,
            'confirm_text' => $vconfirm_text,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($type !== 'select') {
        $options_json = '';
    } else {
        $options_json = '[]';
    }

    // Validation JSON: preserve existing + min/max + number subtype
    $validation = [];
    $fieldid_for_val = clean_myvar_opt("fieldid", "int", 0);
    if ($fieldid_for_val) {
        $existing_val = get_db_row("SELECT validation FROM form_fields WHERE fieldid=@@id@@", ['id' => $fieldid_for_val]);
        if ($existing_val && !empty($existing_val['validation'])) {
            $prev_v = is_string($existing_val['validation'])
                ? json_decode($existing_val['validation'], true)
                : $existing_val['validation'];
            if (is_array($prev_v)) {
                $validation = $prev_v;
            }
        }
    }
    // Keep required flag in sync with the Required select
    if ($required) {
        $validation['required'] = true;
    } else {
        unset($validation['required']);
    }
    if ($type === 'date') {
        $validation['date'] = true;
    } elseif ($type === 'phone') {
        $validation['phone'] = true;
    } else {
        // do not force-remove date/phone if type changed; clean type-specific keys below
        if ($type !== 'date') {
            unset($validation['date']);
        }
        if ($type !== 'phone') {
            unset($validation['phone']);
        }
    }

    // Number subtype + numeric min/max
    if ($type === 'number') {
        $subtype = clean_myvar_opt('number_subtype', 'string', 'decimal');
        if (!in_array($subtype, ['integer', 'decimal', 'money'], true)) {
            $subtype = 'decimal';
        }
        $validation['number_subtype'] = $subtype;
        $vmin = clean_myvar_opt('val_min', 'string', '');
        $vmax = clean_myvar_opt('val_max', 'string', '');
        if (isset($_REQUEST['val_min'])) {
            $vmin = trim((string)$_REQUEST['val_min']);
        }
        if (isset($_REQUEST['val_max'])) {
            $vmax = trim((string)$_REQUEST['val_max']);
        }
        if ($vmin !== '' && is_numeric($vmin)) {
            $validation['min'] = $subtype === 'integer' ? (int)$vmin : (float)$vmin;
        } else {
            unset($validation['min']);
        }
        if ($vmax !== '' && is_numeric($vmax)) {
            $validation['max'] = $subtype === 'integer' ? (int)$vmax : (float)$vmax;
        } else {
            unset($validation['max']);
        }
    } elseif ($type === 'date') {
        unset($validation['number_subtype']);
        // Date min/max: mode current | static + optional static value
        $min_mode = clean_myvar_opt('date_min_mode', 'string', '');
        $max_mode = clean_myvar_opt('date_max_mode', 'string', '');
        $min_static = clean_myvar_opt('date_min_static', 'string', '');
        $max_static = clean_myvar_opt('date_max_static', 'string', '');
        if (isset($_REQUEST['date_min_static'])) {
            $min_static = trim((string)$_REQUEST['date_min_static']);
        }
        if (isset($_REQUEST['date_max_static'])) {
            $max_static = trim((string)$_REQUEST['date_max_static']);
        }
        if ($min_mode === 'current') {
            $validation['min'] = 'current';
        } elseif ($min_mode === 'static' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $min_static)) {
            $validation['min'] = $min_static;
        } else {
            unset($validation['min']);
        }
        if ($max_mode === 'current') {
            $validation['max'] = 'current';
        } elseif ($max_mode === 'static' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $max_static)) {
            $validation['max'] = $max_static;
        } else {
            unset($validation['max']);
        }
        // Relative to another date field
        $rel_field = clean_myvar_opt('date_rel_field', 'string', '');
        $rel_op = clean_myvar_opt('date_rel_op', 'string', '');
        if (isset($_REQUEST['date_rel_field'])) {
            $rel_field = trim((string)$_REQUEST['date_rel_field']);
        }
        if (isset($_REQUEST['date_rel_op'])) {
            $rel_op = trim((string)$_REQUEST['date_rel_op']);
        }
        $allowed_ops = ['before', 'before_or_equal', 'after', 'after_or_equal', 'same'];
        $rel_field = preg_replace('/[^a-zA-Z0-9_]/', '', $rel_field);
        if ($rel_field !== '' && in_array($rel_op, $allowed_ops, true)) {
            $validation['relative'] = [
                'field' => $rel_field,
                'op' => $rel_op,
            ];
        } else {
            unset($validation['relative']);
        }
    } else {
        unset($validation['number_subtype'], $validation['min'], $validation['max'], $validation['relative']);
    }

    $validation_json = !empty($validation)
        ? json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '';

    form_engine_ensure_schema();

    $params = compact('field_key', 'label', 'type', 'section', 'sortorder', 'required', 'active', 'helptext', 'visibility', 'required_when', 'now', 'pageid');
    $params['extra_attrs'] = $extra_attrs_json;
    $params['options'] = $options_json;
    $params['validation'] = $validation_json;

    if ($fieldid) {
        $params['fieldid'] = $fieldid;
        // Match by fieldid only — seeded defaults use pageid=0, not the current page
        execute_db_sql(
            "UPDATE form_fields SET
                field_key=@@field_key@@, label=@@label@@, type=@@type@@, section=@@section@@,
                sortorder=@@sortorder@@, required=@@required@@, active=@@active@@, helptext=@@helptext@@,
                visibility=@@visibility@@, required_when=@@required_when@@, extra_attrs=@@extra_attrs@@,
                options=@@options@@, validation=@@validation@@, modified=@@now@@
             WHERE fieldid=@@fieldid@@",
            $params
        );
    } else {
        // Prefer global (pageid=0) uniqueness for field_key
        $form_key = form_engine_request_form_key();
        $exists = get_db_row(
            "SELECT fieldid FROM form_fields
             WHERE form_key=@@fk@@ AND field_key=@@key@@ AND (pageid=0 OR pageid=@@pageid@@) LIMIT 1",
            ['pageid' => $pageid, 'key' => $field_key, 'fk' => $form_key]
        );
        if ($exists) {
            ajax_return(form_editor_ui($form_key, $pageid), "Field key already exists");
            return;
        }
        // New custom fields still attach to current pageid; core defaults stay on 0
        $params['pageid'] = 0;
        $params['form_key'] = $form_key;
        execute_db_sql(
            "INSERT INTO form_fields
                (form_key, pageid, field_key, label, type, section, sortorder, required, active, helptext, visibility, required_when, extra_attrs, options, validation, is_system, created, modified)
             VALUES
                (@@form_key@@, @@pageid@@, @@field_key@@, @@label@@, @@type@@, @@section@@, @@sortorder@@, @@required@@, @@active@@, @@helptext@@, @@visibility@@, @@required_when@@, @@extra_attrs@@, @@options@@, @@validation@@, 0, @@now@@, @@now@@)",
            $params
        );
    }
    ajax_return(form_editor_ui($form_key, $pageid));
}


function form_engine_delete_field() {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    if (!defined('FORMEDITORLIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formeditorlib.php');
    }
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $form_key = form_engine_request_form_key();
    $fieldid = clean_myvar_req("fieldid", "int");
    $row = get_db_row("SELECT * FROM form_fields WHERE fieldid=@@id@@", ['id' => $fieldid]);
    if ($row) {
        if (function_exists('form_engine_unbind_field')) {
            form_engine_unbind_field($form_key, $row['field_key'] ?? '', $pageid);
        }
        execute_db_sql("DELETE FROM form_fields WHERE fieldid=@@id@@", ['id' => $fieldid]);
    }
    ajax_return(form_editor_ui($form_key, $pageid));
}


function form_engine_migrate() {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $form_key = form_engine_request_form_key();
    // Load feature runtime if the form def registers migration_run helpers
    form_engine_load_form_runtime($form_key);
    $def = form_engine_get_form_def($form_key);
    $result = form_engine_run_migration($form_key, $pageid);
    $ok = !empty($result['ok']) || !isset($result['ok']);
    $msg = $result['message'] ?? ($ok ? 'Migration complete.' : 'Migration failed.');
    $color = $ok ? '#166534' : '#b91c1c';
    $html = '<span style="color:' . $color . '">' . htmlspecialchars($msg) . '</span>';
    // Optional post-migrate UI from form def
    $post = $def['migration_result_html'] ?? null;
    if ($post && is_string($post) && function_exists($post)) {
        $html .= call_user_func($post, $form_key, $result);
    }
    ajax_return($html);
}


function form_engine_drop_deprecated() {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    $form_key = form_engine_request_form_key();
    form_engine_load_form_runtime($form_key);
    if (function_exists('form_engine_load_form_migration')) {
        form_engine_load_form_migration($form_key);
    }
    $def = form_engine_get_form_def($form_key);
    $fn = $def['drop_deprecated'] ?? null;
    $result = ['ok' => false, 'message' => 'No drop-deprecated handler for this form.'];
    if ($fn && is_string($fn) && function_exists($fn)) {
        $result = call_user_func($fn);
    } elseif ($fn && is_callable($fn)) {
        $result = call_user_func($fn);
    }
    $color = !empty($result['ok']) ? '#166534' : '#b91c1c';
    $html = '<span style="color:' . $color . '">' . htmlspecialchars($result['message'] ?? '') . '</span>';
    $post = $def['migration_result_html'] ?? null;
    if ($post && is_string($post) && function_exists($post)) {
        $html .= call_user_func($post, $form_key, $result);
    }
    ajax_return($html);
}


function form_engine_save_hooks() {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    $form_key = form_engine_request_form_key();
    $raw = clean_myvar_opt("bindings_json", "string", "[]");
    if (($raw === '' || $raw === '[]') && isset($_REQUEST['bindings_json'])) {
        $raw = (string)$_REQUEST['bindings_json'];
    }
    $bindings = json_decode($raw, true);
    if (!is_array($bindings)) {
        $bindings = [];
    }
    $n = 0;
    foreach ($bindings as $b) {
        if (!is_array($b) || empty($b['hook_id'])) {
            continue;
        }
        // Expected: { hook_id, bindings: { slotName: [form_field_key, ...] } }
        $slot_bindings = [];
        if (!empty($b['bindings']) && is_array($b['bindings'])) {
            foreach ($b['bindings'] as $slot => $keys) {
                $slot_bindings[(string)$slot] = array_values(array_filter(array_map('strval', (array)$keys)));
            }
        } elseif (!empty($b['field_keys']) && is_array($b['field_keys'])) {
            $slot_bindings['value'] = array_values(array_filter(array_map('strval', $b['field_keys'])));
        } elseif (isset($b['field_key']) && $b['field_key'] !== '') {
            $slot = !empty($b['slot']) ? (string)$b['slot'] : (string)$b['field_key'];
            $slot_bindings[$slot] = [(string)$b['field_key']];
        }
        $all_keys = [];
        foreach ($slot_bindings as $list) {
            foreach ($list as $k) {
                $all_keys[] = $k;
            }
        }
        $fk = $all_keys ? $all_keys[0] : '';
        $meta = ['bindings' => $slot_bindings];
        form_engine_save_hook_binding(
            $form_key,
            0,
            (string)$b['hook_id'],
            $fk,
            null,
            $meta
        );
        $n++;
    }
    ajax_return('<span style="color:#166534">Saved ' . (int)$n . ' hook binding(s).</span>');
}


function form_engine_reorder() {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    if (!defined('FORMEDITORLIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formeditorlib.php');
    }
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $form_key = form_engine_request_form_key();
    $order = clean_myvar_opt("order", "string", "");
    if ($order === '' && isset($_REQUEST['order'])) {
        $order = (string)$_REQUEST['order'];
    }
    if ($order) {
        $ids = array_filter(array_map('intval', explode(',', $order)));
        $sort = 10;
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            execute_db_sql(
                "UPDATE form_fields SET sortorder=@@s@@, modified=@@m@@ WHERE fieldid=@@id@@",
                ['s' => $sort, 'id' => $id, 'm' => time()]
            );
            $sort += 10;
        }
    }
    ajax_return(form_editor_ui($form_key, $pageid));
}


// Dispatch
$action = clean_myvar_opt('action', 'string', '');
if ($action && function_exists($action)) {
    $action();
} else {
    if (function_exists('ajax_return')) {
        ajax_return('', 'Unknown form engine action: ' . $action);
    } else {
        echo json_encode(['ajaxerror' => 'Unknown action']);
    }
}
