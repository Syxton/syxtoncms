<?php
/***************************************************************************
* roleslib.php - Roles function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/08/2016
* Revision: 1.2.7
***************************************************************************/

if(!isset($LIBHEADER)) include('header.php');
$ROLESLIB = true;

$ABILITIES = new stdClass();

function is_siteadmin($userid){
global $CFG,$ROLES;
	if(!isset($userid)){ return false; }
	if(!get_db_count("SELECT * FROM roles_assignment WHERE userid='$userid' AND pageid='$CFG->SITEID' AND confirm=0 AND roleid='$ROLES->admin'")){ return false; }
	return true;
}

function remove_all_roles($userid){
    $SQL = "DELETE FROM roles_assignment WHERE userid='$userid'";
    execute_db_sql($SQL);
    $SQL = "DELETE FROM roles_ability_peruser WHERE userid='$userid'";
    execute_db_sql($SQL);
    $SQL = "DELETE FROM roles_ability_perfeature_peruser WHERE userid='$userid'";
    execute_db_sql($SQL);  
}

//Add an ability and assign a role it's value
function add_role_ability($section,$ability,$displayname,$power,$desc,$creator='0',$editor='0',$guest='0',$visitor='0'){
    if(!get_db_row("SELECT * FRMO abilities WHERE section='$section' AND section_display='$displayname'")){
        //CREATE ROLE ABILITY
    	execute_db_sql("INSERT INTO abilities (section,section_display,ability,ability_display,power) VALUES('$section','$displayname','$ability','$desc','$power')");	
    	
    	//ASSIGN PERMISSIONS
    	execute_db_sql("INSERT INTO roles_ability (roleid,ability,allow,section) VALUES(1,'$ability','1','$section'),(2,'$ability','$creator','$section'),(3,'$ability','$editor','$section'),(4,'$ability','$guest','$section'),(5,'$ability','$visitor','$section'),(6,'$ability','0','$section')");        
    }
}

//	This is a fully implemented roles structure for the system.  The following is the importance structure
//	
//	Feature specific per individual user per page
//		| 
//		Indivual user specific per page
//			|
//			Feature specific per group per page
//				|
//				Group specific per page
//					|
//					Feature specific per role per page
//						|
//						Role specific per page
//							|
//							Role specific per SITE LEVEL permissions
function user_has_ability_in_page($userid, $ability, $pageid, $feature = "", $featureid=0){
global $CFG,$ROLES,$ABILITIES;
	
	if(!$featureid && isset($ABILITIES->$ability)){ //Get cached abilities first (SAVES TIME!)
		if($ABILITIES->$ability->allow){ return true; }
        return false;	
	}

	if(is_siteadmin($userid)){ return true; }
	$roleid = get_user_role($userid,$pageid);
    
	if($roleid == $ROLES->visitor){
		if(role_has_ability_in_page($ROLES->visitor, $ability, $pageid, $feature, $featureid)){ return true; }
		return false;
	}else{

	//$groupallowed = -1; //This will be a group check.  1 if allowed 0 if not allowed -1 if not specified
	$groupallowed = groups_SQL($userid,$pageid,$ability);
	$featuregroupallowed = groups_SQL($userid,$pageid,$ability,$feature,$featureid); //This will be a group check.  1 if allowed 0 if not allowed -1 if not specified

	$SQL = "
	SELECT 1 as allowed FROM roles_ability ra WHERE
		(
			1 IN (SELECT allow FROM roles_ability_perfeature_peruser WHERE pageid=$pageid AND userid=$userid AND feature='$feature' AND featureid='$featureid' AND ability='$ability' AND allow=1)
			OR
			(
				1 IN (SELECT allow FROM roles_ability_peruser WHERE userid=$userid AND pageid=$pageid AND ability='$ability' AND allow=1)
				OR
				$featuregroupallowed[0]
				$groupallowed[0]
				(
					1 IN (SELECT allow FROM roles_ability_perfeature WHERE pageid=$pageid AND roleid=$roleid AND feature='$feature' AND featureid='$featureid' AND ability='$ability' AND allow=1) 
					OR
					(
						1 IN (SELECT allow FROM roles_ability_perpage WHERE roleid=$roleid AND pageid=$pageid AND ability='$ability' AND allow=1)
						OR
						(
							1 IN (SELECT allow FROM roles_ability WHERE roleid=$roleid AND ability='$ability' AND allow=1)
						)
						AND 0 NOT IN (SELECT allow FROM roles_ability_perpage WHERE roleid=$roleid AND pageid=$pageid AND ability='$ability' AND allow=0)
					)
					AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature WHERE pageid=$pageid AND roleid=$roleid AND feature='$feature' AND featureid='$featureid' AND ability='$ability' AND allow=0)
				$groupallowed[1]
				$featuregroupallowed[1]	
				)
				AND 0 NOT IN (SELECT allow FROM roles_ability_peruser WHERE userid=$userid AND pageid=$pageid AND ability='$ability' AND allow=0)
			)
			AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature_peruser WHERE pageid=$pageid AND userid=$userid AND feature='$feature' AND featureid='$featureid' AND ability='$ability' AND allow=0)
		)
	LIMIT 1
	";

	if(get_db_row($SQL)) {
	   return true; 
    } else {
        error_log("FAILED SQL: $SQL");
	}
	return false;
	}
}

