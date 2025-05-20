<?php
/***************************************************************************
* pics.php - Pics modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.5.9
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
        $sub = '';
        while (!file_exists($sub . 'header.php')) {
            $sub = $sub == '' ? '../' : $sub . '../';
        }
        include($sub . 'header.php');
    }

    if (!defined('PICSLIB')) { include_once ($CFG->dirroot . '/features/pics/picslib.php'); }

    echo fill_template("tmp/page.template", "start_of_page_template", false, ["head" => get_js_tags(["features/pics/pics.js", "features/pics/uploads.js"])]);

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");
}

function pics_settings() {
global $CFG, $MYVARS, $USER;
    $featureid = clean_myvar_opt("featureid", "int", false); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $feature = "pics";

    //Default Settings
    $default_settings = default_settings($feature, $pageid, $featureid);

    //Check if any settings exist for this feature
    if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
    } else { //No Settings found...setup default settings
        if (save_batch_settings($default_settings)) {
            pics_settings();
        }
    }
}

function add_pics() {
global $CFG, $USER;
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    if (!user_is_able($USER->userid, "addpics", $pageid)) { trigger_error(getlang("no_permission", false, ["addpics"]), E_USER_WARNING); return; }

    $existing_galleries = get_db_result(fetch_template("dbsql/pics.sql", "get_page_galleries", "pics"), ["pageid" => $pageid]);

    ajaxapi([
        "id" => "new_gallery",
        "url" => "/features/pics/pics_ajax.php",
        "data" => [
            "action" => "new_gallery",
            "param" => "js||$('#new_gallery').val()||js",
            "pageid" => $pageid,
        ],
        "event" => "change",
        "display" => "gallery_name_div",
    ]);

    $params = [
        "wwwroot" => $CFG->wwwroot,
        "pageid" => $pageid,
        "featureid" => $featureid,
        "hide_select" => $existing_galleries ? '' : 'display:none;',
    ];

    echo fill_template("tmp/pics.template", "add_pics_form", "pics", $params);
}

function manage_pics() {
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    echo '
        <div id="pics_manager">
        ' . get_pics_manager($pageid, $featureid) . '
        </div>';
}
?>