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
	$question = trim(clean_myvar_req("question", "string"));
	$answers = trim(clean_myvar_req("answers", "string"));
	$startdate = clean_myvar_opt("startdate", "string", 0);
	$stopdate = clean_myvar_opt("stopdate", "string", 0);
	$startenabled = clean_myvar_opt("startdateenabled", "bool", false);
	$stopenabled = clean_myvar_opt("stopdateenabled", "bool", false);

	$startdate = !$startenabled && !$startdate ? 0 : (!$startdate ? 0 : strtotime($startdate));
	$stopdate = !$stopenabled && !$stopdate ? 0 : (!$stopdate ? 0 : strtotime($stopdate));

	try {
		start_db_transaction();
		$answers = explode(',', $answers);
		$i = 1;
		while (isset($answers[$i-1])) {
			// Check if this poll answeralready exists so we just update the sort
			if ($answer = get_db_row(fetch_template("dbsql/polls.sql", "check_existing_answer", "polls"), ["pollid" => $pollid, "answer" => $answers[$i - 1]])) {
				execute_db_sql(fetch_template("dbsql/polls.sql", "update_answer_sort", "polls"), ["sort" => $i, "answerid" => $answer["answerid"]]);
			} else { // Answer doesn't exist so we insert it.
				execute_db_sql(fetch_template("dbsql/polls.sql", "insert_answer", "polls"), ["pollid" => $pollid, "answer" => $answers[$i-1], "sort" => $i]);
			}
			$i++;
		}

		//Check for old answers and remove them
		if ($result = get_db_result(fetch_template("dbsql/polls.sql", "get_answers", "polls"), ["pollid" => $pollid])) {
			while ($row = fetch_row($result)) {
				$found = false; // reset found flag.
				$i = 1;
				while (isset($answers[$i-1])) {
					if ($answers[$i-1] == $row["answer"]) {
						$found = true;
					}
					$i++;
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
		echo "Poll edited successfully";
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
		echo "Poll edit failed";
	}
}

function submitanswer() {
global $CFG, $USER, $MYVARS;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$featureid = clean_myvar_opt("featureid", "int", false);
	$extra = $MYVARS->GET["extra"];

	$userid = is_logged_in() ? $USER->userid : 0;
	$ip = get_ip_address();
	execute_db_sql("INSERT INTO polls_response (pollid,ip,userid,answer) VALUES('$featureid','" . $ip."','$userid','$extra')");
	$area = get_db_field("area", "pages_features", "feature='polls' AND featureid=$featureid");
	echo get_poll_results($featureid, $area);
}


function openpoll() {
global $CFG, $USER, $MYVARS;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$featureid = clean_myvar_opt("featureid", "int", false);
	$extra = $MYVARS->GET["extra"];
	$today = get_timestamp();
	execute_db_sql("UPDATE polls SET status='2',startdate='$today' WHERE pollid='$featureid'");
	$area = get_db_field("area", "pages_features", "feature='polls' AND featureid=$featureid");
	echo take_poll_form($pageid, $featureid, $area);
}

function closepoll() {
global $CFG, $USER, $MYVARS;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$featureid = clean_myvar_opt("featureid", "int", false);
	$extra = $MYVARS->GET["extra"];
	$today = get_timestamp();
	execute_db_sql("UPDATE polls SET status='3',stopdate='$today' WHERE pollid='$featureid'");
	$area = get_db_field("area", "pages_features", "feature='polls' AND featureid=$featureid");
	echo get_poll_results($featureid, $area);
}

function pollstatuspic() {
global $CFG, $MYVARS, $USER;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$featureid = clean_myvar_opt("featureid", "int", false);
	$extra = $MYVARS->GET["extra"];

	if ($extra == 'open') {
		$returnme = "";
		if (user_is_able($USER->userid, "editopenpolls", $pageid, "polls", $featureid)) {
				$returnme .= make_modal_links(["title"=> "Edit Feature", "class" => "slide_menu_button", "path" => action_path("polls") . "editpoll&pageid=$pageid&featureid=$featureid", "refresh" => "true", "iframe" => true, "width" => "800", "height" => "400", "icon" => icon("pencil")]);
		}
		if (user_is_able($USER->userid, "closepolls", $pageid, "polls", $featureid)) {
				$returnme .= '<button class="slide_menu_button alike" title="Close Poll" onclick="if (confirm(\'Are you sure you would like to close this poll?  Once a poll is closed, it cannot be reopened.\')) { ajaxapi_old(\'/features/polls/polls_ajax.php\',\'closepoll\',\'&pageid=' . $pageid . '&featureid=' . $featureid . '&extra=\',function() { simple_display(\'polldiv' . $featureid . '\'); ajaxapi_old(\'/features/polls/polls_ajax.php\',\'pollstatuspic\',\'&pageid=' . $pageid . '&featureid=' . $featureid . '&extra=close\',function() { simple_display(\'pollstatus' . $featureid . '\'); });}); }">' . icon("circle-stop") . '</button> ';
		}
		echo $returnme;
	} else {
		emptyreturn();
	}
}
?>