//	This is a fully implemented roles structure for the system.  The following is the importance structure
//	
//	Feature specific per individual user per page
//		| 
//		Indivual user specific per page
//			|
//			Feature specific per group per page
//				|
//				Group specific per page
//					|
//					Feature specific per role per page
//						|
//						Role specific per page
//							|
//							Role specific per SITE LEVEL permissions
function get_user_abilities($userid,$pageid,$section=false,$feature="",$featureid=0){
global $CFG,$ROLES,$ABILITIES;
	
	if(is_siteadmin($userid)){
		return get_role_abilities($ROLES->admin,$CFG->SITEID,$section);
	}
		 
	$roleid = get_user_role($userid,$pageid);
	if($roleid == $ROLES->visitor){
		return get_role_abilities($ROLES->visitor,$pageid,$section, $feature, $featureid);
	}else{  		
        if($section){
            if(is_array($section)){
                $section_sql = "";
                foreach($section as $s){
                    $section_sql .= $section_sql == "" ? "section = '$s'" : " || section = '$s'";
                }
                $section = " WHERE ($section_sql)";
            }else{
                $section = " WHERE section = '$section'";    
            }
        }else{
            $section = "";
        }
        
    	//$groupallowed = -1; //This will be a group check.  1 if allowed 0 if not allowed -1 if not specified
    	$groupallowed = groups_SQL($userid,$pageid);
    	$featuregroupallowed = groups_SQL($userid,$pageid,'a.ability',$feature,$featureid); //This will be a group check.  1 if allowed 0 if not allowed -1 if not specified

    	$SQL = "
    	SELECT a.ability,
    		(
    			SELECT 1 as allowed FROM roles_ability ra WHERE
    			(
    				1 IN (SELECT allow FROM roles_ability_perfeature_peruser WHERE pageid=$pageid AND userid=$userid AND feature='$feature' AND featureid='$featureid' AND ability=a.ability AND allow=1)
    				OR
    				(
    					1 IN (SELECT allow FROM roles_ability_peruser WHERE userid=$userid AND pageid=$pageid AND ability=a.ability AND allow=1)
    					OR
    					$featuregroupallowed[0]
    					$groupallowed[0]
    					(
    						1 IN (SELECT allow FROM roles_ability_perfeature WHERE pageid=$pageid AND roleid=$roleid AND feature='$feature' AND featureid='$featureid' AND ability=a.ability AND allow=1) 
    						OR			
    						(
    							1 IN (SELECT allow FROM roles_ability_perpage WHERE roleid=$roleid AND pageid=$pageid AND ability=a.ability AND allow=1)
    							OR
    							(
    								1 IN (SELECT allow FROM roles_ability WHERE roleid=$roleid AND ability=a.ability AND allow=1)
    							)
    							AND 0 NOT IN (SELECT allow FROM roles_ability_perpage WHERE roleid=$roleid AND pageid=$pageid AND ability=a.ability AND allow=0)
    					)
    					AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature WHERE pageid=$pageid AND roleid=$roleid AND feature='$feature' AND featureid='$featureid' AND ability=a.ability AND allow=0)
    				$groupallowed[1]
    				$featuregroupallowed[1]
    				)	
    				AND 0 NOT IN (SELECT allow FROM roles_ability_peruser WHERE userid=$userid AND pageid=$pageid AND ability=a.ability AND allow=0)
    			)
    			AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature_peruser WHERE pageid=$pageid AND userid=$userid AND feature='$feature' AND featureid='$featureid' AND ability=a.ability AND allow=0)
    		)
    	LIMIT 1
    	) as allowed FROM abilities a $section ORDER BY section
    	";

    	if($results = get_db_result($SQL)) {
    		while($row = fetch_row($results)){
    			$ability = $row["ability"];
    			$allow = $row["allowed"] == 1 ? 1 : 0;
                
                if(empty($abilities->$ability)){
                    $abilities = new stdClass();     
                    $abilities->$ability = new stdClass();
                }elseif(empty($abilities->$ability)){
                    $abilities->$ability = new stdClass();    
                }
                
    			$abilities->$ability->allow = $allow;		
    		}
    	} else {
            error_log("FAILED SQL: $SQL");
    	}
    	if(!$section){ $ABILITIES = $abilities; }
    	return $abilities;
	}
}

