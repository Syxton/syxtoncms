<?php
/***************************************************************************
* roles_ajax.php - Roles Ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2013
* Revision: 2.0.2
***************************************************************************/

include ('header.php');
update_user_cookie();

callfunction();

function name_search() {
global $CFG,$ROLES,$USER,$MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $type = !empty($MYVARS->GET['type']) ? $MYVARS->GET['type'] : "per_page_"; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

	$searchstring = "";	$searcharray = explode(" ",$MYVARS->GET["searchstring"]);
	$i=0;
	foreach ($searcharray as $search) {
		$searchstring .= $i == 0 ? "" : " OR ";
    $searchstring .= "(fname LIKE '%$search%' OR lname LIKE '%$search%' OR email LIKE '%$search%')";
    $i++;
	}

	if ($pageid == $CFG->SITEID && is_siteadmin($USER->userid)) {
    $SQL = "SELECT u.*
              FROM users u
             WHERE $searchstring
          ORDER BY u.lname";
	} else {
    $myroleid = get_user_role($USER->userid,$pageid);
    if ($type != "per_page_") { // Feature specific role assignment search. (only searches people that already have page privs)
        $SQL = "SELECT u.*
                  FROM users u
                 WHERE $searchstring
                   AND u.userid IN (SELECT ra.userid
                                      FROM roles_assignment ra
                                     WHERE ra.pageid = '$pageid')
                   AND u.userid NOT IN (SELECT ra.userid
                                          FROM roles_assignment ra
                                         WHERE ra.pageid = '$pageid'
                                           AND ra.roleid <= '$myroleid')
              ORDER BY u.lname";
    } else {  // Page role assignment search.
        $SQL = "SELECT u.*
                  FROM users u
                 WHERE $searchstring
                   AND u.userid NOT IN (SELECT ra.userid
                                          FROM roles_assignment ra
                                         WHERE ra.pageid = '$pageid'
                                           AND ra.roleid <= '$myroleid')
              ORDER BY u.lname";
    }
  }

  $params = array("refreshroles" => ($MYVARS->GET["refreshroles"] == "refreshroles"), "type" => $type, "pageid" => $pageid, "featureid" => $featureid, "feature" => $feature);
  $options = "";
	if ($users = get_db_result($SQL)) {
		while ($row = fetch_row($users)) {
      $options .= template_use("templates/page.template", array("value" => $row['userid'], "display" => $row['fname'] . ' ' . $row['lname'] . ' (' . $row['email'] . ')'), "select_options_template");
		}
	}
  $params["options"] = $options;
  echo template_use("templates/roles_ajax.template", $params, "name_search_template");
}

function add_to_group_search() {
global $CFG,$ROLES,$USER,$MYVARS;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

	$searchstring = "";	$searcharray = explode(" ",$MYVARS->GET["searchstring"]);
	$i=0;
	foreach($searcharray as $search){
		$searchstring .= $i == 0 ? '(fname LIKE \'%'.$search.'%\' OR lname LIKE \'%'.$search.'%\' OR email LIKE \'%'.$search.'%\')' : ' OR (fname LIKE \'%'.$search.'%\' OR lname LIKE \'%'.$search.'%\' OR email LIKE \'%'.$search.'%\')';
		$i++;
	}
	$myroleid = get_user_role($USER->userid,$pageid);
    $SQL = "SELECT u.* FROM users u WHERE $searchstring AND u.userid IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='$pageid') AND u.userid NOT IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='".$CFG->SITEID."' AND ra.roleid='".$ROLES->admin."') AND u.userid NOT IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='$pageid' AND ra.roleid <= '$myroleid') AND u.userid != '".$USER->userid."' AND u.userid NOT IN (SELECT userid FROM groups_users WHERE groupid='$groupid') ORDER BY u.lname";

    $returnme = '<select size="5" width="100%" style="width: 100%; font-size:.85em;" name="userid" id="add_user_select">';
	if($users = get_db_result($SQL)){
		while($row = fetch_row($users)){
            $mygroups = "";
            if($groups = get_db_result("SELECT * FROM groups WHERE groupid IN (SELECT groupid FROM groups_users WHERE userid='".$row['userid']."' AND pageid='$pageid')")){
                while($group_info = fetch_row($groups)){
                    $mygroups .= " " . $group_info["name"];
                }
            }
			$returnme .= '<option value="'.$row['userid'].'">'.$row['fname']. ' ' . $row['lname'] . ' (' . $row['email'] .')'.$mygroups.'</option>';
		}
	}
	$returnme .= '</select>';
	echo $returnme;
}

