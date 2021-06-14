<?php
/***************************************************************************
* roleslib.php - Roles function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/11/2021
* Revision: 1.3.0
***************************************************************************/

if (!isset($LIBHEADER)) { include('header.php'); }
$ROLESLIB = true;
$ABILITIES = new \stdClass;

function is_siteadmin($userid) {
global $CFG, $ROLES;
	if (!isset($userid)) { return false; }
	$params = array("adminroleid" => $ROLES->admin, "userid" => $userid, "siteid" => $CFG->SITEID);
	$SQL = template_use("dbsql/roles.sql", $params, "is_siteadmin");
	if (!get_db_count($SQL)) { return false; }
	return true;
}

function remove_all_roles($userid) {
	$SQL = template_use("dbsql/roles.sql", array("userid" => $userid), "remove_all_roles");
	return execute_db_sqls($SQL);
}

//Add an ability and assign a role it's value
function add_role_ability($section, $ability, $displayname, $power, $desc, $creator='0', $editor='0', $guest='0', $visitor='0') {
  if (!get_db_row("SELECT * FROM abilities WHERE section='$section' AND section_display='$displayname'")) {
	  // CREATE ROLE ABILITY AND ASSIGN PERMISSIONS
		$params = array("section" => $section, "ability" => $ability, "displayname" => $displayname, "power" => $power, "desc" => $desc,
										"creator" => $creator, "editor" => $editor, "guest" => $guest, "visitor" => $visitor);
		$SQL = template_use("dbsql/roles.sql", $params, "add_role_ability");
		execute_db_sqls($SQL);
  }
}

function user_has_ability_in_page($userid, $ability, $pageid, $feature = "", $featureid=0) {
global $CFG, $ROLES, $ABILITIES;
	if (!$featureid && isset($ABILITIES->$ability)) { // Get cached abilities first (SAVES TIME!)
		if ($ABILITIES->$ability->allow) { return true; }
      return false;
	}

	if (is_siteadmin($userid)) { return true; }

	$roleid = get_user_role($userid, $pageid);

	if ($roleid == $ROLES->visitor) {
		if (role_has_ability_in_page($ROLES->visitor, $ability, $pageid, $feature, $featureid)) {
			return true;
		}
	} else {
  	$groupallowed = groups_SQL($userid, $pageid, $ability);
  	$featuregroupallowed = groups_SQL($userid, $pageid, $ability, $feature, $featureid); //This will be a group check.  1 if allowed 0 if not allowed -1 if not specified

		$params = array("pageid" => $pageid, "roleid" => $roleid, "userid" => $userid, "ability" => $ability,
										"feature" => $feature, "featureid" => $featureid, "groupsql" => $groupallowed, "featuregroupsql" => $featuregroupallowed);
  	$SQL = template_use("dbsql/roles.sql", $params, "user_has_ability_in_page");

  	if (get_db_row($SQL)) {
	   return true;
    }
	}
	return false;
}

function get_user_abilities($userid, $pageid, $section=false, $feature = "", $featureid = 0) {
global $CFG, $ROLES, $ABILITIES;

	if (is_siteadmin($userid)) {
		return get_role_abilities($ROLES->admin, $CFG->SITEID, $section);
	}

	$roleid = get_user_role($userid, $pageid);

	if ($roleid == $ROLES->visitor) {
		return get_role_abilities($ROLES->visitor,$pageid,$section, $feature, $featureid);
	} else {
		$section_sql = "";
    if ($section) {
			foreach ((array)$section as $s) { // if string, cast to array and keep going
				$section_sql .= $section_sql == "" ? "section = '$s'" : " OR section = '$s'";
			}
    }

  	$groupallowed = groups_SQL($userid, $pageid);
  	$featuregroupallowed = groups_SQL($userid, $pageid, 'a.ability', $feature, $featureid); //This will be a group check.  1 if allowed 0 if not allowed -1 if not specified

		$params = array("pageid" => $pageid, "userid" => $userid, "feature" => $feature, "featureid" => $featureid, "ability" => $ability,
										"roleid" => $roleid, "issection" => ($section), "section" => $section_sql, "groupsql" => $groupallowed, "featuregroupsql" => $featuregroupallowed);
  	$SQL = template_use("dbsql/roles.sql", $params, "get_user_abilities");

  	if ($results = get_db_result($SQL)) {
	    $abilities = new \stdClass;
  		while ($row = fetch_row($results)) {
  			$ability = $row["ability"];
  			$allow = $row["allowed"] == 1 ? 1 : 0;

        if (empty($abilities->$ability)) {
            $abilities->$ability = new \stdClass;
        }
  			$abilities->$ability->allow = $allow;
  		}
  	} else {
      error_log("FAILED SQL: $SQL");
  	}
  	if (!$section) {
			$ABILITIES = $abilities;
		}
  	return $abilities;
	}
}

