<?php
/***************************************************************************
* roles.php - Role relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2013
* Revision: 2.5.1
***************************************************************************/

include('header.php');
echo '
	<script type="text/javascript">
    function swap_highlights(option1,option2){
        option1 = "#"+option1;
        option2 = "#"+option2;
	 	if($(option1).emptybg() && $(option2).emptybg()){
            $(option1).css("background-color","yellow");
        }else if($(option1).emptybg()){
			$(option1).css("background-color","inherit");
            $(option2).css("background-color","inherit");
		}
		blur();
  	}
    function swap_highlights2(option1,option2){
        option1 = "#"+option1;
        option2 = "#"+option2;
	 	if($(option1).emptybg() && $(option2).emptybg()){
	 		$(option1).css("background-color","yellow");
        }else{
			$(option1).css("background-color","yellow");
            $(option2).css("background-color","inherit");
		}
		blur();
  	}
    function clear_highlights(option1,option2){
        $(option1).css("background-color","inherit");
        $(option2).css("background-color","inherit");
		blur();
  	}
	</script>
';

callfunction();

echo '</body></html>';

function assign_roles(){
global $CFG,$MYVARS,$USER,$ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed  
   
	if(!user_has_ability_in_page($USER->userid,"assign_roles",$pageid)){ echo get_page_error_message("no_permission",array("assign_roles")); return; }
	$myroleid = get_user_role($USER->userid,$pageid);
	$rightslist = "";
	$returnme = '<form onsubmit="clear_display(\'per_page_roles_div\'); ajaxapi(\'/ajax/roles_ajax.php\',\'name_search\',\'&amp;pageid='.$pageid.'&amp;type=per_page_&amp;refreshroles=refreshroles&amp;searchstring=\'+trim(document.getElementById(\'per_page_search\').value), function(){ simple_display(\'per_page_users_display_div\'); }); return false;" >User Search: <input type="text" id="per_page_search" size="18" />&nbsp;<input type="submit" value="Search" /></form>';
	$SQL = "SELECT u.* FROM users u WHERE u.userid IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.confirm=0 AND ra.pageid='$pageid') AND u.userid NOT IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.roleid=6 OR (ra.userid = '".$USER->userid."' ) OR (ra.pageid='".$CFG->SITEID."' AND ra.roleid='".$ROLES->admin."') OR (ra.pageid='$pageid' AND ra.roleid <= '$myroleid')) ORDER BY u.lname";
	$returnme .= 'Users:<br />
				<div style="width:100%; text-align:center; vertical-align:top;" id="per_page_users_display_div">
				<select size="10" style="width: 100%; font-size:.85em;" name="userid" id="per_page_user_select" onclick="if(document.getElementById(\'per_page_user_select\').value > 0){ ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_user_roles\',\'&amp;pageid='.$pageid.'&amp;userid=\'+document.getElementById(\'per_page_user_select\').value,function(){ simple_display(\'per_page_roles_div\'); });}">';
	if($pageid == $CFG->SITEID){
        $returnme .= '<option value="0">Search results will be shown here.</option>';    
    }elseif($roles = get_db_result($SQL)){
		while($row = fetch_row($roles)){
			$returnme .= '<option value="'.$row['userid'].'">'.$row['fname']. ' ' . $row['lname'] . ' (' . $row['email'] .')</option>';
		}
	}
	$returnme .= '</select></div><div id="per_page_roles_div" style="width:100%;"></div>';
	echo $returnme;
}

