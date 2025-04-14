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
                        "price" => NULL,
                        "original_price" => NULL,
                    ],
                ]
            ]
        , true);
    }

    $items = [];
    $clean_registrations = [];
    $cart_items = "";
    foreach ($_SESSION['registrations'] as $key => $reg) {
        $reg = $reg->GET;
        $item = [];

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
        $original_price = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] + $picture_cost + $shirt_cost : $event["fee_full"] + $picture_cost + $shirt_cost;
        $price = $original_price;

        // Check for promocode.
        if (isset($_SESSION['registrations'][$key]->item["promocode"])) {
            if ($promo = get_promo_code_match($eventid, $_SESSION['registrations'][$key]->item["promocode"])) {
                $item['promoname'] = $promo['name'];
                $item['promocode'] = $_SESSION['registrations'][$key]->item["promocode"];
                $price = apply_promo($promo, $original_price);
            }
        }
        $item['price'] = $price;
        $item['original_price'] = $original_price;

        if ($picture_cost) {
            $item_name .= " + Picture";
        }
        if ($camper_shirt) {
            $item_name .= " + Shirt";
        }

        $item['name'] = $item_name;

        $_SESSION['registrations'][$key]->item = $item;
    }

    $clean_registrations = remove_duplicate_registrations($_SESSION['registrations']);
    $_SESSION['registrations'] = $clean_registrations;

    return registration_cart_wrapper($_SESSION['registrations'], $checkout);
}

function print_checkout_form($registrations) {
    // Every registration has a list of items and cost.
    // Every registration also has a single selected method of payment.
    // Every registration has a minimum and maximum allowed payment.

    return '
    <div class="registration_cart_checkout">
        <div class="registration_cart_checkout_title">
            Pay Now Amount: $ <input type="text" id="payment_amount" value="0" disabled />
        </div>
    </div>';
}

function registration_cart_wrapper($registrations, $checkout = false) {
    global $_SESSION;

    $cartinfo = print_registration_cart_items_html($registrations);
    $cartinfo .= print_registration_cart_total_html($registrations);

    $checkout_button = ""; $checkout_form = "";
    if (!$checkout) {
        $checkout_button = get_registration_checkout_button();
    } else {
        $checkout_form = print_checkout_form($_SESSION['registrations']);
    }

    return '
    <div class="registration_cart">
        <div class="registration_cart_title">
            Registration Cart
        </div>
            ' . $cartinfo . '
            ' . $checkout_button . '
            ' . $checkout_form . '
    </div>';
}

function get_registration_checkout_button() {
    ajaxapi([
        "id" => "registration_cart_checkout",
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "add_registration_to_cart",
            "checkout" => true,
        ],
        "display" => "registration_div",
        "event" => "click",
    ]);

    return '
    <div class="registration_cart_bottom">
        <button type="button" id="registration_cart_checkout">
            Checkout
        </button>
    </div>';
}

function get_promo_code_form($event, $hash) {
    global $_SESSION;

    $checkout = clean_myvar_opt("checkout", "bool", false);
    ajaxapi([
        "id" => "applycampership_" . $hash,
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "applycampership",
            "code" => "js||$('#campershipcode_" . $hash . "').val()||js", // Code to send.
            "hash" => $hash, // The registration hash.
            "eventid" => $event["eventid"], // Event ID.
            "checkout" => $checkout,
        ],
        "display" => "refreshableregcart",
        "event" => "click",
    ]);

    // Check if promocode already set.
    $promoname = "";
    $promocode = "";
    foreach ($_SESSION['registrations'] as $key => $reg) {
        if ($reg->hash == $hash) {
            if (isset($reg->item["promocode"])) {
                $promocode = $reg->item["promocode"];
                $promoname = $reg->item["promoname"] . " Applied";
            }
        }
    }

    $return = '
        <div>
            <input id="campershipcode_'  . $hash . '"
                type="text"
                name="campershipcode_'  . $hash . '"
                placeholder="Promo Code"
                value="'  . $promocode . '"
                style="display: inline-block;zoom:.8;width: 110px;" />
            <button id="applycampership_'  . $hash . '"
                type="button"
                style="display: inline-block;zoom:.8;">
                Apply
            </button>
            <div id="campershipresult_'  . $hash . '" style="zoom: .8;color: green;padding: 2px;text-align: center;">
                ' . $promoname . '
            </div>
        </div>
    ';

    return $return;
}

function get_promo_code_match($eventid, $code) {
    if (!$promo_code_set = get_promo_code_set($eventid)) {
        return false;
    }

    foreach ($promo_code_set as $promo_code) {
        if ($promo_code["code"] == hash('sha256', $code)) {
            return [
                "name" => $promo_code["name"],
                "reduction" => $promo_code["reduction"],
            ];
        }
    }
}