function user_has_ability_in_pages($userid,$ability,$siteviewable = true,$menuitems = true){
global $CFG,$ROLES;
	$siteviewable = !$siteviewable ? "AND p.pageid NOT IN (SELECT pageid FROM pages WHERE siteviewable=1)" : "";
	$menuitems = !$menuitems ? "AND p.pageid NOT IN (SELECT pageid FROM pages WHERE menu_page=1)" : "";
	if(is_siteadmin($userid)){ return get_db_result("SELECT p.* FROM pages p WHERE p.pageid > 0 $siteviewable $menuitems"); }
	$SQL = "SELECT a.*, (SELECT name FROM pages WHERE pageid=a.pageid) as name FROM (SELECT pu.pageid FROM roles_ability_peruser pu WHERE pu.userid=$userid AND pu.ability='$ability' and pu.allow=1";
	$allroles = get_db_result("SELECT * FROM roles");
	while($role = fetch_row($allroles)){
		$roleid = $role["roleid"];
		$SQL .= " UNION ALL SELECT p.pageid FROM pages p WHERE p.pageid IN
					(
					SELECT ra.pageid FROM roles_assignment ra WHERE ra.userid=$userid AND ra.roleid=$roleid AND ra.confirm=0 AND ra.roleid IN
						(
						SELECT rab.roleid FROM roles_ability rab WHERE rab.ability='$ability' AND rab.allow=1
						)
					)
					OR p.pageid IN
					(
					SELECT pp.pageid FROM roles_ability_perpage pp WHERE pp.roleid=$roleid AND pp.ability='$ability' AND pp.allow=1
					)
					$siteviewable $menuitems
					";
	}
	$SQL .= ") a GROUP BY a.pageid";
    
    if($results = get_db_result($SQL)){
        return $results;
    } else {
        error_log("FAILED SQL: $SQL");
        return false;
	}
}

//	This is a fully implemented roles structure for the system.  The following is the importance structure
//	
//			Feature specific per role for given page
//				|
//				Role specific per page
//					|
//					Role specific per SITE LEVEL permissions
function get_role_abilities($roleid, $pageid, $section=false,$feature="", $featureid=0){
global $CFG,$ROLES,$ABILITIES;
    if($section){
        if(is_array($section)){
            $section_sql = "";
            foreach($section as $s){
                $section_sql .= $section_sql == "" ? "section = '$s'" : " || section = '$s'";
            }
            $section = " WHERE ($section_sql)";
        }else{
            $section = " WHERE section = '$section'";    
        }
    }else{
        $section = "";
    }
	
	$SQL = "
	SELECT a.ability,
		(
	SELECT 1 as allowed FROM roles_ability ra WHERE
		(
		1 IN (SELECT allow FROM roles_ability_perfeature WHERE pageid='$pageid' AND roleid='$roleid' AND feature='$feature' AND featureid='$featureid' AND ability=a.ability AND allow=1) 
		OR
			(
			1 IN (SELECT allow FROM roles_ability_perpage WHERE roleid='$roleid' AND pageid='$pageid' AND ability=a.ability AND allow=1)
			OR
				(
				1 IN (SELECT allow FROM roles_ability WHERE roleid='$roleid' AND ability=a.ability AND allow=1)
				)
			AND 0 NOT IN (SELECT allow FROM roles_ability_perpage WHERE roleid='$roleid' AND pageid='$pageid' AND ability=a.ability AND allow=0)
			)
		AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature WHERE pageid='$pageid' AND roleid='$roleid' AND feature='$feature' AND featureid='$featureid' AND ability=a.ability AND allow=0)
		)
	LIMIT 1
	) as allowed FROM abilities a $section ORDER BY section
	";

	if($results = get_db_result($SQL)){
        $abilities = new stdClass();
		while($row = fetch_row($results)){
			$ability = $row["ability"];
			$allow = $row["allowed"] == 1 ? 1 : 0;
            $abilities->$ability = new stdClass();
			$abilities->$ability->allow = $allow;		
		}
	} else {
        error_log("FAILED SQL: $SQL");
	}
	if(empty($section) && !empty($abilities)){ $ABILITIES = $abilities; }
	return $abilities;
}


