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

/**
 * Performs a name search for users to add to a group
 *
 * @global \stdClass $CFG The global config object
 * @global \stdClass $USER The current user object
 * @global \stdClass $MYVARS The global $_GET and $_POST array
 * @return void
 */
function name_search() {
global $CFG, $USER, $MYVARS;

    // Search for users based on name, email, or username
    $pageid = isset($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; // The page to search on
    $type = isset($MYVARS->GET['type']) ? $MYVARS->GET['type'] : "per_page_"; // The type of search being performed (per_page_ or feature specific)
    $featureid = isset($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; // The feature ID, if feature specific
    $feature = isset($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; // The feature name, if feature specific

    $searchstring = "";
    $searcharray = explode(" ", $MYVARS->GET["searchstring"]);
    $i = 0;
    foreach ($searcharray as $search) {
        $searchstring .= $i == 0 ? "" : " OR ";
        $searchstring .= "(fname LIKE '%$search%' OR lname LIKE '%$search%' OR email LIKE '%$search%')";
        $i++;
    }

    $fields = "u.userid, u.fname, u.lname, u.email";
    if ($pageid == $CFG->SITEID && is_siteadmin($USER->userid)) {
        // If admin on site, search all users
        $SQL = "SELECT $fields
                  FROM users u
                 WHERE $searchstring
              ORDER BY u.lname";
    } else {
        // Get the user's role on the page
        $myroleid = user_role($USER->userid, $pageid);
        if ($type != "per_page_") { // Feature specific role assignment search. (only searches people that already have page privs)
            // Search for users with a higher role on the page
            $SQL = "SELECT $fields
                      FROM users u
                     WHERE $searchstring
                       AND u.userid IN (SELECT ra.userid
                                          FROM roles_assignment ra
                                         WHERE ra.pageid = '$pageid'
                                           AND ra.roleid > '$myroleid')  
                  ORDER BY u.lname";
        } else {  // Page role assignment search.
            // Search for users with a role lower than the user's on the page
            $SQL = "SELECT $fields
                      FROM users u
                     WHERE $searchstring
                       AND u.userid NOT IN (SELECT ra.userid
                                              FROM roles_assignment ra
                                             WHERE ra.pageid = '$pageid'
                                               AND ra.roleid <= '$myroleid')
                  ORDER BY u.lname";
        }
    }

    // Add the search results to the page template
    $params = [ "refreshroles" => (isset($MYVARS->GET["refreshroles"]) && $MYVARS->GET["refreshroles"] == "refreshroles"),
                "type" => $type,
                "pageid" => $pageid,
                "featureid" => $featureid,
                "feature" => $feature,
    ];
    $options = "";
    if ($users = get_db_result($SQL)) {
        while ($row = fetch_row($users)) {
            $vars = [ "selected" => "",
                      "value" => $row['userid'],
                      "display" => fill_string("{fname} {lname} ({email})", $row),
            ];
            $options .= use_template("tmp/page.template", $vars, "select_options_template");
        }
    }
    $params["options"] = $options;
    echo use_template("tmp/roles_ajax.template", $params, "name_search_template");
}

function add_to_group_search() {
global $CFG, $ROLES, $USER, $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $type = !empty($MYVARS->GET['type']) ? $MYVARS->GET['type'] : "per_page_"; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $searchstring = "";	$searcharray = explode(" ", $MYVARS->GET["searchstring"]);
  $i=0;
  foreach ($searcharray as $search) {
    $searchstring .= $i == 0 ? "" : " OR ";
    $searchstring .= "(fname LIKE '%$search%' OR lname LIKE '%$search%' OR email LIKE '%$search%')";
    $i++;
  }

	$myroleid = user_role($USER->userid, $pageid);
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
                                  WHERE userid = '" . $row['userid'] . "'
                                    AND pageid = '$pageid')";
      if ($groups = get_db_result($SQL)) {
        while ($group_info = fetch_row($groups)) {
            $mygroups .= " " . $group_info["name"];
        }
      }

      $params = [
          "selected" => "",
          "value" => $row['userid'],
          "display" => fill_string("{fname} {lname} ({email})", $row) . $mygroups,
      ];
      $options .= use_template("tmp/page.template", $params, "select_options_template");
		}
	}

  echo use_template("tmp/roles_ajax.template", ["options" => $options], "add_to_group_search_template");
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

    $params = [
        "wwwroot" => $CFG->wwwroot,
        "groupname" => get_db_field("name", "groups", "groupid = '$groupid'"),
        "pageid" => $pageid,
        "groupid" => $groupid,
        "feature" => $feature,
        "featureid" => $featureid,
        "canmanage" => user_is_able($USER->userid, "manage_groups", $pageid),
    ];

    $options = '';
    if ($users = get_db_result($SQL)) {
        while ($row = fetch_row($users)) {
            $p = [
                "value" => $row['userid'],
                "display" => fill_string("{fname} {lname} ({email})", $row),
            ];
            $options .= use_template("tmp/page.template", $p, "select_options_template");
        }
    } else {
        $p = ["selected" => "",
                "value" => "0",
                "display" => "No users in this group.",
        ];
        $options .= use_template("tmp/page.template", $p, "select_options_template");
    }

    $params["options"] = $options;
    echo use_template("tmp/roles_ajax.template", $params, "refresh_group_users_template");
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

    $SQL = "DELETE
            FROM groups_users
            WHERE userid = '$userid'
            AND pageid = '$pageid'
            AND groupid = '$groupid'";
    execute_db_sql($SQL);

    echo refresh_manage_groups($pageid, $groupid);
}

