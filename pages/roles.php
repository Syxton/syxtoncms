<?php
/***************************************************************************
* roles.php - Role relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2013
* Revision: 2.5.1
***************************************************************************/

include('header.php');

echo template_use("tmp/roles.template", array(), "roles_header_script");

callfunction();

echo template_use("tmp/page.template", array(), "end_of_page_template");

function assign_roles() {
global $CFG, $MYVARS, $USER, $ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; // Should always be passed

	if (!user_has_ability_in_page($USER->userid, "assign_roles", $pageid)) {
		echo get_page_error_message("no_permission", array("assign_roles"));
		return;
  }

	$myroleid = get_user_role($USER->userid, $pageid);
	$SQL = "SELECT u.*
						FROM users u
					 WHERE u.userid IN (SELECT ra.userid
						 										FROM roles_assignment ra
															 WHERE ra.confirm = 0 AND ra.pageid='$pageid')
						 AND u.userid NOT IN (SELECT ra.userid
							 											FROM roles_assignment ra
																	 WHERE ra.roleid = ".$ROLES->none."
																	 	  OR (ra.pageid='$pageid' AND ra.roleid <= '$myroleid'))
        ORDER BY u.lname";

	$options = "";
	if ($pageid != $CFG->SITEID) {
		if ($roles = get_db_result($SQL)) {
			while ($row = fetch_row($roles)) {
				$options .= template_use("tmp/roles.template", array("user" => $row), "assign_roles_options_template");
			}
		}
	}

	$params = array("pageid" => $pageid, "issiteid" => ($pageid == $CFG->SITEID), "options" => $options);
	echo template_use("tmp/roles.template", $params, "assign_roles_template");
}

function role_specific() {
global $CFG,$USER,$MYVARS,$ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
    $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
    $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
    $abilities = get_user_abilities($USER->userid,$pageid,"roles",$feature,$featureid);
		$roleid = false; $options = "";

	if (!((!$featureid && $abilities->edit_roles->allow) || (($featureid && $abilities->edit_feature_abilities->allow)))) {
		echo get_error_message("generic_permissions");
		return;
	}

	$SQL = "SELECT *
						FROM roles
				   WHERE roleid > " . get_user_role($USER->userid, $pageid) . "
				ORDER BY roleid";

	if ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
    	$roleid = !$roleid ? $row["roleid"] : $roleid;
			$options .= template_use("tmp/roles.template", array("roles" => $row), "role_specific_options_template");
		}
	}

	$params = array("pageid" => $pageid, "feature" => $feature,
									"featureid" => $featureid, "options" => $options,
									"abilities" => print_abilities($pageid, "per_role_", $roleid, false, $feature, $featureid));
	echo template_use("tmp/roles.template", $params, "role_specific_template");
}

function user_specific() {
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
  $abilities = get_user_abilities($USER->userid,$pageid,"roles",$feature,$featureid);

	if (!((!$featureid && $abilities->edit_user_abilities->allow) || ($featureid && $abilities->edit_feature_user_abilities->allow))) {
		echo get_error_message("generic_permissions");
		return;
	}

	$myroleid = get_user_role($USER->userid,$pageid);

  $SQL = "SELECT u.*
					 FROM users u
					WHERE u.userid IN (SELECT ra.userid
															 FROM roles_assignment ra
															WHERE ra.pageid='$pageid')
						AND u.userid NOT IN (SELECT ra.userid
																	 FROM roles_assignment ra
																	WHERE ra.pageid='" . $CFG->SITEID . "'
																	  AND ra.roleid='" . $ROLES->admin . "')
						AND u.userid NOT IN (SELECT ra.userid
							 										 FROM roles_assignment ra
																	WHERE ra.pageid='$pageid'
																	  AND ra.roleid <= '$myroleid')
						AND u.userid != '" . $USER->userid . "'
			 ORDER BY u.lname";

	$options = "";
	if ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
			$options .= template_use("tmp/roles.template", array("user" => $row), "role_specific_options_template");
		}
	}

	$params = array("pageid" => $pageid, "feature" => $feature,
									"featureid" => $featureid, "options" => $options,
									"issiteid" => ($pageid == $CFG->SITEID));
	echo template_use("tmp/roles.template", $params, "user_specific_template");

	echo $returnme;
}

function group_specific() {
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  if (!$featureid) {
      if (!user_has_ability_in_page($USER->userid,"edit_group_abilities",$pageid)) {
				echo get_error_message("generic_permissions");
				return;
			}
  }

	if (!user_has_ability_in_page($USER->userid, "edit_feature_group_abilities", $pageid, $feature, $featureid)) {
		echo get_error_message("generic_permissions");
		return;
	}

	$params = array("grouppage" => group_page($pageid, $feature, $featureid));
	echo template_use("tmp/roles.template", $params, "group_specific_template");
}

function manager() {
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $abilities = merge_abilities(array(get_user_abilities($USER->userid, $pageid, "roles", $feature, $featureid),
																		 get_user_abilities($USER->userid, $pageid, array("feature", "html"), $feature, $featureid)));
  $params = array("wwwroot" => $CFG->wwwroot, "directory" => get_directory(), "feature" => $feature, "featureid" => $featureid);
	$params["warning"] = ($pageid == $CFG->SITEID && !$featureid);
	$params["tab_assign_roles"] = !$featureid && $abilities->assign_roles->allow;
	$params["tab_modify_roles"] = (!$featureid && $abilities->edit_roles->allow) || (($featureid && $abilities->edit_feature_abilities->allow));
	$params["tab_groups"] = (!$featureid && $abilities->edit_group_abilities->allow) || (($featureid && $abilities->edit_feature_group_abilities->allow));
	$params["tab_user"] = (!$featureid && $abilities->edit_user_abilities->allow) || (($featureid && $abilities->edit_feature_user_abilities->allow));
	$params["pagename"] = stripslashes(get_db_field("name","pages","pageid='$pageid'"));
  $params["pageid"] = $pageid;
	$params["featurecontext"] = false;

	if ($featureid && $feature) {
    if (!$settings = fetch_settings($feature, $featureid, $pageid)) {
      make_or_update_settings_array(default_settings($feature, $pageid, $featureid));
     	$settings = fetch_settings($feature, $featureid, $pageid);
    }
		$params["featurecontext"] = true;
		$params["setting"] = $settings->$feature->$featureid->feature_title->setting;
  }

	echo template_use("tmp/roles.template", $params, "roles_manager_template");
}
?>
