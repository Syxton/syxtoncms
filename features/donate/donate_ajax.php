<?php
/***************************************************************************
* donate_ajax.php - donate feature ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.0.7
***************************************************************************/

if (!isset($CFG)) {
    $sub = '';
    while (!file_exists($sub . 'header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'header.php');
}

if (!defined('DONATELIB')) { include_once($CFG->dirroot . '/features/donate/donatelib.php'); }

update_user_cookie();

callfunction();

function select_campaign_form() {
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    ajax_return(select_campaign_forms($featureid, $pageid));
}

function add_or_manage_form() {
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    ajax_return(add_or_manage_forms($featureid, $pageid));
}

function new_campaign_form() {
global $CFG;
    $featureid = clean_myvar_req("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $campaign_id = clean_myvar_opt("campaign_id", "int", false);

    if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

    $title = $goal = $description = $email = $token = $button = $yes_selected = $no_selected = "";
    if ($campaign_id) { // Editing a campaign.
        if ($c = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id = ||campaign_id||", ["campaign_id" => $campaign_id])) {
            $button = "Edit";
            $title = $c["title"];
            $goal = number_format($c["goal_amount"], 2, ".", "");
            $description = $c["goal_description"];
            $email = $c["paypal_email"];
            $token = $c["token"];
            $no_selected = $c["shared"] == "1" ? "" : "selected";
            $yes_selected =$c["shared"] == "1" ? "selected" : "";
        }
    } else {
        $button = "Start";
    }

    ajaxapi([
        "id" => "back_to_donate_$featureid",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "select_campaign_form",
            "featureid" => $featureid,
        ],
        "display" => "donation_display",
    ]);
    $back = fill_template("tmp/donate.template", "back_to_donate", "donate", ["featureid" => $featureid]);

    ajaxapi([
        "id" => "new_campaign_$featureid",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "add_new_campaign",
            "featureid" => $featureid,
            "campaign_id" => $campaign_id,
            "email" => "js||encodeURIComponent($('#email').val())||js",
            "token" => "js||encodeURIComponent($('#token').val())||js",
            "title" => "js||encodeURIComponent($('#title').val())||js",
            "goal" => "js||encodeURIComponent($('#goal').val())||js",
            "description" => "js||encodeURIComponent($('#description').val())||js",
            "shared" => "js||encodeURIComponent($('#shared').val())||js",
        ],
        "display" => "new_campaign_div",
        "event" => "none",
    ]);

    $params = [
        "title" => $title,
        "goal" => $goal,
        "description" => $description,
        "email" => $email,
        "token" => $token,
        "noselected" => $no_selected,
        "yesselected" => $yes_selected,
        "button" => $button,
        "validationscript" => create_validation_script("campaign_form", "new_campaign_$featureid()", true),
    ];
    $content = fill_template("tmp/donate.template", "add_edit_form", "donate", $params);
    $return = format_popup($content, "Start a Donation Campaign", "auto", "0", $back);
    ajax_return($return);
}

function add_new_campaign() {
global $CFG, $USER;
    $featureid = clean_myvar_opt("featureid", "int", false);
    $campaign_id = clean_myvar_opt("campaign_id", "int", false);
    $goal = clean_myvar_opt("goal", "float", 0.00);
    $description = clean_myvar_opt("description", "string", "");
    $email = clean_myvar_opt("email", "string", "");
    $token = clean_myvar_opt("token", "string", "");
    $title = clean_myvar_opt("title", "string", "");
    $shared = clean_myvar_opt("shared", "int", 0);
    $pageid = get_pageid();

    $params = ["pageid" => $pageid, "title" => $title, "goal" => $goal, "description" => $description, "email" => $email, "token" => $token, "shared" => $shared, "datestarted" => get_timestamp(), "metgoal" => 0];
    $return = $error = "";
    try {
        start_db_transaction();
        if ($campaign_id) { // UPDATE
            $params["campaign_id"] = $campaign_id;
            $SQL = fetch_template("dbsql/donate.sql", "update_campaign", "donate");
            if (execute_db_sql($SQL, $params)) { // Edit made
                $return = "<h1>Campaign Edited</h1>";
            } else {
                throw new Exception("An error has occurred, please try again later.");
            }
        } else { // INSERT NEW
            $SQL = fetch_template("dbsql/donate.sql", "insert_campaign", "donate");
            if ($campaign_id = execute_db_sql($SQL, $params)) { //New campaign made
                //Save campaign ID in instance
                execute_db_sql(fetch_template("dbsql/donate.sql", "save_campaignid", "donate"), ["campaign_id" => $campaign_id, "donate_id" => $featureid]);
                $return = "<h1>Campaign Started</h1>";
            } else {
                throw new Exception("An error has occurred, please try again later.");
            }
        }
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }
    ajax_return($return, $error);
}


function join_campaign_form() {
global $CFG, $MYVARS, $USER;
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    $content = 'There are no active campaigns available.';
    $SQL = fetch_template("dbsql/donate.sql", "get_shared_campaigns", "donate");
    if ($result = get_db_result($SQL, ["pageid" => $pageid])) {
        ajaxapi([
            "id" => "join_campaign_$featureid",
            "url" => "/features/donate/donate_ajax.php",
            "data" => [
                "action" => "join_campaign",
                "featureid" => $featureid,
                "campaign_id" => "js||encodeURIComponent($('#campaign_id').val())||js",
            ],
            "display" => "donation_display",
            "event" => "none",
        ]);

        $selectparams = [
            "properties" => [
                "id" => "campaign_id",
            ],
            "values" => $result,
            "valuename" => "campaign_id",
            "displayname" => "title",
        ];
        $params = [
            "featureid" => $featureid,
            "select" => make_select($selectparams),
        ];
        $content = fill_template("tmp/donate.template", "join_campaign_form_select", "donate", $params);
    }

    ajaxapi([
        "id" => "back_to_donate_$featureid",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "select_campaign_form",
            "featureid" => $featureid,
        ],
        "display" => "donation_display",
    ]);
    $back = fill_template("tmp/donate.template", "back_to_donate", "donate", ["featureid" => $featureid]);

    $params = [
        "back" => $back,
        "heading" => "Join a Campaign",
        "content" => $content,
    ];
    $return = fill_template("tmp/donate.template", "join_campaign_form", "donate", $params);
    ajax_return($return);
}

