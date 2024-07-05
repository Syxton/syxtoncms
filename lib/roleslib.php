<?php
/***************************************************************************
* roleslib.php - Roles function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.3.0
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define("ROLES", true);

$ABILITIES = new \stdClass;

function is_siteadmin($userid) {
global $CFG, $ROLES;
    if (!isset($userid)) { return false; }
    $params = ["adminroleid" => $ROLES->admin, "userid" => $userid, "siteid" => $CFG->SITEID];

    if (!get_db_count(fetch_template("dbsql/roles.sql", "is_siteadmin"), $params)) { return false; }
    return true;
}

function remove_all_roles($userid) {
    $templates = [];
    $templates[] = [
        "file" => "dbsql/roles.sql",
        "subsection" => [
            "remove_user_roles_assignment",
            "remove_user_roles_ability_peruser",
            "remove_user_roles_ability_perfeature_peruser",
        ],
    ];
    try {
        start_db_transaction();
        $results = execute_db_sqls(fetch_template_set($templates), ["userid" => $userid]);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
    return $results;
}

// Add an ability and assign a role it's value
function add_role_ability($section, $ability, $displayname, $power, $desc, $creator='0', $editor='0', $guest='0', $visitor='0') {
    try {
        start_db_transaction();
        $SQL = "SELECT ability
                FROM abilities
                WHERE section = ||section||
                AND section_display = ||displayname||";
        if (!get_db_row($SQL, ["section" => $section, "displayname" => $displayname])) {
            $templates = $params = [];
            $templates[] = ["file" => "dbsql/roles.sql", "subsection" => ["insert_abilities", "insert_roles_ability"]];
            $params[] = [ // vars for insert_abilities
                "section" => $section,
                "displayname" => $displayname,
                "ability" => $ability,
                "desc" => $desc,
                "power" => $power,
            ];
            $params[] = [ // vars for insert_roles_ability
                "section" => $section,
                "ability" => $ability,
                "creator" => $creator,
                "editor" => $editor,
                "guest" => $guest,
                "visitor" => $visitor,
            ];
            execute_db_sqls(fetch_template_set($templates), $params);
            commit_db_transaction();
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
}

function user_is_able($userid, $ability, $pageid, $feature = "", $featureid = 0) {
global $CFG, $ROLES, $ABILITIES;
    if (!$featureid && isset($ABILITIES->$ability)) { // Get cached abilities first (SAVES TIME!)
        if ($ABILITIES->$ability->allow) { return true; }
        return false;
    }

    if (is_siteadmin($userid)) { return true; }

    $roleid = user_role($userid, $pageid);

    if ($roleid == $ROLES->visitor) {
        if (role_is_able($ROLES->visitor, $ability, $pageid, $feature, $featureid)) {
            return true;
        }
    } else {
        // This sql template has a few spots with generated sql that need filled in before the params are prepared.
        // It requires a fill_template instead of a fetch_template to add the additional sql to the template first.
        $sqlparams = [
            "groupsql" => groups_SQL($userid, $pageid, $ability),
            "featuregroupsql" => groups_SQL($userid, $pageid, $ability, $feature, $featureid),
        ];
        $SQL = fill_template("dbsql/roles.sql", "user_has_ability_in_page", false, $sqlparams, true);
        $params = [
            "pageid" => $pageid,
            "roleid" => $roleid,
            "userid" => $userid,
            "ability" => $ability,
            "feature" => $feature,
            "featureid" => $featureid,
        ];

        if (get_db_row($SQL, $params)) {
            return true;
        }
    }
    return false;
}

function user_abilities($userid, $pageid, $section = false, $feature = "", $featureid = 0) {
global $CFG, $ROLES, $ABILITIES;

    if (is_siteadmin($userid)) {
        return role_abilities($ROLES->admin, $CFG->SITEID, $section);
    }

    $roleid = user_role($userid, $pageid);

    if ($roleid == $ROLES->visitor) {
        return role_abilities($ROLES->visitor, $pageid, $section, $feature, $featureid);
    } else {
        $section_sql = "";
        if ($section) {
            foreach ((array) $section as $s) { // if string, cast to array and keep going
                $section_sql .= $section_sql == "" ? "section = '$s'" : " OR section = '$s'";
            }
        }

        // This sql template has a few spots with generated sql that need filled in before the params are prepared.
        // It requires a fill_template instead of a fetch_template to add the additional sql to the template first.
        $sqlparams = [
            "issection" => ($section ? true : false),
            "section" => $section_sql,
            "groupsql" => groups_SQL($userid, $pageid),
            "featuregroupsql" => groups_SQL($userid, $pageid, 'a.ability', $feature, $featureid),
        ];
        $SQL = fill_template("dbsql/roles.sql", "get_user_abilities", false, $sqlparams, true);

        $params = ["pageid" => $pageid, "userid" => $userid, "feature" => $feature, "featureid" => $featureid, "roleid" => $roleid];
        if ($results = get_db_result($SQL, $params)) {
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
            trigger_error("FAILED SQL: $SQL", E_USER_ERROR);
        }

        if (!$section) {
            $ABILITIES = $abilities;
        }
        return $abilities;
    }
}

function pages_user_is_able($userid, $ability, $siteviewable = true, $menuitems = true) {
global $CFG, $ROLES;
    if (is_siteadmin($userid)) {
        return get_db_result(fetch_template("dbsql/roles.sql", "admin_has_ability_in_pages", false, ["notsiteviewable" => (!$siteviewable), "notmenuitems" => (!$menuitems)]));
    }

    $params = ["userid" => $userid, "ability" => $ability];

    // This is a partial sql template that needs to create a dynamic field value for each role.
    // It will replace ||roleid|| with ||roleid_1||, ||roleid_2||, etc.  It also adds the roleid as a new parameter to be prepared.
    $rolesql = "";
    foreach ($ROLES as $roleid) {
        $params["roleid_$roleid"] = $roleid;
        $rolesql .= fill_template("dbsql/roles.sql", "user_has_ability_in_pages_perrole", false, ["notsiteviewable" => (!$siteviewable), "notmenuitems" => (!$menuitems), "roleid" => "||roleid_$roleid||"], true);
    }

    // This sql template has a few spots with generated sql that need filled in before the params are prepared.
    // It requires a fill_template instead of a fetch_template to add the additional sql to the template first.
     $SQL = fill_template("dbsql/roles.sql", "user_has_ability_in_pages", false, ["rolesql" => $rolesql], true);

    if ($results = get_db_result($SQL, $params)) {
        return $results;
    }
    return false;
}

function role_abilities($roleid, $pageid, $section = false, $feature = "", $featureid = 0) {
global $CFG, $ROLES, $ABILITIES;
    $section_sql = "";
    $params = [
        "pageid" => $pageid,
        "feature" => $feature,
        "featureid" => $featureid,
        "roleid" => $roleid,
    ];

    if ($section) {
        foreach ((array) $section as $s) { // if string, cast to array and keep going
            $ref = preg_replace('/\s+/', '', $s); // Just in case section has spaces.
            $params["section_$ref"] = $s;
            $section_sql .= $section_sql == "" ? "section = ||section_$ref||" : " OR section = ||section_$ref||";
        }
    }

    // Using fill_template because dynamic sql has to be inserted in the template.
    $SQL = fill_template("dbsql/roles.sql", "get_role_abilities", false, ["issection" => ($section), "section" => $section_sql], true);

    if ($results = get_db_result($SQL, $params)) {
        $abilities = new \stdClass;
        while ($row = fetch_row($results)) {
            $ability = $row["ability"];
            $allow = $row["allowed"] == 1 ? 1 : 0;
            $abilities->$ability = new \stdClass;
            $abilities->$ability->allow = $allow;
        }
    } else {
        trigger_error("FAILED SQL: $SQL", E_USER_ERROR);
    }

    if (empty($section) && !empty($abilities)) {
        $ABILITIES = $abilities;
    }
    return $abilities;
}


/**
 * Check if a role has a specific ability on a given page
 * @param int $roleid The role ID
 * @param string $ability The ability string
 * @param int $pageid The page ID
 * @param string $feature The feature name (optional)
 * @param int $featureid The feature ID (optional)
 * @return bool True if the role has the ability, false otherwise
 */
