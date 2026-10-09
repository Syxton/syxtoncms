<?php
/***************************************************************************
 * formeditorlib.php - Core Form Editor UI (named forms)
 * -------------------------------------------------------------------------
 * Lives under lib/formengine. Independent of Events.
 * Consumers pass form_key (e.g. staff_app).
 ***************************************************************************/
if (!defined('FORMEDITORLIB')) {
    define('FORMEDITORLIB', true);
}
if (!defined('FORMENGINELIB')) {
    global $CFG;
    include_once(($CFG->dirroot ?? dirname(__DIR__, 2)) . '/lib/formengine/formenginelib.php');
}


/**
 * Full editor page: ajax registrations + UI for a form_key.
 * Used by Admin Panel Form Editor and any feature entry points.
 */
function form_editor_page($form_key, $pageid, $show_form_picker = true) {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    form_engine_ensure_schema();
    form_engine_seed_fields($form_key, 0);
    form_engine_seed_hooks($form_key, 0);

    if ($show_form_picker) {
        $reg = form_engine_registry();
        $options = '';
        foreach ($reg as $key => $def) {
            $sel = ($key === $form_key) ? ' selected' : '';
            $options .= '<option value="' . htmlspecialchars($key) . '"' . $sel . '>'
                . htmlspecialchars($def['label'] ?? $key) . '</option>';
        }
        echo '<div style="padding:12px 16px;border-bottom:1px solid #e2e8f0;margin-bottom:8px">';
        echo '<label style="font-weight:600;margin-right:8px">Form</label>';
        echo '<select id="core_form_key" onchange="var u=new URL(location.href);u.searchParams.set(\'form_key\',this.value);location.href=u.toString()">';
        echo $options ?: '<option value="staff_app">Staff Application</option>';
        echo '</select>';
        echo '<span style="margin-left:12px;color:#64748b;font-size:.9em">Form Editor</span>';
        echo '</div>';
    }

    $ajax_url = '/ajax/formengine_ajax.php';
    $common = ['pageid' => $pageid, 'form_key' => $form_key];
    ajaxapi([
        'id' => 'form_engine_save_field',
        'url' => $ajax_url,
        'data' => array_merge($common, [
            'action' => 'form_engine_save_field',
            'attr_onblur' => "js||$('#edit_attr_onblur').val()||js",
            'attr_onchange' => "js||$('#edit_attr_onchange').val()||js",
            'vis_conditions_json' => "js||$('#edit_vis_conditions_json').val()||js",
            'reqw_conditions_json' => "js||$('#edit_reqw_conditions_json').val()||js",
            'options_json' => "js||$('#edit_options_json').val()||js",
            'viewer_url' => "js||$('#edit_viewer_url').val()||js",
            'viewer_height' => "js||$('#edit_viewer_height').val()||js",
            'viewer_confirm' => "js||$('#edit_viewer_confirm').val()||js",
        ]),
        'reqstring' => 'staff_field_form',
        'display' => 'staff_form_editor_container',
        'event' => 'none',
    ]);
    ajaxapi([
        'id' => 'form_engine_delete_field',
        'url' => $ajax_url,
        'data' => array_merge($common, [
            'action' => 'form_engine_delete_field',
            'fieldid' => 'js||fieldid||js',
        ]),
        'display' => 'staff_form_editor_container',
        'event' => 'none',
    ]);
    ajaxapi([
        'id' => 'form_engine_migrate',
        'url' => $ajax_url,
        'data' => array_merge($common, ['action' => 'form_engine_migrate']),
        'display' => 'form_engine_migrate_result',
        'loading' => 'loading_overlay',
        'event' => 'none',
    ]);
    ajaxapi([
        'id' => 'form_engine_drop_deprecated',
        'url' => $ajax_url,
        'data' => array_merge($common, ['action' => 'form_engine_drop_deprecated']),
        'display' => 'form_engine_migrate_result',
        'loading' => 'loading_overlay',
        'event' => 'none',
    ]);
    ajaxapi([
        'id' => 'form_engine_reorder',
        'url' => $ajax_url,
        'data' => array_merge($common, [
            'action' => 'form_engine_reorder',
            'order' => 'js||order||js',
        ]),
        'display' => 'staff_form_editor_container',
        'event' => 'none',
    ]);
    ajaxapi([
        'id' => 'form_engine_save_hooks',
        'url' => $ajax_url,
        'data' => array_merge($common, [
            'action' => 'form_engine_save_hooks',
            'bindings_json' => 'js||window.__sfeHooksJson||js',
        ]),
        'display' => 'sfe-hooks-result',
        'event' => 'none',
    ]);

    echo form_editor_ui($form_key, $pageid);
}