function refresh_manage_groups($pageid, $groupid, $feature = false, $featureid = false) {
global $CFG, $MYVARS, $ROLES, $USER;
    $myroleid = user_role($USER->userid, $pageid);
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
        $p = [
            "selected" => "selected",
            "value" => "0",
            "display" => "Search results will be shown here.",
        ];
        $options1 = use_template("tmp/page.template", $p, "select_options_template");
    } elseif ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
            $mygroups = "";
            $SQL = "SELECT *
                        FROM groups
                    WHERE groupid IN (SELECT groupid
                                        FROM groups_users
                                        WHERE userid = '" . $row['userid'] . "'
                                            AND pageid = '$pageid')";
            if ($groups = get_db_result($SQL)) {
                while ($group_info = fetch_row($groups)) {
                    $mygroups .= fill_string(" {name}", $group_info);
                }
            }
            $p = ["selected" => "",
                    "value" => $row['userid'],
                    "display" => fill_string("{fname} {lname} ({email})", $row) . $mygroups,
            ];
            $options1 .= use_template("tmp/page.template", $p, "select_options_template");
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
                                      WHERE userid = '" . $row['userid'] . "'
                                      AND pageid='$pageid')";
            if ($groups = get_db_result($SQL)) {
                while ($group_info = fetch_row($groups)) {
                    $mygroups .= fill_string(" {name}", $group_info);
                }
            }
            $p = ["selected" => "",
                    "value" => $row['userid'],
                    "display" => fill_string("{fname} {lname} ({email})", $row) . $mygroups,
            ];
            $options2 .= use_template("tmp/page.template", $p, "select_options_template");
        }
	}

    $params = [
        "wwwroot" => $CFG->wwwroot,
        "groupname" => get_db_field("name", "groups", "groupid = '$groupid'"),
        "pageid" => $pageid,
        "groupid" => $groupid,
        "feature" => $feature,
        "featureid" => $featureid,
        "options1" => $options1,
        "options2" => $options2,
    ];
    return use_template("tmp/roles_ajax.template", $params, "refresh_manage_groups_template");
}