function user_has_ability_in_pages($userid, $ability, $siteviewable = true, $menuitems = true) {
global $CFG, $ROLES;
	if (is_siteadmin($userid)) {
		$params = array("notsiteviewable" => (!$siteviewable), "notmenuitems" => (!$menuitems));
  	$SQL = template_use("dbsql/roles.sql", $params, "admin_has_ability_in_pages");
		return get_db_result($SQL);
	}

	$perrole = "";
	foreach ($ROLES as $roleid) {
		$params = array("notsiteviewable" => (!$siteviewable), "notmenuitems" => (!$menuitems), "userid" => $userid, "roleid" => $roleid, "ability" => $ability);
		$perrole .= template_use("dbsql/roles.sql", $params, "user_has_ability_in_pages_perrole");
	}

	$params = array("userid" => $userid, "ability" => $ability, "perrole" => $perrole);
 	$SQL = template_use("dbsql/roles.sql", $params, "user_has_ability_in_pages");

  if ($results = get_db_result($SQL)) {
    return $results;
  }
	return false;
}

function get_role_abilities($roleid, $pageid, $section = false, $feature = "", $featureid = 0) {
global $CFG, $ROLES, $ABILITIES;
	$section_sql = "";
	if ($section) {
		foreach ((array)$section as $s) { // if string, cast to array and keep going
			$section_sql .= $section_sql == "" ? "section = '$s'" : " OR section = '$s'";
		}
	}

	$params = array("pageid" => $pageid, "feature" => $feature, "featureid" => $featureid, "roleid" => $roleid,
									"issection" => ($section), "section" => $section_sql);
	$SQL = template_use("dbsql/roles.sql", $params, "get_role_abilities");

	if ($results = get_db_result($SQL)) {
    $abilities = new \stdClass;
		while ($row = fetch_row($results)) {
			$ability = $row["ability"];
			$allow = $row["allowed"] == 1 ? 1 : 0;
      $abilities->$ability = new \stdClass;
			$abilities->$ability->allow = $allow;
		}
	} else {
    error_log("FAILED SQL: $SQL");
	}
	if (empty($section) && !empty($abilities)) {
		$ABILITIES = $abilities;
	}
	return $abilities;
}

function role_has_ability_in_page($roleid, $ability, $pageid, $feature="", $featureid=0) {
	$params = array("pageid" => $pageid, "feature" => $feature, "featureid" => $featureid, "roleid" => $roleid, "ability" => $ability);
	$SQL = template_use("dbsql/roles.sql", $params, "role_has_ability_in_page");
	if (get_db_row($SQL)) {
    return true;
	}
	return false;
}

function load_roles() {
global $CFG;
	$allroles = get_db_result(template_use("dbsql/roles.sql", array(), "get_roles"));
  $ROLES = new \stdClass;
  while ($row = fetch_row($allroles)) {
    $rolename = $row['name'];
		$ROLES->$rolename = $row['roleid'];
	}
	return $ROLES;
}

function get_user_role($userid=0, $pageid, $ignore_site_admin = false) {
global $CFG, $ROLES, $USER;
  $admin = is_siteadmin($userid) ? true : false;

  if (!$ignore_site_admin) {
   if ($admin) { return $ROLES->admin; }
	}

	$params = array("userid" => $userid, "pageid" => $pageid);
	$SQL = template_use("dbsql/roles.sql", $params, "get_user_role");
	if ($result = get_db_result($SQL)) {
		while ($row = fetch_row($result)) {
			return $row['roleid'];
		}
	}

  if ($admin) { return $ROLES->admin; } // Site admin, but doesn't have a role in the page.

	if (is_logged_in()) {
		if (get_db_field("opendoorpolicy", "pages", "pageid = '$pageid'") == 1) {
			return get_db_field("default_role","pages","pageid='$pageid'");
		}
	}

	if (is_visitor_allowed_page($pageid)) {
		return $ROLES->visitor; // if it is a site viewable page and the user has no specified role, default to visitor
	}
	return $ROLES->none; // no role found.
}

function users_that_have_ability_in_page($ability, $pageid) {
global $CFG,$ROLES;
	$page = get_db_row(template_use("dbsql/pages.sql", array("pageid" => $pageid), "get_page"));

	$params = array("pageid" => $pageid, "ability" => $ability, "siteid" => $CFG->SITEID, "siteoropen" => ($page["siteviewable"] || $page["opendoorpolicy"]));
	$SQL = template_use("dbsql/roles.sql", $params, "users_that_have_ability_in_page");

  if ($results = get_db_result($SQL)) {
    return $results;
  } else {
    return false;
	}
}

//
// GROUPS AREA
//

