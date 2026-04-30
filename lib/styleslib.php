<?php
/***************************************************************************
* styleslib.php - Styles and Theme function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.9
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('STYLESLIB', true);

$STYLES = (object) [];

function get_styles($pageid, $themeid = false, $feature = '', $featureid = '') {
global $CFG, $MYVARS;
    $pageid = clean_var_opt($pageid, "int", get_pageid());
    $themeid = clean_var_opt($themeid, "int", false);
    $feature = clean_var_opt($feature, "string", "page");
    $featureid = clean_var_opt($featureid, "int", false);

    // Start with a blank default list.
    $styles = [];
    $fn = $feature . "_default_styles";
    if (function_exists($fn)) {
        $styles = $fn();
    }

    error_log(print_r($styles, true));
    // THEME RULES
    // Default styles are given pageid = 0
    // Global styles are given forced = 1
    // Feature type specific styles are given featureid = 0
    $pageid = $pageid === $CFG->SITEID ? 0 : $pageid;
    $params = [
        "pageid" => $pageid,
        "themeid" => $themeid,
        "feature" => $feature,
        "featureid" => $featureid,
    ];

    if ($themeid === 0) { // CUSTOM THEME
        // Hasn't saved custom colors yet return defaults;
        if (!get_db_field("id", "styles", "pageid = ||pageid||", ["pageid" => $pageid])) {
            if ($custom_styles = get_custom_styles($pageid, $feature, $featureid)) {
                foreach ($custom_styles as $attribute => $custom) {
                    // Custom style was sent.
                    if ($value = clean_var_opt($attribute, "string", false)) {
                        // Style name matches a known style attribute.
                        if (isset($styles[$attribute])) {
                            $styles[$attribute]["value"] = $value;
                        }
                    }
                }
            }
            error_log(print_r($styles, true));
            return $styles;
        }
        $SQL = fetch_template("dbsql/styles.sql", "custom_theme_styles");
    } elseif ($themeid > 0) { // PAGE THEME IS SET TO A SAVED THEME
        $SQL = fetch_template("dbsql/styles.sql", "saved_theme_styles");
    } else { // NO THEME...LOOK FOR PARENT THEMES
        $params["themeid"] = get_page_themeid($CFG->SITEID);
        $SQL = fetch_template("dbsql/styles.sql", "parent_theme_styles");
    }

    
    if ($result = get_db_result($SQL, $params)) {
        while ($row = fetch_row($result)) {
            if (isset($styles[$row["attribute"]])) {
                $styles[$row["attribute"]]["value"] = $row["value"];
            }
        }
    }

    return $styles;
}

function styles_array_to_css($styles = []) {
    $css = '<style>';
    $root = "";
    foreach ($styles as $attribute => $style) {
        $css .= '
            .style_' . $attribute . ' {
                ' . $style["style"] . ': var(--' . $attribute . ');
            }
        ';

        $root .= '--' . $attribute . ': ' . $style["value"] . ';';
    }
    $css .= ":root { $root }" . "</style>";
    return $css;
}

function theme_selector($pageid, $themeid, $feature="page", $checked1="checked", $checked2="") {
global $CFG, $MYVARS, $USER;
    $params =[
        "pageid" => $pageid,
        "feature" => $feature,
        "checked1" => $checked1,
        "checked2" => $checked2,
        "iscustom" => empty($themeid),
    ];

    ajaxapi([
        "id" => "change_theme_save",
        "url" => "/ajax/themes_ajax.php",
        "data" => [
            "action" => "change_theme_save",
            "pageid" => $pageid,
            "themeid" => "js||encodeURIComponent($('#themes').val())||js",
        ],
        "display" => "themes_page",
        "ondone"  => "setTimeout(function(){ $('#change_saved').remove();}, 3000);",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "themes",
        "url" => "/ajax/themes_ajax.php",
        "data" => [
            "action" => "theme_change",
            "pageid" => $pageid,
            "themeid" => "js||encodeURIComponent($('#themes').val())||js",
        ],
        "display" => "color_preview",
        "event" => "change",
    ]);

    $themeselector = [
        "properties" => [
            "name" => "themes",
            "id" => "themes",
            "style" => "width:225px;",
        ],
        "values" => get_db_result(fetch_template("dbsql/styles.sql", "theme_selector_sql", false, ["notsite" => ($pageid != $CFG->SITEID)])),
        "valuename" => "themeid",
        "displayname" => "name",
        "selected" => $themeid,
    ];
    $params["menu"] = make_select($themeselector);
    $tabs = fill_template("tmp/themes.template", "theme_selector_tabs_template", false, $params);
    $left = $tabs . fill_template("tmp/themes.template", "theme_selector_left_template", false, $params);

    $title = get_db_field("name", "pages", "pageid = ||pageid||", ["pageid" => $pageid]);

    $params = [
        "featuretype" => "preview",
        "featureid" => "preview",
        "buttons" => icon("gear"),
        "icon" => icon("grip-vertical"),
    ];
    $buttons = fill_template("tmp/pagelib.template", "get_button_layout_template", false, $params);
    $params["pagelist"] = get_css_box($title, "", $buttons, NULL, 'pagename', NULL, $themeid, null, $pageid);
    $params["block"] = get_css_box('Title', "Content", $buttons, null, null, null, $themeid, null, $pageid);
    $right = fill_template("tmp/themes.template", "theme_selector_right_template", false, $params);

    return fill_template("tmp/themes.template", "make_template_selector_panes_template", false, ["left" => $left, "right" => $right]);
}

function custom_styles_selector($pageid, $feature, $featureid=false) {
    global $CFG;
        $params = [
            "pageid" => $pageid,
            "feature" => $feature,
            "featureid" => $featureid,
            "checked1" => "",
            "checked2" => "checked",
            "iscustom" => ($feature == "page"),
        ];
        $tabs = fill_template("tmp/themes.template", "theme_selector_tabs_template", false, $params);

        // Styles function
        $styles = $feature . '_default_styles';
        $styles = $styles();
        $revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;

        if ($feature != "page") {
            include_once($CFG->dirroot . '/features/' . $feature. "/" . $feature . 'lib.php');
        }

        $style_inputs = "";
        foreach ($styles as $attribute => $style) { // go through each style type and see if there is a db setting that can replace it.
            $p = [
                "pageid" => $revised_pageid,
                "attribute" => $attribute,
            ];
            $featuresql = "";
            if ($feature !== "page") {
                $p["featureid"] = $featureid;
                $p["feature"] = $feature;
                $featuresql = " AND feature = ||feature|| AND featureid = ||featureid|| ";
            }
            $value = get_db_field("value", "styles", "themeid = 0 AND attribute = ||attribute|| AND pageid = ||pageid|| $featuresql ORDER BY pageid DESC", $p);
            if (!$value) { // No db value found, use the hard coded default value.
                $value = $style['value'];
            }
            $style_inputs .= fill_template("tmp/themes.template", "style_inputs_template", false, [
                "attribute" => $attribute,
                "title" => $style["title"], 
                "value" => $value,
            ]);
        }

        ajaxapi([
            "id" => "save_custom_theme",
            "url" => "/ajax/themes_ajax.php",
            "reqstring" => "colors",
            "data" => [
                "action" => "save_custom_theme",
                "pageid" => $pageid,
                "feature" => $feature,
                "featureid" => $featureid,
            ],
            "ondone" => "location.reload(true);",
        ]);

        $params["style_inputs"] = $style_inputs;
        return $tabs . fill_template("tmp/themes.template", "custom_styles_selector_template", false, $params);
}

function get_custom_styles($pageid, $feature = "page", $featureid = false) {
global $CFG;
    // Styles function
    $fn = $feature . '_default_styles';
    $styles = [];
    if (function_exists($fn)) {
        $styles = $fn();
    }

    $revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;

    if ($feature != "page") {
        include_once($CFG->dirroot . '/features/' . $feature. "/" . $feature . 'lib.php');
    }

    foreach ($styles as $attribute => $style) { // go through each style type and see if there is a db setting that can replace it.
        if ($feature == "page") {
            $SQL = "themeid=0 AND attribute='" . $attribute ."' AND pageid='$revised_pageid' AND feature <= '' AND featureid <= 0 ORDER BY pageid DESC";
        } else {
            $SQL = "themeid=0 AND attribute='" . $attribute ."' AND pageid='$revised_pageid' AND feature='$feature' AND featureid='$featureid' ORDER BY pageid DESC";
        }
        $value = get_db_field("value", "styles", $SQL);
        if ($value) { // No db value found, use the hard coded default value.
            $styles[$attribute]["value"] = $value;
        }
    }

    return $styles;
}

function get_page_themeid($pageid) {
    $featureid = false;
    $settings = fetch_settings("page", $featureid, $pageid);

    if ($settings === false) {
         return "";
  } else {
    if (isset($settings->page->themeid->setting)) {
      return $settings->page->themeid->setting;
    } else {
      return "";
    }
  }
}

/**
 * Make or update a style setting.
 *
 * If id is not provided in $params, it will try to find the id based on
 * the values provided. If id is found, it will update the setting. If
 * id is not found, it will insert a new setting.
 *
 * @param array $params An array of settings objects with the following keys
 *   - id: The id of the setting to update. If not provided it will be found.
 *   - feature: The feature of the setting.
 *   - pageid: The pageid of the setting.
 *   - featureid: The featureid of the setting.
 *   - attribute: The attribute of the setting.
 *   - themeid: The themeid of the setting.
 *   - value: The value of the setting.
 *
 * @return integer|boolean The id of the setting that was made/updated or false
 *   if the statement failed.
 */
