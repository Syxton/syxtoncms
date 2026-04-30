<?php
/***************************************************************************
* themes_ajax.php - Themes and Styles ajax
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.6
***************************************************************************/

include ('header.php');
update_user_cookie();

callfunction();

function theme_change() {
global $CFG, $MYVARS, $USER, $PAGE;
    $themeid = clean_myvar_opt("themeid", "int", 0);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    // Get page styles.
    $styles = get_styles($pageid, $themeid);
    $return = styles_array_to_css($styles);

    $pagename = get_db_field("name", "pages", "pageid = '$pageid'");
    $rolename = get_db_field("display_name", "roles", "roleid = " . user_role($USER->userid, $pageid));

    $params = [
        "featuretype" => "preview",
        "featureid" => "preview",
        "buttons" => icon("gear"),
        "icon" => icon("grip-vertical"),
    ];
    $buttons = fill_template("tmp/pagelib.template", "get_button_layout_template", false, $params);
    $params["pagelist"] = get_css_box($pagename, $rolename, $buttons, NULL, 'pagename', NULL, $themeid, false, $pageid);
    $params["block"] = get_css_box("Title", "Content", $buttons, null, null, null, $themeid, false, $pageid);
    $return .= fill_template("tmp/themes.template", "theme_selector_right_template", false, $params);

    ajax_return($return);
}

function show_themes() {
global $PAGE;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $themeid = get_page_themeid($pageid);
    $themeid = $themeid !== false ? $themeid : $PAGE->thememid;

    // Get page styles.
    $styles = get_styles($pageid, 0);
    $return = styles_array_to_css($styles);

    $return .= theme_selector($pageid, $themeid);
    ajax_return($return);
}

function save_custom_theme() {
global $CFG;
    $feature = clean_myvar_req("feature", "string");
    $featureid = clean_myvar_opt("featureid", "int", 0);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    $pageid = $pageid == $CFG->SITEID ? 0 : $pageid;
    $styles = [];
    if ($feature === "page") {
        $default_list = get_custom_styles($pageid);
        foreach ($default_list as $attribute => $style) {
            $value = clean_myvar_opt($attribute, "string", "");
            $styles[] = [
                "pageid" => $pageid,
                "attribute" => $attribute,
                "value" => $value,
                "themeid" => '0',
                "forced" => '0',
            ];
        }
    } else {
        $default_list = get_custom_styles($pageid, $feature, $featureid);
        foreach ($default_list as $attribute => $style) {
            $value = clean_myvar_opt($attribute, "string", "");
            $styles[] = [
                "feature" => $feature,
                "pageid" => $pageid,
                "featureid" => $featureid,
                "attribute" => $attribute,
                "value" => $value,
                "themeid" => '0',
                "forced" => '0',
            ];
        }
    }

    if (make_or_update_styles_array($styles)) {
        $return = "Saved";
    } else {
        $return = "Failed";
    }

    ajax_return($return);
}

function preview() {
global $CFG, $MYVARS, $USER, $STYLES;
    $featureid = clean_myvar_opt("featureid", "int", 0);
    $feature = clean_myvar_req("feature", "string");
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    // Start with a blank default list.
    $styles = [];
    $fn = $feature . "_default_styles";
    if (function_exists($fn)) {
        $styles = $fn();
    }

    if ($feature == "page") {
        $default_list = get_custom_styles($pageid);
        foreach ($default_list as $attribute => $style) {
            $value = clean_myvar_opt($attribute, "string", false);
            if (!empty($value)) {
                $styles[$attribute]["value"] = $value;
            } else {
                $styles[$attribute]["value"] = $style["value"];
            }
        }

        $STYLES->pagename = $styles;
        $STYLES->page = $styles;

        $pagename = get_db_field("name", "pages", "pageid = '$pageid'");
        $rolename = get_db_field("display_name", "roles", "roleid = " . user_role($USER->userid, $pageid));

        $params = [
            "featuretype" => "preview",
            "featureid" => "preview",
            "buttons" => icon("gear"),
            "icon" => icon("grip-vertical"),
        ];
        $buttons = fill_template("tmp/pagelib.template", "get_button_layout_template", false, $params);
        $params["pagelist"] = get_css_box($pagename, $rolename, $buttons, NULL, 'pagename', "", false, true);
        $params["block"] = get_css_box("Title", "Content", $buttons, NULL, "page", "", false, true);
        $return = fill_template("tmp/themes.template", "theme_selector_right_template", false, $params);
    } else {
        $STYLES->preview = true;
        $default_list = get_custom_styles($pageid, $feature, $featureid);
        foreach ($default_list as $attribute => $style) {
            $value = clean_myvar_opt($attribute, "string", false);
            $styles[$attribute]["value"] = $value;
        }
        $STYLES->$feature = $styles;

        include_once($CFG->dirroot . '/features/' . $feature . '/' . $feature . 'lib.php');
        $function = "display_$feature";
        $return = $function($pageid, "side", $featureid);
        unset($STYLES->preview);
    }

    // Get page styles.
    $return .= styles_array_to_css($STYLES->page);

    ajax_return($return);
}

function show_styles() {
global $CFG, $USER;
    $feature = clean_myvar_req("feature", "string");
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    // Get page styles.
    $styles = get_styles($pageid, 0);
    $return = styles_array_to_css($styles);

    $params = [
        "featuretype" => "preview",
        "featureid" => "preview",
        "buttons" => icon("gear"),
        "icon" => icon("grip-vertical"),
    ];
    $buttons = fill_template("tmp/pagelib.template", "get_button_layout_template", false, $params);

    if ($feature == "page") {
        $pagename = get_db_field("name", "pages", "pageid = '$pageid'");
        $rolename = get_db_field("display_name", "roles", "roleid = " . user_role($USER->userid, $pageid));

        $params = [];
        $params["pagelist"] = get_css_box($pagename, $rolename, $buttons, NULL, 'pagename', NULL, '0', NULL, $pageid);
        $params["block"] = get_css_box("Title", "Content", $buttons, NULL, NULL, NULL, '0', NULL, $pageid);
        $p = [
            "left" => custom_styles_selector($pageid, $feature),
            "right" => fill_template("tmp/themes.template", "theme_selector_right_template", false, $params),
        ];
        $return .= fill_template("tmp/themes.template", "make_template_selector_panes_template", false, $p);
    } else {
          include_once($CFG->dirroot . '/features/' . $feature . '/' . $feature . 'lib.php');
          $function = "display_$feature";
        $p = [
            "left" => custom_styles_selector($pageid, $feature, $featureid),
            "right" => $function($pageid, "side", $featureid),
        ];
        $return .= fill_template("tmp/themes.template", "make_template_selector_panes_template", false, $p);
    }

    ajax_return($return);
}

function change_theme_save() {
global $CFG;
    $themeid = clean_myvar_opt("themeid", "int", 0);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    // Get page styles.
    $styles = get_styles($pageid, $themeid);
    $return = styles_array_to_css($styles);

    //Save selected Theme
    if (!$themeid && $pageid !== $CFG->SITEID) {
        execute_db_sql("DELETE FROM settings WHERE pageid = ||pageid|| AND setting_name = 'themeid'", ["pageid" => $pageid]);
    } else {
        save_setting(false, ["type" => "page", "pageid" => $pageid, "setting_name" => "themeid"], $themeid);
    }

    //Page has theme selected show themes
    $return .= theme_selector($pageid, $themeid);

    $return .= '<div id="change_saved" class="centered">Theme change saved.</div>';

    ajax_return($return);
}
?>