function role_is_able($roleid, $ability, $pageid, $feature = "", $featureid = 0) {
    $params = [
        "pageid" => $pageid,
        "feature" => $feature,
        "featureid" => $featureid,
        "roleid" => $roleid,
        "ability" => $ability,
    ];

    if (get_db_row(fetch_template("dbsql/roles.sql", "role_has_ability_in_page"), $params)) {
        return true;
    }
    return false;
}

/**
 * Load the roles into a global object
 * @global \stdClass $ROLES The roles object
 * @return \stdClass The roles object
 */
function load_roles() {
global $CFG;
    // Store all roles in a global object, keyed by role name
    $ROLES = new \stdClass;

    // Get all roles from the database
    if ($allroles = get_db_result(fetch_template("dbsql/roles.sql", "get_roles"))) {
        while ($row = fetch_row($allroles)) {
            $rolename = $row['name'];
            $ROLES->$rolename = $row['roleid'];
        }
    }
    return $ROLES;
}


function user_role($userid, $pageid, $ignore_site_admin = false) {
global $CFG, $ROLES, $USER;

    if (is_logged_in($userid)) {
        // Check if user is site admin.
        $admin = is_siteadmin($userid) ? true : false;
        if (!$ignore_site_admin && $admin) {
            return $ROLES->admin;
        }

        // Check if user has a role in the page.
        $params = ["userid" => $userid, "pageid" => $pageid];
        if ($result = get_db_result(fetch_template("dbsql/roles.sql", "get_user_role"), $params)) {
            while ($row = fetch_row($result)) {
                return $row['roleid'];
            }
        }

        if ($admin) { return $ROLES->admin; } // Site admin, but doesn't have a role in the page.

        // If page has open door policy, return default role for page.
        if (get_db_field("opendoorpolicy", "pages", "pageid = ||pageid||", ["pageid" => $pageid]) == 1) {
            return get_default_role($pageid);
        }
    }

    // if it is a site viewable page and the user has no specified role, default to visitor
    if (is_visitor_allowed_page($pageid)) {
        return $ROLES->visitor;
    }

    // No role found.
    return $ROLES->none;
}