function delete_group() {
global $CFG, $MYVARS, $USER;
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
    $SQL = use_template("dbsql/roles.sql", ["groupid" => $groupid], "get_group");
    $group = get_db_row($SQL);
    $name = $group["name"];
    $parents = groups_list($pageid, false, false, false, $group["parent"], $groupid, $groupid, "80%", "per_group_edit_group_select", "per_group_edit_group_select");
  } else { // CREATING
    $parents = groups_list($pageid, false, false, false, null, null, null, "80%", "per_group_edit_group_select", "per_group_edit_group_select");
  }

  $params = [ "wwwroot" => $CFG->wwwroot,
              "name" => $name,
              "parents" => $parents,
              "pageid" => $pageid,
              "groupid" => $groupid,
              "feature" => $feature,
              "featureid" => $featureid,
  ];
  echo use_template("tmp/roles_ajax.template", $params, "create_edit_group_form_template");
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
  $params = [ "is_editing" => boolval($groupid),
              "pageid" => $pageid,
              "groupid" => $groupid,
              "name" => $name,
              "parent" => $parent,
  ];
  $SQL = use_template("dbsql/roles.sql", $params, "save_group");
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
    echo error_string("generic_error");
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
    echo error_string("generic_error");
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
    echo '<form id="per_group_roles_form">' .
            print_abilities($pageid, "per_group_", false, false, $feature, $featureid, $groupid) .
          '</form>';
  } else {
    echo error_string("generic_error");
    return;
  }
}

/**
 * Save the changes to the role abilities
 *
 * This function is called via AJAX from the ability manager page
 * It takes the post variables and uses them to update the roles_ability
 * table with the new abilities
 *
 * @global object $CFG The global config object
 * @global object $MYVARS The global variables object
 */
function save_ability_changes() {
  global $CFG, $MYVARS;

  // Get the ability list from the post
  $abilities = explode("**", $MYVARS->GET['per_role_rightslist']);

  // Extract the page and role ids from the post
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $roleid = !empty($MYVARS->GET['per_role_roleid']) ? $MYVARS->GET['per_role_roleid'] : false; //Should always be passed

  // Extract the feature and feature id from the post (if present)
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false;
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false;

  // Initialize the success flag
  $success = false;

  // Loop through the abilities and update the database
  $i = 0;
  while (isset($abilities[$i])) {
    $ability = $abilities[$i];
    $setting = $MYVARS->GET[$ability] == 1 ? 1 : 0;

    // Create the paramaters for the SQL queries
    $params = [
        "ability" => $ability,
        "pageid" => $pageid,
        "roleid" => $roleid,
        "setting" => $setting,
        "feature" => $feature,
        "featureid" => $featureid,
    ];

    // If this is a site-wide ability
    if ($pageid == $CFG->SITEID && !$featureid) {
      // Check if there is already a default value for this ability
      $default = get_db_field("allow", "roles_ability", "roleid = '$roleid' AND ability = '$ability'");
      $params["section"] = get_db_field("section", "abilities", "ability = '$ability'");

      // If there is a default, check if the default should be changed
      if ($default !== false && $default !== $setting) {
        // If the default is being changed, remove the old default
        $SQL = use_template("dbsql/roles.sql", $params, "remove_role_override");
        $success = execute_db_sql($SQL) ? true : false;
      }

      // Insert the new default
      $SQL = use_template("dbsql/roles.sql", $params, "insert_role_override");
      $success = execute_db_sql($SQL) ? true : false;
    } else { // If this is a feature-specific ability
      // Check if there is already an override for this ability
      $default = get_db_field("allow", "roles_ability", "roleid = '$roleid' AND ability = '$ability'");

      if ($feature && $featureid) { // If this is a feature-specific ability
        $SQL = use_template("dbsql/roles.sql", $params, "get_page_role_feature_override");
        $alreadyset = get_db_count($SQL);

        if ($alreadyset) { // If there is an override, check if it should be changed
          if ($setting == $default) { // If the override should be removed         
            $SQL = use_template("dbsql/roles.sql", $params, "remove_page_role_feature_override");
            $success = execute_db_sql($SQL) ? true : false;
          } else { // If the override should be changed
            $SQL = use_template("dbsql/roles.sql", $params, "update_page_role_feature_override");
            $success = execute_db_sql($SQL) ? true : false;
          }
        } elseif ($setting != $default && !$alreadyset) { // If the override should be added
          $SQL = use_template("dbsql/roles.sql", $params, "insert_page_role_feature_override");
          $success = execute_db_sql($SQL) ? true : false;
        }
      } else { // If this is a page-specific ability
        $SQL = use_template("dbsql/roles.sql", $params, "get_page_role_override");
        $alreadyset = get_db_count($SQL);

        if ($alreadyset) { // If there is an override, check if it should be changed
          if ($setting == $default) { // If the override should be removed
            $SQL = use_template("dbsql/roles.sql", $params, "remove_page_role_override");
            $success = execute_db_sql($SQL) ? true : false;
          } else { // If the override should be changed
            $SQL = use_template("dbsql/roles.sql", $params, "update_page_role_override");
            $success = execute_db_sql($SQL) ? true : false;
          }
        } elseif ($setting != $default && !$alreadyset) { // If the override should be added
          $SQL = use_template("dbsql/roles.sql", $params, "insert_page_role_override");
          $success = execute_db_sql($SQL) ? true : false;
        }
      }
    }
    $i++;
  }

  // If the updates were successful, return a success message
  if ($success) {
    echo "Changes Saved";
  } else { // Otherwise, return a failure message
    echo "Save Failed";
  }
}