function join_campaign() {
    $featureid = clean_myvar_opt("featureid", "int", false);
    $campaign_id = clean_myvar_opt("campaign_id", "int", false);

    $return = $error = "";
    try {
        if ($campaign_id) { // Campaign ID chosen
            // Save campaign ID in instance
            $SQL = fetch_template("dbsql/donate.sql", "save_campaignid", "donate");
            execute_db_sql($SQL, ["campaign_id" => $campaign_id, "donate_id" => $featureid]);
            $return = '
                <h1>Campaign Joined</h1>
                You can now accept donations for your chosen campaign.';
        } else {
            $return = "Could not join campaign.";
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return);
}

function add_offline_donations_form() {
global $CFG;
    if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    ajaxapi([
        "id" => "add_or_manage_form_$featureid",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "add_offline_donation",
            "featureid" => $featureid,
            "amount" => "js||encodeURIComponent($('#amount').val())||js",
            "name" => "js||encodeURIComponent($('#name').val())||js",
        ],
        "display" => "donation_display",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "back_to_donate_$featureid",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "add_or_manage_form",
            "featureid" => $featureid,
            "campaign_id" => "js||encodeURIComponent($('#campaign_id').val())||js",
        ],
        "display" => "donation_display",
    ]);
    $back = fill_template("tmp/donate.template", "back_to_donate", "donate", ["featureid" => $featureid]);

    $params = [
        "validation" => create_validation_script("donation_form" , "add_or_manage_form_$featureid();", true),
    ];
    $content = fill_template("tmp/donate.template", "offline_donation_form", "donate", $params);

    $return = format_popup($content, "Start a Donation Campaign", "auto", "0", $back);

    ajax_return($return);
}

