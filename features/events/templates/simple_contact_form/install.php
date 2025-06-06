<?php
/***************************************************************************
 * install.php - reg page installer
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 02/17/2012
 * $Revision: 0.0.5
 ***************************************************************************/
//Form name:Section:Title
$thisversion = 2024072500;
$templatename = 'Simple Contact Form';
$templatefolder = 'simple_contact_form';
$registrant_name = 'Name';
$orderbyfield = 'Name';

$formlist = "
Name:Info:Name;
Name_First:Info:First Name;
Name_Last:Info:Last Name;
Address_Line1:Info:Address 1;
Address_Line2:Info:Address 2;
Address_City:Info:City;
Address_State:Info:State;
Address_Zipcode:Info:Zipcode;
Phone:Info:Phone;
Overnight:Info:Overnight;
Gender:Info:Gender;
total_owed:Pay:Amount Owed;
paid:Pay:Amount Paid";

$settings = [
    [
        'setting_name' => 'template_setting_overnight',
        'display'=> 'Overnight Option',
        'inputtype' => 'yes/no',
        'defaultsetting' => "1",
        'validation' => '',
        'warning' => '',
    ],
    [
        'global' => true,
        'setting_name' => 'facebookappid',
        'display'=> 'Facebook App ID',
        'inputtype' => 'text',
        'defaultsetting' => '',
    ],
    [
        'global' => true,
        'setting_name' => 'facebooksecret',
        'display'=> 'Facebook App Secret',
        'inputtype' => 'text',
        'defaultsetting' => '',
    ],
];

// Format it for db;
$formlist = str_replace(["\r", "\n", "\t"], '', $formlist);

$settings = dbescape(serialize($settings));

// If it is already installed, don't install it again.
if (!$template = get_event_template_by_name($templatename)) {
    // Event template specific settings.

    // Uninstall the father's day template
    $SQL = "DELETE FROM events_templates WHERE folder = 'father_coaching_weekend'";
    execute_db_sql($SQL);

    // Install new registration template.
    $templateid = execute_db_sql(
        fetch_template("dbsql/events.sql", "insert_events_template", "events"),
        [
            "name" => $templatename,
            "folder" => $templatefolder,
            "formlist" => $formlist,
            "registrant_name" => $registrant_name,
            "orderbyfield" => $orderbyfield,
            "settings" => $settings,
        ]
    );

    execute_db_sql(
        fetch_template("dbsql/settings.sql", "insert_setting"),
        [
            "type" => "events_template",
            "pageid" => 0,
            "featureid" => 0,
            "setting_name" => "version",
            "setting" => $thisversion,
            "extra" => $templatefolder,
        ]
    );
} else { // Update event template.
    $templateid = $template["template_id"];
    $version = get_db_field("setting", "settings", "setting_name='version' AND type='events_template' AND extra='$templatefolder'");

    $thisversion = 2018031200;
    if ($version < $thisversion) {
        $settings = [
            [
                'setting_name' => 'template_setting_overnight',
                'display'=> 'Overnight Option',
                'inputtype' => 'yes/no',
                'defaultsetting' => "1",
                'validation' => '',
                'warning' => '',
            ],
        ];
        $settings = dbescape(serialize($settings));

        $SQL = "UPDATE events_templates SET settings = '$settings' WHERE folder='$templatefolder'";
        if (execute_db_sql($SQL)) { // If successful upgrade.
            execute_db_sql(
                fetch_template("dbsql/settings.sql", "update_setting_by_extra"),
                [
                    "setting" => $thisversion,
                    "setting_name" => "version",
                    "type" => "events_template",
                    "extra" => $templatefolder,
                ]
            );
        }
    }

    $thisversion = 2018082101;
    if ($version < $thisversion) {
        $SQL = "UPDATE events_templates SET formlist = '$formlist' WHERE folder='$templatefolder'";
        if (execute_db_sql($SQL)) { // If successful upgrade.
            execute_db_sql(
                fetch_template("dbsql/settings.sql", "update_setting_by_extra"),
                [
                    "setting" => $thisversion,
                    "setting_name" => "version",
                    "type" => "events_template",
                    "extra" => $templatefolder,
                ]
            );
        }
    }

    $thisversion = 2024053000;
    if ($version < $thisversion) {
        $templatesettings = [];
        if ($templates = get_db_result("SELECT * FROM events_templates WHERE folder='$templatefolder'")) {
            while ($template = fetch_row($templates)) {
                if (!empty($template["settings"])) { // There are settings in this template
                    $settings = unserialize($template["settings"]);
                    $newsettings = [];
                    foreach ($settings as $setting) {
                        $newsetting = [];
                        foreach ($setting as $key => $value) { // Save each setting with the default if no other is given
                            switch ($key) {
                                case "name":
                                    $newsetting["setting_name"] = $value;
                                    break;
                                case "default":
                                    $newsetting["defaultsetting"] = $value;
                                    break;
                                case "type":
                                    $newsetting["inputtype"] = $value;
                                    break;
                                case "title":
                                    $newsetting["display"] = $value;
                                    break;
                                default:
                                    $newsetting[$key] = $value;
                                    break;
                            }
                        }
                        $newsettings[] = $newsetting;
                    }
                    $newsettings = dbescape(serialize($newsettings));
                    $SQL = "UPDATE events_templates SET settings = '$newsettings' WHERE folder='$templatefolder'";
                    if (execute_db_sql($SQL)) { // If successful upgrade.
                        execute_db_sql(
                            fetch_template("dbsql/settings.sql", "update_setting_by_extra"),
                            [
                                "setting" => $thisversion,
                                "setting_name" => "version",
                                "type" => "events_template",
                                "extra" => $templatefolder,
                            ]
                        );
                    }
                }
            }
        }
    }

    $thisversion = 2024072500;
    if ($version < $thisversion) {
        $settings = serialize($settings);
        $SQL = "UPDATE events_templates SET settings = ||settings|| WHERE folder = ||folder||";
        if (execute_db_sql($SQL, ["settings" => $settings, "folder" => $templatefolder])) { // If successful upgrade.
            execute_db_sql(
                fetch_template("dbsql/settings.sql", "update_setting_by_extra"),
                [
                    "setting" => $thisversion,
                    "setting_name" => "version",
                    "type" => "events_template",
                    "extra" => $templatefolder,
                ]
            );
        }
    }
}

$globalsettings = [
    [
        "type" => "events_template_global",
        "featureid" => $templateid,
        "setting_name" => "facebookappid",
        "defaultsetting" => "",
    ],
    [
        "type" => "events_template_global",
        "featureid" => $templateid,
        "setting_name" => "facebooksecret",
        "defaultsetting" => "",
    ],
];

// Make sure that global settings exist.
save_batch_settings($globalsettings);
?>