//This function will get an array of the groups hierarchy
function get_groups_hierarchy($userid, $pageid, $parent = 0) {
	$params = array("pageid" => $pageid, "userid" => $userid, "parent" => $parent);
	$SQL = template_use("dbsql/roles.sql", $params, "get_groups_hierarchy");

	if ($groups = get_db_result($SQL)) {	// If you are in a group on this page.
    $groups_array = array();
    while ($group = fetch_row($groups)) {
			$groups_array[] = $group["groupid"];
      // Check for child groups
      if ($child_group = get_groups_hierarchy($userid, $pageid, $group["groupid"])) { // Check if user is in a group that is a child of this group
        $groups_array = array_merge($groups_array, $child_group);
      }
    }
    return $groups_array;
	}
  return false;
}

//This function gets the permission of an ability according to groups
function groups_SQL($userid, $pageid, $ability='a.ability', $feature=false, $featureid=false) {
	//Return array of groups hierarchy for given user in this page
	$hierarchy = get_groups_hierarchy($userid, $pageid);

	//Groups don't exist
	if (empty($hierarchy)) { $groupsSQL[0] = ""; $groupsSQL[1] = "";	return $groupsSQL; }

	//Add quotes around a specific ability or link to SQL variable if not given
	$ability = $ability == 'a.ability' ? 'a.ability' : "'".$ability."'";

	//Decide which table the SQL requires
	$table = $feature && $featureid ? 'roles_ability_perfeature_pergroup' : 'roles_ability_pergroup';

	//Add feature checks if a perfeature SQL is asked for
	$extraSQL = $feature && $featureid ? "AND feature='$feature' AND featureid='$featureid'" : "";

	//Create dynamic groups sql
	$SQL1 = "";	$SQL2 = "";
  foreach ($hierarchy as $group) {
    $SQL1 .= "( 1 IN (SELECT allow FROM $table WHERE groupid='$group' AND ability=$ability AND allow='1' $extraSQL) OR ";
    $SQL2 .= " ) AND 0 NOT IN (SELECT allow FROM $table WHERE groupid='$group' AND ability=$ability AND allow='0' $extraSQL) ";
  }

	$groupsSQL[0] = $SQL1;
	$groupsSQL[1] = $SQL2;

	return $groupsSQL;
}

function merge_abilities($abilities) {
  $merged = array();
  foreach ($abilities as $ability) {
  	$merged = (object) array_merge((array) $merged, (array) $ability);
  }
  return $merged;
}

function group_page($pageid, $feature, $featureid) {
global $CFG,$USER;
	$params = array("pageid" => $pageid, "feature" => $feature, "featureid" => $featureid, "wwwroot" => $CFG->wwwroot,
									"groups_list" => groups_list($pageid, $feature, $featureid),
									"canmanagegroups" => user_has_ability_in_page($USER->userid, "manage_groups", $pageid));
	return template_use("tmp/roles.template", $params, "group_page_template");
}

function groups_list($pageid, $feature = false, $featureid = false, $action = true, $selectid = 0, $excludeid = 0, $excludechildrenofid = 0, $width = "100%", $id = "group_select", $name = "groupid") {
	$params = array("name" => $name, "pageid" => $pageid, "feature" => $feature, "featureid" => $featureid, "width" => $width, "id" => $id,
									"enableaction" => $action, "groups" => sub_groups_list($pageid, false, "", $selectid, $excludeid, $excludechildrenofid));
  return template_use("tmp/roles.template", $params, "groups_list_template");
}

function sub_groups_list($pageid, $parent = false, $level = "", $selectid = 0, $excludeid = 0, $excludechildrenofid = 0) {
  $options = "";
	$parent = $parent ? $parent : "0";
  $SQL = template_use("dbsql/roles.sql", array("pageid" => $pageid, "parent" => $parent), "get_subgroups");
  if ($groups = get_db_result($SQL)) {
    while ($group = fetch_row($groups)) {
      $group_count = get_db_count(template_use("dbsql/roles.sql", array("groupid" => $group['groupid']), "get_group_users"));
			$display = $level . $group['name'] . ' (' . $group_count . ')';
			$selected = $selectid == $group["groupid"] ? "selected" : "";
			$options .= $excludeid != $group["groupid"] ? template_use("tmp/page.template", array("value" => $group['groupid'], "display" => $display, "selected" => $selected), "select_options_template") : '';

			// get subgroups using recurssive call.
			$SQL = template_use("dbsql/roles.sql", array("pageid" => $pageid, "parent" => $group['groupid']), "get_subgroups");
      if ($subgroups = get_db_row($SQL)) {
        $options .= $excludechildrenofid != $group["groupid"] ? sub_groups_list($pageid, $group['groupid'], $level . "-- ", $selectid, $excludeid) : '';
      }
		}
	}
  return $options;
}

