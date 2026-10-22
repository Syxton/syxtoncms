<?php
/***************************************************************************
 * formrenderlib.php - Form engine field rendering & value display
 * -------------------------------------------------------------------------
 * Shared by all form_key consumers. Markup CSS hooks are configurable via
 * a profile (defaults match the staff application form for BC).
 ***************************************************************************/
if (!defined('FORMRENDERLIB')) {
    define('FORMRENDERLIB', true);
}
if (!defined('FORMENGINELIB')) {
    $__dir = dirname(__FILE__);
    if (file_exists($__dir . '/formenginelib.php')) {
        include_once($__dir . '/formenginelib.php');
    }
}

/**
 * Default markup / CSS class profile for field rendering.
 * @return array
 */
function form_engine_default_markup_profile() {
    return [
        'row_class' => 'rowContainer',
        'viewonly_row_class' => 'rowContainer staff-viewonly-row',
        'label_class' => 'rowTitle',
        'viewonly_value_class' => 'staff-viewonly-value',
        'section_class' => 'staff-subsection',
        'section_heading_class' => 'staff-subsection-heading',
        'field_data_attr' => 'data-staff-field-key',
        'section_data_attr' => 'data-staff-section',
        'help_class' => 'tooltipContainer info',
        'file_viewer_class' => 'staff-file-viewer',
        'file_confirm_class' => 'staff-file-confirm',
        'file_confirm_row_class' => 'staff-file-confirm-row',
        'file_confirm_text_class' => 'staff-file-confirm-text',
        'number_prefix_class' => 'staff-number-prefix',
        'lang_feature' => '/features/events',
    ];
}

/**
 * Merge caller overrides onto the default markup profile.
 * @param array $override Partial profile overrides
 * @return array
 */
function form_engine_merge_markup_profile($override = []) {
    $base = form_engine_default_markup_profile();
    if (!is_array($override)) {
        return $base;
    }
    return array_merge($base, $override);
}

/**
 * Normalize field validation JSON to an array.
 * @param array $field Field row
 * @return array
 */
function form_engine_field_validation($field) {
    $validation = $field['validation'] ?? [];
    if (!is_array($validation)) {
        $validation = (is_string($validation) && $validation !== '') ? (json_decode($validation, true) ?: []) : [];
    }
    return is_array($validation) ? $validation : [];
}

/**
 * Normalize checkbox / signature values to "1" or "0".
 * @param mixed $val Raw value
 * @return string "1" or "0"
 */
function form_engine_normalize_checkbox_value($val) {
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
 * Parse a date-only string or timestamp into a UTC-midnight unix timestamp.
 * @param mixed $str Date string (m/d/Y, Y-m-d) or numeric timestamp
 * @return int Unix timestamp or 0
 */
function form_engine_parse_date_only($str) {
    $str = trim((string)$str);
    if ($str === '') {
        return 0;
    }
    // Already a pure integer timestamp
    if (is_numeric($str) && (string)(int)$str === (string)$str && (int)$str > 0) {
        // Normalize existing timestamps to UTC midnight of their UTC calendar day
        return form_engine_normalize_ts_to_utc_midnight((int)$str);
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
 * Normalize a unix timestamp to UTC midnight of its UTC calendar day.
 * @param int $ts Unix timestamp
 * @return int
 */
function form_engine_normalize_ts_to_utc_midnight($ts) {
    $ts = (int)$ts;
    if ($ts <= 0) {
        return 0;
    }
    $day = gmdate('Y-m-d', $ts);
    $dt = DateTime::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));
    return $dt ? (int)$dt->getTimestamp() : 0;
}

/**
 * Format a timestamp or date string as m/d/Y for display (UTC calendar day).
 * @param mixed $ts Timestamp or date string
 * @return string
 */
function form_engine_format_date_only($ts) {
    if ($ts === null || $ts === '' || $ts === false) {
        return '';
    }
    if (!is_numeric($ts)) {
        $s = trim((string)$ts);
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $s)) {
            return $s;
        }
        $ts = form_engine_parse_date_only($s);
    }
    $ts = (int)$ts;
    if ($ts <= 0) {
        return '';
    }
    return gmdate('m/d/Y', $ts);
}

/**
 * Human-readable display string for a field value (view-only mode).
 * @param array $field Field row
 * @param mixed $value Stored value
 * @return string
 */
function form_engine_display_value($field, $value) {
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
        $formatted = form_engine_format_date_only($val);
        return $formatted !== '' ? htmlspecialchars($formatted) : '';
    }
    if ($type === 'number') {
        if ($val === '' || $val === null) {
            return '';
        }
        $validation = $field['validation'] ?? [];
        if (is_string($validation) && $validation !== '') {
            $validation = json_decode($validation, true) ?: [];
        }
        if (!is_array($validation)) {
            $validation = [];
        }
        $subtype = (string)($validation['number_subtype'] ?? 'decimal');
        if ($subtype === 'money' && is_numeric($val)) {
            return htmlspecialchars('$' . number_format((float)$val, 2, '.', ','));
        }
        if ($subtype === 'integer' && is_numeric($val)) {
            return htmlspecialchars((string)(int)$val);
        }
        return htmlspecialchars((string)$val);
    }
    if ($type === 'textarea') {
        return nl2br(htmlspecialchars((string)$val));
    }
    return htmlspecialchars((string)$val);
}

