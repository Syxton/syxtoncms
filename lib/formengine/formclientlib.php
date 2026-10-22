<?php
/***************************************************************************
 * formclientlib.php - Form engine client helpers (rules JS, multipage UI)
 * -------------------------------------------------------------------------
 * Shared by form_key consumers. Defaults match staff application selectors
 * for backward compatibility; pass $opts to retarget.
 ***************************************************************************/
if (!defined('FORMCLIENTLIB')) {
    define('FORMCLIENTLIB', true);
}
if (!defined('FORMENGINELIB')) {
    $__dir = dirname(__FILE__);
    if (file_exists($__dir . '/formenginelib.php')) {
        include_once($__dir . '/formenginelib.php');
    }
}
if (!defined('FORMRENDERLIB')) {
    $__dir = dirname(__FILE__);
    if (file_exists($__dir . '/formrenderlib.php')) {
        include_once($__dir . '/formrenderlib.php');
    }
}

/**
 * Default client/JS options for multipage forms and visibility rules.
 * @return array
 */
function form_engine_default_client_opts() {
    return [
        'form_selector' => '#staffapplication_form',
        'pager_id' => 'staff-form-pager',
        'progress_label_id' => 'sfp-progress-label',
        'page_class' => 'staff-form-page',
        'nav_class' => 'staff-form-nav',
        'progress_class' => 'staff-form-progress',
        'pager_class' => 'staff-form-pager',
        'step_class' => 'sfp-step',
        'prev_id' => 'sfp-prev',
        'next_id' => 'sfp-next',
        'submit_id' => 'sfp-submit',
        'submit_name' => 'submit',
        'submit_value' => 'Submit Application',
        'apply_rules_global' => 'staffFormApplyRules',
        'rules_ns' => 'staffRules',
        'daterel_ns' => 'staffDateRel',
        'preserve_keys_on_hide' => ['agerange', 'dateofbirth'],
        'extra_targets' => [
            'section_parental' => '#sub18',
        ],
        'viewonly' => false,
        'default_section' => 'General',
    ];
}

/**
 * Merge caller overrides onto default client options.
 * @param array $override Partial client option overrides
 * @return array
 */
function form_engine_merge_client_opts($override = []) {
    $base = form_engine_default_client_opts();
    if (!is_array($override)) {
        return $base;
    }
    $out = array_merge($base, $override);
    if (isset($override['extra_targets']) && is_array($override['extra_targets'])) {
        $out['extra_targets'] = array_merge($base['extra_targets'], $override['extra_targets']);
    }
    if (isset($override['preserve_keys_on_hide']) && is_array($override['preserve_keys_on_hide'])) {
        $out['preserve_keys_on_hide'] = $override['preserve_keys_on_hide'];
    }
    return $out;
}

/**
 * Group fields into pages by section title.
 * @param array $fields Field rows
 * @param string $default_section Section name when field has none
 * @return array List of pages [title, fields]
 */
function form_engine_group_fields_by_section(array $fields, $default_section = 'General') {
    $pages = [];
    $index = [];
    foreach ($fields as $field) {
        $sec = trim((string)($field['section'] ?? ''));
        if ($sec === '') {
            $sec = $default_section;
        }
        if (!isset($index[$sec])) {
            $index[$sec] = count($pages);
            $pages[] = ['title' => $sec, 'fields' => []];
        }
        $pages[$index[$sec]]['fields'][] = $field;
    }
    return $pages;
}

/**
 * Multipage step pager HTML.
 * @param array $pages Page list from form_engine_group_fields_by_section
 * @param array $opts Client options (form_selector, etc.)
 * @return string HTML
 */
function form_engine_pager_html(array $pages, $opts = []) {
    $o = form_engine_merge_client_opts($opts);
    $n = count($pages);
    $steps = '';
    foreach ($pages as $i => $page) {
        $cls = $o['step_class'] . ($i === 0 ? ' active' : '');
        $steps .= '<button type="button" class="' . htmlspecialchars($cls) . '" data-page="' . (int)$i . '">'
            . htmlspecialchars($page['title']) . '</button>';
    }
    return '
    <div class="' . htmlspecialchars($o['progress_class']) . '"><span id="' . htmlspecialchars($o['progress_label_id']) . '">Step 1 of ' . (int)$n . '</span></div>
    <div class="' . htmlspecialchars($o['pager_class']) . '" id="' . htmlspecialchars($o['pager_id']) . '">' . $steps . '</div>';
}

