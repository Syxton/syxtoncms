<?php
/***************************************************************************
 * lib.php - library for template functions
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 03/28/2025
 * $Revision: 0.0.1
 ***************************************************************************/

function print_registration_cart($checkout = false) {
    global $_SESSION;

    if (!isset($_SESSION['registrations']) || count($_SESSION['registrations']) === 0) {
        return registration_cart_wrapper(
            [
                (object) [
                    "item" => [
                        "name" => "There are no registrations in your cart.",
                        "price" => "--",
                    ],
                ]
            ]
        );
    }

    $items = [];
    $clean_registrations = [];
    $cart_items = "";
    foreach ($_SESSION['registrations'] as $key => $reg) {
        $reg = $reg->GET;

        // Get event info
        $eventid = clean_param_req($reg, "eventid", "int");
        $event = get_event($eventid);
        $templateid = $event['template_id'];
        $template = get_event_template($templateid);

        // Build item name and create unique hash.
        $camper_names = get_camper_names($reg);
        $item_name = '<div>' . $camper_names["full"] . " - " . $event['name'] . " Registration</div>";
        $hash = md5($item_name);

        // New entry, save unique hash.
        $_SESSION['registrations'][$key]->hash = md5($item_name);

        // Get registration info for pictures.
        $picture_cost = get_db_field("setting", "settings", "type='events_template' AND extra = ||extra|| AND setting_name='template_setting_pictures_price'", ["extra" => $eventid]);
        $camper_picture = clean_param_opt($reg, "camper_picture", "bool", false);
        $picture_cost = $camper_picture ? $picture_cost : 0;

        // Get registration info for shirts.
        $shirt_cost = get_db_field("setting", "settings", "type='events_template' AND extra = ||extra|| AND setting_name='template_setting_shirt_price'", ["extra" => $eventid]);
        $camper_shirt = clean_param_opt($reg, "camper_shirt", "bool", false);
        $shirt_cost = $camper_shirt ? $shirt_cost : 0;

        // Total up the registration bill
        $reg_total = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] + $picture_cost + $shirt_cost : $event["fee_full"] + $picture_cost + $shirt_cost;

        if ($picture_cost) {
            $item_name .= " + Picture";
        }
        if ($camper_shirt) {
            $item_name .= " + Shirt";
        }

        $item = [
            "name" => $item_name,
            "price" => $reg_total,
        ];

        $_SESSION['registrations'][$key]->item = $item;
    }

    $clean_registrations = remove_duplicate_registrations($_SESSION['registrations']);
    $_SESSION['registrations'] = $clean_registrations;

    return registration_cart_wrapper($_SESSION['registrations'], $checkout);
}

function print_checkout_forms($registrations) {

}

function registration_cart_wrapper($registrations, $checkout = false) {
    $total = get_total_in_cart($registrations);
    $cartinfo = print_registration_cart_items_html($registrations);
    $cartinfo .= print_registration_cart_total_html($registrations);

    $checkout_button = "";
    if ($total > 0 && $checkout) {
        $checkout_button = get_registration_checkout_button();
    }

    return '
    <div class="registration_cart">
        <div class="registration_cart_title">
            Registration Cart
        </div>
            ' . $cartinfo . '
            ' . $checkout_button . '
    </div>';
}

function get_registration_checkout_button() {
    return '
    <div class="registration_cart_bottom">
        <button type="button" id="registration_cart_checkout">
            Checkout
        </button>
    </div>';
}

function remove_duplicate_registrations($registrations) {
    $reversed = array_reverse($registrations);
    $clean_registrations = [];
    foreach ($reversed as $key => $reg) {
        $hash = $reg->hash;
        if (!found_registration_hash($clean_registrations, $hash)) {
            $clean_registrations[] = (object) [
                "hash" => $hash,
                "item" => $reg->item,
                "GET" => $reg->GET,
            ];
        }
    }
    return array_reverse($clean_registrations);
}

function found_registration_hash($array, $hash) {
    foreach ($array as $reg) {
        if ($reg->hash == $hash) {
            return true;
        }
    }
    return false;
}

function print_registration_cart_items_html($registrations) {
    $return = "";
    foreach ($registrations as $reg) {
        $delete = "";
        if (isset($reg->item) && isset($reg->hash)) {
            $delete = '
            <button type="button" class="registration_cart_item_remove" onclick="remove_camp_2025_registration(\'' . $reg->hash . '\');">
                ' . icon("trash") . '
            </button>
            ';
        }
        $item = $reg->item;
        $item_price = clean_param_opt($item, "price", "float", false);
        $item_price = $item_price ? '$' . number_format($item['price'], 2, ".", "") : $item['price'];
        $return .= '
        <div id="registration_cart_item">
            <div>
                ' . $delete . '
            </div>
            <div class="registration_cart_item_name">
                ' . $item['name'] . '
            </div>
            <div class="registration_cart_item_price">
                ' . $item_price . '
            </div>
        </div>
        ';
    }

    return $return;
}

function get_total_in_cart($registrations) {
    $total = 0;
    foreach ($registrations as $reg) {
        if (!isset($reg->item) || !isset($reg->hash)) {
            continue;
        }

        $item = $reg->item;
        $item_price = clean_param_opt($item, "price", "float", false);
        if ($item_price !== false) {
            $total += $item['price'];
        }
    }

    return $total;
}
function print_registration_cart_total_html($registrations) {
    $total = get_total_in_cart($registrations);

    if ($total == 0) {
        return "";
    }

    return '
    <div id="registration_cart_total">
        <div class="registration_cart_item_name">
            Registration Total
        </div>
        <div class="registration_cart_item_price">
            $' . number_format($total, 2, ".", "") . '
        </div>
    </div>
    ';
}

function get_camper_names($reg) {
    // Prepare names
    $names = [];
    $camper_name_middle = clean_param_opt($reg, "camper_name_middle", "string", "");
    $names["middle"] = empty($camper_name_middle) ? '' : " " . nameize($camper_name_middle) . ".";
    $names["first"] = nameize(clean_param_opt($reg, "camper_name_first", "string", ""));
    $names["last"] = nameize(clean_param_opt($reg, "camper_name_last", "string", ""));
    $names["full"] = $names["last"] . ", " . $names["first"] . " " . $names["middle"];
    return $names;
}