function form_editor_hooks_panel_html($form_key, $pageid) {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/features/events/staffform/formenginelib.php');
    }
    $hooks = form_engine_get_hooks($form_key, $pageid);
    $fields = form_engine_get_fields($form_key, $pageid, true);
    $field_opts = '<option value="">— unbound —</option>';
    foreach ($fields as $f) {
        if (($f['type'] ?? '') === 'section') {
            continue;
        }
        $field_opts .= '<option value="' . htmlspecialchars($f['field_key']) . '">'
            . htmlspecialchars(($f['label'] ?? '') . ' (' . $f['field_key'] . ')') . '</option>';
    }

    $rows = '';
    foreach ($hooks as $h) {
        $hid = htmlspecialchars($h['hook_id']);
        $req = !empty($h['required'])
            ? '<span style="background:#fee2e2;color:#991b1b;padding:1px 6px;border-radius:4px;font-size:.75em">required</span>'
            : '<span style="background:#e2e8f0;color:#475569;padding:1px 6px;border-radius:4px;font-size:.75em">optional</span>';
        $meta = $h['meta'] ?? [];
        $extra = '';
        if (!empty($meta['field_keys']) && is_array($meta['field_keys'])) {
            $extra = '<div class="sfe-hint">Sources: <code>' . htmlspecialchars(implode(', ', $meta['field_keys'])) . '</code></div>';
            $sel = '<em style="color:#64748b">multi-field (see sources)</em>';
        } elseif (!empty($meta['computed'])) {
            $extra = '<div class="sfe-hint">Computed: <code>' . htmlspecialchars($meta['computed']) . '</code></div>';
            $sel = '<em style="color:#64748b">computed</em>';
        } else {
            $cur = (string)($h['field_key'] ?? '');
            $opts = $field_opts;
            if ($cur !== '') {
                $opts = str_replace(
                    'value="' . htmlspecialchars($cur) . '"',
                    'value="' . htmlspecialchars($cur) . '" selected',
                    $opts
                );
            }
            $sel = '<select class="sfe-hook-field" data-hook-id="' . $hid . '">' . $opts . '</select>';
        }
        $rows .= '<tr>
            <td><code>' . $hid . '</code><div class="sfe-hint">' . htmlspecialchars($h['help'] ?? '') . '</div>' . $extra . '</td>
            <td>' . htmlspecialchars($h['label'] ?? $h['hook_id']) . ' ' . $req . '</td>
            <td>' . $sel . '</td>
        </tr>';
    }

    return '
    <details class="sfe-collapse has-content" style="margin:12px 0" open>
        <summary>Process hooks (backend bindings)</summary>
        <div class="sfe-collapse-body">
            <p class="sfe-hint">These slots feed application status, consent, and background-check logic.
            Bind each required hook to a form field. Custom questions need no binding.</p>
            <table class="sfe-table" style="margin-top:8px">
                <thead><tr><th>Hook</th><th>Label</th><th>Bound field</th></tr></thead>
                <tbody>' . $rows . '</tbody>
            </table>
            <div class="sfe-actions">
                <button type="button" class="btn-secondary" onclick="formEngineSaveHooks()">Save hook bindings</button>
                <span id="sfe-hooks-result" style="margin-left:8px"></span>
            </div>
        </div>
    </details>';
}

/**
 * Resolve "Position in form" selection into a sortorder integer.
 * place_after: keep | start | end | after:{fieldid}
 * When inserting/moving after another field, bumps following sortorders to make room.
 */
