<?php
/***************************************************************************
* roles.php - Role relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.5.1
***************************************************************************/

include('header.php');

echo use_template("tmp/roles.template", [], "roles_header_script");

callfunction();

echo use_template("tmp/page.template", [], "end_of_page_template");

function assign_roles() {
global $CFG, $MYVARS, $USER, $ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; // Should always be passed

	if (!user_is_able($USER->userid, "assign_roles", $pageid)) {
        trigger_error(error_string("no_function", ["assign_roles"]), E_USER_WARNING);
		return;
  }

	$myroleid = user_role($USER->userid, $pageid);
	$SQL = "SELECT u.*
						FROM users u
					 WHERE u.userid IN (SELECT ra.userid
						 										FROM roles_assignment ra
															 WHERE ra.confirm = 0 AND ra.pageid='$pageid')
						 AND u.userid NOT IN (SELECT ra.userid
							 											FROM roles_assignment ra
																	 WHERE ra.roleid = " . $ROLES->none."
																	 		OR (ra.pageid='$pageid' AND ra.roleid <= '$myroleid'))
        ORDER BY u.lname";

	$options = "";
	if ($pageid != $CFG->SITEID) {
		if ($roles = get_db_result($SQL)) {
			while ($row = fetch_row($roles)) {
				$options .= use_template("tmp/roles.template", ["user" => $row], "assign_roles_options_template");
			}
		}
	}

	$params = ["pageid" => $pageid, "issiteid" => ($pageid == $CFG->SITEID), "options" => $options];
	echo use_template("tmp/roles.template", $params, "assign_roles_template");
}

function role_specific() {
global $CFG, $USER, $MYVARS, $ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing
    $abilities = user_abilities($USER->userid, $pageid, "roles", $feature, $featureid);
		$roleid = false; $options = "";

	if (!((!$featureid && $abilities->edit_roles->allow) || (($featureid && $abilities->edit_feature_abilities->allow)))) {
		echo error_string("generic_permissions");
		return;
	}

	$SQL = 'SELECT *
				FROM roles
			 WHERE roleid > ' . user_role($USER->userid, $pageid) . '
			ORDER BY roleid';

	if ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
  		$roleid = !$roleid ? $row["roleid"] : $roleid;
			$options .= use_template("tmp/roles.template", ["roles" => $row], "role_specific_options_template");
		}
	}

	$params = ["pageid" => $pageid, "feature" => $feature,
									"featureid" => $featureid, "options" => $options,
									"abilities" => print_abilities($pageid, "per_role_", $roleid, false, $feature, $featureid)];
	echo use_template("tmp/roles.template", $params, "role_specific_template");
}

function user_specific() {
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
	$featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	$feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing
	$abilities = user_abilities($USER->userid, $pageid, "roles", $feature, $featureid);

	if (!((!$featureid && $abilities->edit_user_abilities->allow) || ($featureid && $abilities->edit_feature_user_abilities->allow))) {
		echo error_string("generic_permissions");
		return;
	}

	$myroleid = user_role($USER->userid, $pageid);

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
			$options .= use_template("tmp/roles.template", ["user" => $row], "user_specific_options_template");
		}
	}

	$params = [ "pageid" => $pageid,
				"feature" => $feature,
				"featureid" => $featureid,
				"options" => $options,
				"issiteid" => ($pageid == $CFG->SITEID),
	];
	echo use_template("tmp/roles.template", $params, "user_specific_template");
}

function group_specific() {
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
  $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
  $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

  if (!$featureid) {
      if (!user_is_able($USER->userid, "edit_group_abilities", $pageid)) {
				echo error_string("generic_permissions");
				return;
			}
  }

	if (!user_is_able($USER->userid, "edit_feature_group_abilities", $pageid, $feature, $featureid)) {
		echo error_string("generic_permissions");
		return;
	}

	$params = ["grouppage" => group_page($pageid, $feature, $featureid)];
	echo use_template("tmp/roles.template", $params, "group_specific_template");
}

function manager() {
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    $abilities = merge_abilities([
        user_abilities($USER->userid, $pageid, "roles", $feature, $featureid),
        user_abilities($USER->userid, $pageid, ["feature", "html"], $feature, $featureid),
    ]);
    $params = [
        "feature" => $feature,
        "featureid" => $featureid,
        "warning" => ($pageid == $CFG->SITEID && !$featureid),
        "tab_assign_roles" => (!$featureid && $abilities->assign_roles->allow),
        "tab_modify_roles" => (!$featureid && $abilities->edit_roles->allow) || (($featureid && $abilities->edit_feature_abilities->allow)),
        "tab_groups" => (!$featureid && $abilities->edit_group_abilities->allow) || (($featureid && $abilities->edit_feature_group_abilities->allow)),
        "tab_user" => (!$featureid && $abilities->edit_user_abilities->allow) || (($featureid && $abilities->edit_feature_user_abilities->allow)),
        "pagename" => stripslashes(get_db_field("name", "pages", "pageid='$pageid'")),
        "pageid" => $pageid,
        "featurecontext" => false,
    ];

    if ($featureid && $feature) {
        if (!$settings = fetch_settings($feature, $featureid, $pageid)) {
        save_batch_settings(default_settings($feature, $pageid, $featureid));
            $settings = fetch_settings($feature, $featureid, $pageid);
        }
            $params["featurecontext"] = true;
            $params["setting"] = $settings->$feature->$featureid->feature_title->setting;
    }

	echo use_template("tmp/roles.template", $params, "roles_manager_template");
}
?>