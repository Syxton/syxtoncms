<?php
/***************************************************************************
* polls.php - modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 0.9.6
***************************************************************************/

if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); } 
    
    callfunction();
    
    echo '
        <script type="text/javascript" src="'. $CFG->wwwroot .'/features/polls/polls.js"></script>
    ';
    
    echo '</body></html>';
}

function polls_settings() {
global $CFG,$MYVARS,$USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "polls";

	//Default Settings	
	$default_settings = default_settings($feature,$pageid,$featureid);
	$setting_names = get_setting_names($default_settings);
    
	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature,$featureid,$pageid)) {
        echo make_settings_page($setting_names,$settings,$default_settings,$feature,$featureid,$pageid);
	} else { //No Settings found...setup default settings
		if (make_or_update_settings_array($default_settings)) { polls_settings(); }
	}

}

function editpoll() {
global $CFG, $MYVARS, $USER;
	date_default_timezone_set("UTC");
	$pageid = $MYVARS->GET["pageid"];
    if (!user_has_ability_in_page($USER->userid,"editpolls",$pageid)) { echo get_page_error_message("no_permission",array("editpolls")); return; }
	$pollid= $MYVARS->GET["featureid"];
	$row = get_db_row("SELECT * FROM polls WHERE pollid='$pollid'");
	$savedstart = $row['startdate'] ? date('m/d/Y',$row['startdate']) : '0';
	$startdate = $row['startdate'] ? '<div id="savedstartdatediv" style="color:gray;display:inline;">Currently set for: ' . date('l dS \of F Y',$row['startdate']) . ' <input type="button" value="Clear" onclick="javascript: zeroout(\'savedstartdate\');" /></div>' : false;
	$savedstop = $row['stopdate'] ? date('m/d/Y',$row['stopdate']) : '0';
	$stopdate = $row['stopdate'] ? '<div id="savedstopdatediv" style="color:gray;display:inline;">Currently set for: ' . date('l dS \of F Y',$row['stopdate']) . ' <input type="button" value="Clear" onclick="javascript: zeroout(\'savedstopdate\');" /></div>' : false;
	
	$answers = "";
    if ($result = get_db_result("SELECT * FROM polls_answers WHERE pollid='$pollid' ORDER BY sort")) {
        while ($answer = fetch_row($result)) {
            $answers .= $answers == "" ? $answer["answer"] : ",".$answer["answer"];    
        }
    }
    
    echo '
    <script type="text/javascript" src="'. $CFG->wwwroot .'/scripts/popupcalendar.js"></script>
    <input id="savedstartdate" type="hidden" value='.$savedstart.' />
    <input id="savedstopdate" type="hidden" value='.$savedstop.' />
    <input id="lasthint" type="hidden" />
    <div id="edit_html_div">
    <form>
    <table style="width:100%">
    	<tr>
    		<td class="field_title" style="width:150px;">
    			Poll Question:
    		</td>
    		<td class="field_input">
    			<input type="text" id="polls_question" size="30" value="'. $row['question'] .'"/>
    			<span class="hint">This is the question of your poll ex. (What is your favorite fruit?)<span class="hint-pointer">&nbsp;</span></span>
    		</td>
    	</tr><tr><td></td><td class="field_input"><span id="question_error" class="error_text"></span></td></tr>
    	<tr>
    		<td class="field_title" style="width:150px;">
    			Poll Answers:
    		</td>
    		<td class="field_input">
    			<input type="text" id="polls_answers" size="30" value="'. $answers .'"/>
    			<span class="hint">These are the answers to your poll, comma delimited. <br /> ex. (Apples,Oranges,Pears)<span class="hint-pointer">&nbsp;</span></span>
    		</td>
    	</tr><tr><td></td><td class="field_input"><span id="answers_error" class="error_text"></span></td></tr>
    	</table>
    	
    	<script>prepareInputsForHints();</script>
    	<table>
    	<tr>
    		<td class="field_title" style="width:150px;">
    			Start Date:
    		</td>
    		<td class="field_input">
    			<input type="checkbox" id="startdateenabled" onclick="hide_show_span(\'startdatespan\')" /> (optional) '.$startdate.'
    		</td>
    	</tr>
    	<tr>
    	<tr>
    		<td class="field_title" style="width:150px;">
    		</td>
    		<td class="field_input">
    		<div id="startdatespan" style="display:none;">
    			<script>DateInput(\'startdate\', true)</script>
    		</div>
    		</td>
    	</tr><tr><td></td><td class="field_input"><div id="startdate_error" class="error_text"></div></td></tr>
    	<tr>
    		<td class="field_title" style="width:150px;">
    			Stop Date:
    		</td>
    		<td class="field_input">
    			<input type="checkbox" id="stopdateenabled" onclick="hide_show_span(\'stopdatespan\')" /> (optional) '.$stopdate.'
    		</td>
    	</tr>
    	<tr>
    		<td class="field_title" style="width:150px;">
    		</td>
    		<td class="field_input">
    			<div id="stopdatespan" style="display:none;">
    			<script>DateInput(\'stopdate\', true)</script>
    			</div>
    		</td>
    	</tr><tr><td></td><td class="field_input"><div id="stopdate_error" class="error_text"></div></td></tr>
    	<tr>
    		<td></td>
    		<td style="text-align:left;">
    			<input type="button" value="Save" onclick="if (valid_poll_fields()) { var startdateenabled = !document.getElementById(\'startdateenabled\').checked ? \'&amp;startdateenabled=0&amp;startdate=\' + document.getElementById(\'savedstartdate\').value : \'&amp;startdateenabled=1&amp;startdate=\' + document.getElementById(\'startdate\').value; var stopdateenabled = !document.getElementById(\'stopdateenabled\').checked ? \'&amp;stopdateenabled=0&amp;stopdate=\' + document.getElementById(\'savedstopdate\').value : \'&amp;stopdateenabled=1&amp;stopdate=\' + document.getElementById(\'stopdate\').value; ajaxapi(\'/features/polls/polls_ajax.php\',\'poll_submit\',\'&amp;question=\' + document.getElementById(\'polls_question\').value + \'&amp;answers=\' + document.getElementById(\'polls_answers\').value + startdateenabled + stopdateenabled + \'&amp;pollid='.$pollid.'\',function() { close_modal();});}" />
    		</td>
    	</tr>
    	</table>
    </form>
    </div>';
}
?>