//	This is a fully implemented roles structure for the system.  The following is the importance structure
//	
//			Feature specific per role for given page
//				|
//				Role specific per page
//					|
//					Role specific per SITE LEVEL permissions
function role_has_ability_in_page($roleid, $ability, $pageid, $feature="", $featureid=0){
global $CFG,$ROLES;

	$SQL = "
	SELECT 1 as allowed FROM roles_ability ra WHERE
		(
		1 IN (SELECT allow FROM roles_ability_perfeature WHERE pageid=$pageid AND roleid=$roleid AND feature='$feature' AND featureid='$featureid' AND ability='$ability' AND allow=1) 
		OR
			(
			1 IN (SELECT allow FROM roles_ability_perpage WHERE roleid=$roleid AND pageid=$pageid AND ability='$ability' AND allow=1)
			OR
				(
				1 IN (SELECT allow FROM roles_ability WHERE roleid=$roleid AND ability='$ability' AND allow=1)
				)
			AND 0 NOT IN (SELECT allow FROM roles_ability_perpage WHERE roleid=$roleid AND pageid=$pageid AND ability='$ability' AND allow=0)
			)
		AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature WHERE pageid=$pageid AND roleid=$roleid AND feature='$feature' AND featureid='$featureid' AND ability='$ability' AND allow=0)
		)
	LIMIT 1
	";

	if(get_db_row($SQL)){ return true;
	} else {
        error_log("FAILED SQL: $SQL");
        return false;
	}
}

function load_roles(){
global $CFG;
	$allroles = get_db_result("SELECT * FROM roles");
    $ROLES = new stdClass();
    while($row = fetch_row($allroles)){
        $rolename = $row['name'];
		$ROLES->$rolename = $row['roleid'];
	}
	return $ROLES;
}

function get_user_role($userid=0, $pageid){
global $CFG,$ROLES,$USER;
	if(is_siteadmin($userid)){ return $ROLES->admin; }
	$SQL = "SELECT * FROM roles_assignment WHERE userid='$userid' AND pageid='$pageid' AND confirm=0 LIMIT 1";
	if($result = get_db_result($SQL)){	
		while($row = fetch_row($result)){
			return $row['roleid'];
		}
	}
	if(is_logged_in()){
		if(get_db_field("opendoorpolicy","pages","pageid='$pageid'") == 1){ return get_db_field("default_role","pages","pageid='$pageid'"); }
	}
    
	if(is_visitor_allowed_page($pageid)){ return $ROLES->visitor; //if it is a site viewable page and the user has no specified role, default to visitor
	}else{ return $ROLES->none; }  
}

function users_that_have_ability_in_page($ability, $pageid){
global $CFG,$ROLES;
	$page = get_db_row("SELECT * FROM pages WHERE pageid=$pageid");
	if($page["siteviewable"] || $page["opendoorpolicy"]){  
    	$SQL = "
    	SELECT * FROM users u
    	WHERE
    	((
    		userid NOT IN 
    			(
    			SELECT userid FROM roles_assignment 
    				WHERE pageid=$pageid AND confirm=0 AND 
    							(
    							roleid IN 
    								(SELECT roleid FROM roles_ability WHERE ability='$ability' AND allow=0) 
    							 	AND roleid NOT IN 
    								(SELECT roleid FROM roles_ability_perpage WHERE ability='$ability' AND pageid=$pageid AND allow=1)
    							)
    							OR
    							roleid IN (SELECT roleid FROM roles_ability_perpage WHERE ability='$ability' AND pageid=$pageid AND allow=0)
    			)
    		AND userid NOT IN (SELECT pu.userid FROM roles_ability_peruser pu WHERE pu.pageid='$pageid' AND pu.ability='$ability' AND pu.allow='0')
    	) 
    	OR userid IN (SELECT pu.userid FROM roles_ability_peruser pu WHERE pu.pageid='$pageid' AND pu.ability='$ability' AND pu.allow='1'))
    	OR userid IN (SELECT userid FROM roles_assignment WHERE roleid=1 AND pageid=".$CFG->SITEID.")
    	";
	}else{
    	$SQL = "
    	SELECT * FROM users u
    	WHERE
    	((
    		userid IN 
    			(
    			SELECT userid FROM roles_assignment 
    				WHERE pageid=$pageid AND confirm=0 AND 
    							(
    							roleid IN 
    								(SELECT roleid FROM roles_ability WHERE ability='$ability' AND allow=1) 
    							 	AND roleid NOT IN 
    								(SELECT roleid FROM roles_ability_perpage WHERE ability='$ability' AND pageid=$pageid AND allow=0)
    							)
    							OR
    							roleid IN (SELECT roleid FROM roles_ability_perpage WHERE ability='$ability' AND pageid=$pageid AND allow=1)
    			)
    		AND userid NOT IN (SELECT pu.userid FROM roles_ability_peruser pu WHERE pu.pageid='$pageid' AND pu.ability='$ability' AND pu.allow='0')
    	) 
    	OR userid IN (SELECT pu.userid FROM roles_ability_peruser pu WHERE pu.pageid='$pageid' AND pu.ability='$ability' AND pu.allow='1'))
    	OR userid IN (SELECT userid FROM roles_assignment WHERE roleid=1 AND pageid=".$CFG->SITEID.")
    	";
	}

    if($results = get_db_result($SQL)){
        return $results;
    } else {
        error_log("FAILED SQL: $SQL");
        return false;
	}
}