function role_specific(){
global $CFG,$USER,$MYVARS,$ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed  
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    $abilities = get_user_abilities($USER->userid,$pageid,"roles",$feature,$featureid);
    
	if(!((!$featureid && $abilities->edit_roles->allow) || (($featureid && $abilities->edit_feature_abilities->allow)))){ echo get_error_message("generic_permissions"); return; }
    
    $myroleid = get_user_role($USER->userid,$pageid);
	$SQL = 'SELECT * FROM roles WHERE roleid > '.$myroleid.' ORDER BY roleid';
	$returnme = '<form id="per_role_roles_form"><div style="width:100%; text-align:center"><select name="per_role_roleid" id="per_role_role_select" onchange="ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_edit_roles\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;roleid=\'+this.value,function(){ simple_display(\'per_role_abilities_div\'); });">';
    $roleid = false;
	if($roles = get_db_result($SQL)){
		while($row = fetch_row($roles)){
            $roleid = !$roleid ? $row["roleid"] : $roleid;
			$returnme .= '<option value="'.$row['roleid'].'">'.$row['display_name'].'</option>';
		}
	}
	$returnme .= '</select></form>';
	$returnme .= '<div id="per_role_saved_div1" style="width:100%;text-align:center;height: 2px;padding-bottom: 20px;padding-top: 20px;"></div><div id="per_role_abilities_div" style="width:100%;">';
	$returnme .= print_abilities($pageid,"per_role_",$roleid,false,$feature,$featureid);
	$returnme .= '</div><div id="per_role_saved_div2" style="width:100%;text-align:center;height: 2px;padding-bottom: 10px;padding-top: 10px;"></div>';
	echo $returnme;
}

function user_specific(){
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed  
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    $abilities = get_user_abilities($USER->userid,$pageid,"roles",$feature,$featureid);
    
	if(!((!$featureid && $abilities->edit_user_abilities->allow) || ($featureid && $abilities->edit_feature_user_abilities->allow))){ echo get_error_message("generic_permissions"); return; }
	$myroleid = get_user_role($USER->userid,$pageid);
	$rightslist = "";
	$returnme = '<form onsubmit="clear_display(\'per_user_abilities_div\'); ajaxapi(\'/ajax/roles_ajax.php\',\'name_search\',\'&amp;pageid='.$pageid.'&amp;type=per_user_&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;refreshroles=&amp;searchstring=\'+trim(document.getElementById(\'per_user_search\').value), function(){simple_display(\'per_user_users_display_div\');}); return false;" >User Search: <input type="text" id="per_user_search" size="18" />&nbsp;<input type="submit" value="Search" /></form>';
	
    $SQL = "SELECT u.* FROM users u WHERE u.userid IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='$pageid') AND u.userid NOT IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='".$CFG->SITEID."' AND ra.roleid='".$ROLES->admin."') AND u.userid NOT IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='$pageid' AND ra.roleid <= '$myroleid') AND u.userid != '".$USER->userid."' ORDER BY u.lname";
	
    $returnme .= '	<form id="per_user_roles_form">
					Users:<br />
					<div style="width:100%; text-align:center; vertical-align:top;" id="per_user_users_display_div">
						<select size="10" width="100%" style="width: 100%; font-size:.85em;" name="userid" id="per_user_user_select" onclick="if(document.getElementById(\'per_user_user_select\').value > 0){ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_user_abilities\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;userid=\'+document.getElementById(\'per_user_user_select\').value,function(){ simple_display(\'per_user_abilities_div\'); });}">';
    if($pageid == $CFG->SITEID){
        $returnme .= '<option value="0">Search results will be shown here.</option>';    
    }elseif($roles = get_db_result($SQL)){
		while($row = fetch_row($roles)){
			$returnme .= '<option value="'.$row['userid'].'">'.$row['fname']. ' ' . $row['lname'] . ' (' . $row['email'] .')</option>';
		}
	}
	$returnme .= '</select></div><div id="per_user_saved_div1" style="width:100%;text-align:center;height: 2px;padding-bottom: 18px;padding-top: 10px;"></div><div id="per_user_abilities_div" style="width:100%;"></div><div id="per_user_saved_div2" style="width:100%;text-align:center;height: 2px;padding-bottom: 10px;padding-top: 10px;"></div></form>';
	echo $returnme;
}

