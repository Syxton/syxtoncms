<?php
/***************************************************************************
 * formeditorlib.php - Core Form Editor UI (named forms)
 * -------------------------------------------------------------------------
 * Lives under lib/formengine. Independent of Events.
 * Consumers pass a registered form_key.
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
 * @param string $form_key Form identifier
 * @param int $pageid Page id
 * @param bool $show_form_picker Show form selector when multiple forms are registered
 * @return void Echoes HTML
 */
function form_editor_page($form_key, $pageid, $show_form_picker = true) {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    form_engine_ensure_schema();
    form_engine_def($form_key);
    form_engine_sync_field_defs($form_key, 0);
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
        echo $options ?: '<option value="">(no forms registered)</option>';
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
            'attr_onblur' => "js@@$('#edit_attr_onblur').val()@@js",
            'attr_onchange' => "js@@$('#edit_attr_onchange').val()@@js",
            'vis_conditions_json' => "js@@$('#edit_vis_conditions_json').val()@@js",
            'reqw_conditions_json' => "js@@$('#edit_reqw_conditions_json').val()@@js",
            'options_json' => "js@@$('#edit_options_json').val()@@js",
            'viewer_url' => "js@@$('#edit_viewer_url').val()@@js",
            'viewer_height' => "js@@$('#edit_viewer_height').val()@@js",
            'viewer_confirm' => "js@@$('#edit_viewer_confirm').val()@@js",
            'viewer_confirm_text' => "js@@$('#edit_viewer_confirm_text').val()@@js",
            'no_autofill' => "js@@$('#edit_no_autofill').val()@@js",
        ]),
        'reqstring' => 'form_field_form',
        'display' => 'form_editor_container',
        'event' => 'none',
    ]);
    ajaxapi([
        'id' => 'form_engine_delete_field',
        'url' => $ajax_url,
        'data' => array_merge($common, [
            'action' => 'form_engine_delete_field',
            'fieldid' => 'js@@fieldid@@js',
        ]),
        'display' => 'form_editor_container',
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
            'order' => 'js@@order@@js',
        ]),
        'display' => 'form_editor_container',
        'event' => 'none',
    ]);
    ajaxapi([
        'id' => 'form_engine_save_hooks',
        'url' => $ajax_url,
        'data' => array_merge($common, [
            'action' => 'form_engine_save_hooks',
            'bindings_json' => 'js@@window.__sfeHooksJson@@js',
        ]),
        'display' => 'sfe-hooks-result',
        'event' => 'none',
    ]);

    echo form_editor_ui($form_key, $pageid);
}

/**
 * Process-hooks panel HTML for binding form fields to hook slots.
 * @param string $form_key Form identifier
 * @param int $pageid Page id
 * @return string HTML
 */