function save_user_ability_changes() {
global $CFG, $MYVARS;
  $abilities = explode("**", $MYVARS->GET['per_user_rightslist']);
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $userid = !empty($MYVARS->GET['userid']) ? $MYVARS->GET['userid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $i = 0;
	while (isset($abilities[$i])) {
		$ability = $abilities[$i];
		$setting = $MYVARS->GET[$ability] == 1 ? 1 : 0;
		$roleid = user_role($userid, $pageid);

    //$default = $featureid ? (user_is_able($userid, $ability, $pageid, $feature, $featureid) ? "1" : "0") : (user_is_able($roleid, $ability, $pageid, $feature) ? "1" : "0");
    //figure out the default
    if ($featureid) { //feature specific ability change
      $default = user_is_able($userid, $ability, $pageid, $feature, $featureid) ? "1" : "0";
    } else { //page specific ability change
      $default = user_is_able($userid, $ability, $pageid) ? "1" : "0";
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
			if ($alreadyset && $setting == $default) {
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
                     SET allow = '$setting'
                   WHERE userid = '$userid'
                     AND pageid = '$pageid'
                     AND feature = '$feature'
                     AND featureid = '$featureid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        } else {
          $SQL = "UPDATE roles_ability_peruser
                     SET allow = '$setting'
                   WHERE userid = '$userid'
                     AND pageid = '$pageid'
                     AND ability = '$ability'";
          execute_db_sql($SQL);
        }
      }
		} elseif ($setting != $default && !$alreadyset) {
      if ($feature && $featureid) {
        $SQL = "INSERT INTO roles_ability_perfeature_peruser (userid, pageid, feature, featureid, ability, allow)
                     VALUES('$userid', '$pageid', '$feature', '$featureid', '$ability', '$setting')";
        execute_db_sql($SQL);
      } else {
        $SQL = "INSERT INTO roles_ability_peruser (userid, pageid, ability, allow)
                     VALUES ('$userid', '$pageid', '$ability', '$setting')";
        execute_db_sql($SQL);
    	}
		}
		$i++;
	}
	echo "Changes Saved";
}

function save_group_ability_changes() {
global $CFG, $MYVARS;
  $abilities = explode("**", $MYVARS->GET['per_group_rightslist']);
	$pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : false; //Should always be passed
  $groupid = !empty($MYVARS->GET['groupid']) ? $MYVARS->GET['groupid'] : false; //Should always be passed
  $featureid = !empty($MYVARS->GET['featureid']) ? $MYVARS->GET['featureid'] : false; //Only passed on feature specific managing
  $feature = !empty($MYVARS->GET['feature']) ? $MYVARS->GET['feature'] : false; //Only passed on feature specific managing

  $i = 0;
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
  $myroleid = user_role($USER->userid, $pageid);
  $roleid = user_role($userid, $pageid, true);

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
        $p = ["selected" => "",
              "value" => $row['roleid'],
              "display" => stripslashes($row['display_name']),
        ];
        $options .= use_template("tmp/page.template", $p, "select_options_template");
      }
		}
	}

  $params = [ "rolename" => $rolename,
              "pageid" => $pageid,
              "userid" => $userid,
              "options" => $options,
  ];
  echo use_template("tmp/roles_ajax.template", $params, "refresh_user_roles_template");
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
                   VALUES ('$userid', '$pageid', '$roleid')";
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
