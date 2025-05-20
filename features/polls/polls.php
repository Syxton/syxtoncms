<?php
/***************************************************************************
* polls.php - modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.9.6
***************************************************************************/

if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
        $sub = '';
        while (!file_exists($sub . 'header.php')) {
            $sub = $sub == '' ? '../' : $sub . '../';
        }
        include($sub . 'header.php');
    }

    echo fill_template("tmp/page.template", "start_of_page_template", false, ["head" => js_script_wrap($CFG->wwwroot . '/features/polls/polls.js')]);

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");

}

function polls_settings() {
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $feature = "polls";

    //Default Settings
    $default_settings = default_settings($feature, $pageid, $featureid);

    //Check if any settings exist for this feature
    if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
    } else { //No Settings found...setup default settings
        if (save_batch_settings($default_settings)) { polls_settings(); }
    }

}

function editpoll() {
global $USER;
    date_default_timezone_set("UTC");
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $pollid = clean_myvar_req("featureid", "int");

    if (!user_is_able($USER->userid, "editpolls", $pageid)) {
        trigger_error(getlang("no_permission", false, ["editpolls"]), E_USER_WARNING);
        return;
    }

    $row = get_db_row("SELECT * FROM polls WHERE pollid='$pollid'");
    $savedstart = $row['startdate'] ? date('Y-m-d', $row['startdate']) : '';
    $startdate = $row['startdate'] ? '<div id="savedstartdatediv" style="color:gray;display:inline;">Currently set for: ' . date('l dS \of F Y', $row['startdate']) . ' <input type="button" value="Clear" onclick="zeroout(\'savedstartdate\');" /></div>' : false;
    $savedstop = $row['stopdate'] ? date('Y-m-d', $row['stopdate']) : '';
    $stopdate = $row['stopdate'] ? '<div id="savedstopdatediv" style="color:gray;display:inline;">Currently set for: ' . date('l dS \of F Y', $row['stopdate']) . ' <input type="button" value="Clear" onclick="zeroout(\'savedstopdate\');" /></div>' : false;

    $answers = "";
    if ($result = get_db_result("SELECT * FROM polls_answers WHERE pollid='$pollid' ORDER BY sort")) {
        while ($answer = fetch_row($result)) {
            $answers .= $answers == "" ? $answer["answer"] : "," . $answer["answer"];
        }
    }

    echo get_js_tags(["jquery"]);

    ajaxapi([
        "id" => "polls_edit_form",
        "if" => "valid_poll_fields()",
        "url" => "/features/polls/polls_ajax.php",
        "data" => [
            "action" => "poll_submit",
            "question" => "js||encodeURIComponent($('#polls_question').val())||js",
            "answers" => "js||encodeURIComponent($('#polls_answers').val())||js",
            "startdateenabled" => "js||$('#startdateenabled').is(':checked')||js",
            "stopdateenabled" => "js||$('#stopdateenabled').is(':checked')||js",
            "startdate" => "js||$('#startdateenabled').is(':checked') ? $('#startdate').val() : $('#savedstartdate').val()||js",
            "stopdate" => "js||$('#stopdateenabled').is(':checked') ? $('#stopdate').val() : $('#savedstopdate').val()||js",
            "pollid" => $pollid,
        ],
        "ondone" => "close_modal();",
        "event" => "submit",
    ]);

    $params = [];
    $params["savedstart"] = $savedstart;
    $params["savedstop"] = $savedstop;
    $params["startdate"] = $startdate;
    $params["stopdate"] = $stopdate;
    $params["question"] = $row["question"];
    $params["answers"] = $answers;
    $params["pollid"] = $pollid;
    echo fill_template("tmp/polls.template", "polls_edit_form", "polls", $params);
    echo js_code_wrap('prepareInputsForHints();');
}
?>