/**
 * Render a single form field as HTML (input or view-only).
 * @param array $field Field row
 * @param mixed $value Current value
 * @param array $opts viewonly, markup profile keys, etc.
 * @return string HTML
 */
function form_engine_render_field($field, $value, $opts = []) {
    if (!is_array($opts)) {
        $opts = ['viewonly' => !empty($opts)];
    }
    $viewonly = !empty($opts['viewonly']);
    $m = form_engine_merge_markup_profile(isset($opts['markup']) ? $opts['markup'] : []);
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
            return '<div id="' . htmlspecialchars($attrs['wrapper_id']) . '" ' . $m['section_data_attr'] . '="' . htmlspecialchars($key) . '"' . $vis_attr . '>
                        <h4 class="' . htmlspecialchars($m['section_heading_class']) . '">' . htmlspecialchars($label) . '</h4>';
        }
        return '<div class="' . htmlspecialchars($m['section_class']) . '" ' . $m['section_data_attr'] . '="' . htmlspecialchars($key) . '"' . $vis_attr . '>'
             . '<h4 class="' . htmlspecialchars($m['section_heading_class']) . '">' . htmlspecialchars($label) . '</h4>'
             . '</div>';
    }

    // View-only / print: show answers as text, not form controls
    if ($viewonly) {
        $display = form_engine_display_value($field, $val);
        $html = '<div class="' . htmlspecialchars($m['viewonly_row_class']) . '" ' . $m['field_data_attr'] . '="' . htmlspecialchars($key) . '">';
        $html .= '<label class="' . htmlspecialchars($m['label_class']) . '">' . htmlspecialchars($label) . '</label>';
        $html .= '<div class="' . htmlspecialchars($m['viewonly_value_class']) . '">' . $display . '</div>';
        $html .= '</div>';
        return $html;
    }

    // Close parental wrapper after its last field (handled by caller or by a special end marker if needed)
    $html = '<div class="' . htmlspecialchars($m['row_class']) . '" ' . $m['field_data_attr'] . '="' . htmlspecialchars($key) . '">';
    $html .= '<label class="' . htmlspecialchars($m['label_class']) . '" for="' . htmlspecialchars($key) . '">' . htmlspecialchars($label) . '</label>';

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
    // Min / max (numbers and dates); "current" resolved at render for dates
    if (array_key_exists('min', $validation) && $validation['min'] !== '' && $validation['min'] !== null) {
        $min_v = $validation['min'];
        if ($type === 'date' && (string)$min_v === 'current') {
            $min_v = function_exists('get_timestamp')
                ? date('Y-m-d', get_timestamp())
                : date('Y-m-d');
        }
        $data_rules .= ' data-rule-min="' . htmlspecialchars((string)$min_v) . '"';
        $data_rules .= ' min="' . htmlspecialchars((string)$min_v) . '"';
    }
    if (array_key_exists('max', $validation) && $validation['max'] !== '' && $validation['max'] !== null) {
        $max_v = $validation['max'];
        if ($type === 'date' && (string)$max_v === 'current') {
            $max_v = function_exists('get_timestamp')
                ? date('Y-m-d', get_timestamp())
                : date('Y-m-d');
        }
        $data_rules .= ' data-rule-max="' . htmlspecialchars((string)$max_v) . '"';
        $data_rules .= ' max="' . htmlspecialchars((string)$max_v) . '"';
    }
    // Relative date vs another field (jquery.validator method "daterel")
    if ($type === 'date' && !empty($validation['relative']) && is_array($validation['relative'])) {
        $rel_field = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($validation['relative']['field'] ?? ''));
        $rel_op = (string)($validation['relative']['op'] ?? '');
        $allowed_ops = ['before', 'before_or_equal', 'after', 'after_or_equal', 'same'];
        if ($rel_field !== '' && in_array($rel_op, $allowed_ops, true)) {
            $param = '#' . $rel_field . '::' . $rel_op;
            $data_rules .= ' data-rule-daterel="' . htmlspecialchars($param) . '"';
            $msgs = [
                'before' => 'This date must be before the related date.',
                'before_or_equal' => 'This date must be on or before the related date.',
                'after' => 'This date must be after the related date.',
                'after_or_equal' => 'This date must be on or after the related date.',
                'same' => 'This date must match the related date.',
            ];
            $data_rules .= ' data-msg-daterel="' . htmlspecialchars($msgs[$rel_op]) . '"';
            // Hint for custom revalidation when the other field changes
            $data_rules .= ' data-daterel-other="' . htmlspecialchars($rel_field) . '"';
        }
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
        if ($locked && !$viewonly && in_array($type, ['text', 'phone', 'date', 'number', 'textarea'], true)) {
            $extra .= ' readonly="readonly"';
        } else {
            $extra .= ' disabled="disabled"';
        }
    }

    switch ($type) {
        case 'text':
        case 'phone':
            $html .= '<input type="text" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '"
                        value="' . htmlspecialchars($val) . '"' . $data_rules . $extra . ' />';
            break;

        case 'number':
            $subtype = (string)($validation['number_subtype'] ?? 'decimal');
            if (!in_array($subtype, ['integer', 'decimal', 'money'], true)) {
                $subtype = 'decimal';
            }
            $step = 'any';
            $inputmode = 'decimal';
            if ($subtype === 'integer') {
                $step = '1';
                $inputmode = 'numeric';
            } elseif ($subtype === 'money') {
                $step = '0.01';
                $inputmode = 'decimal';
            }
            $num_val = '';
            if ($val !== '' && $val !== null && is_numeric($val)) {
                if ($subtype === 'integer') {
                    $num_val = (string)(int)$val;
                } elseif ($subtype === 'money') {
                    $num_val = number_format((float)$val, 2, '.', '');
                } else {
                    $num_val = (string)$val;
                }
            } elseif ($val !== '' && $val !== null) {
                $num_val = (string)$val;
            }
            $prefix = ($subtype === 'money') ? '<span class="' . htmlspecialchars($m['number_prefix_class']) . '" style="margin-right:4px">$</span>' : '';
            $html .= $prefix . '<input type="number" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '"
                        value="' . htmlspecialchars($num_val) . '" step="' . htmlspecialchars($step) . '" inputmode="' . htmlspecialchars($inputmode) . '"'
                   . $data_rules . $extra . ' />';
            break;

        case 'date':
            // HTML5 date input expects Y-m-d; parse accepts Y-m-d and m/d/Y
            $date_val = '';
            if ($val !== '' && $val !== null) {
                $ts = is_numeric($val) ? (int)$val : form_engine_parse_date_only($val);
                if ($ts > 0) {
                    $date_val = gmdate('Y-m-d', $ts);
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$val)) {
                    $date_val = (string)$val;
                }
            }
            // Drop legacy slash-format custom rule; browser + server parse handle it
            $date_rules = $data_rules;
            if (!empty($validation['date']) || !empty($validation['required'])) {
                // keep required / date flags; custom slash regex is not needed for type=date
                $date_rules = preg_replace('/\s*data-rule-custom="[^"]*"/', '', $date_rules);
                $date_rules = preg_replace('/\s*data-msg-custom="[^"]*"/', '', $date_rules);
            }
            $html .= '<input type="date" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '"
                        value="' . htmlspecialchars($date_val) . '"' . $date_rules . $extra . ' />';
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
                $html .= '<div class="' . htmlspecialchars($m['file_viewer_class']) . '" style="margin:8px 0;border:1px solid #cbd5e1;border-radius:6px;overflow:hidden;background:#f8fafc">';
                $html .= '<div style="padding:6px 10px;font-size:.85em;background:#e2e8f0;color:#334155"><a href="' . $safe_url . '" target="_blank" rel="noopener">Open in new tab</a></div>';
                $html .= '<iframe src="' . $safe_url . '" title="' . htmlspecialchars($label) . '" style="width:100%;height:' . $height . 'px;border:0;display:block" loading="lazy"></iframe>';
                $html .= '</div>';
            } else {
                $html .= '<div class="' . htmlspecialchars($m['help_class']) . '">No document URL configured.</div>';
            }
            if ($need_confirm && !$viewonly) {
                $checked = (!empty($val) && $val !== '0') ? ' checked="checked"' : '';
                $confirm_text = trim((string)($opts['confirm_text'] ?? ''));
                if ($confirm_text === '') {
                    $confirm_text = 'I have reviewed this document';
                }
                // Checkbox + text as siblings (not a flex <label>) so validation .error
                // labels are not trapped/squished inside the confirm row.
                $html .= '<div class="' . htmlspecialchars($m['file_confirm_class']) . '" style="margin-top:10px">';
                $html .= '<div class="' . htmlspecialchars($m['file_confirm_row_class']) . '">';
                $html .= '<input type="checkbox" id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '" value="1"'
                       . ' style="width:45px;height:30px;margin:0"'
                       . $checked . $data_rules . $extra . ' />';
                $html .= '<label for="' . htmlspecialchars($key) . '" class="' . htmlspecialchars($m['file_confirm_text_class']) . '">'
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
            $helptext = getlang($help, $m['lang_feature']);
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
            $html .= '<div class="' . htmlspecialchars($m['help_class']) . '">' . $helptext . '</div>';
        }
    }
    $html .= '<div class="spacer" style="clear: both;"></div></div>';
    return $html;
}