/**
 * Render one form page (section of fields).
 * @param array $page Page [title, fields]
 * @param int $index Zero-based page index
 * @param array $vals field_key => value
 * @param array $opts viewonly, wrap_field, render_field, client opts
 * @return string HTML
 */
function form_engine_page_html(array $page, $index, array $vals, $opts = []) {
    $o = form_engine_merge_client_opts($opts);
    $viewonly = !empty($o['viewonly']);
    $active = ($viewonly || (int)$index === 0) ? ' active' : '';
    $title = htmlspecialchars($page['title']);

    $render = $o['render_field'] ?? null;
    if (!$render || !is_callable($render)) {
        $render = function ($field, $value, $vo) {
            return form_engine_render_field($field, $value, ['viewonly' => $vo]);
        };
    }
    $wrap = $o['wrap_field'] ?? null;

    $field_html = '';
    $ctx = ['inside_wrapper' => null];
    foreach ($page['fields'] as $field) {
        $key = $field['field_key'];
        $val = $vals[$key] ?? '';
        $chunk = is_callable($render) ? $render($field, $val, $viewonly) : form_engine_render_field($field, $val, ['viewonly' => $viewonly]);
        if ($wrap && is_callable($wrap)) {
            // Prefer direct call so &$ctx reference works for named functions
            $chunk = $wrap($field, $chunk, $ctx, $viewonly);
        }
        $field_html .= $chunk;
    }
    if (!empty($ctx['inside_wrapper']) && $ctx['inside_wrapper'] === 'close_pending') {
        $field_html .= '</div>';
        $ctx['inside_wrapper'] = null;
    } elseif (!empty($ctx['inside_wrapper'])) {
        // open wrapper still open at end of page
        $field_html .= '</div>';
        $ctx['inside_wrapper'] = null;
    }

    return '
        <div class="' . htmlspecialchars($o['page_class']) . $active . '" data-page="' . (int)$index . '" data-section="' . $title . '">
            <h3 style="margin-top:0">' . $title . '</h3>
            ' . $field_html . '
        </div>';
}

/**
 * Submit / next-previous navigation HTML for multipage forms.
 * @param int $page_count Number of pages
 * @param array $opts viewonly, submit_value, form_selector
 * @return string HTML
 */
function form_engine_submit_nav_html($page_count, $opts = []) {
    $o = form_engine_merge_client_opts($opts);
    if (!empty($o['viewonly'])) {
        return '';
    }
    $submit_val = htmlspecialchars($o['submit_value']);
    $submit_name = htmlspecialchars($o['submit_name']);
    if ((int)$page_count > 1) {
        return '
            <div class="' . htmlspecialchars($o['nav_class']) . '">
                <button type="button" class="secondary" id="' . htmlspecialchars($o['prev_id']) . '" disabled>Previous</button>
                <button type="button" id="' . htmlspecialchars($o['next_id']) . '">Next</button>
                <input class="submit" id="' . htmlspecialchars($o['submit_id']) . '" name="' . $submit_name . '" type="submit" value="' . $submit_val . '" style="display:none" />
            </div>';
    }
    return '<input class="submit" name="' . $submit_name . '" type="submit" onmouseover="this.focus();" value="' . $submit_val . '" />';
}

/**
 * JavaScript for multipage navigation and validation gates.
 * @param int $page_count Number of pages
 * @param array $opts Client options
 * @return string HTML script block
 */