function form_editor_hooks_panel_html($form_key, $pageid) {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    $hooks = function_exists('form_engine_get_bindable_hooks')
        ? form_engine_get_bindable_hooks($form_key, $pageid)
        : form_engine_get_hooks($form_key, $pageid);
    $fields = form_engine_get_fields($form_key, $pageid, true);

    // Index form fields by type for slot filters
    $fields_by_type = [];
    $all_bindable_fields = [];
    foreach ($fields as $f) {
        if (($f['type'] ?? '') === 'section') {
            continue;
        }
        $fk = (string)$f['field_key'];
        $ftype = (string)($f['type'] ?? 'text');
        $lab = trim((string)($f['label'] ?? '')) . ' (' . $fk . ')';
        $entry = ['key' => $fk, 'type' => $ftype, 'label' => $lab];
        $all_bindable_fields[] = $entry;
        $fields_by_type[$ftype][] = $entry;
    }

    /**
     * Options list for a slot: form fields matching $type (or all if none match / type null).
     * $selected = list of currently bound form field_keys.
     */
    $options_for = function ($type, array $selected, $multiple) use ($fields_by_type, $all_bindable_fields) {
        $pool = [];
        if ($type && !empty($fields_by_type[$type])) {
            $pool = $fields_by_type[$type];
        } else {
            $pool = $all_bindable_fields;
        }
        // Always include already-selected keys even if type drifted
        $seen = [];
        foreach ($pool as $e) {
            $seen[$e['key']] = true;
        }
        foreach ($all_bindable_fields as $e) {
            if (in_array($e['key'], $selected, true) && empty($seen[$e['key']])) {
                $pool[] = $e;
                $seen[$e['key']] = true;
            }
        }
        $html = '';
        if (!$multiple) {
            $html .= '<option value="">— unbound —</option>';
        }
        foreach ($pool as $e) {
            $sel = in_array($e['key'], $selected, true) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($e['key']) . '"' . $sel . '>'
                . htmlspecialchars($e['label']) . '</option>';
        }
        return $html;
    };

    $rows = '';
    $total = 0;
    $unbound = 0;
    foreach ($hooks as $h) {
        $total++;
        $hid = htmlspecialchars($h['hook_id']);
        $req = !empty($h['required'])
            ? '<span style="background:#fee2e2;color:#991b1b;padding:1px 6px;border-radius:4px;font-size:.9em">required</span>'
            : '<span style="background:#e2e8f0;color:#475569;padding:1px 6px;border-radius:4px;font-size:.9em">optional</span>';

        $slots = function_exists('form_engine_hook_fields_spec')
            ? form_engine_hook_fields_spec($h)
            : [];

        $is_bound = false;

        $sel = '';
        if (!$slots) {
            $sel = '<em style="color:#94a3b8">No slots declared</em>';
        } else {
            foreach ($slots as $slot) {
                $slot_name = (string)($slot['slot'] ?? '');
                if ($slot_name === '') {
                    continue;
                }
                $stype = $slot['type'] ?? null;
                $amt = (int)($slot['amount'] ?? 1);
                $selected = array_values(array_filter(array_map('strval', $slot['keys'] ?? [])));
                if ($selected) {
                    $is_bound = true;
                }
                $is_multi = ($amt === 0 || $amt > 1);
                $type_bit = $stype ? htmlspecialchars((string)$stype) : 'any';
                $amt_bit = $is_multi ? 'unlimited' : '1';

                $matchfields = '';
                if ($is_multi) {
                    // Multiselect list of matching form fields
                    $size = max(3, min(8, count($all_bindable_fields)));
                    $matchfields .= '
                        <select multiple
                                size="' . (int)$size . '"
                                class="sfe-hook-multi-select"
                                data-hook-id="' . $hid . '"
                                data-slot="' . htmlspecialchars($slot_name) . '"
                                style="width:100%;min-height:72px">
                            ' . $options_for($stype, $selected, true) . '
                        </select>
                        <div style="font-size:.9em;color:#64748b;margin-top:2px">
                            Ctrl / Cmd-click to select multiple
                        </div>';
                } else {
                    // Single select of matching form fields
                    $matchfields .= '
                        <select
                            class="sfe-hook-field"
                            data-hook-id="' . $hid . '"
                            data-slot="' . htmlspecialchars($slot_name) . '"
                            style="width:100%">
                            ' . $options_for($stype, $selected, false) . '
                        </select>';
                }

                $sel .= '
                    <div class="sfe-hook-slot" style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:8px;margin:6px 0;padding:8px;border:1px solid #e2e8f0;border-radius:6px;background:#deefff">
                        <div style="min-width:120px;padding-top:6px">
                            <code style="font-weight:600">
                                Backend name: ' . htmlspecialchars($slot_name) . '
                            </code>
                            <div style="font-size:.9em;color:#64748b;font-weight: bold;">
                                Hooks to: ' . $amt_bit . ' ' . $type_bit . '
                            </div>
                        </div>
                        <div style="flex:1;min-width:180px">
                        ' . $matchfields . '
                        </div>
                    </div>';
            }
            $sel = '<div class="sfe-hook-slots">' . $sel . '</div>';
        }

        if (!$is_bound) {
            $unbound++;
        }
        $rows .= '<tr>
            <td data-label="Hook"><code>' . $hid . '</code><div class="sfe-hint">' . htmlspecialchars($h['help'] ?? '') . '</div></td>
            <td data-label="Label">' . htmlspecialchars($h['label'] ?? $h['hook_id']) . ' ' . $req . '</td>
            <td data-label="Slots">' . $sel . '</td>
        </tr>';
    }

    $bound = max(0, $total - $unbound);
    $count_bits = (int)$total . ' hook' . ($total === 1 ? '' : 's');
    if ($total > 0) {
        $count_bits .= ' · ' . (int)$bound . ' bound';
        if ($unbound > 0) {
            $count_bits .= ' · <span style="color:#b45309;font-weight:600">' . (int)$unbound . ' open</span>';
        } else {
            $count_bits .= ' · <span style="color:#166534">all bound</span>';
        }
    }
    $summary_class = $unbound > 0 ? 'has-content' : '';

    return '
    <details class="sfe-collapse ' . $summary_class . '" style="margin:12px 0">
        <summary>Process hooks <span style="font-weight:400;color:#64748b">(' . $count_bits . ')</span></summary>
        <div class="sfe-collapse-body">
            <p class="sfe-hint">Each hook declares named <strong>slots</strong>. Bind form fields to those slots:
            amount <code>1</code> → single select; amount <code>0</code> → multi-select (values become <code>slot_1</code>, <code>slot_2</code>, …).</p>
            <div class="sfe-table-scroll">
            <table class="sfe-table sfe-hooks-table" style="margin-top:8px">
                <thead><tr><th>Hook</th><th>Label</th><th>Slots</th></tr></thead>
                <tbody>' . $rows . '</tbody>
            </table>
            </div>
            <div class="sfe-actions">
                <button type="button" class="btn-secondary" onclick="formEngineSaveHooks()">Save hook bindings</button>
                <span id="sfe-hooks-result" style="margin-left:8px"></span>
            </div>
        </div>
    </details>';
}