function add_offline_donation() {
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $name = clean_myvar_opt("name", "string", "Anonymous");
    $amount = number_format(clean_myvar_opt("amount", "float", 0.00), 2, ".", "");

    $return = $error = "";
    try {
        $campaign = get_db_row(fetch_template("dbsql/donate.sql", "get_donation_campaign", "donate"), ["donate_id" => $featureid]);
        $params = [];
        $params["campaign_id"] = $campaign["campaign_id"];
        $params["name"] = $name;
        $params["amount"] = $amount;
        $params["paypal_TX"] = 'Offline';
        $params["timestamp"] = get_timestamp();
        execute_db_sql(fetch_template("dbsql/donate.sql", "insert_donation", "donate"), $params);
        $return = add_or_manage_forms($featureid, $pageid);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function manage_donations_form() {
global $CFG, $MYVARS, $USER;
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    $campaign = get_db_row(fetch_template("dbsql/donate.sql", "get_donation_campaign", "donate"), ["donate_id" => $featureid]);
    $SQL = fetch_template("dbsql/donate.sql", "get_campaign_donations", "donate");
    if ($result = get_db_result($SQL, ["campaign_id" => $campaign["campaign_id"]])) {
        // Edit Donation action.
        ajaxapi([
            "id" => "edit_donation_form",
            "paramlist" => "donationid",
            "url" => "/features/donate/donate_ajax.php",
            "data" => [
                "action" => "edit_donation_form",
                "featureid" => $featureid,
                "donationid" => "js||donationid||js",
            ],
            "display" => "donation_display",
            "ondone" => "loaddynamicjs('donation_script');",
            "event" => "none",
        ]);

        // Delete Donation action.
        ajaxapi([
            "id" => "delete_donation_form",
            "paramlist" => "donationid",
            "if" => "confirm('Are you sure you want to delete this donation record?')",
            "url" => "/features/donate/donate_ajax.php",
            "data" => [
                "action" => "delete_donation",
                "featureid" => $featureid,
                "donationid" => "js||donationid||js",
            ],
            "display" => "donation_display",
            "event" => "none",
        ]);

        $donations = '';
        while ($row = fetch_row($result)) {
            $type = $row["paypal_TX"] == "Offline" ? "Offline" : "Paypal";
            $tx = $row["paypal_TX"] == "Offline" ? "--" : $row["paypal_TX"];
            $name = $row["name"] == "" ? "Anonymous" : $row["name"];

            $params = [
                "donationid" => $row["donationid"],
                "name" => $name,
                "amount" => number_format($row["amount"], 2, ".", ""),
                "time" => date('m/d/Y', $row["timestamp"] + get_offset()),
                "type" => $type,
                "tx" => $tx
            ];
            $donations .= fill_template("tmp/donate.template", "donation_row", "donate", $params);
        }
        $content = fill_template("tmp/donate.template", "donations_table", "donate", ["donations" => $donations]);
    } else {
        $content = 'No donations have been made yet . ';
    }

    // Back button action.
    ajaxapi([
        "id" => "back_to_donate_$featureid",
        "url" => "/features/donate/donate_ajax.php",
        "data" => [
            "action" => "add_or_manage_form",
            "featureid" => $featureid,
        ],
        "display" => "donation_display",
    ]);
    $back = fill_template("tmp/donate.template", "back_to_donate", "donate", ["featureid" => $featureid]);

    $return = format_popup($content, 'Manage Donations', "auto", "0", $back);

    ajax_return($return);
}

function edit_donation_form() {
global $CFG, $USER;
    $donationid = clean_myvar_req("donationid", "int");
    $featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    $return = $error = '';
    try {
        if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

        $donation = get_db_row(fetch_template("dbsql/donate.sql", "get_donation", "donate"), ["donationid" => $donationid]);
        $options = '';
        $SQL = fetch_template("dbsql/donate.sql", "get_donation_campaigns", "donate");
        if ($result = get_db_result($SQL, ["campaign_id" => $donation["campaign_id"]])) {
            $selected = $donation["campaign_id"];
            while ($c = fetch_row($result)) {
                $select = $selected == $c["campaign_id"] ? "selected" : "";
                $options .= '<option value="' . $c["campaign_id"] . '" ' . $select . '>' . $c["title"] . '</option>';
            }
        }

        // Back button action.
        ajaxapi([
            "id" => "manage_donations_form",
            "url" => "/features/donate/donate_ajax.php",
            "data" => [
                "action" => "manage_donations_form",
                "featureid" => $featureid,
            ],
            "display" => "donation_display",
        ]);

        // Save Donation action.
        ajaxapi([
            "id" => "edit_donation_save",
            "url" => "/features/donate/donate_ajax.php",
            "data" => [
                "action" => "edit_donation_save",
                "featureid" => $featureid,
                "donationid" => $donationid,
                "amount" => "js||encodeURIComponent($('#amount').val())||js",
                "name" => "js||encodeURIComponent($('#name').val())||js",
                "campaign_id" => "js||encodeURIComponent($('#campaign_id').val())||js",
                "paypal_TX" => "js||encodeURIComponent($('#paypal_TX').val())||js",
                "amount" => "js||encodeURIComponent($('#amount').val())||js",
            ],
            "display" => "donation_display",
            "event" => "none",
        ]);

        $params = [
            "options" => $options,
            "amount" => number_format($donation["amount"], 2, ".", ""),
            "name" => $donation["name"],
            "tx" => $donation["paypal_TX"],
        ];

        $return = '
            <button id="manage_donations_form" style="position: absolute;">
                Back
            </button>
            <div id="donation_script" style="display:none">
                ' . create_validation_script("donation_form" , "edit_donation_save();", true) . '
            </div>';
        $return .= format_popup(fill_template("tmp/donate.template", "edit_donation_form", "donate", $params), "Edit Donation", "auto", "0");
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function edit_donation_save() {
    try {
        $params = [
            "amount" => clean_myvar_opt("amount", "float", 0.00),
            "name" => clean_myvar_opt("name", "string", "Anonymous"),
            "paypal_TX" => clean_myvar_opt("paypal_TX", "string", "Offline"),
            "campaign_id" => clean_myvar_opt("campaign_id", "int", false),
            "donationid" => clean_myvar_req("donationid", "int"),
        ];
        execute_db_sql(fetch_template("dbsql/donate.sql", "update_donation", "donate"), $params);
        manage_donations_form();
    } catch (\Throwable $e) {
        ajax_return("", $e->getMessage());
    }
}

function delete_donation() {
    $donationid = clean_myvar_req("donationid", "int");

    try {
        execute_db_sql(fetch_template("dbsql/donate.sql", "delete_donation", "donate"), ["donationid" => $donationid]);
        manage_donations_form();
    } catch (\Throwable $e) {
        ajax_return("", $e->getMessage());
    }
}
?>