function form_engine_multipage_js($page_count, $opts = []) {
    $o = form_engine_merge_client_opts($opts);
    $page_count = (int)$page_count;
    $form = json_encode($o['form_selector']);
    $pager = json_encode('#' . $o['pager_id']);
    $progress = json_encode('#' . $o['progress_label_id']);
    $pageClass = $o['page_class'];
    $stepClass = $o['step_class'];
    $prev = json_encode('#' . $o['prev_id']);
    $next = json_encode('#' . $o['next_id']);
    $submit = json_encode('#' . $o['submit_id']);
    $applyGlobal = json_encode($o['apply_rules_global']);

    return '
<script type="text/javascript">
(function(){
    var total = ' . $page_count . ';
    var cur = 0;
    var formSel = ' . $form . ';
    var pageSel = formSel + " .' . $pageClass . '";
    var pagerSel = ' . $pager . ';
    var stepSel = pagerSel + " .' . $stepClass . '";
    function pages(){ return jQuery(pageSel); }
    function showPage(i, opts){
        if (typeof jQuery === "undefined") return;
        opts = opts ? opts : {};
        cur = Math.max(0, Math.min(total - 1, i));
        pages().removeClass("active").eq(cur).addClass("active");
        jQuery(stepSel).removeClass("active").each(function(){
            var p = parseInt(jQuery(this).data("page"), 10);
            jQuery(this).toggleClass("active", p === cur);
            jQuery(this).toggleClass("done", p < cur);
        });
        jQuery(' . $progress . ').text("Step " + (cur + 1) + " of " + total);
        jQuery(' . $prev . ').prop("disabled", cur === 0);
        if (cur >= total - 1) {
            jQuery(' . $next . ').hide();
            jQuery(' . $submit . ').show();
        } else {
            jQuery(' . $next . ').show();
            jQuery(' . $submit . ').hide();
        }
        var ag = window[' . $applyGlobal . '];
        if (typeof ag === "function") ag();
        if (!opts.noScroll) {
            try {
                var root = jQuery(formSel).closest("[id]")[0] || jQuery(formSel)[0];
                if (root && typeof root.getBoundingClientRect === "function") {
                    var rect = root.getBoundingClientRect();
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
            var val = $el.is(":checkbox") ? ($el.is(":checked") ? "1" : "") : String($el.val() ? $el.val() : "");
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
        jQuery(' . $next . ').on("click", function(e){
            e.preventDefault();
            e.stopPropagation();
            if (!validateCurrentPage()) return;
            showPage(cur + 1);
        });
        jQuery(' . $prev . ').on("click", function(e){
            e.preventDefault();
            e.stopPropagation();
            showPage(cur - 1);
        });
        jQuery(pagerSel).on("click", ".' . $stepClass . '", function(e){
            e.preventDefault();
            e.stopPropagation();
            var p = parseInt(jQuery(this).data("page"), 10);
            if (isNaN(p) || p === cur) return;
            if (p < cur) {
                showPage(p);
                return;
            }
            for (var i = cur; i < p; i++) {
                if (!validatePage(i)) {
                    showPage(i);
                    alert("Please complete the required fields on this step before continuing.");
                    return;
                }
            }
            showPage(p);
        });
        showPage(0, { noScroll: true });
    }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", bind);
    else bind();
})();
</script>';
}

/**
 * JavaScript for visibility / required_when field rules.
 * @param array $fields Field rows
 * @param array $opts Client options
 * @return string HTML script block
 */
function form_engine_rules_js(array $fields, $opts = []) {
    $o = form_engine_merge_client_opts($opts);
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
        return '<!-- form engine: no visibility/required rules -->';
    }
    $json = json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $json = str_replace(['</', '<!--'], ['<\/', '<\!--'], $json);
    $extra = json_encode($o['extra_targets'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $preserve = json_encode(array_values($o['preserve_keys_on_hide']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $form = json_encode($o['form_selector']);
    $applyGlobal = json_encode($o['apply_rules_global']);
    $rulesNs = json_encode($o['rules_ns']);
    $daterelNs = json_encode($o['daterel_ns']);
    $fieldAttr = 'data-staff-field-key';
    $sectionAttr = 'data-staff-section';
    if (function_exists('form_engine_default_markup_profile')) {
        $mp = form_engine_default_markup_profile();
        $fieldAttr = $mp['field_data_attr'] ?? $fieldAttr;
        $sectionAttr = $mp['section_data_attr'] ?? $sectionAttr;
    }
    $fieldAttrJs = json_encode($fieldAttr);
    $sectionAttrJs = json_encode($sectionAttr);

    return '
<script type="text/javascript">
(function(){
    var RULES = ' . $json . ';
    var FORM_SEL = ' . $form . ';
    var EXTRA_TARGETS = ' . $extra . ';
    var PRESERVE_KEYS = ' . $preserve . ';
    var FIELD_ATTR = ' . $fieldAttrJs . ';
    var SECTION_ATTR = ' . $sectionAttrJs . ';
    var RULES_NS = ' . $rulesNs . ';
    var DATEREL_NS = ' . $daterelNs . ';
    function valOf(name) {
        var $byId = jQuery("#" + name);
        if ($byId.length && !$byId.is("input[type=hidden]")) {
            if ($byId.is(":checkbox")) return $byId.is(":checked") ? "1" : "0";
            var vid = $byId.val();
            if (vid != null && String(vid) !== "") return String(vid);
            if ($byId.is("select") && vid != null) return String(vid);
        }
        var $named = jQuery(FORM_SEL + " [name=\'" + name + "\']");
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
        var conds = rule.conditions ? rule.conditions : [];
        if (!conds.length) return true;
        var logic = String(rule.logic ? rule.logic : "and").toLowerCase();
        if (logic === "or") {
            for (var i = 0; i < conds.length; i++) if (matchCond(conds[i])) return true;
            return false;
        }
        for (var j = 0; j < conds.length; j++) if (!matchCond(conds[j])) return false;
        return true;
    }
    function targetFor(r) {
        var $t = jQuery();
        $t = $t.add(jQuery("[" + SECTION_ATTR + "=\'" + r.key + "\']"));
        $t = $t.add(jQuery("[" + FIELD_ATTR + "=\'" + r.key + "\']"));
        var $el = jQuery(FORM_SEL + " [name=\'" + r.key + "\']");
        if ($el.length) $t = $t.add($el.closest(".rowContainer"));
        if (EXTRA_TARGETS && EXTRA_TARGETS[r.key]) {
            $t = $t.add(jQuery(EXTRA_TARGETS[r.key]));
        }
        return $t;
    }
    function isPreserved(key) {
        return PRESERVE_KEYS && PRESERVE_KEYS.indexOf(key) >= 0;
    }
    function applyRules() {
        if (typeof jQuery === "undefined") return;
        RULES.forEach(function(r) {
            var $row = targetFor(r);
            if (r.visibility) {
                var show = evalLogic(r.visibility);
                if ((r.visibility.action ? r.visibility.action : "show") === "hide") show = !show;
                if (show) {
                    $row.show();
                } else {
                    $row.hide();
                    var $el = jQuery(FORM_SEL + " [name=\'" + r.key + "\']");
                    if ($el.is(":checkbox")) {
                        $el.prop("checked", false);
                    } else if ($el.is("select")) {
                        if (!isPreserved(r.key)) $el.val($el.find("option:first").val());
                    } else if ($el.length && !isPreserved(r.key)) {
                        $el.val("");
                    }
                }
            }
            if (r.required_when) {
                var need = evalLogic(r.required_when);
                var $el2 = jQuery(FORM_SEL + " [name=\'" + r.key + "\']");
                if (need) {
                    $el2.attr("data-rule-required", "true");
                } else {
                    $el2.removeAttr("data-rule-required").removeData("rule-required");
                }
            }
        });
    }
    window[' . $applyGlobal . '] = applyRules;
    function bind() {
        if (typeof jQuery === "undefined") { setTimeout(bind, 50); return; }
        var evChange = "change." + RULES_NS + " keyup." + RULES_NS;
        var sel = FORM_SEL + " input, " + FORM_SEL + " select, " + FORM_SEL + " textarea";
        jQuery(document).off(evChange, sel).on(evChange, sel, applyRules);
        applyRules();
        setTimeout(applyRules, 100);
        setTimeout(applyRules, 500);
        var evDate = "change." + DATEREL_NS;
        jQuery(document).off(evDate, FORM_SEL + " input[type=date]")
            .on(evDate, FORM_SEL + " input[type=date]", function() {
                var id = this.id ? this.id : this.name;
                if (!id) return;
                jQuery(FORM_SEL + " input[data-daterel-other=\\"" + id + "\\"]").each(function() {
                    var hasValidator = jQuery(this).data("valid");
                    if (!hasValidator) {
                        var $form = jQuery(this).closest("form");
                        hasValidator = $form.length ? $form.data("validator") : null;
                    }
                    if (hasValidator) {
                        try { jQuery(this).valid(); } catch (e) {}
                    }
                });
            });
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bind);
    } else {
        bind();
    }
})();
</script>';
}