/**
 * Resolve "Position in form" selection into a sortorder integer.
 * @param string $form_key Form identifier
 * @param int $pageid Page id
 * @param int $fieldid Existing field id (0 when inserting)
 * @param string $place_after keep|start|end|after:{fieldid}
 * @return int sortorder
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
                "SELECT sortorder FROM form_fields WHERE fieldid=@@id@@",
                ['id' => $fieldid]
            );
            if ($row) {
                return (int)$row['sortorder'];
            }
        }
        $place = 'end';
    }

    $scope = "form_key = @@fk@@ AND pageid IN (0, @@p@@)";
    $p = ['p' => $pageid, 'fk' => $form_key];

    if ($place === 'start') {
        // Make room at the top; new field always gets sortorder 10
        execute_db_sql(
            "UPDATE form_fields SET sortorder = sortorder + 10 WHERE {$scope}"
                . ($fieldid > 0 ? " AND fieldid <> @@id@@" : ""),
            $fieldid > 0 ? array_merge($p, ['id' => $fieldid]) : $p
        );
        return 10;
    }

    if (strpos($place, 'after:') === 0) {
        $after_id = (int)substr($place, 6);
        if ($after_id > 0) {
            $row = get_db_row(
                "SELECT sortorder FROM form_fields WHERE fieldid=@@id@@",
                ['id' => $after_id]
            );
            if ($row) {
                $sort = (int)$row['sortorder'] + 1;
                // Make room: shift fields at or after this slot
                execute_db_sql(
                    "UPDATE form_fields SET sortorder = sortorder + 1
                     WHERE {$scope} AND sortorder >= @@s@@"
                        . ($fieldid > 0 ? " AND fieldid <> @@id@@" : ""),
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

/**
 * Main form editor UI (field list, edit form, hooks panel).
 * @param string $form_key Form identifier
 * @param int $pageid Page id
 * @return string HTML
 */