function group_specific(){
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed  
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    
    if(!$featureid){
        if(!user_has_ability_in_page($USER->userid,"edit_group_abilities",$pageid)){ echo get_error_message("generic_permissions"); return; }
    }else{
        if(!user_has_ability_in_page($USER->userid,"edit_feature_group_abilities",$pageid,$feature,$featureid)){ echo get_error_message("generic_permissions"); return; }
    }
	
	$rightslist = "";
    $returnme = '<div id="per_group_whole_page" style="width:100%;">';
    $returnme .= group_page($pageid,$feature,$featureid);
    $returnme .= '</div>';
	echo $returnme;
}

function manager(){
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed  
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    echo '  <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts/jquery-ui.min.js"></script>
            <link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'styles/jqueryui&amp;f=jquery-ui.css" />
            
            <script type="text/javascript">
            	$(function() {
            		$("#tabs").tabs({
            			beforeLoad: function( event, ui ) {
            			    var d = new Date();
            			    ui.ajaxSettings.url += "&currTime=" + d.toUTCString();
                            ui.jqXHR.error(function() {
                              ui.panel.html("Couldn\'t load this tab. We\'ll try to fix this as soon as possible." );
                            });
                          }
            		});
            	});
        	</script>
    ';
    $abilities = merge_abilities(array(get_user_abilities($USER->userid,$pageid,"roles",$feature,$featureid),get_user_abilities($USER->userid,$pageid,array("feature","html"),$feature,$featureid)));
    $tab_assign_roles = !$featureid && $abilities->assign_roles->allow ? '<li><a href="roles.php?action=assign_roles&pageid='.$pageid.'">Assign Roles</a></li>' : '';
    $tab_modify_roles = (!$featureid && $abilities->edit_roles->allow) || (($featureid && $abilities->edit_feature_abilities->allow)) ? '<li><a href="roles.php?action=role_specific&feature='.$feature.'&featureid='.$featureid.'&pageid='.$pageid.'">Modify Roles</a></li>' : '';
    $tab_groups = (!$featureid && $abilities->edit_group_abilities->allow) || (($featureid && $abilities->edit_feature_group_abilities->allow)) ? '<li><a href="roles.php?action=group_specific&feature='.$feature.'&featureid='.$featureid.'&pageid='.$pageid.'">Group Abilities</a></li>' : '';
    $tab_user = (!$featureid && $abilities->edit_user_abilities->allow) || (($featureid && $abilities->edit_feature_user_abilities->allow)) ? '<li><a href="roles.php?action=user_specific&feature='.$feature.'&featureid='.$featureid.'&pageid='.$pageid.'">User Abilities</a></li>' : '';
    
    $pagename = get_db_field("name","pages","pageid='$pageid'");
    $pagecontext = '<strong>Page:</strong> <em>'.stripslashes($pagename).'</em>';    
    if($featureid && $feature){
        if(!$settings = fetch_settings($feature,$featureid,$pageid)){
            make_or_update_settings_array(default_settings($feature,$pageid,$featureid));
           	$settings = fetch_settings($feature,$featureid,$pageid);
        }
        $featurecontext = '&nbsp;&nbsp;<strong>Specific for the '.$feature.' feature:</strong> <em>"' . $settings->$feature->$featureid->feature_title->setting . '"</em>';
    }else{
        $featurecontext = "";
    }
    $warning = $pageid == $CFG->SITEID && !$featureid ? ' <span style="background-color:red;padding:3px;float:right;border:5px solid black;">WARNING: Changes affect all pages.</span>' : '';
    echo '
    <div id="context" style="font-size:.85em;background-color:lightGrey;margin:3px;padding:3px;height:30px;">
        <span style="display:inline-block;float:left;">'.$pagecontext.$featurecontext.'</span>'.$warning.'
    </div>
    <div id="tabs" style="font-size:.9em">
    	<ul style="height:30px">
    		'.$tab_assign_roles.'
    		'.$tab_modify_roles.'
            '.$tab_groups.'
    		'.$tab_user.'
    	</ul>
    </div>
    ';
}
?>