function refresh_group_users(){
global $CFG,$MYVARS,$USER;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

   	if ($pageid == $CFG->SITEID && is_siteadmin($USER->userid)) {
   	    $SQL = "SELECT u.* FROM users u WHERE
                    u.userid IN (SELECT userid FROM groups_users WHERE
                                    pageid='$pageid' AND
                                    groupid='$groupid')
                    ORDER BY u.lname";
	} else {
        $SQL = "SELECT u.* FROM users u WHERE
                    u.userid IN (SELECT ra.userid FROM roles_assignment ra WHERE
                                    ra.pageid='$pageid') AND
                    u.userid IN (SELECT userid FROM groups_users WHERE
                                    pageid='$pageid' AND
                                    groupid='$groupid')
                    ORDER BY u.lname";
  	}
    $groupname = get_db_field("name","groups","groupid='$groupid'");
    $returnme = $groupname.'
                 <span style="float:right">';
    $returnme .= user_has_ability_in_page($USER->userid,"manage_groups",$pageid) ? '<a href="javascript: ajaxapi(\'/ajax/roles_ajax.php\',\'create_edit_group_form\',\'&amp;pageid='.$pageid.'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\',function(){ simple_display(\'per_group_display_div\'); });"><img src="'.$CFG->wwwroot.'/images/edit.png" /></a> <a href="javascript:if(confirm(\'Are you sure you wish to delete this group?\')){ ajaxapi(\'/ajax/roles_ajax.php\',\'delete_group\',\'&amp;pageid='.$pageid.'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\',function(){ simple_display(\'per_group_whole_page\'); }); }"><img src="'.$CFG->wwwroot.'/images/delete.png" /></a>' : "";
    $returnme .= '</span>
                <div style="width:100%; text-align:center;">
                    <select size="5" width="100%" style="width: 100%; font-size:.85em;" name="per_group_userid" id="per_group_user_select">';
    if($users = get_db_result($SQL)){
		while($row = fetch_row($users)){
			$returnme .= '<option value="'.$row['userid'].'">'.$row['fname']. ' ' . $row['lname'] . ' (' . $row['email'] .')</option>';
		}
	}else{
        $returnme .= '<option value="0">No users in this group.</option>';
	}
	$returnme .= '</select>';
    $returnme .= user_has_ability_in_page($USER->userid,"manage_groups",$pageid) ? '<a class="imgandlink" href="javascript: ajaxapi(\'/ajax/roles_ajax.php\',\'manage_group_users_form\',\'&amp;pageid='.$pageid.'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\',function(){ simple_display(\'per_group_display_div\'); });"><img src="'.$CFG->wwwroot.'/images/user_role.png" /> Manage Users</a>' : "";
    $returnme .= '</div>';
	echo $returnme;
}

function manage_group_users_form(){
global $CFG,$MYVARS,$ROLES,$USER;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    $returnme = refresh_manage_groups($pageid,$groupid,$feature=false,$featureid=false);
    echo $returnme;
}

function add_group_user(){
global $CFG,$MYVARS,$ROLES,$USER;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    execute_db_sql("INSERT INTO groups_users (userid,pageid,groupid) VALUES('$userid','$pageid','$groupid')");
    $returnme = refresh_manage_groups($pageid,$groupid);
    echo $returnme;
}

function remove_group_user(){
global $CFG,$MYVARS,$ROLES,$USER;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    execute_db_sql("DELETE FROM groups_users WHERE userid='$userid' AND pageid='$pageid' AND groupid='$groupid'");
    $returnme = refresh_manage_groups($pageid,$groupid);
    echo $returnme;
}