function form_editor_ui($form_key, $pageid) {
    global $CFG;
    if (!defined('FORMENGINELIB')) {
        include_once($CFG->dirroot . '/lib/formengine/formenginelib.php');
    }
    $def = form_engine_def($form_key);
    form_engine_sync_field_defs($form_key, 0);
    $form_title = ($def['label'] ?? $form_key) . ' — Form Editor';

    $fields = form_engine_get_fields($form_key, $pageid, true);
    $fixed_keys = form_engine_fixed_keys($form_key);
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
        $is_fixed = in_array($f['field_key'], $fixed_keys, true);
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
        $viewer_confirm_text = '';
        if (($f['type'] ?? '') === 'file_viewer' && is_array($options)) {
            $viewer_url = (string)($options['url'] ?? $options['viewer_url'] ?? '');
            $viewer_height = (string)($options['height'] ?? $options['viewer_height'] ?? '480');
            $viewer_confirm = !empty($options['confirm']) || !isset($options['confirm']) ? '1' : '0';
            if (isset($options['confirm'])) {
                $viewer_confirm = $options['confirm'] ? '1' : '0';
            }
            $viewer_confirm_text = (string)($options['confirm_text'] ?? '');
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
            $badge = '<span class="sfe-hook-badge" title="Bound to process hook(s): ' . htmlspecialchars(implode(', ', $hook_labels)) . '">hooked</span>';
            $del_msg = 'This field is bound to process hook(s): ' . implode(', ', $hook_labels)
                . '.\n\nDeleting it will unbind those hooks. Continue?';
        }
        $edit_icon = function_exists('icon') ? icon([['icon' => 'pen-to-square']]) : 'Edit';
        $del_icon = function_exists('icon') ? icon([['icon' => 'trash']]) : 'Delete';
        $del = ($fid === 0)
            ? ''
            : '<button type="button" class="btn-danger" title="Delete" onclick="if(confirm(' . json_encode($del_msg) . ')) form_engine_delete_field(' . $fid . ')">' . $del_icon . '</button>';

        $validation = $f['validation'] ?? [];
        if (is_string($validation) && $validation !== '') {
            $validation = json_decode($validation, true) ?: [];
        }
        if (!is_array($validation)) {
            $validation = [];
        }
        $number_subtype = (string)($validation['number_subtype'] ?? 'decimal');
        if (!in_array($number_subtype, ['integer', 'decimal', 'money'], true)) {
            $number_subtype = 'decimal';
        }
        $val_min = array_key_exists('min', $validation) ? $validation['min'] : '';
        $val_max = array_key_exists('max', $validation) ? $validation['max'] : '';
        // For date fields expose as date_min/date_max; numbers use val_min/val_max
        $date_min = (($f['type'] ?? '') === 'date') ? $val_min : '';
        $date_max = (($f['type'] ?? '') === 'date') ? $val_max : '';
        $date_rel_field = '';
        $date_rel_op = 'before';
        if (($f['type'] ?? '') === 'date') {
            $val_min = '';
            $val_max = '';
            $rel = $validation['relative'] ?? null;
            if (is_array($rel)) {
                $date_rel_field = (string)($rel['field'] ?? '');
                $date_rel_op = (string)($rel['op'] ?? 'before');
            }
        }

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
            'viewer_confirm_text' => $viewer_confirm_text,
            'no_autofill' => !empty($attrs['no_autofill']) ? '1' : '0',
            'number_subtype' => $number_subtype,
            'val_min' => $val_min,
            'val_max' => $val_max,
            'date_min' => $date_min,
            'date_max' => $date_max,
            'date_rel_field' => $date_rel_field,
            'date_rel_op' => $date_rel_op,
            'is_fixed' => $is_fixed ? 1 : 0,
            'is_core' => $is_hooked ? 1 : 0, // hooked — editor may still warn on key change
        ];

        $edit_js_id = $fid > 0 ? $fid : ("'" . $map_key . "'");
        $rows .= '<tr data-fieldid="' . (int)$fid . '" class="sfe-field-row">
            <td class="sfe-drag-handle" data-label="" title="Drag to reorder">&#8942;&#8942;</td>
            <td data-label="Key"><span class="sfe-key-cell"><code>' . htmlspecialchars($f['field_key']) . '</code>' . $badge . '</span></td>
            <td data-label="Label">' . htmlspecialchars($f['label']) . '</td>
            <td data-label="Type">' . htmlspecialchars($f['type']) . '</td>
            <td data-label="Section">' . htmlspecialchars($f['section'] ?? '') . '</td>
            <td data-label="Show/Hide" style="text-align:center">' . $vis . '</td>
            <td data-label="Req. When" style="text-align:center">' . $reqw . '</td>
            <td data-label="Status">' . $active . '</td>
            <td data-label="Actions" class="sfe-actions-cell">
                <button type="button" title="Edit" onclick="formEditorEditField(' . $edit_js_id . ')">' . $edit_icon . '</button>
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

    $params = [
        'form_title' => htmlspecialchars($form_title),
        'form_key' => htmlspecialchars($form_key),
        'hooks_panel' => form_editor_hooks_panel_html($form_key, $pageid),
        'field_rows' => $rows,
        'section_opts' => $section_opts,
        'position_opts' => $position_opts,
        'migration_tools' => form_editor_migration_tools_html($form_key),
        'form_key_json' => json_encode($form_key),
        'pageid' => (int)$pageid,
        'edit_json' => $edit_json,
        'key_opts_js' => $key_opts_js,
    ];
    return fill_template('lib/formengine/tmp/formeditor.template', 'form_editor_ui', false, $params);
}

/**
 * Optional migration tools block from the form def migration_tools_html callback.
 * @param string $form_key Form identifier
 * @return string HTML
 */
function form_editor_migration_tools_html($form_key) {
    $def = form_engine_def($form_key);
    form_engine_include_def_lib($form_key, 'migration_lib');
    $fn = $def['migration_tools_html'] ?? null;
    if ($fn && is_string($fn) && function_exists($fn)) {
        return call_user_func($fn, $form_key);
    }
    if ($fn && is_callable($fn)) {
        return call_user_func($fn, $form_key);
    }
    return '';
}