function form_editor_resolve_sortorder($form_key, $pageid, $fieldid, $place_after) {
    $pageid = (int)$pageid;
    $fieldid = (int)$fieldid;
    $place = trim((string)$place_after);
    if ($place === '') {
        $place = 'end';
    }

    // Keep current position when editing
    if ($place === 'keep') {
        if ($fieldid > 0) {
            $row = get_db_row(
                "SELECT sortorder FROM form_fields WHERE fieldid=||id||",
                ['id' => $fieldid]
            );
            if ($row) {
                return (int)$row['sortorder'];
            }
        }
        $place = 'end';
    }

    $scope = "form_key = ||fk|| AND pageid IN (0, ||p||)";
    $p = ['p' => $pageid, 'fk' => $form_key];

    if ($place === 'start') {
        // Make room at the top; new field always gets sortorder 10
        execute_db_sql(
            "UPDATE form_fields SET sortorder = sortorder + 10 WHERE {$scope}"
                . ($fieldid > 0 ? " AND fieldid <> ||id||" : ""),
            $fieldid > 0 ? array_merge($p, ['id' => $fieldid]) : $p
        );
        return 10;
    }

    if (strpos($place, 'after:') === 0) {
        $after_id = (int)substr($place, 6);
        if ($after_id > 0) {
            $row = get_db_row(
                "SELECT sortorder FROM form_fields WHERE fieldid=||id||",
                ['id' => $after_id]
            );
            if ($row) {
                $sort = (int)$row['sortorder'] + 1;
                // Make room: shift fields at or after this slot
                execute_db_sql(
                    "UPDATE form_fields SET sortorder = sortorder + 1
                     WHERE {$scope} AND sortorder >= ||s||"
                        . ($fieldid > 0 ? " AND fieldid <> ||id||" : ""),
                    $fieldid > 0
                        ? array_merge($p, ['s' => $sort, 'id' => $fieldid])
                        : array_merge($p, ['s' => $sort])
                );
                return $sort;
            }
        }
        $place = 'end';
    }

    // Default: end of list
    $row = get_db_row("SELECT MAX(sortorder) AS m FROM form_fields WHERE {$scope}", $p);
    return ($row && $row['m'] !== null) ? ((int)$row['m'] + 10) : 500;
}

