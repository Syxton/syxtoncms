<?php
/***************************************************************************
* donatelib.php - donate feature library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.1.5
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('DONATELIB', true);

function display_donate($pageid, $area, $featureid) {
global $CFG, $USER, $donateSETTINGS;
    $abilities = user_abilities($USER->userid, $pageid,"donate", "donate", $featureid);
    if (!$settings = fetch_settings("donate", $featureid, $pageid)) {
        save_batch_settings(default_settings("donate", $pageid, $featureid));
        $settings = fetch_settings("donate", $featureid, $pageid);
    }

    if (!empty($abilities->makedonation->allow)) {
        return get_donate($pageid, $featureid, $settings, $abilities, $area);
    }
}

function get_donate($pageid, $featureid, $settings, $abilities, $area=false, $resultsonly=false) {
global $CFG, $USER;
    $returnme = "";
    if ($result = get_db_result(fetch_template("dbsql/donate.sql", "get_donate_instance", "donate"), ["donate_id" => $featureid])) {
        while ($row = fetch_row($result)) {
         // if viewing from rss feed
            if ($resultsonly) {
                $returnme .= '<table style="width:100%;border:1px solid silver;padding:10px;">
                                             <tr>
                                                <th>' . $settings->donate->$featureid->feature_title->setting . '</th>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <br /><br />
                                                    <div class="donationblock">
                                                    ' . get_donation_results($row["id"]) . '
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>';
            } else { //regular donate feature viewing
                $buttons = get_button_layout("donate", $featureid, $pageid);
                $title = $settings->donate->$featureid->feature_title->setting;
                $title = '<span class="box_title_text">' . $title . '</span>';
                $returnme .= get_css_box($title, '<div class="donationblock">' . donation_form($featureid, $settings) . '</div>', $buttons, null, 'donate', $featureid, false, false, false, false, false, false);
            }
        }
    }
    return $returnme;
}

function donation_form($featureid, $settings) {
global $CFG;
    $return = "";

    $protocol = get_protocol();
    if ($campaign = get_db_row(fetch_template("dbsql/donate.sql", "get_donation_campaign", "donate"), ["donate_id" => $featureid])) {
        if ($CFG->paypal) {
            $paypal = 'www.paypal.com';
        } else {
            $paypal = 'www.sandbox.paypal.com';
        }

        if ($donations = get_db_row(fetch_template("dbsql/donate.sql", "get_campaign_donations_total", "donate"), ["campaignid" => $campaign["campaign_id"]])) {
            $total = $donations["total"];
            $total = empty($total) ? "0" : $total;
        }

        $return .= get_css_tags(["features/donate/donate.css"]);
        $return .= get_js_tags(["features/donate/donate.js"]);
        $params = [
            "campaign" => $campaign,
            "paypal" => $paypal,
            "wwwroot" => $protocol . $CFG->wwwroot,
        ];
        $button = fill_template("tmp/donate.template", "give_button", "donate", $params);
        $return .= donate_meter($campaign, $total, $button, $settings->donate->$featureid->metertype->setting);
    } else { // Not setup yet
        $return .= 'You must first setup a donation campaign.<br />';
    }

    return $return;
}

function donate_meter($campaign, $total, $button, $type = "horizontal") {
    if ($campaign["metgoal"] == 1 || (round($total / $campaign["goal_amount"],2) * 100) > 100) {
        $perc = "100";
    } else {
        $perc = round($total / $campaign["goal_amount"],2) * 100;
    }

    $params = [
        "title" => $campaign["title"],
        "goal_amount" => $campaign["goal_amount"],
        "goal_description" => $campaign["goal_description"],
        "total" => $total,
        "perc" => $perc
    ];

    switch ($type) {
        case "vertical":
            $return = fill_template("tmp/donate.template", "verticalmeter", "donate", $params);
            break;
        case "horizontal":
            $return = fill_template("tmp/donate.template", "horizontalmeter", "donate", $params);
            break;
    }

    $return .= $button;
    $return .= js_code_wrap('thermometer("thermometer");', "", true);

    return $return;
}

function insert_blank_donate($pageid, $settings = false) {
global $CFG;
    $type = "donate";
    try {
        start_db_transaction();
        if ($featureid = execute_db_sql(fetch_template("dbsql/donate.sql", "insert_donate_instance", $type), ["campaign_id" => 0])) {
            $area = get_db_field("default_area", "features", "feature = ||feature||", ["feature" => $type]);
            $sort = get_db_count(fetch_template("dbsql/features.sql", "get_features_by_page_area"), ["pageid" => $pageid, "area" => $area]) + 1;
            $params = [
                    "pageid" => $pageid,
                    "feature" => $type,
                    "featureid" => $featureid,
                    "sort" => $sort,
                    "area" => $area,
            ];
            execute_db_sql(fetch_template("dbsql/features.sql", "insert_page_feature"), $params);
            commit_db_transaction();
            return $featureid;
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
    return false;
}

function donate_delete($pageid, $featureid) {
    $params = [
        "pageid" => $pageid,
        "featureid" => $featureid,
        "feature" => "donate",
    ];

    try {
        start_db_transaction();
        execute_db_sql(fetch_template("dbsql/features.sql", "delete_feature"), $params);
        execute_db_sql(fetch_template("dbsql/features.sql", "delete_feature_settings"), $params);
        execute_db_sql(fetch_template("dbsql/donate.sql", "delete_donate_instance", "donate"), $params);
        resort_page_features($pageid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        return false;
    }
}

function donate_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
    $settings = fetch_settings("donate", $featureid, $pageid);
    $returnme = "";

    $donate_abilities = user_abilities($USER->userid, $pageid,"donate", "donate", $featureid);
    $feature_abilities = user_abilities($USER->userid, $pageid,"features", "donate", $featureid);

    $campaign = get_db_row(fetch_template("dbsql/donate.sql", "get_donation_campaign", "donate"), ["donate_id" => $featureid]);
    $edit = get_db_row(fetch_template("dbsql/donate.sql", "get_donation_instance_if_owner_of_campaign", "donate"), ["donate_id" => $featureid, "origin_page" => $pageid]) ? true : false;
    if ($campaign && $edit && $donate_abilities->adddonation->allow) {
        $p = [
            "title" => "Manage Donations",
            "path" => action_path("donate") . "managedonations&pageid=$pageid&featureid=$featureid",
            "refresh" => "true",
            "iframe" => true,
            "validate" => "true",
            "width" => "800",
            "icon" => icon("sack-dollar"),
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($p);
    }

    if ($donate_abilities->managedonation->allow) {
        $p = [
            "title" => "Campaign Settings",
            "path" => action_path("donate") . "editcampaign&pageid=$pageid&featureid=$featureid",
            "refresh" => "true",
            "iframe" => true,
            "validate" => "true",
            "width" => "750",
            "height" => "600",
            "icon" => icon("pencil"),
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($p);
    }
    return $returnme;
}

function select_campaign_forms($featureid, $pageid) {
    $campaign_id = $isjoined = false;
    if ($edit = get_db_row(fetch_template("dbsql/donate.sql", "get_donation_instance_if_owner_of_campaign", "donate"), ["donate_id" => $featureid, "origin_page" => $pageid])) {
        $campaign_id = $edit["campaign_id"];
    } elseif ($joined = get_db_row(fetch_template("dbsql/donate.sql", "get_donation_instance_if_joined_to_campaign", "donate"), ["donate_id" => $featureid])) {
        $isjoined = true;
        $campaign_id = $joined["campaign_id"];
    }

    ajaxapi([
        "id" => "new_campaign_form",
        "paramlist" => "donationid",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "new_campaign_form",
            "featureid" => $featureid,
            "campaign_id" => $campaign_id,
        ],
        "display" => "donation_display",
        "ondone" => "loaddynamicjs('donation_script');",
    ]);

    ajaxapi([
        "id" => "join_campaign_form",
        "paramlist" => "donationid",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "join_campaign_form",
            "featureid" => $featureid,
        ],
        "display" => "donation_display",
    ]);

    $params = [
        "campaign_id" => $campaign_id,
        "isjoined" => $isjoined,
        "name" => ($campaign_id ? get_db_field("title", "donate_campaign", "campaign_id='" . $campaign_id . "'") : ""),
    ];
    $return = fill_template("tmp/donate.template", "get_campaign_forms", "donate", $params);
    return $return;
}

function add_or_manage_forms($featureid, $pageid) {
global $CFG, $USER;
    ajaxapi([
        "id" => "add_offline_donations_form",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "add_offline_donations_form",
            "featureid" => $featureid,
        ],
        "display" => "donation_display",
        "ondone" => "loaddynamicjs('donation_script');",
    ]);

    ajaxapi([
        "id" => "manage_donations_form",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "manage_donations_form",
            "featureid" => $featureid,
        ],
        "display" => "donation_display",
    ]);

    return fill_template("tmp/donate.template", "add_or_manage_forms", "donate");
}

function donate_default_settings($type, $pageid, $featureid) {
global $CFG;
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "Donate",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "defaultsetting" => "horizontal",
            "display" => "Thermometer Orientation",
            "setting_name" => "metertype",
            "inputtype" => "select_array",
            "extraforminfo" => [
                ["selectvalue" => "horizontal", "selectname" => "Horizontal"],
                ["selectvalue" => "vertical", "selectname" => "Vertical"],
            ],
            "numeric" => null,
            "validation" => null,
            "warning" => "Select the orientation of the donation thermometer.",
        ],
        [
            "setting_name" => "enablerss",
            "defaultsetting" => "0",
            "display" => "Enable RSS",
            "inputtype" => "yes/no",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}
?>