//GROUPS AREA

//This function will get an array of the groups hierarchy
function get_groups_hierarchy($userid, $pageid){
	$i = 0;
	if($group = get_db_row("SELECT * FROM groups_users WHERE pageid=$pageid AND userid=$userid")){	
		$groups_array[$i] = $group["groupid"];
		$group = get_db_row("SELECT * FROM groups WHERE groupid=" . $group["groupid"]);
        while(isset($group["parent"])){
			$group = get_db_row("SELECT * FROM groups WHERE groupid=" . $group["parent"]);
			$i++;
			$groups_array[$i] = $group["groupid"];
		}
		return $groups_array;
	}else{ return false; }
}

//This function gets the permission of an ability according to groups
function groups_SQL($userid,$pageid,$ability='a.ability',$feature=false,$featureid=false){
	//Return array of groups hierarchy for given user in this page
	$hierarchy = get_groups_hierarchy($userid, $pageid);
	
	//Groups don't exist or featureid was not given
	if(!$hierarchy || $featureid===0){	$groupsSQL[0] = "";	$groupsSQL[1] = "";	return $groupsSQL; }
	
	//Add quotes around a specific ability or link to SQL variable if not given
	$ability = $ability == 'a.ability' ? 'a.ability' : "'".$ability."'";
	
	//Decide which table the SQL requires
	$table = $feature && $featureid ? 'roles_ability_perfeature_pergroup' : 'roles_ability_pergroup';
	
	//Add feature checks if a perfeature SQL is asked for
	$extraSQL = $feature && $featureid ? "AND feature='$feature' AND featureid='$featureid'" : "";
	
	//Create dynamic groups sql
	$SQL1 = "";	$SQL2 = "";	$i=0;
	while(isset($hierarchy[$i])){
		$SQL1 .= "( 1 IN (SELECT allow FROM $table WHERE groupid=$hierarchy[$i] AND ability=$ability AND allow=1 $extraSQL) OR ";
		$i++;
	}$i--; //take it back down 1
	
	while(isset($hierarchy[$i])){
		$SQL2 .= " )  AND 0 NOT IN (SELECT allow FROM $table WHERE groupid=".$hierarchy[($i)]." AND ability=$ability AND allow=0 $extraSQL) ";
		$i--;
	}
	
	$groupsSQL[0] = $SQL1;
	$groupsSQL[1] = $SQL2;
	
	return $groupsSQL;
}

function merge_abilities($abilities){
global $USER;
    $merged = array();
    foreach($abilities as $ability){
       $merged =  (object) array_merge((array) $merged, (array) $ability);
    }
    return $merged;
}

function group_page($pageid,$feature,$featureid){
global $CFG,$USER;
    $returnme = '<table style="font-size:1em;width:100%">
                    <tr><td style="width:40%;text-align:center;vertical-align:top;">
                        <strong>Groups:</strong>
                            <div id="group_list_div">
                                '. groups_list($pageid,$feature,$featureid) .'
                            </div>';
    $returnme .= user_has_ability_in_page($USER->userid,"manage_groups",$pageid) ? '<a class="imgandlink" href="javascript: ajaxapi(\'/ajax/roles_ajax.php\',\'create_edit_group_form\',\'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;pageid='.$pageid.'\',function(){ simple_display(\'per_group_display_div\'); });"><img src="'.$CFG->wwwroot.'/images/add.png" /> Create New Group</a>' : '';
    $returnme .= '          <br />
                        </td>
                        <td style="width:5px"></td>
                        <td><div id="per_group_display_div"></div></td>
                    </tr>
                </table><hr />
                <div id="per_group_saved_div1" style="width:100%;text-align:center;height: 2px;padding-bottom: 18px;padding-top: 10px;"></div><div id="per_group_abilities_div" style="width:100%;"></div><div id="per_group_saved_div2" style="width:100%;text-align:center;height: 2px;padding-bottom: 10px;padding-top: 10px;"></div>';
    return $returnme;
}