function print_abilities($pageid, $type = "per_role_", $roleid = false, $userid = false, $feature = false, $featureid = false, $groupid = false) {
global $CFG;
	$rightslist = $currentstyle = $section = $notsettitle = $notsettoggle = $save_button = "";
	$default_toggle = false;

	// Save button
	if ($roleid || $groupid || $userid) {
		if ($roleid) {
			$save_function = 'save_ability_changes';
			$refresh_function = 'refresh_edit_roles';
			$swap_function = "swap_highlights";
		} elseif ($groupid) {
			$save_function = 'save_group_ability_changes';
			$refresh_function = 'refresh_group_abilities';
			$swap_function = "swap_highlights2";
			$default = "Default";
		} elseif ($userid) {
			$save_function = 'save_user_ability_changes';
			$refresh_function = 'refresh_user_abilities';
			$swap_function = "swap_highlights";
		}
		$params = array("pageid" => $pageid, "type" => $type, "roleid" => $roleid, "userid" => $userid, "feature" => $feature, "featureid" => $featureid, "groupid" => $groupid,
										"save_function" => $save_function, "refresh_function" => $refresh_function);
		$save_button = template_use("tmp/roles.template", $params, "print_abilities_save_button");
	}

	$SQL = template_use("dbsql/roles.sql", array("feature" => $feature, "is_feature" => ($feature && $featureid)), "print_abilities");
  if ($pages = get_db_result($SQL)) {
		$i = 0; $abilities = "";
		$style_row1 = 'class="roles_row1"';
		$style_row2 = 'class="roles_row2"';
		while ($row = fetch_row($pages)) {
			$currentstyle = $currentstyle == $style_row1 ? $style_row2 : $style_row1;
			$currentstyle = $section == $row['section'] ? $currentstyle : $style_row1;

      if ($roleid && empty($userid)) { // Role based only
        $rights = role_has_ability_in_page($roleid, $row['ability'], $pageid, $feature, $featureid) ? "1" : "0";
				$SQL = template_use("dbsql/roles.sql", array("ability" => $row['ability'], "pageid" => $pageid, "roleid" => $roleid), "get_page_role_override");
        $notify = get_db_count($SQL) ? true : false;
      } elseif ($groupid) { // Group based
				$default_toggle = true;
				$params = array("ability" => $row['ability'], "pageid" => $pageid, "feature" => $feature, "featureid" => $featureid, "groupid" => $groupid);
        $rights = ($feature && $featureid) ? get_db_row(template_use("dbsql/roles.sql", $params, "get_page_group_feature_override")) : get_db_row(template_use("dbsql/roles.sql", $params, "get_page_group_override"));
        $rights = $rights["allow"] === "0" ? "0" : ($rights["allow"] === "1" ? "1" : false);
        $notify = $rights !== false ? true : false;
				$default_checked = $rights === false ? true : false;
			} elseif ($userid) { // User based
				$params = array("ability" => $row['ability'], "pageid" => $pageid, "feature" => $feature, "featureid" => $featureid, "userid" => $userid);
        if ($feature && $featureid) { // Feature user override
					$SQL = template_use("dbsql/roles.sql", $params, "get_page_feature_user_override");
          $rights = user_has_ability_in_page($userid, $row['ability'], $pageid, $feature, $featureid) ? "1" : "0";
          $notify = get_db_count($SQL) ? true : false;
  			} else { // Page user override
					$SQL = template_use("dbsql/roles.sql", $params, "get_page_user_override");
          $rights = user_has_ability_in_page($userid, $row['ability'], $pageid) ? "1" : "0";
          $notify = get_db_count($SQL) ? true : false;
  			}
      }

			$notify1 = $notify2 = false; // not set
			if ($rights === "1") { // set to allow
				$notify1 = true;
				$notify2 = false;
			} else if( $rights === "0") { // set to disallow
				$notify1 = false;
				$notify2 = true;
			}

			$params = array("type" => $type, "currentstyle" => $currentstyle, "ability" => $row, "swap_function" => $swap_function, "thissection" => ($section != $row['section']),
											"notify1" => $notify1, "notify2" => $notify2, "notify" => $notify, "default_toggle" => $default_toggle, "default_checked" => $default_checked);
			$abilities .= template_use("tmp/roles.template", $params, "print_abilities_ability");

			$rightslist .= $rightslist == "" ? $row['ability'] : "**".$row['ability'];
			$section = $row['section']; // Remmember last section so we know when a new section starts.
			$i++;
		}
  }

	$params = array("default" => $default, "abilities" => $abilities, "type" => $type, "save" => $save_button, "rightslist" => $rightslist);
	return template_use("tmp/roles.template", $params, "print_abilities");
}
?>