function form_editor_ui($form_key, $pageid) {
    if (!defined('STAFFFORMLIB')) {
        global $CFG;
        include_once($CFG->dirroot . '/features/events/staffform/staffformlib.php');
    }
    form_engine_seed_fields($form_key, 0);

    $fields = form_engine_get_fields($form_key, $pageid, true);
    $consent_keys = form_engine_fixed_keys($form_key);
    // Labels/delete warnings come from hook bindings (no hard protected list)

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
        $bound_hooks = function_exists('form_engine_hooks_for_field')
            ? form_engine_hooks_for_field($form_key, $f['field_key'] ?? '', $pageid)
            : [];
        $is_hooked = !empty($bound_hooks);
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

        $badge = '';
        $del_msg = 'Delete this field?';
        if ($is_hooked) {
            $hook_labels = array_map(function ($h) {
                return $h['label'] ?? $h['hook_id'];
            }, $bound_hooks);
            $badge = ' <span title="Bound to process hook(s): ' . htmlspecialchars(implode(', ', $hook_labels)) . '" '
                . 'style="background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:4px;font-size:.75em">hooked</span>';
            $del_msg = 'This field is bound to process hook(s): ' . implode(', ', $hook_labels)
                . '.\n\nDeleting it will unbind those hooks. Continue?';
        }
        $edit_icon = function_exists('icon') ? icon([['icon' => 'pen-to-square']]) : 'Edit';
        $del_icon = function_exists('icon') ? icon([['icon' => 'trash']]) : 'Delete';
        $del = ($fid === 0)
            ? ''
            : '<button type="button" class="btn-danger" title="Delete" onclick="if(confirm(' . json_encode($del_msg) . ')) form_engine_delete_field(' . $fid . ')">' . $del_icon . '</button>';

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
            'is_core' => $is_hooked ? 1 : 0, // hooked — editor may still warn on key change
        ];

        $edit_js_id = $fid > 0 ? $fid : ("'" . $map_key . "'");
        $rows .= '<tr draggable="true" data-fieldid="' . (int)$fid . '" class="sfe-field-row">
            <td class="sfe-drag-handle" title="Drag to reorder">&#8942;&#8942;</td>
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

    // Position dropdown options (After: Label) — used in the edit form
    $position_opts = '<option value="start">At the beginning</option>';
    $position_opts .= '<option value="end" selected>At the end</option>';
    foreach ($fields as $f) {
        $fid = (int)($f['fieldid'] ?? 0);
        if ($fid <= 0) {
            continue;
        }
        $lbl = trim((string)($f['label'] ?? $f['field_key']));
        $sec = trim((string)($f['section'] ?? ''));
        $disp = $lbl !== '' ? $lbl : $f['field_key'];
        if ($sec !== '') {
            $disp .= ' (' . $sec . ')';
        }
        $position_opts .= '<option value="after:' . $fid . '">After: ' . htmlspecialchars($disp) . '</option>';
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
            Add, edit, reorder, and configure questions for the staff application form
            (<code>form_key=staff_app</code>).
            <strong>Process hooks</strong> below map questions to backend status logic.
            Custom questions are free-form and only stored in form data.
        </p>
        ' . form_editor_hooks_panel_html($form_key, $pageid) . '
        <p class="sfe-dnd-hint">Drag the <strong>⋮⋮</strong> handle to reorder fields. Order is saved automatically.</p>
        <table class="sfe-table" id="sfe-fields-table">
            <thead>
                <tr>
                    <th style="width:28px" title="Drag to reorder"></th>
                    <th>Key</th><th>Label</th><th>Type</th><th>Section</th>
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
                        <label>Position in form</label>
                        <select name="place_after" id="edit_place_after">
                            <option value="keep">Keep current position</option>
                            ' . $position_opts . '
                        </select>
                        <div class="sfe-hint">Or drag rows in the list above to reorder.</div>
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
        ' . form_editor_migration_tools_html($form_key) . '
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
        form_engine_save_field();
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
        // Default to keep current position when editing; hide "after self" option
        $("#edit_place_after").val("keep");
        $("#edit_place_after option").prop("disabled", false).show();
        if (f.fieldid > 0) {
            $("#edit_place_after option[value=\"after:" + f.fieldid + "\"]").prop("disabled", true).hide();
        }
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

    function formEngineSaveHooks() {
        var bindings = [];
        document.querySelectorAll(".sfe-hook-field").forEach(function(sel) {
            bindings.push({
                hook_id: sel.getAttribute("data-hook-id"),
                field_key: sel.value || ""
            });
        });
        window.__sfeHooksJson = JSON.stringify(bindings);
        var out = document.getElementById("sfe-hooks-result");
        if (out) out.innerHTML = "<em>Saving…</em>";
        if (typeof form_engine_save_hooks === "function") {
            form_engine_save_hooks();
        }
    }

    function formEngineRunMigrate() {
        var btn = document.getElementById("form_engine_migrate_btn");
        var out = document.getElementById("form_engine_migrate_result");
        if (btn) {
            if (btn.disabled) return;
            btn.disabled = true;
            btn.setAttribute("data-label", btn.innerHTML);
            btn.innerHTML = "Migrating…";
        }
        if (out) out.innerHTML = "<em>Working… please wait.</em>";
        if (typeof form_engine_migrate === "function") {
            form_engine_migrate();
        }
        // Re-enable after response paints (ajax replaces #form_engine_migrate_result)
        setTimeout(function() {
            var b = document.getElementById("form_engine_migrate_btn");
            if (b) {
                b.disabled = false;
                if (b.getAttribute("data-label")) b.innerHTML = b.getAttribute("data-label");
            }
        }, 8000);
    }

    function formEngineDropDeprecated() {
        if (!confirm("Permanently drop deprecated answer columns from events_staff and events_staff_archive? This cannot be undone. Only proceed after a successful data migration.")) {
            return;
        }
        var btn = document.getElementById("staff_form_drop_cols_btn");
        if (btn) {
            if (btn.disabled) return;
            btn.disabled = true;
            btn.innerHTML = "Dropping columns…";
        }
        if (typeof form_engine_drop_deprecated === "function") {
            form_engine_drop_deprecated();
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
        $("#edit_place_after option").prop("disabled", false).show();
        $("#edit_place_after").val("end");
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
            if (typeof form_engine_reorder === "function") {
                // ajaxapi uses js||order||js — set global for the binding
                window.order = ids.join(",");
                form_engine_reorder();
            } else if (typeof jQuery !== "undefined") {
                jQuery.post("/features/events/events_ajax.php", {
                    action: "form_engine_reorder",
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