function refresh_manage_groups($pageid,$groupid,$feature=false,$featureid=false){
global $CFG,$MYVARS,$ROLES,$USER;
    $myroleid = get_user_role($USER->userid,$pageid);
    $returnme = $pageid == $CFG->SITEID ? '<form onsubmit="ajaxapi(\'/ajax/roles_ajax.php\',\'add_to_group_search\',\'&amp;pageid='.$pageid.'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;searchstring=\'+trim(document.getElementById(\'per_group_search_text\').value), function(){simple_display(\'per_group_users_display_div\');}); return false;" >User Search: <input type="text" id="per_group_search_text" size="18" />&nbsp;<input type="submit" value="Search" /></form>' : '';
    $returnme .= 'Add Users:<br />
					<div style="width:100%; text-align:center; vertical-align:top;" id="per_group_users_display_div">
						<select size="5" width="100%" style="width: 100%; font-size:.85em;" name="userid" id="add_user_select">';

    $SQL = "SELECT u.* FROM users u WHERE
                u.userid IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='$pageid') AND
                u.userid NOT IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='$pageid' AND ra.roleid <= '$myroleid') AND
                u.userid NOT IN (SELECT userid FROM groups_users WHERE groupid='$groupid')
            ORDER BY u.lname";
	if($pageid == $CFG->SITEID){
        $returnme .= '<option value="0">Search results will be shown here.</option>';
    }elseif($roles = get_db_result($SQL)){
		while($row = fetch_row($roles)){
            $mygroups = "";
            if($groups = get_db_result("SELECT * FROM groups WHERE groupid IN (SELECT groupid FROM groups_users WHERE userid='".$row['userid']."' AND pageid='$pageid')")){
                while($group_info = fetch_row($groups)){
                    $mygroups .= " " . $group_info["name"];
                }
            }
			$returnme .= '<option value="'.$row['userid'].'">'.$row['fname']. ' ' . $row['lname'] . ' (' . $row['email'] .')'.$mygroups.'</option>';
		}
	}
	$returnme .= '</select></div><a style="float:right;" class="imgandlink" href="javascript: if(document.getElementById(\'add_user_select\').value > 0){ ajaxapi(\'/ajax/roles_ajax.php\',\'add_group_user\',\'&amp;userid=\'+document.getElementById(\'add_user_select\').value+\'&amp;pageid='.$pageid.'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\',function(){ simple_display(\'per_group_display_div\'); }); ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_groups_list\',\'&amp;pageid='.$pageid.'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\',function(){ simple_display(\'group_list_div\'); }); }"><img src="'.$CFG->wwwroot.'/images/add.png" /> Add User</a><br />';

    $returnme .= 'Remove Users:<br />
					<div style="width:100%; text-align:center; vertical-align:top;" id="per_group_users_display_div2">
						<select size="5" width="100%" style="width: 100%; font-size:.85em;" id="remove_user_select">';
    $SQL = "SELECT u.* FROM users u WHERE
                u.userid IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='$pageid') AND
                u.userid IN (SELECT userid FROM groups_users WHERE groupid='$groupid') AND
                u.userid NOT IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='$pageid' AND ra.roleid <= '$myroleid')
            ORDER BY u.lname";
	if($roles = get_db_result($SQL)){
		while($row = fetch_row($roles)){
            $mygroups = "";
            if($groups = get_db_result("SELECT * FROM groups WHERE groupid IN (SELECT groupid FROM groups_users WHERE userid='".$row['userid']."' AND pageid='$pageid')")){
                while($group_info = fetch_row($groups)){
                    $mygroups .= " " . $group_info["name"];
                }
            }
			$returnme .= '<option value="'.$row['userid'].'">'.$row['fname']. ' ' . $row['lname'] . ' (' . $row['email'] .')'.$mygroups.'</option>';
		}
	}
	$returnme .= '</select></div><a style="float:right;" class="imgandlink" href="javascript: if(document.getElementById(\'remove_user_select\').value > 0){ ajaxapi(\'/ajax/roles_ajax.php\',\'remove_group_user\',\'&amp;userid=\'+document.getElementById(\'remove_user_select\').value+\'&amp;pageid='.$pageid.'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\',function(){ simple_display(\'per_group_display_div\'); }); ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_groups_list\',\'&amp;pageid='.$pageid.'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\',function(){ simple_display(\'group_list_div\'); }); }"><img src="'.$CFG->wwwroot.'/images/subtract.png" /> Remove User</a>';
    return $returnme;
}

