<?php
/***************************************************************************
* polls_ajax.php - Polls backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.4.2
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}

if (!defined('POLLSLIB')) { include_once ($CFG->dirroot . '/features/polls/pollslib.php'); }

update_user_cookie();

//Retrieve from Javascript
callfunction();

function poll_submit() {
global $CFG, $MYVARS;
    $pollid = clean_myvar_req("pollid", "int");
    $poll = get_db_row(fetch_template("dbsql/polls.sql", "get_poll", "polls"), ["pollid" => $pollid]);

    $status = $poll['status'] === 2 ? 2 : 1;
    $question = trim(clean_myvar_req("question", "html"));
    $answers = trim(rawurldecode(clean_myvar_req("answers", "html")));
    $startdate = clean_myvar_opt("startdate", "string", 0);
    $stopdate = clean_myvar_opt("stopdate", "string", 0);
    $startenabled = clean_myvar_opt("startdateenabled", "bool", false);
    $stopenabled = clean_myvar_opt("stopdateenabled", "bool", false);

    $startdate = !$startenabled && !$startdate ? 0 : (!$startdate ? 0 : strtotime($startdate));
    $stopdate = !$stopenabled && !$stopdate ? 0 : (!$stopdate ? 0 : strtotime($stopdate));

    $return = $error = "";
    try {
        start_db_transaction();

        $answers = explode(',', $answers);

        $i = 1;
        while (isset($answers[$i - 1])) {
            // Check if this poll answer already exists so we just update the sort
            if ($answer = get_db_row(fetch_template("dbsql/polls.sql", "check_existing_answer", "polls"), ["pollid" => $pollid, "answer" => $answers[$i - 1]])) {
                execute_db_sql(fetch_template("dbsql/polls.sql", "update_answer_sort", "polls"), ["sort" => $i, "answerid" => $answer["answerid"]]);
            } else { // Answer doesn't exist so we insert it.
                execute_db_sql(fetch_template("dbsql/polls.sql", "insert_answer", "polls"), ["pollid" => $pollid, "answer" => $answers[$i - 1], "sort" => $i]);
            }
            $i++;
        }

        // Check for old answers and remove them
        if ($result = get_db_result(fetch_template("dbsql/polls.sql", "get_answers", "polls"), ["pollid" => $pollid])) {
            while ($row = fetch_row($result)) {
                $found = false; // reset found flag.
                foreach ($answers as $answer) {
                    if ($found = $answer == $row["answer"]) {
                        break;
                    }
                }

                if (!$found) {
                    execute_db_sql(fetch_template("dbsql/polls.sql", "delete_answer", "polls"), ["answerid" => $row["answerid"]]);
                }
            }
        }

        // Remove responses for answers that no longer exist
        execute_db_sql(fetch_template("dbsql/polls.sql", "delete_deprecated_responses", "polls"), ["pollid" => $pollid]);

        $params = [
            "status" => $status,
            "question" => $question,
            "startdate" => $startdate,
            "stopdate" => $stopdate,
            "pollid" => $pollid,
        ];
        execute_db_sql(fetch_template("dbsql/polls.sql", "update_poll", "polls"), $params);
        commit_db_transaction();
        $return = "Poll edited successfully";
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = "Poll edit failed";
    }

    ajax_return($return, $error);
}

function submitanswer() {
global $CFG, $USER;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $pollid = clean_myvar_opt("featureid", "int", false);
    $answer = clean_myvar_req("extra", "int");

    $return = $error = "";
    try {
        $userid = is_logged_in() ? $USER->userid : 0;
        $ip = get_ip_address();
        $SQL = "INSERT INTO polls_response (pollid,ip,userid,answer) VALUES(||pollid||, ||ip||, ||userid||, ||answer||)";
        execute_db_sql($SQL, ["pollid" => $pollid, "ip" => get_ip_address(), "userid" => $userid, "answer" => $answer]);

        $data = get_feature_data("polls", $pollid);
        $area = $data["area"];

        $return = get_poll_results($pollid, $area);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    ajax_return($return, $error);
}


function change_poll_status() {
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $featureid = clean_myvar_opt("featureid", "int", false);
    $extra = clean_myvar_opt("extra", "string", "open");

    $return = $error = "";
    try {
        if ($extra === "open") {
            $status = 2; // open
            $datetochange = "startdate";
        } else {
            $status = 3; // lcosed (has been opened before)
            $datetochange = "stopdate";
        }

        $SQL = "UPDATE polls SET status = ||status||, $datetochange = ||now|| WHERE pollid = ||featureid||";
        execute_db_sql($SQL, ["status" => $status, "featureid" => $featureid, "now" => get_timestamp()]);
        $data = get_feature_data("polls", $featureid);
        $area = $data["area"];

        if ($extra === "open") {
            $return = take_poll_form($pageid, $featureid, $area);
        } else {
            $return = get_poll_results($featureid, $area);
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function pollstatuspic() {
global $USER;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $featureid = clean_myvar_opt("featureid", "int", false);
    $extra = clean_myvar_opt("extra", "string", "open");

    $return = $error = "";
    try {
        if ($extra === 'open') {
            if (user_is_able($USER->userid, "editopenpolls", $pageid, "polls", $featureid)) {
                $return .= make_modal_links([
                    "title"=> "Edit Feature",
                    "class" => "slide_menu_button",
                    "path" => action_path("polls") .
                    "editpoll&pageid=$pageid&featureid=$featureid",
                    "refresh" => "true",
                    "iframe" => true,
                    "width" => "800",
                    "height" => "400",
                    "icon" => icon("pencil"),
                ]);
            }

            if (user_is_able($USER->userid, "closepolls", $pageid, "polls", $featureid)) {
                // Close poll.
                ajaxapi([
                    "id" => "change_poll_status_$featureid",
                    "if" => "confirm('Are you sure you would like to close this poll?  Once a poll is closed, it cannot be reopened.')",
                    "url" => "/features/polls/polls_ajax.php",
                    "data" => [
                        "action" => "change_poll_status",
                        "pageid" => $pageid,
                        "featureid" => $featureid,
                        "extra" => "close",
                    ],
                    "display" => "polldiv$featureid",
                    "ondone" => "pollstatus$featureid();",
                ]);

                // Update poll status.
                ajaxapi([
                    "id" => "pollstatus$featureid",
                    "if" => "confirm('Do you want to delete this image?')",
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
                $return .= '
                    <button id="change_poll_status_' . $featureid . '" class="slide_menu_button alike" title="Close Poll">
                        ' . icon("circle-stop") . '
                    </button> ';
            }
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}
?>