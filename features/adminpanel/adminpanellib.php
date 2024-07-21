<?php
/***************************************************************************
* adminpanellib.php - Admin Panel function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.7.10
***************************************************************************/

if (!LIBHEADER) {
    $sub = './';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == './' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('ADMINPANELLIB', true);

function display_adminpanel($pageid, $area, $featureid) {
global $CFG, $USER, $ROLES, $ABILITIES;

    if (!$settings = fetch_settings("adminpanel", $featureid, $pageid)) {
        save_batch_settings(default_settings("adminpanel", $pageid, $featureid));
        $settings = fetch_settings("adminpanel", $featureid, $pageid);
    }

    $title = $settings->adminpanel->$featureid->feature_title->setting;
    $content = "";
    $site = $pageid == $CFG->SITEID ? "Site " : "Page ";
    $abilities = user_abilities($USER->userid, $pageid,"roles");

    // File Manager
    $p = [
        "title" => "Manage files",
        "text" => "Manage files",
        "onclick" => "window.open('./scripts/tinymce/plugins/filemanager/dialog.php?type=0&editor=mce_0/','File Mananger','modal, width=850, height=600')",
        "icon" => icon("laptop-file"),
        "class" => "adminpanel_links",
    ];
    $content .= user_is_able($USER->userid, "manage_files", $pageid) ? make_modal_links($p) : "";

    // Roles & Abilities Manager
    $p = [
        "title" => "Roles & Abilites Manager",
        "text" => "Roles & Abilites Manager",
        "path" => $CFG->wwwroot . "/pages/roles.php?action=manager&pageid=$pageid",
        "width" => "700",
        "height" => "600",
        "iframe" => true,
        "icon" => icon("key"),
        "class" => "adminpanel_links",
    ];
    $content .= !empty($abilities->edit_roles->allow) || !empty($abilities->assign_roles->allow) || !empty($abilities->edit_user_abilities->allow) ? make_modal_links($p) : "";

    // Site Admin Area
    if (is_siteadmin($USER->userid)) {
        $p = [
            "title" => "Admin Area",
            "text" => "Admin Area",
            "path" => action_path("adminpanel") . "site_administration&pageid=$pageid",
            "iframe" => true,
            "width"=> "95%",
            "height"=> "95%",
            "icon" => icon("screwdriver-wrench"),
            "class" => "adminpanel_links",
        ];
        $content .= user_is_able($USER->userid, "addevents", $pageid) ? make_modal_links($p) : "";
    }

    $directory = $CFG->dirroot . "/features";
    if ($handle = opendir($directory)) {
        /* This is the correct way to loop over the directory. */
        while (false !== ($dir = readdir($handle))) {
            if (!strstr($dir,".") && is_dir($directory . "/" . $dir)) {
                include_once($directory . "/" . $dir . '/' . $dir . "lib.php");
                $action = $dir . "_adminpanel";
                if (function_exists("$action")) {
                    $content .= $action($pageid);
                }
            }
        }
        // Close the directory handler
        closedir($handle);
    }

    $buttons = get_button_layout("adminpanel", $featureid, $pageid);
    $title = '<span class="box_title_text">' . $title . '</span>';
    $returnme = $content != "" ? get_css_box($title, $content, $buttons, NULL, "adminpanel", $featureid) : "";

    return $returnme;
}

function adminpanel_delete($pageid, $featureid) {
    $params = [
        "pageid" => $pageid,
        "featureid" => $featureid,
        "feature" => "adminpanel",
    ];

    execute_db_sql(fetch_template("dbsql/features.sql", "delete_feature"), $params);
    execute_db_sql(fetch_template("dbsql/features.sql", "delete_feature_settings"), $params);

    resort_page_features($pageid);
}

function adminpanel_buttons($pageid, $featuretype, $featureid) {
    return "";
}

function get_adminpanel_alerts($userid, $countonly = true) {
    $alerts = 0;
    $display_alerts = "";

    // This section creates alerts for users who have requested entry into a page that the user has rights to add them to.
    if ($pages = pages_user_is_able($userid, "assign_roles")) {
        $alerts_rows = "";
        while ($page = fetch_row($pages)) {
            $SQL = fetch_template("dbsql/roles.sql", "get_page_role_requests");
            if ($result = get_db_result($SQL, ["pageid" => $page["pageid"]])) {
                $alerts += count_db_result($result);
                if (!$countonly) {
                    // Loops through all requests from a page.
                    while ($request = fetch_row($result)) {
                        $question = 'Allow ' . get_user_name($request["userid"]) . " into " . get_db_field("name", "pages", "pageid=" . $request["pageid"]) . '?';
                        $buttons = '
                            <button class="alike" onclick="allow_page_request(' . $request["assignmentid"] . ', 1, \'userspan_' . $request["userid"] . '_' . $request["pageid"] . '\');">
                                ' . icon("thumbs-up", 2) . '
                            </button>
                            <button class="alike" onclick="allow_page_request(' . $request["assignmentid"] . ', 0, \'userspan_' . $request["userid"] . '_' . $request["pageid"] . '\');">
                                ' . icon("thumbs-down", 2) . '
                            </button>';
                        $alerts_rows .= fill_template("tmp/pagelib.template", "user_alerts_row", false, ["question" => $question, "buttons" => $buttons]);
                    }
                    $params = [
                        "title" => "Page permission requests",
                        "alerts_rows" => $alerts_rows,
                    ];
                    $display_alerts .= fill_template("tmp/pagelib.template", "user_alerts_group", false, $params);
                }
            }
        }
    }

    // This section creates alerts for invites that have been recieved by the user.
    $SQL = fetch_template("dbsql/roles.sql", "get_user_role_requests");
    if ($result = get_db_result($SQL, ["userid" => $userid])) {
        $alerts += count_db_result($result);
        if (!$countonly) {
            $alerts_rows = "";
            // Loops through all requests from a page.
            while ($invite = fetch_row($result)) {
                $question = 'Allow ' . get_user_name($request["userid"]) . " into " . get_db_field("name", "pages", "pageid=" . $invite["pageid"]) . '?';
                $buttons = '
                    <button class="alike" onclick="allow_page_request(' . $invite["assignmentid"] . ', 1, \'pagespan_' . $invite["userid"] . '_' . $invite["pageid"] . '\');">
                        ' . icon("thumbs-up", 2) . '
                    </button>
                    <button class="alike" onclick="allow_page_request(' . $invite["assignmentid"] . ', 0, \'pagespan_' . $invite["userid"] . '_' . $invite["pageid"] . '\');">
                        ' . icon("thumbs-down", 2) . '
                    </button>';
                $alerts_rows .= fill_template("tmp/pagelib.template", "user_alerts_row", false, ["question" => $question, "buttons" => $buttons]);
            }

            $params = [
                "title" => "Page invitations for you",
                "alerts_rows" => $alerts_rows,
            ];
            $display_alerts .= fill_template("tmp/pagelib.template", "user_alerts_group", false, $params);
        }
    }

    // if you only want the count of alerts, we know that number now.
    if ($countonly) {
        return $alerts;
    }

    if ($alerts) {
        ajaxapi([
            "id" => "refresh_user_alerts",
            "url" => "/ajax/site_ajax.php",
            "paramlist" => "requestid, approve",
            "data" => [
                "action" => "refresh_user_alerts",
                "userid" => $userid,
                "approve" => "js||approve||js",
            ],
            "event" => "none",
            "display" => "user_alerts_div",
            "ondone" => "getRoot()[0].update_alerts();",
        ]);

        ajaxapi([
            "id" => "allow_page_request",
            "url" => "/ajax/site_ajax.php",
            "paramlist" => "requestid, approve, display",
            "data" => [
                "action" => "allow_page_request",
                "requestid" => "js||requestid||js",
                "approve" => "js||approve||js",
            ],
            "ondone" => "if (istrue(data)) { refresh_user_alerts(); }",
        ]);
    }

    return empty($display_alerts) ? false : $display_alerts;
}

function adminpanel_default_settings($type, $pageid, $featureid) {
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "Admin Panel",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}
?>
