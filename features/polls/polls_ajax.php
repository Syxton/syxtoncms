<?php
/***************************************************************************
* polls_ajax.php - Polls backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 1.4.2
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); }
if (!isset($POLLSLIB)) include_once ($CFG->dirroot . '/features/polls/pollslib.php');

update_user_cookie();

//Retrieve from Javascript
callfunction();

function poll_submit() {
global $CFG, $MYVARS;
	$pollid = dbescape($MYVARS->GET["pollid"]);
	$poll = get_db_row("SELECT * FROM polls WHERE pollid='$pollid'");
	$status = $poll['status'] == '2' ? 'status="2"' : 'status="1"';
	$question = $MYVARS->GET["question"]; $answers = trim($MYVARS->GET["answers"]);
	$startdate = $MYVARS->GET["startdate"]; $stopdate = $MYVARS->GET["stopdate"];

	if ($MYVARS->GET["startdateenabled"] && $startdate) { //checked box and a date is included.
		$startdate = ",startdate='".strtotime($startdate)."'";
	}elseif (!$MYVARS->GET["startdateenabled"] && $startdate) {
		$startdate = ",startdate='".strtotime($startdate)."'";
	}elseif (!$MYVARS->GET["startdateenabled"] && !$startdate) {
		$startdate = ",startdate='0'";
	}

	if ($MYVARS->GET["stopdateenabled"] && $stopdate) { //checked box and a date is included.
		$stopdate = ",stopdate='".strtotime($stopdate)."'";
	}elseif (!$MYVARS->GET["stopdateenabled"] && $stopdate) {
		$stopdate = ",stopdate='".strtotime($stopdate)."'";
	}elseif (!$MYVARS->GET["stopdateenabled"] && !$stopdate) {
		$stopdate = ",stopdate='0'";
	}

	$answers = explode(',', $answers);
	$i = 1;
	while (isset($answers[$i-1])) {
        if ($answer = get_db_row("SELECT * FROM polls_answers WHERE pollid='$pollid' AND answer='".$answers[$i-1]."'")) { //already exists so just update the sort
            execute_db_sql("UPDATE polls_answere SET sort='$i' WHERE answerid='".$answer["answerid"]."'");
        } else { //answer doesn't exist so make it
            $SQL = "INSERT INTO polls_answers (pollid,answer,sort) VALUES('$pollid','".$answers[$i-1]."','$i')";
            execute_db_sql($SQL);
        }
		$i++;
	}

    //Check for old answers and remove them
    if ($result = get_db_result("SELECT * FROM polls_answers WHERE pollid='$pollid'")) {
        while ($row = fetch_row($result)) {
       	    $i=1; $found=false;
            while (isset($answers[$i-1])) {
                if ($answers[$i-1] == $row["answer"]) {
                    $found=true;
                }
        		$i++;
        	}

            if (!$found) {
                execute_db_sql("DELETE FROM polls_answers WHERE answerid='".$row["answerid"]."'");
            }
        }
    }

    //Remove responses for answers that no longer exist
    execute_db_sql("DELETE FROM polls_response WHERE pollid='$pollid' AND answer NOT IN (SELECT answerid FROM polls_answers WHERE pollid='$pollid')");

	$SQL = "UPDATE polls SET $status, question='$question' $startdate $stopdate WHERE pollid='$pollid'";
 	if (execute_db_sql($SQL)) { echo "Poll edited successfully"; }
}

function submitanswer() {
global $CFG, $USER, $MYVARS;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $extra = $MYVARS->GET["extra"];

	$userid = is_logged_in() ? $USER->userid : '0';
	execute_db_sql("INSERT INTO polls_response (pollid,ip,userid,answer) VALUES('$featureid','".$USER->ip."','$userid','$extra')");
    $area = get_db_field("area","pages_features","feature='polls' AND featureid=$featureid");
	echo get_poll_results($featureid, $area);
}


function openpoll() {
global $CFG, $USER, $MYVARS;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $extra = $MYVARS->GET["extra"];
	$today = get_timestamp();
	execute_db_sql("UPDATE polls SET status='2',startdate='$today' WHERE pollid='$featureid'");
    $area = get_db_field("area","pages_features","feature='polls' AND featureid=$featureid");
	echo take_poll_form($pageid, $featureid, $area);
}

function closepoll() {
global $CFG, $USER, $MYVARS;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $extra = $MYVARS->GET["extra"];
	$today = get_timestamp();
	execute_db_sql("UPDATE polls SET status='3',stopdate='$today' WHERE pollid='$featureid'");
    $area = get_db_field("area","pages_features","feature='polls' AND featureid=$featureid");
	echo get_poll_results($featureid, $area);
}

function pollstatuspic() {
global $CFG, $MYVARS, $USER;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $extra = $MYVARS->GET["extra"];

	if ($extra == 'open') {
		$returnme = "";
		if (user_has_ability_in_page($USER->userid,"editopenpolls", $pageid,"polls", $featureid)) {
            $returnme .= make_modal_links(array("title"=> "Edit Feature","path"=>$CFG->wwwroot."/features/polls/polls.php?action=editpoll&amp;pageid=$pageid&amp;featureid=$featureid","refresh"=>"true","iframe"=>"true","width"=>"800","height"=>"400","image"=>$CFG->wwwroot."/images/edit.png"));
        }
        if (user_has_ability_in_page($USER->userid,"closepolls", $pageid,"polls", $featureid)) {
            $returnme .= '<a title="Close Poll" onclick="if (confirm(\'Are you sure you would like to close this poll?  Once a poll is closed, it cannot be reopened.\')) { ajaxapi(\'/features/polls/polls_ajax.php\',\'closepoll\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;extra=\',function() { simple_display(\'polldiv'.$featureid.'\'); ajaxapi(\'/features/polls/polls_ajax.php\',\'pollstatuspic\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;extra=close\',function() { simple_display(\'pollstatus'.$featureid.'\'); });}); }"><img src="'.$CFG->wwwroot.'/images/stop.png" alt="Close Poll" /></a> ';
        }
		echo $returnme;
	} else {
		donothing();
	}
}
?>