function users_that_have_ability_in_page($ability, $pageid) {
global $CFG, $ROLES;
    $page = get_db_row(fetch_template("dbsql/pages.sql", "get_page"), ["pageid" => $pageid]);

    $SQL = fetch_template("dbsql/roles.sql", "users_that_have_ability_in_page", false, ["siteoropen" => ($page["siteviewable"] || $page["opendoorpolicy"])]);
    if ($results = get_db_result($SQL, ["pageid" => $pageid, "ability" => $ability, "siteid" => $CFG->SITEID])) {
        return $results;
    }
    return false;
}

//
// GROUPS AREA
//

//This function will get an array of the groups hierarchy
function get_groups_hierarchy($userid, $pageid, $parent = 0) {
    $params = ["pageid" => $pageid, "userid" => $userid, "parent" => $parent];
    if ($groups = get_db_result(fetch_template("dbsql/roles.sql", "get_groups_hierarchy"), $params)) {	// If you are in a group on this page.
        $groups_array = [];
        while ($group = fetch_row($groups)) {
            $groups_array[] = $group["groupid"];
            // Check for child groups
            // Check if user is in a group that is a child of this group
            if ($child_group = get_groups_hierarchy($userid, $pageid, $group["groupid"])) {
                $groups_array = array_merge($groups_array, $child_group);
            }
        }
        return $groups_array;
    }
    return false;
}