function delete_group(){
global $CFG,$MYVARS,$USER;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    if($pageid && $groupid){
        execute_db_sql("DELETE FROM groups WHERE groupid='$groupid' AND pageid='$pageid'");
        execute_db_sql("DELETE FROM groups_users WHERE groupid='$groupid' AND pageid='$pageid'");
        execute_db_sql("DELETE FROM roles_ability_perfeature_pergroup WHERE groupid='$groupid' AND pageid='$pageid'");
        execute_db_sql("DELETE FROM roles_ability_pergroup WHERE groupid='$groupid' AND pageid='$pageid'");
    }

    echo group_page($pageid,$feature,$featureid);
}

function refresh_groups_page(){
global $MYVARS;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    echo group_page($pageid,$feature,$featureid);
}

function refresh_groups_list(){
global $MYVARS;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Only passed when editing
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    echo groups_list($pageid,$feature,$featureid,true,$groupid);
}

function create_edit_group_form(){
global $CFG,$MYVARS,$USER;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Only passed when editing
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    if($groupid){ //EDITING: get form values to fill in
        $group = get_db_row("SELECT * FROM groups WHERE groupid='$groupid'");
        $name = $group["name"];
        $parents = groups_list($pageid,false,false,false,$group["parent"],$groupid,$groupid,"80%","per_group_edit_group_select","per_group_edit_group_select");
    }else{ //CREATING
        $name = '';
        $parents = groups_list($pageid,null,null,null,null,null,null,"80%","per_group_edit_group_select","per_group_edit_group_select");
    }

    $returnme = '<table style="width:100%;font-size:.85em;">';
    $returnme .= '<tr><td colspan="2"><strong>Edit Group</strong></td></tr>';
    $returnme .= '<tr><td>Name:</td><td><input size="45" type="text" id="per_group_name" name="per_group_name" value="'.$name.'" /></td></tr>';
    $returnme .= '<tr><td>Parent:</td><td>'.$parents.'</td></tr>';
    $returnme .= '<tr><td colspan="2" style="text-align:right;"><a class="imgandlink" href="javascript: if(trim(document.getElementById(\'per_group_name\').value).length > 0){ ajaxapi(\'/ajax/roles_ajax.php\',\'save_group\',\'&amp;name=\'+document.getElementById(\'per_group_name\').value+\'&amp;parent=\'+document.getElementById(\'per_group_edit_group_select\').value+\'&amp;groupid='.$groupid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;pageid='.$pageid.'\',function(){ simple_display(\'per_group_whole_page\'); });}else{ alert(\'Name is required.\'); }"><img src="'.$CFG->wwwroot.'/images/save.png" /> Save Group</a></td></tr>';

    echo $returnme;
}

