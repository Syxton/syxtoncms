<?php
/***************************************************************************
 * install.php - Registration Plugin installer
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 02/17/2012
 * $Revision: 0.0.1
 ***************************************************************************/
//Form name:Section:Title
$thisversion = 2025031400;
$templatename = 'Camp Wabashi Week 3.0';
$templatefolder = 'camp_2025';
$registrant_name = 'camper_name';
$orderbyfield = 'camper_name';

// formlist array[];
$formlist = [
    [
        'name' => 'email',
        'section' => 'Camper',
        'title' => 'Email',
        'type' => 'email',
        'required' => true,
    ],
    [
        'name' => 'camper_name',
        'section' => 'Camper',
        'title' => 'Name',
        'type' => 'hidden',
    ],
    [
        'name' => 'camper_name_first',
        'section' => 'Camper',
        'title' => 'First Name',
        'type' => 'text',
        'required' => true,
        'nonumbers' => true,
    ],
    [
        'name' => 'camper_name_last',
        'section' => 'Camper',
        'title' => 'Last Name',
        'type' => 'text',
        'required' => true,
        'nonumbers' => true,
    ],
    [
        'name' => 'camper_name_middle',
        'section' => 'Camper',
        'title' => 'Middle Initial',
        'type' => 'text',
        'required' => true,
        'maxlength' => 1,
        'letters' => true,
    ],
    [
        'name' => 'camper_age',
        'section' => 'Camper',
        'title' => 'Age',
        'type' => 'text',
        'required' => true,
        'number' => true,
        'readonly' => true,
        'customrules' => [
            'min_age' => 'features/events/templates/camp_2025/customrules.php',
            'max_age' => 'features/events/templates/camp_2025/customrules.php',
        ],
    ],
    [
        'name' => 'camper_gender',
        'section' => 'Camper',
        'title' => 'Gender',
        'type' => 'select',
        'options' => [
            'Male' => 'Male',
            'Female' => 'Female',
        ],
        'required' => false,
    ],
    [
        'name' => 'camper_birth_date',
        'section' => 'Camper',
        'title' => 'Birthday',
        'type' => 'date',
        'required' => true,
    ],
    [
        'name' => 'camper_grade',
        'section' => 'Camper',
        'title' => 'Grade',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'camper_shirt',
        'section' => 'Pay',
        'title' => 'Shirt',
        'type' => 'select',
        'options' => [
            'true' => 'Yes',
            'false' => 'No',
        ],
        'required' => false,
    ],
    [
        'name' => 'camper_shirt_size',
        'section' => 'Camper',
        'title' => 'Shirt Size',
        'type' => 'select',
        'options' => [
            'Youth XS',
            'Youth S',
            'Youth M',
            'Youth L',
            'Youth XL',
            'Adult S',
            'Adult M',
            'Adult L',
            'Adult XL',
            'Adult XXL',
        ],
        'required' => 'camper_shirt=true',
    ],
    [
        'name' => 'camper_home_congregation',
        'section' => 'Camper',
        'title' => 'Congregation',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'camper_picture',
        'section' => 'Pay',
        'title' => 'Camper Picture',
        'type' => 'select',
        'options' => [
            'true' => 'Yes',
            'false' => 'No',
        ],
        'required' => false,
    ],
    [
        'name' => 'total_owed',
        'section' => 'Pay',
        'title' => 'Amount Owed',
        'type' => 'hidden',
        'required' => false,
    ],
    [
        'name' => 'paid',
        'section' => 'Pay',
        'title' => 'Amount Paid',
        'type' => 'hidden',
        'required' => false,
    ],
    [
        'name' => 'payment_method',
        'section' => 'Pay',
        'title' => 'Payment Method',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'campership',
        'section' => 'Pay',
        'title' => 'Campership',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'parent_name1',
        'section' => 'Parent',
        'title' => 'Parent/Guardian 1',
        'type' => 'text',
        'required' => true,
    ],
    [
        'name' => 'parent_address_line1',
        'section' => 'Parent',
        'title' => 'Address 1',
        'type' => 'text',
        'required' => true,
    ],
    [
        'name' => 'parent_address_line2',
        'section' => 'Parent',
        'title' => 'Address 2',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'parent_address_city',
        'section' => 'Parent',
        'title' => 'City',
        'type' => 'text',
        'required' => true,
    ],
    [
        'name' => 'parent_address_state',
        'section' => 'Parent',
        'title' => 'State',
        'type' => 'select',
        'options' => get_form_USSTATES_options(),
        'required' => true,
    ],
    [
        'name' => 'parent_address_zipcode',
        'section' => 'Parent',
        'title' => 'Zipcode',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'parent_phone1',
        'section' => 'Parent',
        'title' => 'Phone 1',
        'type' => 'text',
        'required' => true,
    ],
    [
        'name' => 'parent_phone2',
        'section' => 'Parent',
        'title' => 'Phone 2',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'parent_phone3',
        'section' => 'Parent',
        'title' => 'Phone 3',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'parent_phone4',
        'section' => 'Parent',
        'title' => 'Phone 4',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_account',
        'section' => 'Health',
        'title' => 'Account',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_allergies',
        'section' => 'Health',
        'title' => 'Allergies',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_benefit_code',
        'section' => 'Health',
        'title' => 'Benefit Code',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_consent_from',
        'section' => 'Health',
        'title' => 'Consent From',
        'type' => 'date',
        'required' => false,
    ],
    [
        'name' => 'health_consent_to',
        'section' => 'Health',
        'title' => 'Consent To',
        'type' => 'date',
        'required' => false,
    ],
    [
        'name' => 'health_existing',
        'section' => 'Health',
        'title' => 'Existing Conditions',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_expiration_date',
        'section' => 'Health',
        'title' => 'Expiration Date',
        'type' => 'date',
        'required' => false,
    ],
    [
        'name' => 'health_history',
        'section' => 'Health',
        'title' => 'History',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_identification',
        'section' => 'Health',
        'title' => 'Identification',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_insurance',
        'section' => 'Health',
        'title' => 'Insurance',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_medicines',
        'section' => 'Health',
        'title' => 'Medicines',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_member_name',
        'section' => 'Health',
        'title' => 'Member Name',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_relationship',
        'section' => 'Health',
        'title' => 'Relationship',
        'type' => 'text',
        'required' => false,
    ],
    [
        'name' => 'health_tetanus_date',
        'section' => 'Health',
        'title' => 'Tetanus Date',
        'type' => 'date',
        'required' => false,
    ],
];