//This function gets the permission of an ability according to groups
function groups_SQL($userid, $pageid, $ability = 'a.ability', $feature = false, $featureid = false) {
    //Return array of groups hierarchy for given user in this page
    $hierarchy = get_groups_hierarchy($userid, $pageid);

    //Groups don't exist
    if (empty($hierarchy)) { $groupsSQL[0] = ""; $groupsSQL[1] = "";	return $groupsSQL; }

    //Add quotes around a specific ability or link to SQL variable if not given
    $ability = $ability == 'a.ability' ? 'a.ability' : '||ability||';

    //Decide which table the SQL requires
    $table = $feature && $featureid ? 'roles_ability_perfeature_pergroup' : 'roles_ability_pergroup';

    //Add feature checks if a perfeature SQL is asked for
    $extraSQL = $feature && $featureid ? "AND feature = ||feature|| AND featureid = ||featureid||" : "";

    //Create dynamic groups sql
    $SQL1 = "";	$SQL2 = "";
    foreach ($hierarchy as $groupid) {
        $SQL1 .= "( 1 IN (SELECT allow FROM $table WHERE groupid = $groupid AND ability = $ability AND allow = 1 $extraSQL) OR ";
        $SQL2 .= " ) AND 0 NOT IN (SELECT allow FROM $table WHERE groupid = $groupid AND ability = $ability AND allow = 0 $extraSQL) ";
    }

    $groupsSQL[0] = $SQL1;
    $groupsSQL[1] = $SQL2;

    return $groupsSQL;
}

function merge_abilities($abilities) {
  $merged = [];
  foreach ($abilities as $ability) {
        $merged = (object) array_merge((array) $merged, (array) $ability);
  }
  return $merged;
}

function group_page($pageid, $feature, $featureid) {
global $CFG, $USER;
    $params = [
        "pageid" => $pageid,
        "feature" => $feature,
        "featureid" => $featureid,
        "wwwroot" => $CFG->wwwroot,
        "groups_list" => groups_list($pageid, $feature, $featureid),
        "canmanagegroups" => user_is_able($USER->userid, "manage_groups", $pageid),
    ];
    return fill_template("tmp/roles.template", "group_page_template", false, $params);
}

function groups_list($pageid, $feature = false, $featureid = false, $action = true, $selectid = 0, $excludeid = 0, $excludechildrenofid = 0, $width = "100%", $id = "group_select", $name = "groupid") {
    $params = [
        "name" => $name,
        "pageid" => $pageid,
        "feature" => $feature,
        "featureid" => $featureid,
        "width" => $width,
        "id" => $id,
        "enableaction" => $action,
        "groups" => sub_groups_list($pageid, false, "", $selectid, $excludeid, $excludechildrenofid),
    ];
    return fill_template("tmp/roles.template", "groups_list_template", false, $params);
}

