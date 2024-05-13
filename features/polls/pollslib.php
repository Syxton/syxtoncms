<?php
/***************************************************************************
* pollslib.php - Polls function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.3.0
***************************************************************************/

if (!LIBHEADER) {
	$sub = './';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == './' ? '../' : $sub . '../';
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
	$poll = get_db_row("SELECT * FROM polls WHERE pollid='$featureid'");
	$time = get_timestamp();
	
	//Start Poll if past startdate
	if ($poll['startdate'] && $poll['status'] == 1) {
		if ($time > $poll['startdate']) execute_db_sql("UPDATE polls SET status='2' WHERE pollid='$featureid'");
	}
	if ($poll['stopdate'] && $poll['status'] == 2) {
		if ($time > $poll['stopdate']) execute_db_sql("UPDATE polls SET status='3' WHERE pollid='$featureid'");
	}
	if ($poll['startdate'] && $poll['status'] == 2) {
		if ($time < $poll['startdate']) execute_db_sql("UPDATE polls SET status='1' WHERE pollid='$featureid'");
	}
	
	$viewpollability = user_is_able($USER->userid, "viewpolls", $pageid, "polls", $featureid);
	$takepollability = user_is_able($USER->userid, "takepolls", $pageid, "polls", $featureid);
	
	if ($viewpollability) {
		$buttons = get_button_layout("polls", $featureid, $pageid);
		
		if ($poll['status'] == '2') { //Poll is open
			if ($settings->polls->$featureid->allowmultiples->setting == "1") { //Multiple votes are allowed per IP
				if ($settings->polls->$featureid->totalvotelimit->setting != "0" || $settings->polls->$featureid->individualvotelimit->setting != "0") { //A limit is set
					if ($settings->polls->$featureid->totalvotelimit->setting != "0" && $settings->polls->$featureid->totalvotelimit->setting <= get_db_count("SELECT * FROM polls_response WHERE pollid='$featureid'")) {
						return get_css_box($title, get_poll_results($featureid, $area), $buttons, '0px', "polls", $featureid);
					} elseif ($settings->polls->$featureid->individualvotelimit->setting != "0" && $settings->polls->$featureid->individualvotelimit->setting <= get_db_count("SELECT * FROM polls_response WHERE pollid='$featureid' AND (userid='" . $USER->userid."' OR ip='" . $USER->ip."')")) {
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
	if (get_db_field("id", "polls_response", "pollid='$pollid' AND (userid='" . $USER->userid."' OR ip='" . $USER->ip."')")) { return true; }
    return false;
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
    $total = get_db_count("SELECT * FROM polls_response WHERE pollid='$pollid'");
    $area = get_db_field("area", "pages_features", "feature='polls' AND featureid=$pollid");

    $data = $label = "";
     if ($result = get_db_result("SELECT COUNT(*) as count,a.sort,a.answerid FROM polls_response r JOIN polls_answers a ON r.answer=a.answerid WHERE r.pollid='$pollid' GROUP BY r.answer ORDER BY a.sort")) {
        $i = 0;
        while ($answer = fetch_row($result)) {
            $perc = $answer["count"]/$total;
            $data .= $data == "" ? $perc : "|$perc";
            
            if ($area == "middle") {
                $label .= $label == "" ? "N*p1*,000000, $i,,11,,h:20" : "|N*p1*,000000, $i,,11,,h:20";    
            } else {
                $label .= $label == "" ? "N*p1*,000000, $i,,11" : "|N*p1*,000000, $i,,11";    
            }
            
            $i++;
        }
    }
    
    return $data . "&chm=$label";   
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

function get_poll_results($pollid, $area=false) {
global $CFG;
    $poll = get_db_row("SELECT * FROM polls WHERE pollid='$pollid'");
    if (!get_db_row("SELECT * FROM polls_answers WHERE pollid='$pollid'")) {
       $chart = "<br />This poll is not setup yet.<br /><br />"; 
    } elseif (get_db_row("SELECT * FROM polls_response WHERE pollid='$pollid'")) {
        $area = $area ? $area : get_db_field("area", "pages_features", "feature='polls' AND featureid=$pollid");
  		$total = get_db_count("SELECT * FROM polls_response WHERE pollid='$pollid'");
        $settings = fetch_settings("polls", $pollid, $poll["pageid"]);
  		//$title = $settings->polls->$featureid->feature_title->setting;
        $charttype = $area == "middle" ? "cht=bhg" : "cht=bvg"; //horizontal bar graph
        $chartwidth = $area == "middle" ? "1000" : "500";
        $chartheight = $area == "middle" ? "250" : "400";
        $labelstyles = "&chxs=0,676767,11.5,-0.667,lt,676767";
        $showaxis = $area == "middle" ? "&chxt=x" : "&chxt=y";
        $barWandS = "&chbh=a,3"; //automatic bar width
        $chartsize = "&chs=" . $chartwidth."x" . $chartheight;
        $chartcolors = "&chco=" . get_poll_colors($pollid);
        $chartdata = "&chds=0,1&chd=t:" . get_poll_data($pollid); //t: text formatted
        $answerkey = "&chdl=" . rawurlencode(get_poll_legend($pollid));
        $gridlines = $area == "middle" ? "&chg=10,0,0,0" : "&chg=0,10,0,0"; //every 20 percentage points
        $title = "&chtt=" . rawurlencode(get_db_field("question", "polls", "pollid=$pollid"));     
        $chart = make_modal_links(["title"=> "See Full Size", "path" => "//chart.apis.google.com/chart?" . $charttype.$chartsize.$chartcolors.$chartdata.$answerkey.$gridlines.$title.$showaxis.$barWandS . '&width=' . ($chartwidth+25) . '&height=' . ($chartheight+25),"height" => ($chartheight+25),"gallery" => $title,"width" => ($chartwidth+25),"image" => "http://chart.apis.google.com/chart?" . $charttype.$chartsize.$chartcolors.$chartdata.$answerkey.$gridlines.$title.$showaxis.$barWandS,"imagestyles" => "width:100%"]);
        $chart .= '<table style="width:100%; text-align:center;"><tr><td>' . $total . ' Total Votes</td></tr></table>';
    } else { $chart = "<br />No responses yet.<br /><br />"; }
     
	return '<div id="resultsdiv_' . $pollid . '" style="display:block;margin-left:auto;margin-right:auto;width:100%;text-align:center;">' . $chart . '</div>';
}

function take_poll_form($pageid, $pollid, $area) {
global $CFG;
	$poll = get_db_row("SELECT * FROM polls WHERE pollid='$pollid'");
    $form = '<span id="width_' . $pollid . '" style="width:100%;display:block;"></span><div style="margin-right:auto;margin-left:auto;" id="polldiv' . $pollid . '">
	<div style="width:70%;margin: auto;">
    <br /><strong>Question:</strong><br />' . $poll['question'] . '
	<br /><br />';
    if ($result = get_db_result("SELECT * FROM polls_answers WHERE pollid='$pollid' ORDER BY sort")) {
        while ($answer = fetch_row($result)) {
            $form .= '<input type="radio" name="poll' . $pollid . '" value="' . $answer["answerid"] . '" /> ' . $answer["answer"] . '<br />';    
        }
    }

	$form .= '<br /><input type="button" value="Submit" onclick="ajaxapi(\'/features/polls/polls_ajax.php\',\'submitanswer\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $pollid . '&amp;extra=\' + getRadioValue(\'poll' . $pollid . '\'),function() { simple_display(\'polldiv' . $pollid . '\'); });" /><br /><br /></div></div>';
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
	$params = [
		"pageid" => $pageid,
		"featureid" => $featureid,
		"feature" => "polls",
	];

	$SQL = use_template("dbsql/features.sql", $params, "delete_feature");
    execute_db_sql($SQL);
    $SQL = use_template("dbsql/features.sql", $params, "delete_feature_settings");
    execute_db_sql($SQL);
	$SQL = use_template("dbsql/polls.sql", $params, "delete_polls", "polls");
    execute_db_sql($SQL);
	$SQL = use_template("dbsql/polls.sql", $params, "delete_answers", "polls");
    execute_db_sql($SQL);
	$SQL = use_template("dbsql/polls.sql", $params, "delete_responses", "polls");
    execute_db_sql($SQL);

	resort_page_features($pageid);
}

function insert_blank_polls($pageid) {
global $CFG;
	$type = "polls";
	if ($featureid = execute_db_sql("INSERT INTO polls (pageid) VALUES('$pageid')")) {
		$area = get_db_field("default_area", "features", "feature='polls'");
		$sort = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$area'") + 1;
		execute_db_sql("INSERT INTO pages_features (pageid,feature,sort,area,featureid) VALUES('$pageid','polls','$sort','$area','$featureid')");
		execute_db_sql("INSERT INTO settings (type,pageid,featureid,setting_name,setting,extra,defaultsetting) VALUES('$type'," . $pageid.", " . $featureid.",'feature_title','Blank Poll', '','Blank Poll'),('$type'," . $pageid.", " . $featureid.",'allowmultiples','0', '','0'),('$type'," . $pageid.", " . $featureid.",'totalvotelimit','0',NULL,'0'),('$type'," . $pageid.", " . $featureid.",'votelimit','0',NULL,'0')");
		return $featureid;
	}
	return false;
}

function polls_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
	$returnme = "";
	$pollstatus = get_db_field("status", "polls", "pollid='$featureid'");
    $returnme .= '<span id="pollstatus' . $featureid . '" style="display:inline;">';
	
    if (($pollstatus < 2 && user_is_able($USER->userid, "editpolls", $pageid, "polls", $featureid)) || ($pollstatus == 2 && user_is_able($USER->userid, "editopenpolls", $pageid, "polls", $featureid))) { //Poll not created yet
        $returnme .= make_modal_links(["title"=> "Edit Feature", "path" => action_path("polls") . "editpoll&amp;pageid=$pageid&amp;featureid=$featureid", "refresh" => "true", "iframe" => true, "width" => "800", "height" => "400", "image" => $CFG->wwwroot . "/images/edit.png", "class" => "slide_menu_button"]);
	}
    
    if ($pollstatus == '1' && user_is_able($USER->userid, "openpolls", $pageid, "polls", $featureid)) { //Poll is created but not opened
        $returnme .= ' <a class="slide_menu_button" title="Open Poll" onclick="if (confirm(\'Are you sure you would like to open this poll?  Once a poll is opened, it cannot be edited except by site admins.\')) { ajaxapi(\'/features/polls/polls_ajax.php\',\'openpoll\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;extra=\',function() { simple_display(\'polldiv' . $featureid . '\'); ajaxapi(\'/features/polls/polls_ajax.php\',\'pollstatuspic\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;extra=open\',function() { simple_display(\'pollstatus' . $featureid . '\'); });});} "><img src="' . $CFG->wwwroot . '/images/start.png" alt="Open Poll" /></a> ';
	} elseif ($pollstatus == '2' && user_is_able($USER->userid, "closepolls", $pageid, "polls", $featureid)) { //Poll is opened
        $returnme .= ' <a class="slide_menu_button" title="Close Poll" onclick="if (confirm(\'Are you sure you would like to close this poll?  Once a poll is closed, it cannot be reopened.\')) { ajaxapi(\'/features/polls/polls_ajax.php\',\'closepoll\',\'&amp;pageid=' . $pageid . '&amp;featuretype=polls&amp;functionname=closepoll&amp;featureid=' . $featureid . '&amp;extra=\',function() { simple_display(\'polldiv' . $featureid . '\'); ajaxapi(\'/features/polls/polls_ajax.php\',\'pollstatuspic\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;extra=close\',function() { simple_display(\'pollstatus' . $featureid . '\'); });});}"><img src="' . $CFG->wwwroot . '/images/stop.png" alt="Close Poll" /></a> ';
	}
    
    $returnme .= '</span>';
    $returnme = $returnme == '<span id="pollstatus' . $featureid . '" style="display:inline;"></span>' ? '' : $returnme;
	return $returnme;
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