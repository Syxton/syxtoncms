<?php
/***************************************************************************
* calendarlib.php - Calendar function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 1/17/2011
* Revision: 1.2.8
***************************************************************************/

if(!isset($LIBHEADER)){ if(file_exists('./lib/header.php')){ include('./lib/header.php'); }elseif(file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif(file_exists('../../lib/header.php')){ include('../../lib/header.php'); }}
$CALENDARLIB = true;

function display_calendar($pageid, $area, $featureid){
global $CFG, $USER, $ROLES;
    $content = '';

	if(!$settings = fetch_settings("calendar",$featureid,$pageid)){
		make_or_update_settings_array(default_settings("calendar",$pageid,$featureid));
		$settings = fetch_settings("calendar",$featureid,$pageid);
	}

	$title = $settings->calendar->$featureid->feature_title->setting;
	
    if (user_has_ability_in_page($USER->userid, "viewcalendar", $pageid, "calendar", $featureid)){
        if ($area == "middle"){
            $content .= '<span id="calendarmarker"></span><div id="calendar_div" style="width:100%;z-index:2;">' . get_large_calendar($pageid,$USER->userid) . '</div><div id="day_info" style="display:none"></div>';
        }else{
            $content .= '<span id="calendarmarker"></span><div id="calendar_div" style="width:100%;z-index:2;">' . get_small_calendar($pageid, $USER->userid) . '</div><div id="day_info" style="display:none"></div>';
        }
                
        $buttons = is_logged_in() ? get_button_layout("calendar", $featureid, $pageid) : "";
        return get_css_box($title, $content, $buttons, '0px', "calendar", $featureid);
    }
}

function clean_for_overlib($str){
    return str_replace(chr(13), '<br />', str_replace(chr(10), '<br />', $str));
}

function get_small_calendar($pageid, $userid = 0, $month = false, $year = false, $extra_row = false){
    global $CFG;
    $show_site_events = ($pageid == $CFG->SITEID) || get_db_field("setting","settings","type='calendar' AND pageid=$pageid AND setting_name='dont_show_site_events' AND setting='1'") ? true : false;
    date_default_timezone_set($CFG->timezone);
    if (!$month){
        $month = date('m');
    }
    if (!$year){
        $year = date('Y');
    }
    $theday = date('w', mktime(0, 0, 0, $month, 1, $year));
    $daysinmonth = date("t", mktime(0, 0, 0, $month, 1, $year));
    if ($month == '01'){
        $prevmonth = '12';
        $prevyear = $year - 1;
    }else{
        $prevmonth = $month - 1;
        $prevyear = $year;
    }
    $returnme = '<table cellspacing="1" cellpadding="0" border="0" class="mainTable2"><tr><td align="center" colspan="7" class="monthRow">
              		<a href="javascript: ajaxapi(\'/features/calendar/calendar_ajax.php\',\'print_calendar\',\'&amp;displaymode=0&amp;userid=' .
        $userid . '&amp;pageid=' . $pageid . '&amp;month=' . $prevmonth . '&amp;year=' .
        $prevyear . '\',function() { simple_display(\'calendar_div\');});">&laquo;</a>&nbsp;';
    $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
    $yearName = date('Y', mktime(0, 0, 0, $month, 1, $year));
    $returnme .= $monthName . ' ' . $yearName;
    if ($month == '12'){
        $nextmonth = '01';
        $nextyear = $year + 1;
    }else{
        $nextmonth = $month + 1;
        $nextyear = $year;
    }
    
    $returnme .= '&nbsp;<a href="javascript: ajaxapi(\'/features/calendar/calendar_ajax.php\',\'print_calendar\',\'&amp;displaymode=0&amp;userid=' .
        $userid . '&amp;pageid=' . $pageid . '&amp;month=' . $nextmonth . '&amp;year=' .
        $nextyear . '\',function() { simple_display(\'calendar_div\');});">&raquo;</a>
      			</td>
	      	</tr>
	      	<tr class="dayNamesText">
	      		<td style="width:14.5%;text-align:center">S</td>
	      		<td style="width:14.5%;text-align:center">M</td>
	      		<td style="width:14.5%;text-align:center">T</td>
	      		<td style="width:14.5%;text-align:center">W</td>
	      		<td style="width:14.5%;text-align:center">T</td>
	      		<td style="width:14.5%;text-align:center">F</td>
	      		<td style="width:14.5%;text-align:center">S</td>
	      	</tr>
	      	<tr class="rows">';
            
    for($i = 0; $i < $theday; $i++){
        $returnme .= '<td>&nbsp;</td>';
    }
    
    $HowManyRows = 0;
    $whichevents = $show_site_events ? 'AND ((pageid=' . $pageid . ') OR (pageid=' . $CFG->SITEID . ') OR (site_viewable=1))' : 'AND pageid=' . $pageid;
    for($list_day = 1; $list_day <= $daysinmonth; $list_day++){
        if($theday == 0 && $list_day != 1){ $returnme .= '<tr class="rows">'; }
        $tm = date("U", mktime(0, 0, 0, $month, $list_day, $year)) - 86400; // Bir gün önce
        $tn = date("U", mktime(0, 0, 0, $month, $list_day, $year)); // O gün ...
        $tp = date("U", mktime(0, 0, 0, $month, $list_day, $year)) + 86400; // Bir gün sonra
        $SQL = sprintf("SELECT * FROM `calendar_events` WHERE `date` > '%s' AND `date` < '%s' AND `day` = '%s' $whichevents ORDER BY day;",$tm, $tp, $list_day);
        $count = get_db_count($SQL);
        if($count){ //Event exists
            if($result = get_db_result($SQL)){
                $eventlist = $today = "";
                while($event = fetch_row($result)){
                    if($event["cat"] != 0){
                        $category = get_db_row("SELECT * FROM calendar_cat WHERE cat_id=" . $event["cat"]);
                        $background_color = $category["cat_color"] != "" ? 'color:' . $category["cat_color"].';' : 'color: #333333;';
                        $font_color = $category["cat_bgcolor"] != '' ? 'background-color:' . $category["cat_bgcolor"].';' : 'background-color: #CCFF00;';
                        $category_colors = $background_color . $font_color;
                    }else{ $category_colors = 'background-color: #CCFF00; color: #333333;'; }
                }
        
                $returnme .= '<td align="center" style="' . $category_colors .' cursor: pointer;" onclick="ajaxapi(\'/features/calendar/calendar_ajax.php\',\'get_date_info\',\'&amp;show_site_events='.$show_site_events.'&amp;pageid='.$pageid.'&amp;tm='.$tm.'&amp;tn='.$tn.'&amp;tp='.$tp.'&amp;list_day='.$list_day.'\',function() { simple_display(\'day_info\'); show_section(\'day_info\'); var temptimer=10; countdown(\'cal_countdown\',temptimer,function(){ hide_section(\'day_info\'); });})">';
                db_free_result($result);
            }
        }elseif($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year){
            $returnme .= '<td align="center" style="background-color: #FFC18A; color: #CF0000;">';
        }elseif($theday == 6 or $theday == 0){
            $returnme .= '<td align="center" style="background-color: #EEEEEE; color: #666666;">';
        }else{
            $returnme .= '<td align="center" style="background-color: #CCCCCC; color: #333333;">';
        }
        $returnme .= $list_day;
        $returnme .= '</td>';
        if($theday == 6){
            $returnme .= '</tr>';
            $theday = -1;
            $HowManyRows = $HowManyRows + 1;
        }
        $theday++;
    }
    if(($HowManyRows <= 4) and ($extra_row == 1)){
        $returnme .= '</tr>';
        $returnme .= '<tr class="rows">';
        $returnme .= '<td>&nbsp;</td>';
    }
    if($theday != 0){ $returnme .= '</tr>'; }
    $returnme .= '</table>';
    return $returnme;
}

function get_large_calendar($pageid, $userid = 0, $month = false, $year = false,$extra_row = false){
global $CFG;
    $show_site_events = ($pageid == $CFG->SITEID) || get_db_field("setting","settings","type='calendar' AND pageid=$pageid AND setting_name='dont_show_site_events' AND setting='1'") ? true : false;
    date_default_timezone_set($CFG->timezone);
    if(!$month){
        $month = date('m');
    }
    if(!$year){
        $year = date('Y');
    }
    $theday = date('w', mktime(0, 0, 0, $month, 1, $year));
    $daysinmonth = date("t", mktime(0, 0, 0, $month, 1, $year));
    if($month == '01'){
        $prevmonth = '12';
        $prevyear = $year - 1;
    }else{
        $prevmonth = $month - 1;
        $prevyear = $year;
    }
    $returnme = '<table cellspacing="1" cellpadding="0" border="0" class="mainTableLarge"><tr><td align="center" colspan="7" class="monthRowLarge">
              		<a href="javascript: ajaxapi(\'/features/calendar/calendar_ajax.php\',\'print_calendar\',\'&amp;displaymode=1&amp;userid=' .
        $userid . '&amp;pageid=' . $pageid . '&amp;month=' . $prevmonth . '&amp;year=' .
        $prevyear . '\',function() { simple_display(\'calendar_div\');});">&laquo;</a>&nbsp;';
    $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
    $yearName = date('Y', mktime(0, 0, 0, $month, 1, $year));
    $returnme .= $monthName . ' ' . $yearName;
    if($month == '12'){
        $nextmonth = '01';
        $nextyear = $year + 1;
    }else{
        $nextmonth = $month + 1;
        $nextyear = $year;
    }
    $returnme .= '&nbsp;<a href="javascript: ajaxapi(\'/features/calendar/calendar_ajax.php\',\'print_calendar\',\'&amp;displaymode=1&amp;userid=' .
        $userid . '&amp;pageid=' . $pageid . '&amp;month=' . $nextmonth . '&amp;year=' .
        $nextyear . '\',function() { simple_display(\'calendar_div\');});">&raquo;</a>
  			</td>
      	</tr>
      	<tr class="dayNamesTextLarge">
      		<td style="text-align:center">S</td>
      		<td style="text-align:center">M</td>
      		<td style="text-align:center">T</td>
      		<td style="text-align:center">W</td>
      		<td style="text-align:center">T</td>
      		<td style="text-align:center">F</td>
      		<td style="text-align:center">S</td>
      	</tr>
      	<tr class="rowsLarge">';
    for($i = 0; $i < $theday; $i++){
        $returnme .= '<td>&nbsp;</td>';
    }
    $HowManyRows = 0;
    
    $whichevents = $show_site_events ? 'AND ((pageid=' . $pageid . ') OR (pageid=' . $CFG->SITEID . ') OR (site_viewable=1))' : 'AND pageid=' . $pageid;
    
    for($list_day = 1; $list_day <= $daysinmonth; $list_day++){
        if($theday == 0 && $list_day != 1){ $returnme .= '<tr class="rowsLarge">'; }
        $tm = date("U", mktime(0, 0, 0, $month, $list_day, $year)) - 86400; // Bir gün önce
        $tn = date("U", mktime(0, 0, 0, $month, $list_day, $year)); // O gün ...
        $tp = date("U", mktime(0, 0, 0, $month, $list_day, $year)) + 86400; // Bir gün sonra
        $SQL = sprintf("SELECT * FROM `calendar_events` WHERE `date` > '%s' AND `date` < '%s' AND `day` = '%s' $whichevents ORDER BY date;",$tm, $tp, $list_day);

		if($count = get_db_count($SQL)){ //Event exists
            if ($result = get_db_result($SQL)){
                $eventlist = $today = "";
                while ($event = fetch_row($result)){
                    if($event["cat"] != 0){
                        $category = get_db_row("SELECT * FROM calendar_cat WHERE cat_id=" . $event["cat"]);
                        $background_color = $category["cat_color"] != "" ? 'color:' . $category["cat_color"].';' : 'color: #333333;';
                        $font_color = $category["cat_bgcolor"] != '' ? 'background-color:' . $category["cat_bgcolor"].';' : 'background-color: #CCFF00;';
                        $category_colors = $background_color . $font_color;
                    }else{ $category_colors = 'background-color: #CCFF00; color: #333333;'; }
                }
        
                $returnme .= '<td align="center" style="' . $category_colors .' cursor: pointer;" onclick="ajaxapi(\'/features/calendar/calendar_ajax.php\',\'get_date_info\',\'&amp;show_site_events='.$show_site_events.'&amp;pageid='.$pageid.'&amp;tm='.$tm.'&amp;tn='.$tn.'&amp;tp='.$tp.'&amp;list_day='.$list_day.'\',function() { simple_display(\'day_info\'); show_section(\'day_info\'); clearTimeout(temptimer); temptimer = setTimeout(function(){hide_section(\'day_info\')},10000); var temptimer2=10; countdown(\'cal_countdown\',temptimer2);})">';
                db_free_result($result);
            }
        }elseif($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year){
            $returnme .= '<td align="center" style="background-color: #FFC18A; color: #CF0000;">';
        }elseif($theday == 6 or $theday == 0){
            $returnme .= '<td align="center" style="background-color: #EEEEEE; color: #666666;">';
        }else{
            $returnme .= '<td align="center" style="background-color: #CCCCCC; color: #333333;">';
        }
        $returnme .= $list_day;
        if($count){
            if($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year){
                $returnme .= '<br><span>Today</span>';
            }
            $returnme .= '<br><span>Event info.</span>';
        }elseif($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year){
            $returnme .= '<br><span>Today</span>';
        }
        $returnme .= '</td>';
        if($theday == 6){
            $returnme .= '</tr>';
            $theday = -1;
            $HowManyRows = $HowManyRows + 1;
        }
        $theday++;
    }
    if(($HowManyRows <= 4) and ($extra_row_large == 1)){
        $returnme .= "</tr>";
        $returnme .= '<tr class="rowsLarge">';
        $returnme .= '<td>&nbsp;</td>';
    }
    if($theday != 0){ $returnme .= '</tr>'; }
    $returnme .= '</table>';
    return $returnme;
}

function calendar_delete($pageid, $featureid, $sectionid){
    execute_db_sql("DELETE FROM pages_features WHERE feature='calendar' AND pageid='$pageid' AND featureid='$featureid'");
    execute_db_sql("DELETE FROM settings WHERE type='calendar' AND pageid='$pageid' AND featureid='$featureid'");
    
    resort_page_features($pageid);
}

function calendar_buttons($pageid, $featuretype, $featureid){
    global $CFG, $USER;
    $returnme = "";
    return $returnme;
}

function calendar_default_settings($feature,$pageid,$featureid){
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","Calendar",false,"Calendar","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","dont_show_site_events","0",false,"0","Show Global Events","yes/no");
	return $settings_array;
}
?>