function make_or_update_styles($params = []) {
    $fields = ["feature", "pageid", "featureid", "attribute", "themeid"];
    $sqlfields = "";
    $sqlvalues = "";

    // Check if id was not provided but can be found.
    if (!isset($params["id"])) {
        $idsql = "";
        foreach ($fields as $field) {
            if (isset($params[$field]) && $params[$field] !== false) {
                $idsql .= $idsql == "" ? "" : " AND "; // Add AND if not first field.
                $idsql .= "$field = '" . $params[$field] . "'";
            }
        }

        // Make sure you have enough info to find only a single setting.
        if ($idsql !== "" && get_db_count("SELECT * FROM styles WHERE $idsql") == 1) {
            $params["id"] = get_db_field("id", "styles", $idsql);
        }
    }

    $fields[] = "value"; // Add value field to list.
    if (isset($params["id"])) { // Update statement.
        $vars["id"] = $params["id"];
        foreach ($fields as $field) {
            if (isset($params[$field]) && $params[$field] !== false) { // Check $value is set.
                $sqlfields .= empty($sqlfields) ? "" : ", "; // Add comma if not first field.
                $sqlfields .= "$field = '" . $params[$field] . "'";
            }
        }
        $SQL = "UPDATE styles SET $sqlfields WHERE id = '" . $vars["id"] . "'";
    } else { // Insert statement.
        foreach ($fields as $field) {
            if (isset($params[$field]) && $params[$field] !== false) { // Check if field is set.
                $sqlfields .= empty($sqlfields) ? "" : ", "; // Add comma if not first field.
                $sqlfields .= "$field"; // Add field to list of fields.
                $sqlvalues .= empty($sqlvalues) ? "" : ", "; // Add comma if not first field.
                $sqlvalues .= "'" . $params[$field] . "'"; // Add value to list of values.
            }
        }
        $SQL = "INSERT INTO styles($sqlfields) VALUES($sqlvalues)";
    }

    // Whether insert or update statement succeeded we will get the settingid.
    return execute_db_sql($SQL);
}

/**
 * Update settings array with new settings or update existing settings
 *
 * @param array $settings An array of settings objects
 *
 * @return boolean Returns true if all settings were updated or inserted successfully
 */
function make_or_update_styles_array($params) {
    /* Loop through each setting and make it */
    foreach ($params as $p) {
        /* Make or update the setting */
        if (!make_or_update_styles($p)) {
            /* If one setting fails, return false */
            return false;
        }
    }
    /* Return true if all settings were updated or inserted */
    return true;
}
?>
