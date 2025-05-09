<?php
/***************************************************************************
* roles.php - Role relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.5.1
***************************************************************************/

if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}

echo fill_template("tmp/page.template", "start_of_page_template");
echo fill_template("tmp/roles.template", "roles_header_script");

callfunction();

echo fill_template("tmp/page.template", "end_of_page_template");

function assign_roles() {
global $CFG, $MYVARS, $USER, $ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; // Should always be passed

    if (!user_is_able($USER->userid, "assign_roles", $pageid)) {
        trigger_error(getlang("no_function", false, ["assign_roles"]), E_USER_WARNING);
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
                $options .= fill_template("tmp/roles.template", "assign_roles_options_template", false, ["user" => $row]);
            }
        }
    }

    $type = "per_page";
    ajaxapi([
        "id" => $type . "_name_search",
        "url" => "/ajax/roles_ajax.php",
        "data" => [
            "action" => "name_search",
            "pageid" => $pageid,
            "refreshroles" => true,
            "type" => $type,
            "searchstring" => "js||encodeURIComponent($('#" . $type . "_search').val())||js",
        ],
        "before" => "clear_display('" . $type . "_roles_div');",
        "display" => $type . "_users_display_div",
        "event" => "submit",
    ]);

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

    $params = ["pageid" => $pageid, "issiteid" => ($pageid == $CFG->SITEID), "options" => $options];
    echo fill_template("tmp/roles.template", "assign_roles_template", false, $params);
}

function role_specific() {
global $CFG, $USER, $MYVARS, $ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing
    $abilities = user_abilities($USER->userid, $pageid, "roles", $feature, $featureid);
        $roleid = false; $options = "";

    if (!((!$featureid && $abilities->edit_roles->allow) || (($featureid && $abilities->edit_feature_abilities->allow)))) {
        echo getlang("generic_permissions");
        return;
    }

    if ($roles = get_db_result(fetch_template("dbsql/roles.sql", "get_lower_roles"), ["roleid" => user_role($USER->userid, $pageid)])) {
        while ($row = fetch_row($roles)) {
          $roleid = !$roleid ? $row["roleid"] : $roleid;
            $options .= fill_template("tmp/roles.template", "role_specific_options_template", false, ["roles" => $row]);
        }
    }

    ajaxapi([
        "id" => "per_role_role_select",
        "url" => "/ajax/roles_ajax.php",
        "data" => [
            "action" => "refresh_edit_roles",
            "pageid" => $pageid,
            "feature" => $feature,
            "featureid" => $featureid,
            "roleid" => "js||$('#per_role_role_select').val()||js",
        ],
        "display" => "per_role_abilities_div",
        "event" => "change",
    ]);

    $params = [
        "pageid" => $pageid,
        "feature" => $feature,
        "featureid" => $featureid, "options" => $options,
        "abilities" => print_abilities($pageid, "per_role", $roleid, false, $feature, $featureid),
    ];
    echo fill_template("tmp/roles.template", "role_specific_template", false, $params);
}

function user_specific() {
global $CFG, $USER, $MYVARS, $ROLES;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing
    $abilities = user_abilities($USER->userid, $pageid, "roles", $feature, $featureid);

    if (!((!$featureid && $abilities->edit_user_abilities->allow) || ($featureid && $abilities->edit_feature_user_abilities->allow))) {
        echo getlang("generic_permissions");
        return;
    }

    $options = "";

    $SQL = fetch_template("dbsql/roles.sql", "users_that_can_have_abilities_modified");
    $params = [];
    $params["pageid"] = $pageid;
    $params["siteid"] = $CFG->SITEID;
    $params["adminrole"] = $ROLES->admin;
    $params["myrole"] = user_role($USER->userid, $pageid);
    $params["userid"] = $USER->userid;
    if ($roles = get_db_result($SQL, $params)) {
        while ($row = fetch_row($roles)) {
            $options .= fill_template("tmp/roles.template", "user_specific_options_template", false, ["user" => $row]);
        }
    }

    $params = [
        "pageid" => $pageid,
        "feature" => $feature,
        "featureid" => $featureid,
        "options" => $options,
        "issiteid" => ($pageid == $CFG->SITEID),
    ];

    $type = "per_user";
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

    ajaxapi([
        "id" => $type . "_name_search",
        "url" => "/ajax/roles_ajax.php",
        "data" => [
            "action" => "name_search",
            "pageid" => $pageid,
            "feature" => $feature,
            "featureid" => $featureid,
            "type" => $type,
            "searchstring" => "js||encodeURIComponent($('#" . $type . "_search').val())||js",
        ],
        "before" => "clear_display('" . $type . "_users_display_div');",
        "display" => $type . "_users_display_div",
        "event" => "submit",
    ]);

    echo fill_template("tmp/roles.template", "user_specific_template", false, $params);
}

function group_specific() {
global $CFG, $USER, $MYVARS, $ROLES;
    $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $CFG->SITEID; //Should always be passed
    $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
    $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

    if (!$featureid) {
        if (!user_is_able($USER->userid, "edit_group_abilities", $pageid)) {
                    echo getlang("generic_permissions");
                    return;
                }
    }

    if (!user_is_able($USER->userid, "edit_feature_group_abilities", $pageid, $feature, $featureid)) {
        echo getlang("generic_permissions");
        return;
    }

    $params = [
        "grouppage" => group_page($pageid, $feature, $featureid),
    ];
    echo fill_template("tmp/roles.template", "group_specific_template", false, $params);
}

function manager() {
global $CFG, $USER, $MYVARS, $ROLES;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
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

    echo fill_template("tmp/roles.template", "roles_manager_template", false, $params);
}
?>