//Event template specific settings
$settings = [
    [
        'setting_name' => 'template_setting_min_age',
        'display'=> 'Minimum Age',
        'inputtype' => 'text',
        'numeric' => true,
        'defaultsetting' => '0',
    ],
    [
        'setting_name' => 'template_setting_pictures',
        'display'=> 'Pictures',
        'inputtype' => 'yes/no',
        'numeric' => false,
        'defaultsetting' => "0",
    ],
    [
        'setting_name' => 'template_setting_pictures_price',
        'display'=> 'Pictures Price',
        'inputtype' => 'text',
        'numeric' => false,
        'defaultsetting' => '0',
    ],
    [
        'setting_name' => 'template_setting_shirt',
        'display'=> 'Shirts',
        'inputtype' => 'yes/no',
        'defaultsetting' => "0",
    ],
    [
        'setting_name' => 'template_setting_shirt_price',
        'display'=> 'Shirt Price',
        'inputtype' => 'text',
        'numeric' => false,
        'defaultsetting' => '0',
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

$formlist = dbescape(serialize($formlist));
$settings = dbescape(serialize($settings));

// If it is already installed, don't install it again.
if (!$template = get_db_row("SELECT * FROM events_templates WHERE name = ||name||", ["name" => $templatename])) {
    // Install new registration template.
    $SQL = "INSERT INTO events_templates (name, folder, formlist, registrant_name, orderbyfield, settings)
                 VALUES (||name||, ||folder||, ||formlist||, ||registrant_name||, ||orderbyfield||, ||settings||)";

    $templateid = execute_db_sql(
        $SQL, [
            "name" => $templatename,
            "folder" => $templatefolder,
            "formlist" => $formlist,
            "registrant_name" => $registrant_name,
            "orderbyfield" => $orderbyfield,
            "settings" => $settings,
        ]
    );

    // Save the version number of the new template.
    $SQL = "INSERT INTO settings (type, pageid, featureid, setting_name, setting, extra)
                 VALUES (||type||, ||pageid||, ||featureid||, ||setting_name||, ||setting||, ||extra||)";
    execute_db_sql(
        $SQL, [
            "type" => "events_template",
            "pageid" => 0,
            "featureid" => 0,
            "setting_name" => "version",
            "setting" => $thisversion,
            "extra" => $templatefolder,
        ]
    );
} else { // Update formslist, settings, and orderbyfield in case they have changed.
    $templateid = $template["template_id"];

    // Retrieve current version number of template.
    $version = get_db_field("setting", "settings", "setting_name='version' AND type = 'events_template' AND extra = '$templatefolder'");

    // If there is no version number, insert one.
    if (!$version) {
        $SQL = "INSERT INTO settings (type, pageid, featureid, setting_name, setting, extra)
                 VALUES (||type||, ||pageid||, ||featureid||, ||setting_name||, ||setting||, ||extra||)";
        execute_db_sql(
            $SQL, [
                "type" => "events_template",
                "pageid" => 0,
                "featureid" => 0,
                "setting_name" =>
                "version",
                "setting" => $thisversion,
                "extra" => $templatefolder,
            ]
        );
    }

    // $thisversion = 2024072500;
    // if ($version < $thisversion) {
    //     $SQL = "UPDATE settings
    //                SET setting = ||setting||
    //              WHERE setting_name = ||setting_name||
    //                AND type = ||type||
    //                AND extra = ||extra||";
    //     execute_db_sql(
    //          $SQL, [
    //              "setting" => $thisversion,
    //              "setting_name" => "version",
    //              "type" => "events_template",
    //              "extra" => $templatefolder,
    //          ]
    //     );
    // }
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