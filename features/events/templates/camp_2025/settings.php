<?php
global $PAGE;
// formlist array[];
return [
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
        'setting_name' => 'template_setting_promocode_set',
        'display'=> 'Promo Code Set',
        'inputtype' => 'select',
        'extraforminfo' => 'SELECT setid AS selectvalue, setname AS selectname
                            FROM events_promo_set
                            WHERE pageid=||pageid||',
        'extraforminfoparams' => ['pageid' => '$PAGE->id'],
        'defaultsetting' => '0',
        "validation" => null,
        "warning" => "Select a promo code set to use for this template.",
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