function apply_promo($promo, $price) {
    // No such thing as a 0 or negative discount.
    if ($promo["reduction"] <= 0) {
        return $price;
    }

    // Check if % or flat discount.
    if (substr($promo["reduction"], -1) == "%") {
        $price = $price * (1 - (floatval($promo["reduction"]) / 100));
    } else {
        $price = $price - $promo["reduction"];
    }

    // Price can not be less than 0.
    if ($price < 0) {
        $price = 0;
    }

    return $price;
}

function get_promo_code_set($promo_code_set) {
    // TODO: This is a placeholder.
    // this will be replaced with a db call once that feature is implemented.
    return [
        [
            "name" => "David Grubb Campership", // myfirstcamp
            "code" => "c84cfb574f577b90aaa17db7f07e46dbe1ff8aedf5aa2bd00e5fc06f649c3950",
            "reduction" => "100%",
        ],
        [
            "name" => "Eastside Church of Christ Campership", // east25side
            "code" => "2d4c5f274270d10e755c68721548101342e8d1ffae3f1f16b698a181a39771e7",
            "reduction" => "50%",
        ],
        [
            "name" => "Southside Church of Christ Campership", // south25side
            "code" => "ebc3cb388af30b668dfcbda70155f22068eaaca6ae74414493e5a9b51491af49",
            "reduction" => "70",
        ],
        [
            "name" => "Northside Church of Christ Campership", // north25side
            "code" => "e49bcb9d50fc8ea2f015f2c33825c1f73907809e0580f118f2ca4a658f1a0047",
            "reduction" => "10",
        ],
        [
            "name" => "Marshall Church of Christ Campership", // marshall25camp
            "code" => "e97000997a3eb6b5b92bb5c2709422346c703f581c812f5b86468dbbb21a64eb",
            "reduction" => "100%",
        ],
        [
            "name" => "North Meridian Church of Christ Campership", // north25meridian
            "code" => "e4021044c2facf523a01ca8809fd86e01c9f0df8944460d77d8bcd10d7a52dbd",
            "reduction" => "100%",
        ],
        [
            "name" => "Clay City Church of Christ Campership", // clay25city
            "code" => "34f5013b8ea8821633e346799b525c9c5f1e4627634472b5c0e7cdc8f7385ed3",
            "reduction" => "100%",
        ],
        [
            "name" => "Mt. Carmel Church of Christ Campership", // mt25carmel
            "code" => "47d7cb5641702117c08091842ab5b18f764519e3b427a048ff26e0314fda53e8",
            "reduction" => "100%",
        ],
        [
            "name" => "Prairie Creek Church of Christ Campership", // prairie25creek
            "code" => "e6a8dbae96457c62b4388c73b3fc4e128d2b422e100f72c96ad44c2ecce3b93f",
            "reduction" => "100%",
        ]
    ];
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
        $delete = ""; $promo_code_form = "";
        $pay_on_item = "";
        $item = $reg->item;

        $checkout = clean_myvar_opt("checkout", "bool", false);
        $item_price = clean_param_opt($item, "price", "float", "--");
        if ($item_price !== "--") {
            if (isset($reg->item) && isset($reg->hash) && isset($reg->GET) && isset($reg->GET["eventid"])) {
                $event = get_event($reg->GET["eventid"]);

                if ($checkout && $item_price > 0) {
                    $pay_on_item = '
                    <div style="zoom: .8">
                        <strong>Pay Now: </strong>' .
                        make_fee_options(
                            0,
                            $item_price,
                            "payment_amount_" . $reg->hash,
                            'class="payment_amounts" style="width:100px"
                            onchange="calculate_payment_amount();"',
                            $event['sale_end'],
                            $event['sale_fee']
                        ) . '
                    </div>';
                }

                $delete = '
                <button type="button" class="registration_cart_item_remove" onclick="remove_camp_2025_registration(\'' . $reg->hash . '\');">
                    ' . icon("trash") . '
                </button>
                ';

    $event["promocode_set"] = 1; // Remove this once implemented
                if ($event["promocode_set"] > 0) { // A promocode set is selected for this event.
                    $promo_code_form = '
                    <div class="registration_cart_item_promo_code">
                        ' . get_promo_code_form($event, $reg->hash) . '
                    </div>
                    ';
                }
            }

            $item_price = '$' . number_format($item_price, 2, ".", "");
        }

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
                ' . $pay_on_item . '
                ' . $promo_code_form . '
            </div>
        </div>
        ';
    }

    return $return;
}

function get_value_in_cart($registrations) {
    $total = 0;
    foreach ($registrations as $reg) {
        if (!isset($reg->item) || !isset($reg->hash)) {
            continue;
        }

        $item = $reg->item;
        $item_price = clean_param_opt($item, "original_price", "float", false);
        if ($item_price !== false) {
            $total += $item['original_price'];
        }
    }

    return $total;
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