function sub_groups_list($pageid, $parent = false, $level = "", $selectid = 0, $excludeid = 0, $excludechildrenofid = 0) {
    $options = "";
    $parent = $parent ? $parent : 0;

    if ($groups = get_db_result(fetch_template("dbsql/roles.sql", "get_subgroups"), ["pageid" => $pageid, "parent" => $parent])) {
        while ($group = fetch_row($groups)) {
            $group_count = get_db_count(fetch_template("dbsql/roles.sql", "get_group_users"), ["groupid" => $group['groupid']]);
            $display = $level . $group['name'] . ' (' . $group_count . ')';
            $selected = $selectid == $group["groupid"] ? "selected" : "";
            $options .= $excludeid != $group["groupid"] ? fill_template("tmp/page.template", "select_options_template", false, ["value" => $group['groupid'], "display" => $display, "selected" => $selected]) : '';

            // get subgroups using recurssive call.
            if ($subgroups = get_db_row(fetch_template("dbsql/roles.sql", "get_subgroups"), ["pageid" => $pageid, "parent" => $group['groupid']])) {
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
    $default_checked = false;
    $default = "";

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
        $params = [
            "pageid" => $pageid,
            "type" => $type,
            "roleid" => $roleid,
            "userid" => $userid,
            "feature" => $feature,
            "featureid" => $featureid,
            "groupid" => $groupid,
            "save_function" => $save_function,
            "refresh_function" => $refresh_function,
        ];
        $save_button = fill_template("tmp/roles.template", "print_abilities_save_button", false, $params);
    }

    $SQL = fetch_template("dbsql/roles.sql", "print_abilities_sql", false, ["is_feature" => ($feature && $featureid)]);
    if ($allabilities = get_db_result($SQL, ["feature" => $feature])) {
        $i = 0; $abilities = "";
        $style_row1 = 'class="roles_row1"';
        $style_row2 = 'class="roles_row2"';
        while ($row = fetch_row($allabilities)) {
            $currentstyle = $currentstyle == $style_row1 ? $style_row2 : $style_row1;
            $currentstyle = $section == $row['section'] ? $currentstyle : $style_row1;

            if ($roleid && empty($userid)) { // Role based only
                $rights = role_is_able($roleid, $row['ability'], $pageid, $feature, $featureid) ? true : false;
                $SQL = fetch_template("dbsql/roles.sql", "get_page_role_override");
                $notify = get_db_count($SQL, ["ability" => $row['ability'], "pageid" => $pageid, "roleid" => $roleid]) ? true : false;
            } elseif ($groupid) { // Group based
                $default_toggle = true;
                $params = ["ability" => $row['ability'], "pageid" => $pageid, "feature" => $feature, "featureid" => $featureid, "groupid" => $groupid];
                if ($feature && $featureid) { // Feature group override
                    $rights = get_db_row(fetch_template("dbsql/roles.sql", "get_page_group_feature_override"), $params);
                } else {
                    $rights = get_db_row(fetch_template("dbsql/roles.sql", "get_page_group_override"), $params);
                }
                $rights = $rights && $rights["allow"] == "0" ? false : ($rights && $rights["allow"] == "1" ? true : false);
                $notify = $rights !== false ? true : false;
                $default_checked = $rights === false ? true : false;
            } elseif ($userid) { // User based
                $params = ["ability" => $row['ability'], "pageid" => $pageid, "feature" => $feature, "featureid" => $featureid, "userid" => $userid];
                if ($feature && $featureid) { // Feature user override
                    $SQL = fetch_template("dbsql/roles.sql", "get_page_feature_user_override");
                    $rights = user_is_able($userid, $row['ability'], $pageid, $feature, $featureid) ? true : false;
                    $notify = get_db_count($SQL, $params) ? true : false;
                } else { // Page user override
                    $SQL = fetch_template("dbsql/roles.sql", "get_page_user_override");
                    $rights = user_is_able($userid, $row['ability'], $pageid) ? true : false;
                    $notify = get_db_count($SQL, $params) ? true : false;
                }
            }

            $notify1 = $notify2 = false; // not set
            if ($rights === true) { // set to allow
                $notify1 = true;
                $notify2 = false;
            } else if ($rights === false) { // set to disallow
                $notify1 = false;
                $notify2 = true;
            }

            $params = [
                "type" => $type,
                "currentstyle" => $currentstyle,
                "ability" => $row,
                "swap_function" => $swap_function,
                "thissection" => ($section != $row['section']),
                "notify1" => $notify1,
                "notify2" => $notify2,
                "notify" => $notify,
                "default_toggle" => $default_toggle,
                "default_checked" => $default_checked,
            ];
            $abilities .= fill_template("tmp/roles.template", "print_abilities_ability", false, $params);

            $rightslist .= $rightslist == "" ? $row['ability'] : "**" . $row['ability'];
            $section = $row['section']; // Remmember last section so we know when a new section starts.
            $i++;
        }
    }

    $params = [
        "default" => $default,
        "abilities" => $abilities,
        "type" => $type,
        "save" => $save_button,
        "rightslist" => $rightslist,
    ];
    return fill_template("tmp/roles.template", "print_abilities", false, $params);
}
?>
