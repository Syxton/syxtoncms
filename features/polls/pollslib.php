<?php
/***************************************************************************
* pollslib.php - Polls function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.3.0
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('POLLSLIB', true);

function display_polls($pageid, $area, $featureid=false) {
global $CFG, $USER, $ROLES;
    if (!$settings = fetch_settings("polls", $featureid, $pageid)) {
        save_batch_settings(default_settings("polls", $pageid, $featureid));
        $settings = fetch_settings("polls", $featureid, $pageid);
    }

    $title = $settings->polls->$featureid->feature_title->setting;
    $title = '<span class="box_title_text">' . $title . '</span>';
    $poll = get_db_row(fetch_template("dbsql/polls.sql", "get_poll", "polls"), ["pollid" => $featureid]);
    $time = get_timestamp();

    //Start Poll if past startdate
    $SQL = fetch_template("dbsql/polls.sql", "update_poll_status", "polls");
    if ($poll['startdate'] && $poll['status'] == 1) {
        if ($time > $poll['startdate']) {
            execute_db_sql($SQL, ["status" => 2, "pollid" => $featureid]);
        }
    }
    if ($poll['stopdate'] && $poll['status'] == 2) {
        if ($time > $poll['stopdate']) {
            execute_db_sql($SQL, ["status" => 3, "pollid" => $featureid]);
        }
    }
    if ($poll['startdate'] && $poll['status'] == 2) {
        if ($time < $poll['startdate']) {
            execute_db_sql($SQL, ["status" => 1, "pollid" => $featureid]);
        }
    }

    $viewpollability = user_is_able($USER->userid, "viewpolls", $pageid, "polls", $featureid);
    $takepollability = user_is_able($USER->userid, "takepolls", $pageid, "polls", $featureid);

    if ($viewpollability) {
        $buttons = get_button_layout("polls", $featureid, $pageid);

        if ($poll['status'] == '2') { // Poll is open
            if ($settings->polls->$featureid->allowmultiples->setting == "1") { // Multiple votes are allowed per IP
                if ($settings->polls->$featureid->totalvotelimit->setting != "0" || $settings->polls->$featureid->individualvotelimit->setting != "0") { //A limit is set
                    if ($settings->polls->$featureid->totalvotelimit->setting != "0" && $settings->polls->$featureid->totalvotelimit->setting <= get_db_count(fetch_template("dbsql/polls.sql", "get_responses", "polls"), ["pollid" => $featureid])) {
                        return get_css_box($title, get_poll_results($featureid, $area), $buttons, '0px', "polls", $featureid);
                    } elseif ($settings->polls->$featureid->individualvotelimit->setting != "0" && $settings->polls->$featureid->individualvotelimit->setting <= already_taken_poll($featureid)) {
                        return get_css_box($title, get_poll_results($featureid, $area), $buttons, '0px', "polls", $featureid);
                    } else { //No limits are met
                        return get_css_box($title, take_poll_form($pageid, $featureid, $area), $buttons, '0px', "polls", $featureid);
                    }
                } else { //A limit is not set
                    return get_css_box($title, take_poll_form($pageid, $featureid, $area), $buttons, '0px', "polls", $featureid);
                }
            } elseif (!already_taken_poll($featureid)) { //Multiple votes are not allowed and user has not voted
                return get_css_box($title, take_poll_form($pageid, $featureid, $area), $buttons, '0px', "polls", $featureid);
            } else { //Multiple votes are not allowed and user has already voted
                return get_css_box($title, get_poll_results($featureid, $area), $buttons, '0px', "polls", $featureid);
            }
        } elseif ($poll['status'] == '1') { //Poll is created but not yet open
            return get_css_box($title, locked_take_poll_form($pageid, $featureid, $area), $buttons, '0px', "polls", $featureid);
        } else { //Poll is closed so show results
            return get_css_box($title, get_poll_results($featureid, $area), $buttons, '0px', "polls", $featureid);
        }
    } else { return error_string("no_poll_permissions"); }
}

function already_taken_poll($pollid) {
global $CFG, $USER;
    $ip = get_ip_address();
    $userid = $USER->userid ?? 0;
    if ($result = get_db_result(fetch_template("dbsql/polls.sql", "poll_user_responses", "polls"), ["pollid" => $pollid, "userid" => $USER->userid, "ip" => $ip])) {
        return count_db_result($result);
    }
    return 0;
}

function get_poll_colors($pollid) {
    $colors = "";
     if ($result = get_db_result("SELECT * FROM polls_answers WHERE pollid='$pollid' ORDER BY sort")) {
        while ($answer = fetch_row($result)) {
            $color = sprintf("%02X%02X%02X", mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            $colors .= $colors == "" ? $color : ", $color";
        }
    }
    return $colors;
}

function get_poll_data($pollid) {
    $total = get_db_count(fetch_template("dbsql/polls.sql", "get_responses", "polls"), ["pollid" => $pollid]);
    $colors = ['#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', '#46f0f0', '#f032e6', '#bcf60c', '#fabebe', '#008080', '#e6beff', '#9a6324', '#fffac8', '#800000', '#aaffc3', '#808000', '#ffd8b1', '#000075', '#808080', '#ffffff', '#000000'];
    $datastring = '';
    if ($result = get_db_result(fetch_template("dbsql/polls.sql", "poll_data", "polls"), ["pollid" => $pollid])) {
        $c = -1;
        while ($data = fetch_row($result)) {
            $datastring .= ',["' . addslashes($data["answer"]) . '", ' . $data["stat"] . ', "' . $colors[++$c] . '"]';
            $perc = $total ? $data["stat"] / $total : 0;
        }
    }

    return $datastring;
}

function get_poll_legend($pollid) {
    $answers = "";
     if ($result = get_db_result("SELECT * FROM polls_answers WHERE pollid='$pollid' ORDER BY sort")) {
        while ($answer = fetch_row($result)) {
            $answers .= $answers == "" ? $answer["answer"] : "|" . $answer["answer"];
        }
    }
    return $answers;
}

function get_poll_results($pollid, $area = false) {
global $CFG;
    $popup = "";
    $script = get_js_tags(["scripts/frame_resize.js"]);
    $poll = get_db_row(fetch_template("dbsql/polls.sql", "get_poll", "polls"), ["pollid" => $pollid]);
    if (!get_db_row(fetch_template("dbsql/polls.sql", "get_answers", "polls"), ["pollid" => $pollid])) {
        $chart = '<div style="padding: 5px;text-align:center;">This poll is not configured.</div>';
    } elseif (get_db_row(fetch_template("dbsql/polls.sql", "get_responses", "polls"), ["pollid" => $pollid])) {
        $total = get_db_count(fetch_template("dbsql/polls.sql", "get_responses", "polls"), ["pollid" => $pollid]);
        $settings = fetch_settings("polls", $pollid, $poll["pageid"]);

        $p = [
            "title" => "See Full Size",
            "text" => "See Full Size",
            "path" => $CFG->wwwroot . "/features/polls/polls_graph.php?pollid=$pollid&area=middle",
            "iframe" => true,
            "width"=> "95%",
            "height"=> "95%",
            "icon" => icon("up-right-and-down-left-from-center"),
            "class" => "",
        ];
        $popup = '<div style="padding: 5px;text-align:center;margin: 10px">' . make_modal_links($p) . '</div>';
        $chart = '<div style="padding: 5px;text-align:center;">' . $total . ' Total Votes</div>';
        $chart .= '<iframe id="pollresults_' . $pollid . '" onload="resizeCaller(this.id);" src="' . $CFG->wwwroot . '/features/polls/polls_graph.php?pollid=' . $pollid . '" width="100%" frameborder="0"></iframe>' . $popup;
    } else {
        $chart = '<div style="padding: 5px;text-align:center;">No responses yet.</div>';
    }

    return $script . $chart;
}

function take_poll_form($pageid, $pollid, $area) {
    $answers = "";
    $poll = get_db_row("SELECT * FROM polls WHERE pollid = ||pollid||", ["pollid" => $pollid]);
    if ($result = get_db_result("SELECT * FROM polls_answers WHERE pollid = ||pollid|| ORDER BY sort", ["pollid" => $pollid])) {
        while ($answer = fetch_row($result)) {
            $answers .= '<input type="radio" name="poll' . $pollid . '" value="' . $answer["answerid"] . '" /> ' . htmlentities($answer["answer"]) . '<br />';
        }
    }

    ajaxapi([
        "id" => "submitanswer_$pollid",
        "url" => "/features/polls/polls_ajax.php",
        "data" => [
            "action" => "submitanswer",
            "pageid" => $pageid,
            "featureid" => $pollid,
            "extra" => "js||getRadioValue('poll" . $pollid . "')||js",
        ],
        "display" => "polldiv$pollid",
    ]);

    $form = '
        <span id="width_' . $pollid . '" style="width:100%;display:block;"></span>
        <div style="margin-right:auto;margin-left:auto;" id="polldiv' . $pollid . '">
            <div style="width:70%;margin: auto;">
                <br />
                <strong>Question:</strong>
                <br />
                ' . htmlentities($poll['question']) . '
                <br /><br />
                ' . $answers . '
                <br />
                <input id="submitanswer_' . $pollid . '" type="button" value="Submit" />
                <br /><br />
            </div>
        </div>';
    return $form;
}

function locked_take_poll_form($pageid, $pollid) {
global $CFG;
    $poll = get_db_row("SELECT * FROM polls WHERE pollid='$pollid'");
    $form = '<span id="width_' . $pollid . '" style="width:100%;display:block;"></span><div style="margin-right:auto;margin-left:auto;" id="polldiv' . $pollid . '">
    <div style="width:70%;margin: auto;">
    <br /><strong>Question:</strong><br />' . $poll['question'] . '
    <br /><br />';
    if ($result = get_db_result("SELECT * FROM polls_answers WHERE pollid='$pollid' ORDER BY sort")) {
        while ($answer = fetch_row($result)) {
            $form .= '<input type="radio" name="poll' . $pollid . '" value="' . $answer["answerid"] . '" disabled/> ' . $answer["answer"] . '<br />';
        }
    }
    $form .= '<br /><input type="button" value="Submit" disabled /><br /><br /></div></div>';
    return $form;
}

function polls_delete($pageid, $featureid) {
    try {
        start_db_transaction();

        $params = ["pageid" => $pageid, "featureid" => $featureid, "feature" => "polls", "pollid" => $featureid];

        $sql = [];
        $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature"];
        $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature_settings"];
        $sql[] = ["file" => "dbsql/polls.sql", "feature" => "polls", "subsection" => "delete_polls"];
        $sql[] = ["file" => "dbsql/polls.sql", "feature" => "polls", "subsection" => "delete_answers"];
        $sql[] = ["file" => "dbsql/polls.sql", "feature" => "polls", "subsection" => "delete_responses"];

        // Delete feature
        execute_db_sqls(fetch_template_set($sql), $params);

        resort_page_features($pageid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
}

function insert_blank_polls($pageid) {
global $CFG;
    $type = "polls";
    try {
        start_db_transaction();
        if ($featureid = execute_db_sql(fetch_template("dbsql/polls.sql", "insert_poll", $type), ["pageid" => $pageid])) {
            $area = get_db_field("default_area", "features", "feature = ||feature||", ["feature" => $type]);
            $sort = get_db_count(fetch_template("dbsql/features.sql", "get_features_by_page_area"), ["pageid" => $pageid, "area" => $area]) + 1;
            $params = [
                "pageid" => $pageid,
                "feature" => $type,
                "featureid" => $featureid,
                "sort" => $sort,
                "area" => $area,
            ];
            execute_db_sql(fetch_template("dbsql/features.sql", "insert_page_feature"), $params);
            commit_db_transaction();
            return $featureid;
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
    return false;
}

function polls_buttons($pageid, $featuretype, $featureid) {
global $USER;
    $return = "";

    $canedit = user_is_able($USER->userid, "editpolls", $pageid, "polls", $featureid);
    $caneditopen = user_is_able($USER->userid, "editopenpolls", $pageid, "polls", $featureid);
    $canopen = user_is_able($USER->userid, "openpolls", $pageid, "polls", $featureid);
    $canclose = user_is_able($USER->userid, "closepolls", $pageid, "polls", $featureid);

    $pollstatus = get_db_field("status", "polls", "pollid = ||pollid||", ["pollid" => $featureid]);
    if (($pollstatus < 2 && $canedit) || ($pollstatus == 2 && $caneditopen)) { //Poll not created yet
        $return .= make_modal_links([
            "title"=> "Edit Feature",
            "path" => action_path("polls") . "editpoll&pageid=$pageid&featureid=$featureid",
            "refresh" => "true",
            "iframe" => true,
            "width" => "800",
            "height" => "400",
            "icon" => icon("pencil"),
            "class" => "slide_menu_button"
        ]);
    }

    $status = ""; // 1 never been opened, 2 opened, 3 closed.
    $confirm = "";
    if ($pollstatus === 1 && $canopen) { // Poll is created but not opened
        $confirm = "Are you sure you would like to open this poll?  Once a poll is opened, it cannot be edited except by site admins.";
        $status = "open";
        $return .= '
            <button id="change_poll_status_' . $featureid . '" class="slide_menu_button alike" title="Open Poll">
                ' . icon("circle-play") . '
            </button> ';
    } elseif ($pollstatus === 2 && $canclose) { //Poll is opened
        $confirm = "Are you sure you would like to close this poll?  Once a poll is closed, it cannot be reopened.";
        $status = "close";
        $return .= '
            <button id="change_poll_status_' . $featureid . '" class="slide_menu_button alike" title="Close Poll">
                ' . icon("circle-stop") . '
            </button> ';
    }

    if ($canopen || $canclose) {
        // Change poll status.
        ajaxapi([
            "id" => "change_poll_status_$featureid",
            "if" => "confirm('$confirm')",
            "url" => "/features/polls/polls_ajax.php",
            "data" => [
                "action" => "change_poll_status",
                "pageid" => $pageid,
                "featureid" => $featureid,
                "extra" => $status,
            ],
            "display" => "polldiv$featureid",
            "ondone" => "pollstatus$featureid();",
        ]);

        // Update poll status.
        ajaxapi([
            "id" => "pollstatus$featureid",
            "url" => "/features/polls/polls_ajax.php",
            "data" => [
                "action" => "pollstatuspic",
                "pageid" => $pageid,
                "featureid" => $featureid,
                "extra" => "close",
            ],
            "display" => "pollstatus$featureid",
            "event" => "none",
        ]);
    }

    if (empty($return)) { return ""; }

    return '<span class="dynamicbuttons" id="pollstatus' . $featureid . '">' . $return . '</span>';
}

function polls_default_settings($type, $pageid, $featureid) {
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "Poll",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "setting_name" => "allowmultiples",
            "defaultsetting" => "0",
            "display" => "Allow Multiple Votes",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "totalvotelimit",
            "defaultsetting" => "0",
            "display" => "Total Vote Limit",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "< 0",
            "warning" => "Cannot be a negative number. (0 = no limit)",
        ],
        [
            "setting_name" => "individualvotelimit",
            "defaultsetting" => "0",
            "display" => "Individual Vote Limit",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "< 0",
            "warning" => "Cannot be a negative number. (0 = no limit)",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}
?>