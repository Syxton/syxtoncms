<?php
/***************************************************************************
* roles_ajax.php - Roles Ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 06/11/2021
* Revision: 2.0.4
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
      $options .= template_use("tmp/page.template", array("value" => $row['userid'], "display" => $row['fname'] . ' ' . $row['lname'] . ' (' . $row['email'] . ')'), "select_options_template");
		}
	}
  $params["options"] = $options;
  echo template_use("tmp/roles_ajax.template", $params, "name_search_template");
}

function add_to_group_search() {
global $CFG, $ROLES, $USER, $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $type = !empty($MYVARS->GET['type']) ? $MYVARS->GET['type'] : "per_page_"; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $searchstring = "";	$searcharray = explode(" ",$MYVARS->GET["searchstring"]);
  $i=0;
  foreach ($searcharray as $search) {
    $searchstring .= $i == 0 ? "" : " OR ";
    $searchstring .= "(fname LIKE '%$search%' OR lname LIKE '%$search%' OR email LIKE '%$search%')";
    $i++;
  }

	$myroleid = get_user_role($USER->userid, $pageid);
  $SQL = "SELECT u.*
            FROM users u
           WHERE $searchstring
             AND ('$pageid' = '$CFG->SITEID'
                  OR u.userid IN (SELECT ra.userid
                                    FROM roles_assignment ra
                                   WHERE ra.pageid = '$pageid'))
             AND u.userid NOT IN (SELECT ra.userid
                                    FROM roles_assignment ra
                                   WHERE ra.pageid = '$CFG->SITEID'
                                     AND ra.roleid = '$ROLES->admin')
             AND u.userid NOT IN (SELECT ra.userid
                                    FROM roles_assignment ra
                                   WHERE ra.pageid = '$pageid'
                                     AND ra.roleid <= '$myroleid')
             AND u.userid != '$USER->userid'
             AND u.userid NOT IN (SELECT userid
                                    FROM groups_users
                                   WHERE groupid = '$groupid')
        ORDER BY u.lname";

  $options = '';
	if ($users = get_db_result($SQL)) {
		while ($row = fetch_row($users)) {
      $mygroups = "";
      $SQL = "SELECT *
                FROM `groups`
               WHERE groupid IN (SELECT groupid
                                   FROM groups_users
                                  WHERE userid = '".$row['userid']."'
                                    AND pageid = '$pageid')";
      if ($groups = get_db_result($SQL)) {
        while ($group_info = fetch_row($groups)) {
            $mygroups .= " " . $group_info["name"];
        }
      }
      $options .= template_use("tmp/page.template", array("value" => $row['userid'], "display" => $row['fname'] . ' ' . $row['lname'] . ' (' . $row['email'] . ')' . $mygroups), "select_options_template");
		}
	}

  $params["options"] = $options;
  echo template_use("tmp/roles_ajax.template", $params, "add_to_group_search_template");
}

function refresh_group_users() {
global $CFG, $MYVARS, $USER;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

 	if ($pageid == $CFG->SITEID && is_siteadmin($USER->userid)) {
    $SQL = "SELECT u.*
              FROM users u
             WHERE u.userid IN (SELECT userid
                                  FROM groups_users
                                 WHERE pageid = '$pageid'
                                   AND groupid = '$groupid')
          ORDER BY u.lname";
  } else {
    $SQL = "SELECT u.*
              FROM users u
             WHERE ('$pageid' = '$CFG->SITEID'
                   OR u.userid IN (SELECT ra.userid
                                     FROM roles_assignment ra
                                    WHERE ra.pageid = '$pageid'))
               AND u.userid IN (SELECT userid
                                  FROM groups_users
                                 WHERE pageid = '$pageid'
                                   AND groupid = '$groupid')
          ORDER BY u.lname";
	}

  $groupname = get_db_field("name", "groups", "groupid = '$groupid'");
  $params = array("wwwroot" => $CFG->wwwroot, "groupname" => $groupname, "pageid" => $pageid, "groupid" => $groupid, "feature" => $feature, "featureid" => $featureid,
                  "canmanage" => user_has_ability_in_page($USER->userid, "manage_groups", $pageid));

  $options = '';
  if ($users = get_db_result($SQL)) {
		while ($row = fetch_row($users)) {
      $options .= template_use("tmp/page.template", array("value" => $row['userid'], "display" => $row['fname'] . ' ' . $row['lname'] . ' (' . $row['email'] . ')'), "select_options_template");
		}
	} else {
    $options .= template_use("tmp/page.template", array("value" => "0", "display" => "No users in this group."), "select_options_template");
	}

  $params["options"] = $options;
  echo template_use("tmp/roles_ajax.template", $params, "refresh_group_users_template");
}

function manage_group_users_form() {
global $CFG, $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  echo refresh_manage_groups($pageid, $groupid, $feature, $featureid);
}

function add_group_user() {
global $CFG, $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $SQL = "INSERT INTO groups_users (userid, pageid, groupid)
               VALUES('$userid', '$pageid', '$groupid')";
  execute_db_sql($SQL);

  echo refresh_manage_groups($pageid, $groupid);
}

function remove_group_user() {
global $CFG, $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $SQL = "DELETE FROM groups_users
                WHERE userid = '$userid'
                  AND pageid = '$pageid'
                  AND groupid = '$groupid'";
  execute_db_sql($SQL);

  echo refresh_manage_groups($pageid, $groupid);
}

function refresh_manage_groups($pageid, $groupid, $feature = false, $featureid = false) {
global $CFG, $MYVARS, $ROLES, $USER;
    $myroleid = get_user_role($USER->userid, $pageid);
    $SQL = "SELECT u.*
              FROM users u
             WHERE ('$pageid' = '$CFG->SITEID'
                    OR u.userid IN (SELECT ra.userid
                                      FROM roles_assignment ra
                                     WHERE ra.pageid = '$pageid'))
               AND u.userid NOT IN (SELECT ra.userid
                                      FROM roles_assignment ra
                                     WHERE ra.pageid = '$pageid'
                                       AND ra.roleid <= '$myroleid')
               AND u.userid NOT IN (SELECT userid
                                      FROM groups_users
                                     WHERE groupid = '$groupid')
          ORDER BY u.lname";

  $options1 = "";
	if ($pageid == $CFG->SITEID) {
    $options1 = template_use("tmp/page.template", array("value" => "0", "display" => "Search results will be shown here."), "select_options_template");
  } elseif ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
      $mygroups = "";
      $SQL = "SELECT *
                FROM `groups`
               WHERE groupid IN (SELECT groupid
                                   FROM groups_users
                                  WHERE userid = '".$row['userid']."'
                                    AND pageid = '$pageid')";
      if ($groups = get_db_result($SQL)) {
        while ($group_info = fetch_row($groups)) {
            $mygroups .= " " . $group_info["name"];
        }
      }
      $options1 .= template_use("tmp/page.template", array("value" => $row['userid'], "display" => $row['fname']. ' ' . $row['lname'] . ' (' . $row['email'] .')'.$mygroups), "select_options_template");
		}
	}

  $options2 = "";
  $SQL = "SELECT u.*
            FROM users u
           WHERE u.userid NOT IN (SELECT ra.userid
                                    FROM roles_assignment ra
                                   WHERE ra.pageid = '$pageid'
                                     AND ra.roleid <= '$myroleid')
             AND u.userid IN (SELECT userid
                                FROM groups_users
                               WHERE groupid = '$groupid')
        ORDER BY u.lname";

	if ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
      $mygroups = "";
      $SQL = "SELECT *
                FROM `groups`
               WHERE groupid IN (SELECT groupid
                                   FROM groups_users
                                  WHERE userid = '".$row['userid']."'
                                    AND pageid='$pageid')";
      if ($groups = get_db_result($SQL)) {
          while ($group_info = fetch_row($groups)) {
              $mygroups .= " " . $group_info["name"];
          }
      }
      $options2 .= template_use("tmp/page.template", array("value" => $row['userid'], "display" => $row['fname']. ' ' . $row['lname'] . ' (' . $row['email'] .')' . $mygroups), "select_options_template");
		}
	}

  $params = array("wwwroot" => $CFG->wwwroot, "groupname" => $groupname, "pageid" => $pageid, "groupid" => $groupid, "feature" => $feature, "featureid" => $featureid,
                  "options1" => $options1, "options2" => $options2);
  return template_use("tmp/roles_ajax.template", $params, "refresh_manage_groups_template");
}

function delete_group() {
global $CFG,$MYVARS,$USER;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  if ($pageid && $groupid) {
    $SQL = "DELETE
              FROM `groups`
             WHERE groupid = '$groupid'
               AND pageid = '$pageid'";
    execute_db_sql($SQL);

    $SQL = "DELETE
              FROM groups_users
             WHERE groupid = '$groupid'
               AND pageid = '$pageid'";
    execute_db_sql($SQL);

    $SQL = "DELETE
              FROM roles_ability_perfeature_pergroup
             WHERE groupid = '$groupid'
               AND pageid = '$pageid'";
    execute_db_sql($SQL);

    $SQL = "DELETE
              FROM roles_ability_pergroup
             WHERE groupid = '$groupid'
               AND pageid = '$pageid'";
    execute_db_sql($SQL);
  }

  echo group_page($pageid, $feature, $featureid);
}

function refresh_groups_page() {
global $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  echo group_page($pageid, $feature, $featureid);
}

function refresh_groups_list() {
global $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Only passed when editing
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  echo groups_list($pageid, $feature, $featureid, true, $groupid);
}

function create_edit_group_form() {
global $CFG, $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Only passed when editing
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $name = "";
  if ($groupid) { // EDITING: get form values to fill in
    $SQL = template_use("dbsql/roles.sql", array("groupid" => $groupid), "get_group");
    $group = get_db_row($SQL);
    $name = $group["name"];
    $parents = groups_list($pageid, false, false, false, $group["parent"], $groupid, $groupid, "80%", "per_group_edit_group_select", "per_group_edit_group_select");
  } else { // CREATING
    $parents = groups_list($pageid, false, false, false, null, null, null, "80%", "per_group_edit_group_select", "per_group_edit_group_select");
  }

  $params = array("wwwroot" => $CFG->wwwroot, "name" => $name, "parents" => $parents, "pageid" => $pageid, "groupid" => $groupid, "feature" => $feature, "featureid" => $featureid);
  echo template_use("tmp/roles_ajax.template", $params, "create_edit_group_form_template");
}

function save_group() {
global $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $name = !empty($MYVARS->GET['name']) ? dbescape($MYVARS->GET['name']) : false; //Should always be passed
  $parent = !empty($MYVARS->GET['parent']) ? $MYVARS->GET['parent'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Only passed when editing
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $parent = $parent ? $parent : "0";
  $params = array("is_editing" => ($groupid), "pageid" => $pageid, "groupid" => $groupid, "name" => $name, "parent" => $parent);

  $SQL = template_use("dbsql/roles.sql", $params, "save_group");
  execute_db_sql($SQL);

  echo group_page($pageid, $feature, $featureid);
}

function refresh_edit_roles() {
global $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $roleid = !empty($MYVARS->GET['roleid']) ? $MYVARS->GET['roleid'] : false; //Should always be passed
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing

  if ($pageid && $roleid) {
    echo print_abilities($pageid, "per_role_", $roleid, false, $feature, $featureid);
  } else {
    echo get_error_message("generic_error");
    return;
  }
}

//TOP LEVEL PER USER OVERRIDES
function refresh_user_abilities() {
global $MYVARS;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  if ($pageid && $userid) {
    echo print_abilities($pageid, "per_user_", false, $userid, $feature, $featureid);
  } else {
    echo get_error_message("generic_error");
    return;
  }
}

function refresh_group_abilities() {
global $MYVARS;
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  if ($pageid && $groupid) {
    echo '<form id="per_group_roles_form">';
    echo print_abilities($pageid, "per_group_", false, false, $feature, $featureid, $groupid);
    echo '</form>';
  } else {
    echo get_error_message("generic_error");
    return;
  }
}

function save_ability_changes() {
global $CFG, $MYVARS;
  $abilities = explode("**",$MYVARS->GET['per_role_rightslist']);
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $roleid = !empty($MYVARS->GET['per_role_roleid']) ? $MYVARS->GET['per_role_roleid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing
  $success = false;
  $i=0;

	while (isset($abilities[$i])) {
		$ability = $abilities[$i];
		$setting = $MYVARS->GET[$ability] == 1 ? 1 : 0;
		if ($pageid == $CFG->SITEID && !$featureid) {
			$default = get_db_field("allow", "roles_ability", "roleid = '$roleid' AND ability = '$ability'");
			if ($default !== false) {
				if ($default !== $setting) {
					$section = get_db_field("section", "abilities", "ability = '$ability'");
          $SQL = "DELETE
                    FROM roles_ability
                   WHERE roleid = '$roleid'
                     AND ability = '$ability'";
					$success = execute_db_sql($SQL) ? true : false;
          $SQL = "INSERT INTO roles_ability (roleid, section, ability, allow)
                       VALUES('$roleid', '$section', '$ability', '$setting')";
					$success = execute_db_sql($SQL) ? true : false;
				}
			} else { //No entry
				$section = get_db_field("section", "abilities", "ability = '$ability'");
        $SQL = "INSERT INTO roles_ability (roleid,section,ability,allow)
                     VALUES('$roleid', '$section', '$ability', '$setting')";
				$success = execute_db_sql($SQL) ? true : false;
			}
		} else {
			$default = get_db_field("allow", "roles_ability", "roleid = '$roleid' AND ability = '$ability'");
      if ($feature && $featureid) {
        $SQL = "SELECT *
                  FROM roles_ability_perfeature
                 WHERE feature = '$feature'
                   AND featureid = '$featureid'
                   AND roleid = '$roleid'
                   AND pageid = '$pageid'
                   AND ability = '$ability'";
        $alreadyset = get_db_count($SQL);

    		if ($alreadyset) {
    			if ($setting == $default) {
            $SQL = "DELETE
                      FROM roles_ability_perfeature
                     WHERE pageid = '$pageid'
                       AND roleid = '$roleid'
                       AND feature = '$feature'
                       AND featureid = '$featureid'
                       AND ability = '$ability'";
            $success = execute_db_sql($SQL) ? true : false;
    			} else {
            $SQL = "UPDATE roles_ability_perfeature
                       SET allow = '$setting'
                     WHERE roleid = '$roleid'
                       AND feature = '$feature'
                       AND featureid = '$featureid'
                       AND ability = '$ability'";
            $success = execute_db_sql($SQL) ? true : false;
          }
    		} elseif ($setting != $default && !$alreadyset) {
          $SQL = "INSERT INTO roles_ability_perfeature (roleid, pageid, feature, featureid, ability, allow)
                       VALUES('$roleid', '$pageid', '$feature', '$featureid', '$ability', '$setting')";
    			$success = execute_db_sql($SQL) ? true : false;
    		}
      } else {
        $SQL = "SELECT *
                  FROM roles_ability_perpage
                 WHERE roleid = '$roleid'
                   AND pageid = '$pageid'
                   AND ability = '$ability'";
        $alreadyset = get_db_count($SQL);
        if ($alreadyset) {
    		  if ($setting == $default) {
            $SQL = "DELETE
                      FROM roles_ability_perpage
                     WHERE pageid = '$pageid'
                       AND roleid = '$roleid'
                       AND ability = '$ability'";
            $success = execute_db_sql($SQL) ? true : false;
    			} else {
            $SQL = "UPDATE roles_ability_perpage
                       SET allow = '$setting'
                     WHERE roleid = '$roleid'
                       AND ability = '$ability'
                       AND pageid = '$pageid'";
            $success = execute_db_sql($SQL) ? true : false;
          }
    		} elseif ($setting != $default && !$alreadyset) {
          $SQL = "INSERT INTO roles_ability_perpage (roleid, pageid, ability, allow)
                       VALUES('$roleid', '$pageid', '$ability', '$setting')";
    			$success = execute_db_sql($SQL) ? true : false;
        }
    	}
		}
		$i++;
	}
  if ($success) {
    echo "Changes Saved";
  } else {
    echo "Save Failed";
  }
}

function save_user_ability_changes() {
global $CFG,$MYVARS;
  $abilities = explode("**",$MYVARS->GET['per_user_rightslist']);
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $i=0;
	while (isset($abilities[$i])) {
		$ability = $abilities[$i];
		$allow = $MYVARS->GET[$ability] == 1 ? 1 : 0;
		$roleid = get_user_role($userid,$pageid);

    //$default = $featureid ? (user_has_ability_in_page($userid,$ability,$pageid,$feature,$featureid) ? "1" : "0") : (user_has_ability_in_page($roleid,$ability,$pageid,$feature) ? "1" : "0");
    //figure out the default
    if ($featureid) { //feature specific ability change
      $default = user_has_ability_in_page($userid,$ability,$pageid,$feature,$featureid) ? "1" : "0";
    } else { //page specific ability change
      $default = user_has_ability_in_page($userid,$ability,$pageid) ? "1" : "0";
    }

		if ($feature && $featureid) {
      $SQL = "SELECT *
                FROM roles_ability_perfeature_peruser
               WHERE userid = '$userid'
                 AND pageid = '$pageid'
                 AND feature = '$feature'
                 AND featureid = '$featureid'
                 AND ability = '$ability'";
      $alreadyset = get_db_count($SQL);
    } else {
      $SQL = "SELECT *
                FROM roles_ability_peruser
               WHERE userid = '$userid'
                 AND pageid = '$pageid'
                 AND ability = '$ability'";
      $alreadyset = get_db_count($SQL);
		}

		if ($alreadyset) {
			if ($alreadyset && $allow == $default) {
        if ($feature && $featureid) {
          $SQL = "DELETE
                    FROM roles_ability_perfeature_peruser
                   WHERE pageid = '$pageid'
                     AND userid = '$userid'
                     AND feature = '$feature'
                     AND featureid = '$featureid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        } else {
          $SQL = "DELETE
                    FROM roles_ability_peruser
                   WHERE pageid = '$pageid'
                     AND userid = '$userid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        }
      } else {
        if ($feature && $featureid) {
          $SQL = "UPDATE roles_ability_perfeature_peruser
                     SET allow = '$allow'
                   WHERE userid = '$userid'
                     AND pageid = '$pageid'
                     AND feature = '$feature'
                     AND featureid = '$featureid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        } else {
          $SQL = "UPDATE roles_ability_peruser
                     SET allow = '$allow'
                   WHERE userid = '$userid'
                     AND pageid = '$pageid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        }
      }
		} elseif ($allow != $default && !$alreadyset) {
      if ($feature && $featureid) {
        $SQL = "INSERT INTO roles_ability_perfeature_peruser (userid, pageid, feature, featureid, ability, allow)
                     VALUES('$userid', '$pageid', '$feature', '$featureid', '$ability', '$allow')";
        execute_db_sql($SQL);
      } else {
        $SQL = "INSERT INTO roles_ability_peruser (userid, pageid, ability, allow)
                     VALUES('$userid', '$pageid', '$ability', '$allow')";
        execute_db_sql($SQL);
    	}
		}
		$i++;
	}
	echo "Changes Saved";
}

function save_group_ability_changes() {
global $CFG,$MYVARS;
  $abilities = explode("**",$MYVARS->GET['per_group_rightslist']);
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $i=0;
	while (isset($abilities[$i])) {
		$ability = $abilities[$i];
		$allow = $MYVARS->GET[$ability] === 1 ? 1 : $MYVARS->GET[$ability]; //If ability is SET to 1
    $allow = $allow === 0 ? 0 : $allow; //If ability is SET to 0
    $allow = $allow === '' ? false : $allow; //If ability is NOT SET

		if ($feature && $featureid) {
      $SQL = "SELECT *
                FROM roles_ability_perfeature_pergroup
               WHERE groupid = '$groupid'
                 AND pageid = '$pageid'
                 AND feature = '$feature'
                 AND featureid = '$featureid'
                 AND ability = '$ability'";
      $alreadyset = get_db_count($SQL);
		} else {
      $SQL = "SELECT *
                FROM roles_ability_pergroup
               WHERE groupid = '$groupid'
                 AND pageid = '$pageid'
                 AND ability = '$ability'";
      $alreadyset = get_db_count($SQL);
		}

		if ($alreadyset) {
			if ($alreadyset && $allow === false) { //If ability is NOT SET to 1 or 0 but is set in the db
        if ($feature && $featureid) {
          $SQL = "DELETE
                    FROM roles_ability_perfeature_pergroup
                   WHERE pageid = '$pageid'
                     AND groupid = '$groupid'
                     AND feature = '$feature'
                     AND featureid = '$featureid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        } else {
          $SQL = "DELETE
                    FROM roles_ability_pergroup
                   WHERE pageid = '$pageid'
                     AND groupid = '$groupid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        }
      } else { //If ability is SET to 1 or 0 and is already set in the db
        if ($feature && $featureid) {
          $SQL = "UPDATE roles_ability_perfeature_pergroup
                     SET allow = '$allow'
                   WHERE groupid = '$groupid'
                     AND pageid = '$pageid'
                     AND feature = '$feature'
                     AND featureid = '$featureid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        } else {
          $SQL = "UPDATE roles_ability_pergroup
                     SET allow = '$allow'
                   WHERE groupid = '$groupid'
                     AND pageid = '$pageid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        }
      }
		} elseif ($allow !== false && !$alreadyset) { //If ability is SET to 1 or 0 and isn't already set in the db
      if ($feature && $featureid) {
        $SQL = "INSERT INTO roles_ability_perfeature_pergroup (groupid, pageid, feature, featureid, ability, allow)
                     VALUES('$groupid', '$pageid', '$feature', '$featureid', '$ability', '$allow')";
        execute_db_sql($SQL);
      } else {
        $SQL = "INSERT INTO roles_ability_pergroup (groupid, pageid, ability, allow)
                     VALUES('$groupid', '$pageid', '$ability', '$allow')";
        execute_db_sql($SQL);
    	}
		}
		$i++;
	}
	echo "Changes Saved";
}

function refresh_user_roles() {
global $CFG, $USER, $MYVARS, $ROLES;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
  $myroleid = get_user_role($USER->userid, $pageid);
  $roleid = get_user_role($userid, $pageid, true);

  if (isset($roleid)) {
    if (is_siteadmin($userid)) {
      $rolename = "Site Admin";
    } else {
      $rolename = get_db_field("display_name", "roles", "roleid = '$roleid'");
    }
  } else {
  	$roleid = 0;
  	$rolename = "Unassigned";
  }


  $sql_admin = $pageid != $CFG->SITEID ? " WHERE roleid != '$ROLES->admin'" : "";
	$SQL = "SELECT *
            FROM roles
            $sql_admin
        ORDER BY roleid";
  $options = '';
	if ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
			if ($row['roleid'] != $roleid && $row['roleid'] >= $myroleid) {
        $options .= template_use("tmp/page.template", array("value" => $row['roleid'], "display" => stripslashes($row['display_name'])), "select_options_template");
      }
		}
	}

  $params = array("rolename" => $rolename, "pageid" => $pageid, "userid" => $userid, "options" => $options);
  echo template_use("tmp/roles_ajax.template", $params, "refresh_user_roles_template");
}

function assign_role() {
global $MYVARS, $ROLES;
	$roleid = $MYVARS->GET['roleid'];
	$userid = $MYVARS->GET['userid'];
	$pageid = $MYVARS->GET['pageid'];

  $SQL = "DELETE
            FROM roles_assignment
           WHERE userid = '$userid'
             AND pageid = '$pageid'";
	if (execute_db_sql($SQL)) {
		if ($roleid !== $ROLES->none) { //No role besides "No Role" was given
      $SQL = "INSERT INTO roles_assignment (userid, pageid, roleid)
                   VALUES('$userid', '$pageid', '$roleid')";
      if (execute_db_sql($SQL)) {
        echo "Changes Saved";
      } else {
        echo "No Role Given";
      }
    } else {
      echo "Role Removed";
    }
	} else {
		echo "Changes Not Saved";
	}
}
?>
