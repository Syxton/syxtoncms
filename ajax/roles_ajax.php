<?php
/***************************************************************************
* roles_ajax.php - Roles Ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
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
    $pageid = clean_myvar_opt("pageid", "int", false); // The page to search on
    $type = clean_myvar_opt("type", "string", "per_page"); // The type of search being performed (per_page or feature specific)
    $featureid = clean_myvar_opt("featureid", "int", false); // The feature ID, if feature specific
    $feature = clean_myvar_opt("feature", "string", false); // The feature name, if feature specific
    $refreshroles = clean_myvar_opt("refreshroles", "bool", false); // The feature name, if feature specific

    $searchstring = "";
    $searcharray = explode(" ", $MYVARS->GET["searchstring"]);
    $i = 0;
    foreach ($searcharray as $search) {
        $searchstring .= $i == 0 ? "" : " OR ";
        $searchstring .= "(fname LIKE '%$search%' OR lname LIKE '%$search%' OR email LIKE '%$search%')";
        $i++;
    }

    $params = [];
    if ($pageid == $CFG->SITEID && is_siteadmin($USER->userid)) {
        // If admin on site, search all users
        $SQL = fill_template("dbsql/roles.sql", "user_search_all", false, ["search" => $searchstring]);
    } else {
        // Feature specific role assignment search. (only searches people that already have page privs)
        if ($type !== "per_page") {
            // Search for users with a higher role on the page
            $SQL = fill_template("dbsql/roles.sql", "user_search_higher_role", false, ["searchstring" => $searchstring]);
        } else {  // Page role assignment search.
            // Search for users with a role lower than the user's on the page
            $SQL = fill_template("dbsql/roles.sql", "user_search_lower_role", false, ["searchstring" => $searchstring]);
        }

        // Get the user's role on the page
        $params["myroleid"] = user_role($USER->userid, $pageid);
        $params["pageid"] = $pageid;
    }

    $options = "";
    if ($users = get_db_result($SQL, $params)) {
        while ($row = fetch_row($users)) {
            $vars = [
                "selected" => "",
                "value" => $row['userid'],
                "display" => getlang("firstlastemail", false, $row),
            ];
            $options .= fill_template("tmp/page.template", "select_options_template", false, $vars);
        }
    }

    get_name_search_actions($type, $pageid, $feature, $featureid, $refreshroles);

    // Add the search results to the page template
    $params = [
        "type" => $type,
        "options" => $options,
    ];

    $return = fill_template("tmp/roles_ajax.template", "name_search_template", false, $params);

    ajax_return($return);
}

function get_name_search_actions($type, $pageid, $feature = false, $featureid = false, $refreshroles = false) {
    if ($refreshroles) {
        ajaxapi([
            "id" => $type . "_user_select",
            "if" => "$('#" . $type . "_user_select').val() > 0",
            "url" => "/ajax/roles_ajax.php",
            "data" => [
                "action" => "refresh_user_roles",
                "pageid" => $pageid,
                "userid" => "js||$('#" . $type . "_user_select').val()||js",
            ],
            "display" => $type . "_roles_div",
        ]);
    } else {
        ajaxapi([
            "id" => $type . "_user_select",
            "if" => "$('#" . $type . "_user_select').val() > 0",
            "url" => "/ajax/roles_ajax.php",
            "data" => [
                "action" => "refresh_user_abilities",
                "pageid" => $pageid,
                "feature" => $feature,
                "featureid" => $featureid,
                "userid" => "js||$('#" . $type . "_user_select').val()||js",
            ],
            "display" => $type . "_abilities_div",
        ]);
    }
}

function refresh_group_users() {
global $CFG, $MYVARS, $USER;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    $sqlparams = [];
    $sqlparams["pageid"] = $pageid;
    $sqlparams["groupid"] = $groupid;
    if ($pageid == $CFG->SITEID && is_siteadmin($USER->userid)) {
        $SQL = fetch_template("dbsql/roles.sql", "get_users_in_group");
    } else {
        $sqlparams["siteid"] = $CFG->SITEID;
        $SQL = fetch_template("dbsql/roles.sql", "get_page_users_in_groups");
    }

    $options = '';
    if ($users = get_db_result($SQL, $sqlparams)) {
        while ($row = fetch_row($users)) {
            $p = [
                "value" => $row['userid'],
                "display" => getlang("firstlastemail", false, $row),
            ];
            $options .= fill_template("tmp/page.template", "select_options_template", false, $p);
        }
    } else {
        $p = [
            "selected" => "",
            "value" => "0",
            "display" => "No users in this group.",
        ];
        $options .= fill_template("tmp/page.template", "select_options_template", false, $p);
    }

    group_users_actions($pageid, $groupid, $feature, $featureid);

    $params = [
        "groupname" => get_db_field("name", "groups", "groupid = ||groupid||", ["groupid" => $groupid]),
        "groupid" => $groupid,
        "canmanage" => user_is_able($USER->userid, "manage_groups", $pageid),
        "options" => $options,
    ];
    $return = fill_template("tmp/roles_ajax.template", "refresh_group_users_template", false, $params);

    ajax_return($return);
}

function group_users_actions($pageid, $groupid, $feature, $featureid) {
    ajaxapi([
        "id" => "delete_group",
        "if" => "confirm('Are you sure you wish to delete this group?')",
        "url" => "/ajax/roles_ajax.php",
        "data" => [
            "action" => "delete_group",
            "pageid" => $pageid,
            "groupid" => $groupid,
            "feature" => $feature,
            "featureid" => $featureid,
        ],
        "display" => "per_group_whole_page",
    ]);

    ajaxapi([
        "id" => "manage_group_users_form",
        "url" => "/ajax/roles_ajax.php",
        "data" => [
            "action" => "manage_group_users_form",
            "pageid" => $pageid,
            "groupid" => $groupid,
            "feature" => $feature,
            "featureid" => $featureid,
        ],
        "display" => "per_group_display_div",
    ]);
}

function manage_group_users_form() {
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing
    group_manager_actions($pageid, $groupid, $feature, $featureid);

    $return = refresh_manage_groups($pageid, $groupid, $feature, $featureid);
    ajax_return($return);
}

function add_group_user() {
global $CFG, $MYVARS;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $userid = clean_myvar_opt("userid", "int", false); //Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    $error = "";
    try {
        start_db_transaction();

        execute_db_sql(fetch_template("dbsql/roles.sql", "insert_group_user"), [
            "userid" => $userid,
            "pageid" => $pageid,
            "groupid" => $groupid,
        ]);

        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    $return = groups_list($pageid, $feature, $featureid, true, $groupid);

    ajax_return($return, $error);
}

function remove_group_user() {
global $CFG, $MYVARS;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $userid = clean_myvar_opt("userid", "int", false); //Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    $error = "";
    try {
        start_db_transaction();

        execute_db_sql(fetch_template("dbsql/roles.sql", "delete_group_user"), [
            "userid" => $userid,
            "pageid" => $pageid,
            "groupid" => $groupid,
        ]);

        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    $return = groups_list($pageid, $feature, $featureid, true, $groupid);

    ajax_return($return);
}

function refresh_manage_groups($pageid, $groupid, $feature = false, $featureid = false) {
global $CFG, $MYVARS, $ROLES, $USER;
    $roleid = user_role($USER->userid, $pageid);

    $params = [
        "wwwroot" => $CFG->wwwroot,
        "groupname" => get_db_field("name", "groups", "groupid = '$groupid'"),
        "pageid" => $pageid,
        "groupid" => $groupid,
        "feature" => $feature,
        "featureid" => $featureid,
        "options1" => get_non_group_members_select($pageid, $roleid, $groupid, false),
        "options2" => get_group_members_select($pageid, $roleid, $groupid),
    ];
    return fill_template("tmp/roles_ajax.template", "refresh_manage_groups_template", false, $params);
}

function get_non_group_members_select($pageid, $roleid, $groupid, $searchparams = "") {
global $CFG, $ROLES, $USER;
    $options = "";
    if ($searchparams === false) {
        $p = [
            "selected" => "selected",
            "value" => "0",
            "display" => "Search results will be shown here.",
        ];
        $options = fill_template("tmp/page.template", "select_options_template", false, $p);
    } else {
        $searcharray = explode(" ", $searchparams);
        $searchstring = "";
        $i = 0;
        foreach ($searcharray as $search) {
            $searchstring .= $i == 0 ? "" : " OR ";
            $searchstring .= "(fname LIKE '%$search%' OR lname LIKE '%$search%' OR email LIKE '%$search%')";
            $i++;
        }

        $SQL = fill_template("dbsql/roles.sql", "get_nongroup_users", false, ["searchstring" => $searchstring]);
        $params = [
            "searchstring" => $searchstring,
            "pageid" => $pageid,
            "roleid" => $roleid,
            "groupid" => $groupid,
            "siteid" => $CFG->SITEID,
            "userid" => $USER->userid,
            "adminrole" => $ROLES->admin,
        ];
        if ($users = get_db_result($SQL, $params)) {
            while ($row = fetch_row($users)) {
                $mygroups = "";
                $SQL = fetch_template("dbsql/roles.sql", "get_group_by_member");
                if ($groups = get_db_result($SQL, ["userid" => $row['userid'], "pageid" => $pageid])) {
                    while ($group_info = fetch_row($groups)) {
                        $mygroups .= fill_string(" {name}", $group_info);
                    }
                }

                $params = [
                    "selected" => "",
                    "value" => $row['userid'],
                    "display" => getlang("firstlastemail", false, $row) . $mygroups,
                ];
                $options .= fill_template("tmp/page.template", "select_options_template", false, $params);
            }
        }
    }
    return $options;
}

function get_group_members_select($pageid, $roleid, $groupid) {
    $options = "";

    $SQL = fetch_template("dbsql/roles.sql", "get_group_users_by_role");
    $params = [
        "pageid" => $pageid,
        "roleid" => $roleid,
        "groupid" => $groupid,
    ];
    if ($roles = get_db_result($SQL, $params)) {
        while ($row = fetch_row($roles)) {
            $mygroups = "";
            $SQL = fetch_template("dbsql/roles.sql", "get_group_by_member");
            if ($groups = get_db_result($SQL, ["userid" => $row['userid'],"pageid" => $pageid])) {
                while ($group_info = fetch_row($groups)) {
                    $mygroups .= fill_string(" {name}", $group_info);
                }
            }
            $p = [
                "selected" => "",
                "value" => $row['userid'],
                "display" => getlang("firstlastemail", false, $row) . $mygroups,
            ];
            $options .= fill_template("tmp/page.template", "select_options_template", false, $p);
        }
    }
    return $options;
}

function add_to_group_search() {
global $CFG, $ROLES, $USER, $MYVARS;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $type = clean_myvar_opt("type", "string", "per_page_"); //Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing
    $searchparams = clean_myvar_opt("searchstring", "html", "");

    group_manager_actions($pageid, $groupid, $feature, $featureid);

    $roleid = user_role($USER->userid, $pageid);
    $params = [
        "wwwroot" => $CFG->wwwroot,
        "groupname" => get_db_field("name", "groups", "groupid = '$groupid'"),
        "pageid" => $pageid,
        "groupid" => $groupid,
        "feature" => $feature,
        "featureid" => $featureid,
        "searchstring" => $searchparams,
        "options1" => get_non_group_members_select($pageid, $roleid, $groupid, $searchparams),
        "options2" => get_group_members_select($pageid, $roleid, $groupid),
    ];
    $return = fill_template("tmp/roles_ajax.template", "refresh_manage_groups_template", false, $params);

    ajax_return($return);
}

function group_manager_actions($pageid, $groupid, $feature = false, $featureid = false) {
    ajaxapi([
        "id" => "add_to_group_search",
        "url" => "/ajax/roles_ajax.php",
        "data" => [
            "action" => "add_to_group_search",
            "pageid" => $pageid,
            "groupid" => $groupid,
            "feature" => $feature,
            "featureid" => $featureid,
            "searchstring" => "js||encodeURIComponent($('#per_group_search_text').val())||js",
        ],
        "display" => "per_group_display_div",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "add_user_to_group",
        "if" => "$('#add_user_select').val() > 0",
        "url" => "/ajax/roles_ajax.php",
        "data" => [
            "action" => "add_group_user",
            "userid" => "js||$('#add_user_select').val()||js",
            "pageid" => $pageid,
            "groupid" => $groupid,
            "feature" => $feature,
            "featureid" => $featureid,
        ],
        "display" => "group_list_div",
        "ondone" => "add_to_group_search();"
    ]);

    ajaxapi([
        "id" => "remove_user_from_group",
        "if" => "$('#remove_user_select').val() > 0",
        "url" => "/ajax/roles_ajax.php",
        "data" => [
            "action" => "remove_group_user",
            "userid" => "js||$('#remove_user_select').val()||js",
            "pageid" => $pageid,
            "groupid" => $groupid,
            "feature" => $feature,
            "featureid" => $featureid,
        ],
        "display" => "group_list_div",
        "ondone" => "add_to_group_search();"
    ]);
}

function delete_group() {
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    $return = $error = "";
    try {
        start_db_transaction();
        if ($pageid && $groupid) {
            $params = ["groupid" => $groupid, "pageid" => $pageid];
            $SQL = fetch_template("dbsql/roles.sql", "delete_group", false,  $params);
            execute_db_sql($SQL, $params);

            $SQL = fetch_template("dbsql/roles.sql", "delete_group_users", false,  $params);
            execute_db_sql($SQL, $params);

            $SQL = fetch_template("dbsql/roles.sql", "remove_group_roles_ability_perfeature_pergroup", false,  $params);
            execute_db_sql($SQL, $params);

            $SQL = fetch_template("dbsql/roles.sql", "remove_group_roles_ability_pergroup", false,  $params);
            execute_db_sql($SQL, $params);
        }
        commit_db_transaction();
        $return = group_page($pageid, $feature, $featureid);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    ajax_return($return, $error);
}

function create_edit_group_form() {
global $CFG;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); //Only passed when editing
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    $return = $error = "";
    try {
        $name = "";
        if ($groupid) { // EDITING: get form values to fill in
            $group = get_db_row(fetch_template("dbsql/roles.sql", "get_group"), ["groupid" => $groupid]);
            $name = $group["name"];
            $parents = groups_list($pageid, false, false, false, $group["parent"], $groupid, $groupid, "100%", "per_group_edit_group_select", "per_group_edit_group_select");
        } else { // CREATING
            $parents = groups_list($pageid, false, false, false, 0, 0, 0, "100%", "per_group_edit_group_select", "per_group_edit_group_select");
        }

        ajaxapi([
            "id" => "save_group",
            "if" => "trim($('#per_group_name').val()).length > 0",
            "else" => "alert('Name is required.');",
            "url" => "/ajax/roles_ajax.php",
            "data" => [
                "action" => "save_group",
                "name" => "js||encodeURIComponent($('#per_group_name').val())||js",
                "parent" => "js||encodeURIComponent($('#per_group_edit_group_select').val())||js",
                "groupid" => $groupid,
                "pageid" => $pageid,
            ],
            "display" => "per_group_whole_page",
        ]);

        $params = [
            "wwwroot" => $CFG->wwwroot,
            "name" => $name,
            "parents" => $parents,
            "pageid" => $pageid,
            "groupid" => $groupid,
            "feature" => $feature,
            "featureid" => $featureid,
        ];
        $return = fill_template("tmp/roles_ajax.template", "create_edit_group_form_template", false, $params);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function save_group() {
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
    $name = clean_myvar_req("name", "string", false); // Should always be passed
    $parent = clean_myvar_opt("parent", "int", 0); // Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); // Only passed when editing
    $featureid = clean_myvar_opt("featureid", "int", false); // Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); // Only passed on feature specific managing

    $return = $error = "";
    try {
        $params = [
            "pageid" => $pageid,
            "groupid" => $groupid,
            "name" => $name,
            "parent" => $parent,
        ];
        $SQL = fetch_template("dbsql/roles.sql", "save_group", false, ["is_editing" => boolval($groupid)]);
        execute_db_sql($SQL, $params);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    $return = group_page($pageid, $feature, $featureid);

    ajax_return($return, $error);
}

function refresh_edit_roles() {
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
    $roleid = clean_myvar_req("roleid", "int"); // Should always be passed
    $feature = clean_myvar_opt("feature", "string", false); // Only passed on feature specific managing
    $featureid = clean_myvar_opt("featureid", "int", false); // Only passed on feature specific managing

    $return = $error = "";
    try {
        if (!$pageid || !$roleid) {
            throw new \Throwable(getlang("generic_error"));
        }

        $return = print_abilities($pageid, "per_role", $roleid, false, $feature, $featureid);

    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

//TOP LEVEL PER USER OVERRIDES
function refresh_user_abilities() {
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
    $userid = clean_myvar_req("userid", "int"); // Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); // Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); // Only passed on feature specific managing

    $return = $error = "";
    try {
        if (!$pageid || !$userid) {
            throw new \Throwable(getlang("generic_error"));
        }

        $return = print_abilities($pageid, "per_user", false, $userid, $feature, $featureid);

    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function refresh_group_abilities() {
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
    $groupid = clean_myvar_req("groupid", "int"); // Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); // Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); // Only passed on feature specific managing

    $return = $error = "";
    try {
        if (!$pageid || !$groupid) {
            throw new \Throwable(getlang("generic_error"));
        }
        $return =
            '<form id="per_group_roles_form">' .
                print_abilities($pageid, "per_group", false, false, $feature, $featureid, $groupid) .
            '</form>';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
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
    $abilities = explode("**", clean_myvar_opt("per_role_rightslist", "string", ""));

    // Extract the page and role ids from the post
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
    $roleid = clean_myvar_opt("per_role_roleid", "int", false); // Should always be passed

    // Extract the feature and feature id from the post (if present)
    $featureid = clean_myvar_opt("featureid", "int", false);
    $feature = clean_myvar_opt("feature", "string", false);

    // Initialize the success flag
    $success = false;

      try {
        start_db_transaction();
        // Loop through the abilities and update the database
        $i = 0;
        while (isset($abilities[$i])) {
            $ability = $abilities[$i];
            $allow = clean_myvar_opt($ability, "int", 0) == 1 ? 1 : 0;

            // Create the paramaters for the SQL queries
            $params = [
                "ability" => $ability,
                "pageid" => $pageid,
                "roleid" => $roleid,
                "allow" => $allow,
                "feature" => $feature,
                "featureid" => $featureid,
            ];

            // If this is a site-wide ability
            if ($pageid == $CFG->SITEID && !$featureid) {
                // Check if there is already a default value for this ability
                $default = get_db_field("allow", "roles_ability", "roleid = '$roleid' AND ability = '$ability'");
                $params["section"] = get_db_field("section", "abilities", "ability = '$ability'");

                // If there is a default, check if the default should be changed
                if ($default !== false && $default !== $allow) {
                    // If the default is being changed, remove the old default
                    execute_db_sql(fetch_template("dbsql/roles.sql", "remove_role_override"), $params) ? true : false;
                }

                // Insert the new default
                execute_db_sql(fetch_template("dbsql/roles.sql", "insert_role_override"), $params) ? true : false;
            } else { // If this is a feature-specific ability
                // Check if there is already an override for this ability
                $default = get_db_field("allow", "roles_ability", "roleid = '$roleid' AND ability = '$ability'");

                if ($feature && $featureid) { // If this is a feature-specific ability
                    $alreadyset = get_db_count(fetch_template("dbsql/roles.sql", "get_page_role_feature_override"), $params);

                    if ($alreadyset) { // If there is an override, check if it should be changed
                        if ($allow == $default) { // If the override should be removed
                            execute_db_sql(fetch_template("dbsql/roles.sql", "remove_page_role_feature_override"), $params) ? true : false;
                        } else { // If the override should be changed
                            execute_db_sql(fetch_template("dbsql/roles.sql", "update_page_role_feature_override"), $params) ? true : false;
                        }
                    } elseif ($allow != $default && !$alreadyset) { // If the override should be added
                        execute_db_sql(fetch_template("dbsql/roles.sql", "insert_page_role_feature_override"), $params) ? true : false;
                    }
                } else { // If this is a page-specific ability
                    $alreadyset = get_db_count(fetch_template("dbsql/roles.sql", "get_page_role_override"), $params);

                    if ($alreadyset) { // If there is an override, check if it should be changed
                        if ($allow == $default) { // If the override should be removed
                            execute_db_sql(fetch_template("dbsql/roles.sql", "remove_page_role_override"), $params) ? true : false;
                        } else { // If the override should be changed
                            execute_db_sql(fetch_template("dbsql/roles.sql", "update_page_role_override"), $params) ? true : false;
                        }
                    } elseif ($allow != $default && !$alreadyset) { // If the override should be added
                        execute_db_sql(fetch_template("dbsql/roles.sql", "insert_page_role_override"), $params) ? true : false;
                    }
                }
            }
            $i++;
        }
        commit_db_transaction();
        $success = true;
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }

    // If the updates were successful, return a success message
    if ($success) {
        echo "Changes Saved";
    } else { // Otherwise, return a failure message
        echo "Save Failed";
    }
}

function save_user_ability_changes() {
    $abilities = explode("**", clean_myvar_opt("per_user_rightslist", "string", ""));
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $userid = clean_myvar_opt("userid", "int", false); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    $i = 0;
    while (isset($abilities[$i])) {
        $ability = $abilities[$i];
        $allow = clean_myvar_opt($ability, "int", 0) == 1 ? 1 : 0;
        $roleid = user_role($userid, $pageid);

        $params = [
            "userid" => $userid,
            "pageid" => $pageid,
            "ability" => $ability,
            "allow" => $allow,
            "feature" => $feature,
            "featureid" => $featureid,
        ];

        // figure out the default
        if ($featureid) { // feature specific ability change
            $default = user_is_able($userid, $ability, $pageid, $feature, $featureid) ? 1 : 0;
        } else { // page specific ability change
            $default = user_is_able($userid, $ability, $pageid) ? 1 : 0;
        }

        if ($feature && $featureid) {
            $alreadyset = get_db_count(fetch_template("dbsql/roles.sql", "get_page_feature_user_override"), $params);
        } else {
            $alreadyset = get_db_count(fetch_template("dbsql/roles.sql", "get_page_user_override"), $params);
        }

        if ($alreadyset) {
            if ($allow == $default) {
                if ($feature && $featureid) {
                    execute_db_sql(fetch_template("dbsql/roles.sql", "remove_roles_ability_perfeature_peruser_override"), $params);
                } else {
                    execute_db_sql(fetch_template("dbsql/roles.sql", "remove_roles_ability_peruser_override"), $params);
                }
            } else {
                if ($feature && $featureid) {
                    execute_db_sql(fetch_template("dbsql/roles.sql", "update_roles_ability_perfeature_peruser_override"), $params);
                } else {
                    execute_db_sql(fetch_template("dbsql/roles.sql", "update_roles_ability_peruser_override"), $params);
                }
            }
        } elseif ($allow != $default && !$alreadyset) {
            if ($feature && $featureid) {
                execute_db_sql(fetch_template("dbsql/roles.sql", "insert_roles_ability_perfeature_peruser_override"), $params);
            } else {
                execute_db_sql(fetch_template("dbsql/roles.sql", "insert_roles_ability_peruser_override"), $params);
            }
        }
        $i++;
    }
    echo "Changes Saved";
}

function save_group_ability_changes() {
global $CFG, $MYVARS;
    $abilities = explode("**", clean_myvar_opt("per_group_rightslist", "string", ""));
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    $return = $error = "";
    try {
        start_db_transaction();
        $i = 0;
        while (isset($abilities[$i])) {
            $ability = $abilities[$i];
            $allow = clean_myvar_opt($ability, "int", false);

            $allow = false; // If ability is NOT SET default to false
            if (isset($MYVARS->GET[$ability])) {
                $allow = $MYVARS->GET[$ability];
                $allow = $allow == 1 || $allow == 0 ? $allow : false;
            }

            $params = [
                "groupid" => $groupid,
                "pageid" => $pageid,
                "ability" => $ability,
                "allow" => $allow,
                "feature" => $feature,
                "featureid" => $featureid,
            ];

            if ($feature && $featureid) {
                $SQL = fetch_template("dbsql/roles.sql", "get_page_group_feature_override");
            } else {
                $SQL = fetch_template("dbsql/roles.sql", "get_page_group_override");
            }

            $alreadyset = get_db_count($SQL, $params);
            if ($alreadyset) {
                if ($alreadyset && $allow === false) { // If ability is NOT SET to 1 or 0 but is set in the db
                    if ($feature && $featureid) {
                        $SQL = fetch_template("dbsql/roles.sql", "remove_roles_ability_perfeature_pergroup_override");
                    } else {
                        $SQL = fetch_template("dbsql/roles.sql", "remove_roles_ability_pergroup_override");
                    }
                    execute_db_sql($SQL, $params);
                } else { //If ability is SET to 1 or 0 and is already set in the db
                    if ($feature && $featureid) {
                        $SQL = fetch_template("dbsql/roles.sql", "update_page_group_feature_override");
                    } else {
                        $SQL = fetch_template("dbsql/roles.sql", "update_page_group_override");
                    }
                    execute_db_sql($SQL, $params);
                }
            } elseif ($allow !== false && !$alreadyset) { //If ability is SET to 1 or 0 and isn't already set in the db
                if ($feature && $featureid) {
                    $SQL = fetch_template("dbsql/roles.sql", "insert_page_group_feature_override");
                } else {
                    $SQL = fetch_template("dbsql/roles.sql", "insert_page_group_override");
                }
                execute_db_sql($SQL, $params);
            }
            $i++;
        }
        commit_db_transaction();
        $return = "Changes Saved";
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    ajax_return($return, $error);
}

function refresh_user_roles() {
global $CFG, $USER, $MYVARS, $ROLES;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $userid = clean_myvar_opt("userid", "int", false); //Should always be passed
    $myroleid = user_role($USER->userid, $pageid);
    $roleid = user_role($userid, $pageid, true);

    $return = $error = "";
    try {
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

        $sql_admin = $pageid !== $CFG->SITEID ? " WHERE roleid <> '$ROLES->admin'" : "";
        $SQL = "SELECT *
                FROM roles
                $sql_admin
                ORDER BY roleid";
        $options = '';
        if ($roles = get_db_result($SQL)) {
            while ($row = fetch_row($roles)) {
                if ($row['roleid'] != $roleid && $row['roleid'] >= $myroleid) {
                    $p = [
                        "selected" => "",
                        "value" => $row['roleid'],
                        "display" => stripslashes($row['display_name']),
                    ];
                    $options .= fill_template("tmp/page.template", "select_options_template", false, $p);
                }
            }
        }

        ajaxapi([
            "id" => "assign_role_button",
            "url" => "/ajax/roles_ajax.php",
            "data" => [
                "action" => "assign_role",
                "pageid" => $pageid,
                "userid" => $userid,
                "roleid" => "js||$('#role_select').val()||js",
            ],
            "ondone" => "jq_display('per_page_saved_div1', data); jq_display('per_page_saved_div2', data); refresh_user_roles();",
        ]);

        ajaxapi([
            "id" => "refresh_user_roles",
            "url" => "/ajax/roles_ajax.php",
            "data" => [
                "action" => "refresh_user_roles",
                "pageid" => $pageid,
                "userid" => "js||$('#per_page_user_select').val()||js",
            ],
            "event" => "none",
            "display" => "per_page_roles_div",
            "ondone" => "setTimeout(function() { clear_display('per_page_saved_div1'); clear_display('per_page_saved_div2'); }, 5000);",
        ]);

        $params = [
            "rolename" => $rolename,
            "pageid" => $pageid,
            "userid" => $userid,
            "options" => $options,
        ];
        $return = fill_template("tmp/roles_ajax.template", "refresh_user_roles_template", false, $params);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function assign_role() {
global $ROLES;
    $roleid = clean_myvar_opt("roleid", "int", false);
    $userid = clean_myvar_req("userid", "int");
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    $return = $error = "";
    try {
        if (execute_db_sql(fetch_template("dbsql/roles.sql", "remove_user_role_assignment"), ["userid" => $userid, "pageid" => $pageid])) {
            if ($roleid !== $ROLES->none) { // No role besides "No Role" was given
                $SQL = fetch_template("dbsql/roles.sql", "insert_role_assignment");
                if (execute_db_sql($SQL, ["userid" => $userid, "pageid" => $pageid, "roleid" => $roleid, "confirm" => 0])) {
                    $return = "Changes Saved";
                } else {
                    $return = "No Role Given";
                }
            } else {
                $return = "Role Removed";
            }
        } else {
            $return = "Changes Not Saved";
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}
?>
