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

function print_checkout_form($registrations) {
    // Every registration has a list of items and cost.
    // Every registration also has a single selected method of payment.
    // Every registration has a minimum and maximum allowed payment.
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

function get_promo_code_form($event, $hash) {
    $event["promocode_set"] = "id of promocode set";
    $promo_code_set = get_promo_code_set($event["promocode_set"]);
    $return = promo_code_js($promo_code_set, $hash) . '
        <input id="campershipcode'  . $hash . '" type="text" name="campershipcode'  . $hash . '" style="display: inline-block" />
        <button id="applycampership" type="button">
            Apply
        </button>
        <div id="campershipresult'  . $hash . '" style="padding: 2px;text-align: center;"></div>
    ';

    return $return;
}

function get_promo_code_set($promo_code_set) {
    // TODO: This is a placeholder.
    // this will be replaced with a db call once that feature is implemented.
    return [
        [
            "name" => "David Grubb Campership", // myfirstcamp
            "code" => "c84cfb574f577b90aaa17db7f07e46dbe1ff8aedf5aa2bd00e5fc06f649c3950"
        ],
        [
            "name" => "Eastside Church of Christ Campership", // east25side
            "code" => "2d4c5f274270d10e755c68721548101342e8d1ffae3f1f16b698a181a39771e7"
        ],
        [
            "name" => "Southside Church of Christ Campership", // south25side
            "code" => "ebc3cb388af30b668dfcbda70155f22068eaaca6ae74414493e5a9b51491af49"
        ],
        [
            "name" => "Northside Church of Christ Campership", // north25side
            "code" => "e49bcb9d50fc8ea2f015f2c33825c1f73907809e0580f118f2ca4a658f1a0047"
        ],
        [
            "name" => "Marshall Church of Christ Campership", // marshall25camp
            "code" => "e97000997a3eb6b5b92bb5c2709422346c703f581c812f5b86468dbbb21a64eb"
        ],
        [
            "name" => "North Meridian Church of Christ Campership", // north25meridian
            "code" => "e4021044c2facf523a01ca8809fd86e01c9f0df8944460d77d8bcd10d7a52dbd"
        ],
        [
            "name" => "Clay City Church of Christ Campership", // clay25city
            "code" => "34f5013b8ea8821633e346799b525c9c5f1e4627634472b5c0e7cdc8f7385ed3"
        ],
        [
            "name" => "Mt. Carmel Church of Christ Campership", // mt25carmel
            "code" => "47d7cb5641702117c08091842ab5b18f764519e3b427a048ff26e0314fda53e8"
        ],
        [
            "name" => "Prairie Creek Church of Christ Campership", // prairie25creek
            "code" => "e6a8dbae96457c62b4388c73b3fc4e128d2b422e100f72c96ad44c2ecce3b93f"
        ]
    ];
}

function promo_code_js($promo_code_set, $hash) {

    $codes = '[';
    foreach ($promo_code_set as $code) {
        $codes .= '{
            name: "' . $code["name"] . '",
            code: "' . $code["code"] . '"
        },
        ';
    }
    $codes = "[$codes];";

    $return = '
        <script>
            $(function () {
                $(".applycampershipbutton").on("click", async function (e) {
                    e.preventDefault();

                    // Create an array of valid codes.
                    var camperships'  . $hash . ' = ' . $codes . ';

                    const code = await sha256($(#campershipcode'  . $hash . ').val());
                    var valid = false;
                    // Find code in camperships array.
                    for (var i = 0; i < camperships'  . $hash . '.length; i++) {
                        if (code == camperships'  . $hash . '[i]["code"]) {
                            // Found the code, add the name to the form.
                            valid = camperships'  . $hash . '[i]["name"];
                            break;
                        }
                    }

                    // Add option to payment_method select and set it as selected.
                    if (valid) {
                        $("#campership").val(valid);
                        $("#campershipresult'  . $hash . '").html("Successfully Applied Campership.");
                    }

                    // Remove Campership option if it exists and give message in campershipresult
                    if (!valid) {
                        $("#campershipresult'  . $hash . '").html("Invalid Campership Code.");
                    }
                });
            });
        </script>
    ';
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
        if (isset($reg->item) && isset($reg->hash)) {
            $delete = '
            <button type="button" class="registration_cart_item_remove" onclick="remove_camp_2025_registration(\'' . $reg->hash . '\');">
                ' . icon("trash") . '
            </button>
            ';

            if (isset($reg->GET) && isset($reg->GET["eventid"])) {
                $event = get_event($reg->GET["eventid"]);
                if ($event["promocode_set"] > 0) { // A promocode set is selected for this event.
                    $promo_code_form = '
                    <div class="registration_cart_item_promo_code">
                        ' . get_promo_code_form($event, $reg->hash) . '
                    </div>
                    ';
                }
            }
        }

        $item = $reg->item;
        $item_price = clean_param_opt($item, "price", "float", "--");
        if ($item_price !== "--") {
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
            </div>
            ' . $promo_code_form . '
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