function groups_list($pageid,$feature = false,$featureid = false,$action=true,$selectid=0,$excludeid=0,$excludechildrenofid=0,$width="100%",$id="group_select",$name="groupid"){
    $action = $action ? 'onchange="if($(\'#group_select\').val() > 0){ ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_group_users\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;groupid=\'+document.getElementById(\'group_select\').value,function(){ simple_display(\'per_group_display_div\'); }); ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_group_abilities\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;groupid=\'+document.getElementById(\'group_select\').value,function(){ simple_display(\'per_group_abilities_div\'); });}else{ clear_display(\'per_group_display_div\'); clear_display(\'per_group_abilities_div\'); }"' : '';
    $returnme = '<select style="width: '.$width.'; font-size:.85em;" name="'.$name.'" id="'.$id.'" '.$action.' >';
    $returnme .= '<option value="0">No group selected</option>';
    $returnme .= sub_groups_list($pageid,false,"",$selectid,$excludeid,$excludechildrenofid);
    $returnme .= '</select>';
    return $returnme;    
}

function sub_groups_list($pageid,$groupid=false,$level="",$selectid=0,$excludeid=0,$excludechildrenofid=0){
    $returnme = "";
    $subsql = $groupid ? "parent='$groupid'" : "parent='0'";
    $SQL = "SELECT * FROM groups WHERE pageid='$pageid' AND $subsql ORDER BY name";

    if($groups = get_db_result($SQL)){
        while($group = fetch_row($groups)){
            $group_count = get_db_count("SELECT * FROM groups_users WHERE groupid='".$group['groupid']."'");
            $selected = $selectid == $group["groupid"] ? "selected" : "";
            $returnme .= $excludeid != $group["groupid"] ? '<option value="'.$group['groupid'].'" '.$selected.'>'.$level.$group['name'].' ('.$group_count.')</option>' : '';
            if($subgroups = get_db_row("SELECT * FROM groups WHERE pageid='$pageid' AND parent = '".$group['groupid']."'")){
                $returnme .= $excludechildrenofid != $group["groupid"] ? sub_groups_list($pageid,$group['groupid'],$level."-- ",$selectid,$excludeid) : '';    
            }
		}
	}
    return $returnme;   
}

