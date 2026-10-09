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

function form_engine_save_field() {
    global $CFG;
    if (!defined('STAFFFORMLIB')) {
        include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
    }
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $form_key = clean_myvar_opt("form_key", "string", defined("FORM_KEY_STAFF_APP") ? FORM_KEY_STAFF_APP : "staff_app");
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
        $existing = get_db_row("SELECT extra_attrs FROM form_fields WHERE fieldid=||id||", ['id' => $fieldid_tmp]);
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
        $options_json = json_encode([
            'url' => $vurl,
            'height' => $vheight,
            'confirm' => $vconfirm ? 1 : 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($type !== 'select') {
        $options_json = '';
    } else {
        $options_json = '[]';
    }

    ensure_staff_form_tables();

    $params = compact('field_key', 'label', 'type', 'section', 'sortorder', 'required', 'active', 'helptext', 'visibility', 'required_when', 'now', 'pageid');
    $params['extra_attrs'] = $extra_attrs_json;
    $params['options'] = $options_json;

    if ($fieldid) {
        $params['fieldid'] = $fieldid;
        // Match by fieldid only — seeded defaults use pageid=0, not the current page
        execute_db_sql(
            "UPDATE form_fields SET
                field_key=||field_key||, label=||label||, type=||type||, section=||section||,
                sortorder=||sortorder||, required=||required||, active=||active||, helptext=||helptext||,
                visibility=||visibility||, required_when=||required_when||, extra_attrs=||extra_attrs||,
                options=||options||, modified=||now||
             WHERE fieldid=||fieldid||",
            $params
        );
    } else {
        // Prefer global (pageid=0) uniqueness for field_key
        $form_key = defined('FORM_KEY_STAFF_APP') ? FORM_KEY_STAFF_APP : 'staff_app';
        $exists = get_db_row(
            "SELECT fieldid FROM form_fields
             WHERE form_key=||fk|| AND field_key=||key|| AND (pageid=0 OR pageid=||pageid||) LIMIT 1",
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
                (form_key, pageid, field_key, label, type, section, sortorder, required, active, helptext, visibility, required_when, extra_attrs, options, is_system, created, modified)
             VALUES
                (||form_key||, ||pageid||, ||field_key||, ||label||, ||type||, ||section||, ||sortorder||, ||required||, ||active||, ||helptext||, ||visibility||, ||required_when||, ||extra_attrs||, ||options||, 0, ||now||, ||now||)",
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
    $form_key = clean_myvar_opt("form_key", "string", defined("FORM_KEY_STAFF_APP") ? FORM_KEY_STAFF_APP : "staff_app");
    $fieldid = clean_myvar_req("fieldid", "int");
    $row = get_db_row("SELECT * FROM form_fields WHERE fieldid=||id||", ['id' => $fieldid]);
    if ($row) {
        // Unbind any process hooks pointing at this field, then delete
        if (function_exists('form_engine_unbind_field')) {
            form_engine_unbind_field($form_key, $row['field_key'] ?? '', $pageid);
        }
        execute_db_sql("DELETE FROM form_fields WHERE fieldid=||id||", ['id' => $fieldid]);
    }
    ajax_return(form_editor_ui($form_key, $pageid));
}


function form_engine_migrate() {
    global $CFG;
    if (!defined('STAFFFORMLIB')) {
        include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
    }
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/features/events/staffform/formenginelib.php');
    }
    $pageid = clean_myvar_opt("pageid", "int", null);
    // Named form migration plan for staff_app (seeds hooks + form_data backfill)
    $result = function_exists('form_engine_run_migration')
        ? form_engine_run_migration($form_key, $pageid)
        : migrate_staff_form_data($pageid);
    $msg = "Migration complete: seeded {$result['seeded_fields']} fields, updated {$result['staff_updated']} staff rows, {$result['archive_updated']} archive rows.";
    $still = (int)($result['staff_still_empty'] ?? 0) + (int)($result['archive_still_empty'] ?? 0);
    if ($still > 0) {
        $msg .= " WARNING: {$still} row(s) still have empty form_data.";
        $html = '<span style="color:#b91c1c">' . htmlspecialchars($msg) . '</span>';
        ajax_return($html);
        return;
    }

    $status = staff_form_migration_status();
    if (!empty($status['complete'])) {
        // Fully done (no deprecated columns either) — hide tooling
        $html = '<span style="color:#166534">' . htmlspecialchars($msg) . ' Migration tools are no longer needed.</span>'
              . '<script>var t=document.getElementById("staff_form_migration_tools"); if(t){ setTimeout(function(){ t.style.display="none"; }, 2500); }</script>';
    } else {
        $html = '<span style="color:#166534">' . htmlspecialchars($msg) . '</span> '
              . '<button type="button" id="staff_form_drop_cols_btn" class="btn-secondary" style="margin-left:10px" '
              . 'onclick="staffFormDropDeprecated()">Remove deprecated DB columns</button>';
    }
    ajax_return($html);
}


function form_engine_drop_deprecated() {
    global $CFG;
    if (!defined('STAFFFORMLIB')) {
        include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
    }
    $result = drop_deprecated_staff_columns();
    $color = !empty($result['ok']) ? '#166534' : '#b91c1c';
    $html = '<span style="color:' . $color . '">' . htmlspecialchars($result['message']) . '</span>';
    if (!empty($result['ok'])) {
        $status = staff_form_migration_status();
        if (!empty($status['complete'])) {
            $html .= '<script>var t=document.getElementById("staff_form_migration_tools"); if(t){ setTimeout(function(){ t.style.display="none"; }, 2000); }</script>';
        }
    }
    ajax_return($html);
}


function form_engine_save_hooks() {
    global $CFG;
    if (!defined('STAFFFORMLIB')) {
        include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
    }
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/features/events/staffform/formenginelib.php');
    }
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $form_key = clean_myvar_opt("form_key", "string", defined("FORM_KEY_STAFF_APP") ? FORM_KEY_STAFF_APP : "staff_app");
    $raw = clean_myvar_opt("bindings_json", "string", "[]");
    if (($raw === '' || $raw === '[]') && isset($_REQUEST['bindings_json'])) {
        $raw = (string)$_REQUEST['bindings_json'];
    }
    $bindings = json_decode($raw, true);
    if (!is_array($bindings)) {
        $bindings = [];
    }
    $form_key = FORM_KEY_STAFF_APP;
    $n = 0;
    foreach ($bindings as $b) {
        if (!is_array($b) || empty($b['hook_id'])) {
            continue;
        }
        // Only single-field bindings are editable here (computed/multi left as seeded)
        form_engine_save_hook_binding(
            $form_key,
            0, // global bindings for staff_app
            (string)$b['hook_id'],
            isset($b['field_key']) ? (string)$b['field_key'] : null,
            null
        );
        $n++;
    }
    ajax_return('<span style="color:#166534">Saved ' . (int)$n . ' hook binding(s).</span>');
}


function form_engine_reorder() {
    global $CFG;
    if (!defined('STAFFFORMLIB')) {
        include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
    }
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $form_key = clean_myvar_opt("form_key", "string", defined("FORM_KEY_STAFF_APP") ? FORM_KEY_STAFF_APP : "staff_app");
    // order is expected as comma-separated fieldids
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
            // Match by fieldid only (defaults live on pageid=0)
            execute_db_sql(
                "UPDATE form_fields SET sortorder=||s||, modified=||m|| WHERE fieldid=||id||",
                ['s' => $sort, 'id' => $id, 'm' => time()]
            );
            $sort += 10;
        }
    }
    ajax_return(form_editor_ui($form_key, $pageid));
}


?>

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