function save_group(){
global $CFG,$MYVARS,$USER;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $name = !empty($MYVARS->GET['name']) ? dbescape($MYVARS->GET['name']) : false; //Should always be passed
    $parent = !empty($MYVARS->GET['parent']) ? $MYVARS->GET['parent'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Only passed when editing
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    if($groupid){ //EDITING
        $parent = $parent ? "parent='$parent'" : "parent='0'";
        execute_db_sql("UPDATE groups SET name='$name',$parent WHERE groupid='$groupid' AND pageid='$pageid'");
    }else{ //CREATING
        $parent = $parent ? "'$parent'" : "0";
        execute_db_sql("INSERT INTO groups (name,parent,pageid) VALUES('$name',$parent,'$pageid')");
    }

    echo group_page($pageid,$feature,$featureid);
}

function refresh_edit_roles(){
global $CFG,$MYVARS;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $roleid = !empty($MYVARS->GET['roleid']) ? $MYVARS->GET['roleid'] : false; //Should always be passed
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing

    if($pageid && $roleid){
        echo print_abilities($pageid,"per_role_",$roleid,false,$feature,$featureid);
    }else{ echo get_error_message("generic_error"); return; }

}

//TOP LEVEL PER USER OVERRIDES
function refresh_user_abilities(){
global $CFG, $MYVARS;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    if($pageid && $userid){
        echo print_abilities($pageid,"per_user_",false,$userid,$feature,$featureid);
    }else{ echo get_error_message("generic_error"); return; }
}

function refresh_group_abilities(){
global $CFG,$MYVARS;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    if($pageid && $groupid){
        echo '<form id="per_group_roles_form">';
        echo print_abilities($pageid,"per_group_",false,false,$feature,$featureid,$groupid);
        echo '</form>';
    }else{ echo get_error_message("generic_error"); return; }
}

function save_ability_changes(){
global $CFG,$MYVARS;
    $abilities = explode("**",$MYVARS->GET['per_role_rightslist']);
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $roleid = !empty($MYVARS->GET['per_role_roleid']) ? $MYVARS->GET['per_role_roleid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    $success = false;
    $i=0;
	while(isset($abilities[$i])){
		$ability = $abilities[$i];
		$setting = $MYVARS->GET[$ability] == 1 ? 1 : 0;
		if($pageid == $CFG->SITEID && !$featureid){
			$default = get_db_field("allow","roles_ability","roleid='$roleid' AND ability='$ability'");
			if($default !== false){
				if($default !== $setting){
					$section = get_db_field("section","abilities","ability='$ability'");
					$success = execute_db_sql("DELETE FROM roles_ability WHERE roleid='$roleid' AND ability='$ability'") ? true : false;
					$success = execute_db_sql("INSERT INTO roles_ability (roleid,section,ability,allow) VALUES('$roleid','$section','$ability','$setting')") ? true : false;
				}
			}else{ //No entry
				$section = get_db_field("section","abilities","ability='$ability'");
				$success = execute_db_sql("INSERT INTO roles_ability (roleid,section,ability,allow) VALUES('$roleid','$section','$ability','$setting')") ? true : false;
			}
		}else{
			$default = get_db_field("allow","roles_ability","roleid='$roleid' AND ability='$ability'");
            if($feature && $featureid){
                $alreadyset = get_db_count("SELECT * FROM roles_ability_perfeature WHERE feature='$feature' AND featureid='$featureid' AND roleid='$roleid' AND pageid='$pageid' AND ability='$ability'");
    			if($alreadyset){
    				if($setting == $default){ $success = execute_db_sql("DELETE FROM roles_ability_perfeature WHERE pageid='$pageid' AND roleid='$roleid' AND feature='$feature' AND featureid='$featureid' AND ability='$ability'") ? true : false;
    				}else{ $success = execute_db_sql("UPDATE roles_ability_perfeature SET allow='$setting' WHERE roleid='$roleid' AND feature='$feature' AND featureid='$featureid' AND ability='$ability'") ? true : false; }
    			}elseif($setting != $default && !$alreadyset){
    				$success = execute_db_sql("INSERT INTO roles_ability_perfeature (roleid,pageid,feature,featureid,ability,allow) VALUES('$roleid','$pageid','$feature','$featureid','$ability','$setting')") ? true : false;
    			}
            }else{
                $alreadyset = get_db_count("SELECT * FROM roles_ability_perpage WHERE roleid='$roleid' AND pageid='$pageid' AND ability='$ability'");
                if($alreadyset){
    				if($setting == $default){ $success = execute_db_sql("DELETE FROM roles_ability_perpage WHERE pageid='$pageid' AND roleid='$roleid' AND ability='$ability'") ? true : false;
    				}else{ $success = execute_db_sql("UPDATE roles_ability_perpage SET allow='$setting' WHERE roleid='$roleid' AND ability='$ability' AND pageid='$pageid'") ? true : false; }
    			}elseif($setting != $default && !$alreadyset){
    				$success = execute_db_sql("INSERT INTO roles_ability_perpage (roleid,pageid,ability,allow) VALUES('$roleid','$pageid','$ability','$setting')") ? true : false;
                }
    		}
		}
		$i++;
	}
    if($success){
        echo "Changes Saved";
    }else{
        echo "Save Failed";
    }
}

function save_user_ability_changes(){
global $CFG,$MYVARS;
    $abilities = explode("**",$MYVARS->GET['per_user_rightslist']);
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    $i=0;
	while(isset($abilities[$i])){
		$ability = $abilities[$i];
		$allow = $MYVARS->GET[$ability] == 1 ? 1 : 0;
		$roleid = get_user_role($userid,$pageid);

        //$default = $featureid ? (user_has_ability_in_page($userid,$ability,$pageid,$feature,$featureid) ? "1" : "0") : (user_has_ability_in_page($roleid,$ability,$pageid,$feature) ? "1" : "0");
        //figure out the default
        if($featureid){ //feature specific ability change
            $default = user_has_ability_in_page($userid,$ability,$pageid,$feature,$featureid) ? "1" : "0";
        }else{ //page specific ability change
            $default = user_has_ability_in_page($userid,$ability,$pageid) ? "1" : "0";
        }

		if($feature && $featureid){
            $alreadyset = get_db_count("SELECT * FROM roles_ability_perfeature_peruser WHERE userid='$userid' AND pageid='$pageid' AND feature='$feature' AND featureid='$featureid' AND ability='$ability'");
        }else{
            $alreadyset = get_db_count("SELECT * FROM roles_ability_peruser WHERE userid='$userid' AND pageid='$pageid' AND ability='$ability'");
		}

		if($alreadyset){
			if($alreadyset && $allow == $default){
                if($feature && $featureid){
                    execute_db_sql("DELETE FROM roles_ability_perfeature_peruser WHERE pageid='$pageid' AND userid='$userid' AND feature='$feature' AND featureid='$featureid' AND ability='$ability'");
        		}else{
                    execute_db_sql("DELETE FROM roles_ability_peruser WHERE pageid='$pageid' AND userid='$userid' AND ability='$ability'");
        		}
            }else{
                if($feature && $featureid){
                    execute_db_sql("UPDATE roles_ability_perfeature_peruser SET allow='$allow' WHERE userid='$userid' AND pageid='$pageid' AND feature='$feature' AND featureid='$featureid' AND ability='$ability'");
        		}else{
                    execute_db_sql("UPDATE roles_ability_peruser SET allow='$allow' WHERE userid='$userid' AND pageid='$pageid' AND ability='$ability'");
        		}
            }
		}elseif($allow != $default && !$alreadyset){
            if($feature && $featureid){
                execute_db_sql("INSERT INTO roles_ability_perfeature_peruser (userid,pageid,feature,featureid,ability,allow) VALUES('$userid','$pageid','$feature','$featureid','$ability','$allow')");
            }else{
                execute_db_sql("INSERT INTO roles_ability_peruser (userid,pageid,ability,allow) VALUES('$userid','$pageid','$ability','$allow')");
    		}
		}
		$i++;
	}
	echo "Changes Saved";
}

function save_group_ability_changes(){
global $CFG,$MYVARS;
    $abilities = explode("**",$MYVARS->GET['per_group_rightslist']);
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

    $i=0;
	while(isset($abilities[$i])){
		$ability = $abilities[$i];
		$allow = $MYVARS->GET[$ability] === 1 ? 1 : $MYVARS->GET[$ability]; //If ability is SET to 1
        $allow = $allow === 0 ? 0 : $allow; //If ability is SET to 0
        $allow = $allow === '' ? false : $allow; //If ability is NOT SET

		if($feature && $featureid){
            $alreadyset = get_db_count("SELECT * FROM roles_ability_perfeature_pergroup WHERE groupid='$groupid' AND pageid='$pageid' AND feature='$feature' AND featureid='$featureid' AND ability='$ability'");
		}else{
            $alreadyset = get_db_count("SELECT * FROM roles_ability_pergroup WHERE groupid='$groupid' AND pageid='$pageid' AND ability='$ability'");
		}

		if($alreadyset){
			if($alreadyset && $allow === false){ //If ability is NOT SET to 1 or 0 but is set in the db
                if($feature && $featureid){
                    execute_db_sql("DELETE FROM roles_ability_perfeature_pergroup WHERE pageid='$pageid' AND groupid='$groupid' AND feature='$feature' AND featureid='$featureid' AND ability='$ability'");
        		}else{
                    execute_db_sql("DELETE FROM roles_ability_pergroup WHERE pageid='$pageid' AND groupid='$groupid' AND ability='$ability'");
        		}
            }else{ //If ability is SET to 1 or 0 and is already set in the db
                if($feature && $featureid){
                    execute_db_sql("UPDATE roles_ability_perfeature_pergroup SET allow='$allow' WHERE groupid='$groupid' AND pageid='$pageid' AND feature='$feature' AND featureid='$featureid' AND ability='$ability'");
        		}else{
                    execute_db_sql("UPDATE roles_ability_pergroup SET allow='$allow' WHERE groupid='$groupid' AND pageid='$pageid' AND ability='$ability'");
        		}
            }
		}elseif($allow !== false && !$alreadyset){ //If ability is SET to 1 or 0 and isn't already set in the db
            if($feature && $featureid){
                execute_db_sql("INSERT INTO roles_ability_perfeature_pergroup (groupid,pageid,feature,featureid,ability,allow) VALUES('$groupid','$pageid','$feature','$featureid','$ability','$allow')");
            }else{
                execute_db_sql("INSERT INTO roles_ability_pergroup (groupid,pageid,ability,allow) VALUES('$groupid','$pageid','$ability','$allow')");
    		}
		}
		$i++;
	}
	echo "Changes Saved";
}

function refresh_user_roles(){
global $CFG, $USER, $MYVARS, $ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
    $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
    $myroleid = get_user_role($USER->userid,$pageid);
    $roleid = get_user_role($userid,$pageid,true);
    if (isset($roleid)) {
        if(is_siteadmin($userid)){
            $rolename = "<strong>Site Admin</strong>";
        } else {
            $rolename = get_db_field("display_name","roles","roleid='$roleid'");
        }
    } else {
    	$roleid = 0;
    	$rolename = "Unassigned";
    }
    $returnme = "<br /><br />Current Role: " . $rolename . '<br /><br />Assign Role:';
    $sql_admin = $pageid != $CFG->SITEID ? " WHERE roleid != '$ROLES->admin'" : "";
	$SQL = "SELECT * FROM roles $sql_admin ORDER BY roleid";
	if($roles = get_db_result($SQL)){
		$returnme .= '<form id="roles_form"><div style="width:100%; text-align:center"><select name="roleid" id="role_select" >';
		while($row = fetch_row($roles)){
			if($row['roleid'] != $roleid && $row['roleid'] >= $myroleid){ $returnme .= '<option value="'.$row['roleid'].'">'.$row['display_name'].'</option>'; }
		}
		$returnme .= '</select>&nbsp;<input type="button" value="Assign" onclick="ajaxapi(\'/ajax/roles_ajax.php\',\'assign_role\',\'&amp;pageid='.$pageid.'&amp;userid='.$userid.'&amp;roleid=\'+document.getElementById(\'role_select\').value,function(){ simple_display(\'per_page_saved_div1\'); simple_display(\'per_page_saved_div2\'); ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_user_roles\',\'&amp;pageid='.$pageid.'&amp;userid=\'+document.getElementById(\'per_page_user_select\').value,function(){ simple_display(\'per_page_roles_div\'); }); setTimeout(function(){ clear_display(\'per_page_saved_div1\'); clear_display(\'per_page_saved_div2\'); },5000);} );" /><div id="per_page_saved_div1" style="height: 2px; padding-top: 10px;"></div></div></form>';
	}
	echo $returnme;
}

function assign_role(){
global $CFG, $MYVARS, $ROLES;
	$roleid = $MYVARS->GET['roleid'];
	$userid = $MYVARS->GET['userid'];
	$pageid = $MYVARS->GET['pageid'];

	if(execute_db_sql("DELETE FROM roles_assignment WHERE userid='$userid' AND pageid='$pageid'")){
		if($roleid !== $ROLES->none){ //No role besides "No Role" was given
            if(execute_db_sql("INSERT INTO roles_assignment (userid,pageid,roleid) VALUES('$userid','$pageid','$roleid')")){
                echo "Changes Saved";
            }else{
                echo "No Role Given";
            }
        }else{
            echo "Role Removed";
        }
	}else{
		echo "Changes Not Saved";
	}
}
?>