function print_abilities($pageid,$type = "per_role_",$roleid = false,$userid = false,$feature = false,$featureid = false,$groupid = false){
global $CFG;
	$rightslist = $currentstyle = $section = $notsettitle = $notsettoggle = "";
	$table_style = 'class="roles_table"';
	$style_header = 'class="roles_header"';
	$style_row1 = 'class="roles_row1"';
	$style_row2 = 'class="roles_row2"';
    $sql_add = $feature && $featureid ? " WHERE section='$feature' OR (ability='editfeaturesettings' OR ability='removefeatures' OR ability='movefeatures' OR ability='edit_feature_abilities' OR ability='edit_feature_group_abilities' OR ability='edit_feature_user_abilities') " : "";
	$SQL = "SELECT *, CONCAT(section,ability) as i FROM abilities $sql_add GROUP BY i ORDER BY section";

    if($pages = get_db_result($SQL)){
        if($roleid){
            $returnme = '<div style="width:100%; text-align:center"><input type="button" value="Save" onclick="ajaxapi(\'/ajax/roles_ajax.php\',\'save_ability_changes\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\'+create_request_string(\''.$type.'roles_form\'),function(){ simple_display(\''.$type.'saved_div1\'); simple_display(\''.$type.'saved_div2\'); setTimeout(function(){ clear_display(\''.$type.'saved_div1\'); clear_display(\''.$type.'saved_div2\'); },5000);} );ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_edit_roles\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;roleid=\'+document.getElementById(\''.$type.'role_select\').value,function(){ simple_display(\''.$type.'abilities_div\'); });" /></div>';
        }elseif($groupid){
            $returnme = '<div style="width:100%; text-align:center"><input type="button" value="Save" onclick="ajaxapi(\'/ajax/roles_ajax.php\',\'save_group_ability_changes\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;groupid='.$groupid.'\'+create_request_string(\''.$type.'roles_form\'),function(){ simple_display(\''.$type.'saved_div1\'); simple_display(\''.$type.'saved_div2\'); setTimeout(function(){ clear_display(\''.$type.'saved_div1\'); clear_display(\''.$type.'saved_div2\'); },5000);} ); ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_group_abilities\',\'&amp;groupid='.$groupid.'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;userid=\'+document.getElementById(\''.$type.'user_select\').value,function(){ simple_display(\''.$type.'abilities_div\'); });" /></div>';  
        }elseif($userid){
            $returnme = '<div style="width:100%; text-align:center"><input type="button" value="Save" onclick="ajaxapi(\'/ajax/roles_ajax.php\',\'save_user_ability_changes\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;userid='.$userid.'\'+create_request_string(\''.$type.'roles_form\'),function(){ simple_display(\''.$type.'saved_div1\'); simple_display(\''.$type.'saved_div2\'); setTimeout(function(){ clear_display(\''.$type.'saved_div1\'); clear_display(\''.$type.'saved_div2\'); },5000);} ); ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_user_abilities\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;userid=\'+document.getElementById(\''.$type.'user_select\').value,function(){ simple_display(\''.$type.'abilities_div\'); });" /></div>';  
        }
        if($groupid){ $notsettitle = "Unset"; }
        $returnme .= '<table '.$table_style.'><tr><td>Abilities</td><td style="width: 32px;">Allow</td><td style="width: 32px;">'.$notsettitle.'</td><td style="width: 32px;">Deny</td></tr>';
    	$i=0;
		while($row = fetch_row($pages)){
			$currentstyle = $currentstyle == $style_row1 ? $style_row2 : $style_row1;
			$breakline = $i > 0 ? "<br />" : "";
			$printsection = $section == $row['section'] ? "" : "<tr><td colspan=\"4\">$breakline</td></tr><tr ".$style_header.'><td colspan="4"><strong>' . $row['section_display'] . '</strong></td></tr>';
			$currentstyle = $printsection != "" ? $style_row1 : $currentstyle;
            if($roleid && empty($userid)){ //Role based only
                $rights = role_has_ability_in_page($roleid,$row['ability'],$pageid,$feature,$featureid) ? "1" : "0";
                $notify = get_db_count("SELECT * FROM roles_ability_perpage WHERE pageid='$pageid' AND roleid='$roleid' AND ability='".$row['ability']."'") ? 'background-color:yellow;' : '';
            }elseif($groupid){ //Group based
                $rights = ($feature && $featureid) ? get_db_row("SELECT * FROM roles_ability_perfeature_pergroup WHERE pageid='$pageid' AND feature='$feature' AND featureid='$featureid' AND groupid='$groupid' AND ability='".$row['ability']."'") : get_db_row("SELECT * FROM roles_ability_pergroup WHERE pageid='$pageid' AND groupid='$groupid' AND ability='".$row['ability']."'");
                $rights = $rights["allow"] === "0" ? "0" : ($rights["allow"] === "1" ? "1" : false);
                $notify = $rights !== false ? 'background-color:yellow;"' : '';
                $notsettoggle = $rights === false ? '<input onclick="clear_highlights(\''.$type.'abilty_'.$row["abilityid"].'_no\',\''.$type.'abilty_'.$row["abilityid"].'_yes\');" type="radio" name="'.$row['ability'].'" value="" checked>' : '<input onclick="clear_highlights(\''.$type.'abilty_'.$row["abilityid"].'_no\',\''.$type.'abilty_'.$row["abilityid"].'_yes\');" type="radio" name="'.$row['ability'].'" value="">';
            }elseif($userid){ //User based
                if($feature && $featureid){ //Feature user override
                    $rights = user_has_ability_in_page($userid,$row['ability'],$pageid,$feature,$featureid) ? "1" : "0";
                    $notify = get_db_count("SELECT * FROM roles_ability_perfeature_peruser WHERE pageid='$pageid' AND feature='$feature' AND featureid='$featureid' AND userid='$userid' AND ability='".$row['ability']."'") ? 'background-color:yellow;' : '';
    			}else{ //Page user override
                    $rights = user_has_ability_in_page($userid,$row['ability'],$pageid) ? "1" : "0";
                    $notify = get_db_count("SELECT * FROM roles_ability_peruser WHERE pageid='$pageid' AND userid='$userid' AND ability='".$row['ability']."'") ? 'background-color:yellow;' : '';
    			}                
            }
            $swap_function = $groupid ? "swap_highlights2" : "swap_highlights";
			
            if($rights === "1"){ //set to allow
                $radiobuttons = '<td><div style="width:22px;'.$notify.'" id="'.$type.'abilty_'.$row["abilityid"].'_yes"><input onclick="'.$swap_function.'(\''.$type.'abilty_'.$row["abilityid"].'_yes\',\''.$type.'abilty_'.$row["abilityid"].'_no\');" type="radio" name="'.$row['ability'].'" value="1" checked></div></td><td>'.$notsettoggle.'</td><td><div style="width:22px;" id="'.$type.'abilty_'.$row["abilityid"].'_no"><input onclick="'.$swap_function.'(\''.$type.'abilty_'.$row["abilityid"].'_no\',\''.$type.'abilty_'.$row["abilityid"].'_yes\');" type="radio" name="'.$row['ability'].'" value="0"></div></td>';	 
			}elseif($rights === "0"){ //set to disallow
                $radiobuttons = '<td><div style="width:22px;" id="'.$type.'abilty_'.$row["abilityid"].'_yes"><input onclick="'.$swap_function.'(\''.$type.'abilty_'.$row["abilityid"].'_yes\',\''.$type.'abilty_'.$row["abilityid"].'_no\');" type="radio" name="'.$row['ability'].'" value="1"></div></td><td>'.$notsettoggle.'</td><td><div style="width:22px;'.$notify.'" id="'.$type.'abilty_'.$row["abilityid"].'_no"><input onclick="'.$swap_function.'(\''.$type.'abilty_'.$row["abilityid"].'_no\',\''.$type.'abilty_'.$row["abilityid"].'_yes\');" type="radio" name="'.$row['ability'].'" value="0" checked></div></td>';		 
			}else{ //not set
                $radiobuttons = '<td><div style="width:22px;" id="'.$type.'abilty_'.$row["abilityid"].'_yes"><input onclick="'.$swap_function.'(\''.$type.'abilty_'.$row["abilityid"].'_yes\',\''.$type.'abilty_'.$row["abilityid"].'_no\');" type="radio" name="'.$row['ability'].'" value="1"></div></td><td>'.$notsettoggle.'</td><td><div style="width:22px;'.$notify.'" id="'.$type.'abilty_'.$row["abilityid"].'_no"><input onclick="'.$swap_function.'(\''.$type.'abilty_'.$row["abilityid"].'_no\',\''.$type.'abilty_'.$row["abilityid"].'_yes\');" type="radio" name="'.$row['ability'].'" value="0"></div></td>';					 
			}
            
            $rightslist .= $rightslist == "" ? $row['ability'] : "**".$row['ability'];
			$section = $row['section'];
			$returnme .= $printsection;
			$returnme .= '<tr '.$currentstyle.'><td>' . $row['ability_display'] . "</td>$radiobuttons</tr>";		
		$i++;
		}
        $returnme .= '</table><input type="hidden" name="'.$type.'rightslist" value="'.$rightslist.'" />';
        $returnme .= '<div style="width:100%; text-align:center"><br />';
	    
        if($roleid){
            $returnme .= '<input type="button" value="Save" onclick="ajaxapi(\'/ajax/roles_ajax.php\',\'save_ability_changes\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'\'+create_request_string(\''.$type.'roles_form\'),function(){ simple_display(\''.$type.'saved_div1\'); simple_display(\''.$type.'saved_div2\'); setTimeout(function(){ clear_display(\''.$type.'saved_div1\'); clear_display(\''.$type.'saved_div2\'); },5000);} );ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_edit_roles\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;roleid=\'+document.getElementById(\''.$type.'role_select\').value,function(){ simple_display(\''.$type.'abilities_div\'); });" />';
        }elseif($groupid){
            $returnme .= '<input type="button" value="Save" onclick="ajaxapi(\'/ajax/roles_ajax.php\',\'save_group_ability_changes\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;groupid='.$groupid.'\'+create_request_string(\''.$type.'roles_form\'),function(){ simple_display(\''.$type.'saved_div1\'); simple_display(\''.$type.'saved_div2\'); setTimeout(function(){ clear_display(\''.$type.'saved_div1\'); clear_display(\''.$type.'saved_div2\'); },5000);} ); ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_user_abilities\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;userid=\'+document.getElementById(\''.$type.'user_select\').value,function(){ simple_display(\''.$type.'abilities_div\'); });" />';  
        }elseif($userid){
            $returnme .= '<input type="button" value="Save" onclick="ajaxapi(\'/ajax/roles_ajax.php\',\'save_user_ability_changes\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;userid='.$userid.'\'+create_request_string(\''.$type.'roles_form\'),function(){ simple_display(\''.$type.'saved_div1\'); simple_display(\''.$type.'saved_div2\'); setTimeout(function(){ clear_display(\''.$type.'saved_div1\'); clear_display(\''.$type.'saved_div2\'); },5000);} ); ajaxapi(\'/ajax/roles_ajax.php\',\'refresh_user_abilities\',\'&amp;pageid='.$pageid.'&amp;feature='.$feature.'&amp;featureid='.$featureid.'&amp;userid=\'+document.getElementById(\''.$type.'user_select\').value,function(){ simple_display(\''.$type.'abilities_div\'); });" />';  
        }
        $returnme .= '</div>';
    }
	return $returnme;        
}
?>