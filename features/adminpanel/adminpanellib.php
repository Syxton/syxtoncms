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
    $abilities = user_abilities($USER->userid, $pageid, "roles");

    // ---- System links (always visible) ----
    $system_links = "";

    // File Manager
    $p = [
        "title" => "Manage files",
        "text" => "Manage files",
        "onclick" => "window.open('./scripts/tinymce/plugins/filemanager/index.php?standalone=1&pageid=$pageid&userid=$USER->userid','File Mananger','location=no,toolbar=no,menubar=no,scrollbars=yes,resizable=yes')",
        "icon" => icon("laptop-file"),
        "class" => "adminpanel_links",
    ];
    $system_links .= user_is_able($USER->userid, "manage_files", $pageid) ? make_modal_links($p) : "";

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
    $system_links .= !empty($abilities->edit_roles->allow) || !empty($abilities->assign_roles->allow) || !empty($abilities->edit_user_abilities->allow) ? make_modal_links($p) : "";

    // Site Admin Area
    if (is_siteadmin($USER->userid)) {
        $p = [
            "title" => "Admin Area",
            "text" => "Admin Area",
            "path" => action_path("adminpanel") . "site_administration&pageid=$pageid",
            "iframe" => true,
            "width" => "95%",
            "height" => "95%",
            "icon" => icon("screwdriver-wrench"),
            "class" => "adminpanel_links",
        ];
        $system_links .= user_is_able($USER->userid, "addevents", $pageid) ? make_modal_links($p) : "";
    }

    // ---- Feature groups (collapsible) ----
    $feature_groups = [];
    $directory = $CFG->dirroot . "/features";
    if ($handle = opendir($directory)) {
        $dirs = [];
        while (false !== ($dir = readdir($handle))) {
            if ($dir === '.' || $dir === '..' || $dir === 'adminpanel') {
                continue;
            }
            if (!strstr($dir, ".") && is_dir($directory . "/" . $dir)) {
                $dirs[] = $dir;
            }
        }
        closedir($handle);
        sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($dirs as $dir) {
            $lib = $directory . "/" . $dir . "/" . $dir . "lib.php";
            if (!file_exists($lib)) {
                continue;
            }
            include_once($lib);
            $action = $dir . "_adminpanel";
            if (!function_exists($action)) {
                continue;
            }
            $feature_html = $action($pageid);
            if (trim((string)$feature_html) === "") {
                continue;
            }
            $feature_groups[$dir] = $feature_html;
        }
    }

    $content = adminpanel_render_groups($system_links, $feature_groups);

    $buttons = get_button_layout("adminpanel", $featureid, $pageid);
    $title = '<span class="box_title_text">' . $title . '</span>';
    $returnme = $content != "" ? get_css_box($title, $content, $buttons, NULL, "adminpanel", $featureid) : "";

    return $returnme;
}

/**
 * Friendly display names for known feature folders.
 */
function adminpanel_feature_label($dir) {
    static $labels = [
        'events'       => 'Events',
        'bloglocker'   => 'Blog Locker',
        'calendar'     => 'Calendar',
        'chat'         => 'Chat',
        'donate'       => 'Donate',
        'forum'        => 'Forum',
        'html'         => 'HTML',
        'news'         => 'News',
        'onlineusers'  => 'Online Users',
        'participants' => 'Participants',
        'pics'         => 'Pics',
    ];
    if (isset($labels[$dir])) {
        return $labels[$dir];
    }
    return ucwords(str_replace(['_', '-'], ' ', $dir));
}

/**
 * Render System (always open) + feature collapsible groups.
 */
function adminpanel_render_groups($system_links, $feature_groups) {
    $html = '
<style>
.ap-groups { display:flex; flex-direction:column; gap:8px; }
.ap-group {
    border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;
    overflow:hidden;
}
.ap-group-summary {
    list-style:none; cursor:pointer; user-select:none;
    display:flex; align-items:center; gap:8px;
    padding:8px 12px; font-weight:600; color:#1e293b;
    background:#f1f5f9;
}
.ap-group-summary::-webkit-details-marker { display:none; }
.ap-group-summary::before {
    content:""; display:inline-block; width:0; height:0;
    border-left:5px solid #64748b; border-top:4px solid transparent; border-bottom:4px solid transparent;
    transition: transform .15s ease;
}
.ap-group[open] > .ap-group-summary::before { transform: rotate(90deg); }
.ap-group-summary .ap-count {
    margin-left:auto; font-weight:500; font-size:.75em; color:#64748b;
    background:#e2e8f0; border-radius:999px; padding:1px 8px;
}
.ap-group-body { padding:6px 10px 10px; background:#fff; }
.ap-group-system { border-color:#cbd5e1; }
.ap-group-system > .ap-group-summary { background:#e2e8f0; cursor:default; }
.ap-group-system > .ap-group-summary::before { display:none; }
</style>
<div class="ap-groups">';

    if (trim((string)$system_links) !== "") {
        // System is always expanded (not collapsible).
        $html .= '
    <div class="ap-group ap-group-system">
        <div class="ap-group-summary">System</div>
        <div class="ap-group-body">' . $system_links . '</div>
    </div>';
    }

    foreach ($feature_groups as $dir => $links) {
        $label = htmlspecialchars(adminpanel_feature_label($dir));
        // Count link-like children roughly by adminpanel_links class occurrences
        $count = substr_count($links, 'adminpanel_links');
        $count_html = $count > 0 ? '<span class="ap-count">' . $count . '</span>' : '';
        $html .= '
    <details class="ap-group">
        <summary class="ap-group-summary">' . $label . $count_html . '</summary>
        <div class="ap-group-body">' . $links . '</div>
    </details>';
    }

    $html .= '
</div>';

    return $html;
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
                        $buttons =
                        button_maker([
                            "class" => "alike",
                            "onclick" => "allow_page_request(" . $request["assignmentid"] . ", 1, 'userspan_" . $request["userid"] . "_" . $request["pageid"] . "');",
                            "content" => icon("thumbs-up", 2),
                        ]) .
                        button_maker([
                            "class" => "alike",
                            "onclick" => "allow_page_request(" . $request["assignmentid"] . ", 0, 'userspan_" . $request["userid"] . "_" . $request["pageid"] . "');",
                            "content" => icon("thumbs-down", 2),
                        ]);
                        $alerts_rows .= fill_template("tmp/pagelib.template", "user_alerts_row", false, ["question" => $question, "buttons" => $buttons]);
                    }
                }
            }
        }

        $params = [
            "title" => "Page permission requests",
            "alerts_rows" => $alerts_rows,
        ];
        $display_alerts .= fill_template("tmp/pagelib.template", "user_alerts_group", false, $params);
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
                $buttons =
                button_maker([
                    "class" => "alike",
                    "onclick" => "allow_page_request(" . $invite["assignmentid"] . ", 1, 'pagespan_" . $invite["userid"] . "_" . $invite["pageid"] . "');",
                    "content" => icon("thumbs-up", 2),
                ]) .
                button_maker([
                    "class" => "alike",
                    "onclick" => "allow_page_request(" . $invite["assignmentid"] . ", 0, 'pagespan_" . $invite["userid"] . "_" . $invite["pageid"] . "');",
                    "content" => icon("thumbs-down", 2),
                ]);
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
            "event" => "none",
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
