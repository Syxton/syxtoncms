<?php
/***************************************************************************
 * lib.php - library for template functions
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 03/28/2025
 * $Revision: 0.0.1
 ***************************************************************************/

define("TEMP_PROPS", [
    "SHIRTSIZES" => ["Youth XS", "Youth S", "Youth M", "Youth L", "Youth XL", "Adult S", "Adult M", "Adult L", "Adult XL", "Adult XXL"],
]);

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
        $item_name = '<div class="registration_cart_item_info">' . $camper_names["full"] . " - " . $event['name'] . " Registration</div>";

        // New entry, save unique hash.
        $_SESSION['registrations'][$key]->hash = hash("sha256", $camper_names["full"] . $event["eventid"]);

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
            $item_name .= '<div class="registration_cart_item_extra">' . icon("camera") . ' Picture</div>';
        }
        if ($camper_shirt) {
            $item_name .= '<div class="registration_cart_item_extra">' . icon("shirt") . ' Shirt</div>';
        }

        $item['name'] = $item_name;

        $_SESSION['registrations'][$key]->item = $item;
    }

    $clean_registrations = remove_duplicate_registrations($_SESSION['registrations']);
    $_SESSION['registrations'] = $clean_registrations;

    return registration_cart_wrapper($_SESSION['registrations'], $checkout);
}

function already_registered($eventid, $name, $birthdate) {
    $SQL = '
        SELECT *
        FROM events_registrations
        WHERE regid IN (
                        SELECT regid
                        FROM events_registrations_values
                        WHERE elementname="camper_name"
                        AND value=||name||
                        )
        AND regid IN (
                        SELECT regid
                        FROM events_registrations_values
                        WHERE elementname="camper_birth_date"
                        AND value=||birthdate||
                    )
        AND eventid=||eventid||';
    $already_registered = get_db_count($SQL, ["eventid" => $eventid, "name" => $name, "birthdate" => $birthdate]);
    return $already_registered > 0;
}

function print_checkout_form($registrations) {
    // Every registration has a list of items and cost.
    // Every registration also has a single selected method of payment.
    // Every registration has a minimum and maximum allowed payment.

    ajaxapi([
        "id" => "cart_register_button",
        "if" => "(confirm('Are you sure you want to complete this registration?'))",
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "register",
        ],
        "reqstring" => "cart1",
        "display" => "registration_div",
        "event" => "click",
    ]);


    return '
    <div class="registration_cart_checkout">
        <h2>All Done?</h2>
        Finalize your registration by clicking the button below.
        <div class="registration_cart_checkout_title">
            <strong>Pay Now Amount: $ </strong><input type="text" id="payment_amount" value="0" disabled style="margin-left: 2px;" />
        </div>
        <div class="registration_cart_checkout_title">
            <button id="cart_register_button" style="display: block; margin: auto; background: green; color: white;">
                Finalize Registration
            </button>
        </div>
        <h2>More Registrations Needed?</h2>
        You might be able to quickly add registrations with the buttons below.
        <div class="registration_cart_checkout_title">
            To Register another person for this event:
                <button id="add_new_button">
                    Add New Registration
                </button>
            <br />
            To Register the same person for a different event:  Select the week and click the button below.<br />
            ' . registration_copy_options($registrations) . ' <button id="copy_registration">Copy Registration</button>
        </div>
    </div>';
}

function registration_copy_options($registrations) {
    global $PAGE;

    $options = "";

    // Get all events that are registerable.
    $registerable_events = get_registerable_events($PAGE->id);

    // Loop through current registrations in cart to make a list of events/users already in cart.
    $cart_events = [];
    foreach ($registrations as $registration) {
        $reg = $registration->GET;
        $name = get_camper_names($reg);

        foreach ($registerable_events as $event) {
            if ($event["id"] == $reg["eventid"]) {
                continue;
            }

            // Already registered.
            if (already_registered($event["id"], $name["full"], $reg["camper_birth_date"])) {
                continue;
            }

            $options .= '
                    <option value="' . $event["id"] . '|' . $registration->hash . '">
                        ' . $name["full"] . ' -> ' . $event["title"] . '
                    </option>';
        }

        $cart_events[] = [
            "eventid" => $reg["eventid"],
            "camper_name" => $name["full"],
            "camper_birth_date" => $reg["camper_birth_date"],
        ];
    }


    foreach ($registerable_events as $event) {
        foreach ($cart_events as $cart_event) {
            // Check if already registered.
            if (already_registered($event["id"], $cart_event["camper_name"], $cart_event["camper_birth_date"])) {
                continue;
            }

            $options .= '
                <option value="' . $event["id"] . '">
                ' . $event["title"] . '
                </option>';
        }
    }

    // Make options for each set of current registration user -> event.

    return '
        <select id="copy_event">
            <option value="0">Select an event</option>
            ' . $options . '
        </select>';
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
                    <br />
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
        <div class="registration_cart_item">
            <div>
                ' . $delete . '
            </div>
            <div class="registration_cart_item_name">
                ' . $item['name'] . '
            </div>
            <div class="registration_cart_item_price">
                <strong>Price: ' . $item_price . '</strong>
                ' . $promo_code_form . '
                ' . $pay_on_item . '
            </div>
        </div>
        ';
    }

    return '<form id="cart1" name="cart1">' . $return . '</form>';
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
    <div class="registration_cart_total">
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

function create_registration_array($elements, $post) {
    $regarray = [];
    foreach ($elements as $element) {
        if (isset($element["displayonly"])) {
            continue;
        }

        $name = $element["name"];
        $regarray[$name] = "";
        if (isset($post[$name])) {
            $regarray[$name] = urldecode($post[$name]);
        }
    }

    // Array cleanup
    $regarray = cleanup_registration_array($regarray);

    return $regarray;
}

function cleanup_registration_array($regarray) {
    // First phase is cleanup input.
    foreach ($regarray as $key => $value) {
        switch ($key) {
            case "camper_name_first":
            case "camper_name_last":
            case "camper_name_middle":
                $value = clean_param_opt($regarray, $key, "string", "");
                $regarray[$key] = nameize($value);
                break;
            case "parent_phone1":
            case "parent_phone2":
            case "parent_phone3":
            case "parent_phone4":
                $value = clean_param_opt($regarray, $key, "string", "");
                $regarray[$key] = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $value)), 2);
                break;
            default:
                $regarray[$key] = trim($value);
        }
    }

    // Second phase is using cleaned values to generate some special fields.
    $regarray["camper_name"] = $regarray["camper_name_last"] . ", " . $regarray["camper_name_first"] . " " . $regarray["camper_name_middle"];

    // Turn shirt size index into value.
    if ($regarray["camper_shirt"] > 0) {
        $sizes = TEMP_PROPS["SHIRTSIZES"];
        $regarray["camper_shirt_size"] = $sizes[$regarray["camper_shirt_size"]];
    }

    return $regarray;
}

function attach_registration_payment_info($regarray, $paymentinfo) {
    $regarray["total_value"] = $paymentinfo["original_price"];
    $regarray["total_owed"] = $paymentinfo["price"];
    $regarray["total_paid"] = 0;
    $regarray["campership"] = "";

    if (isset($paymentinfo["promoname"])) {
        $regarray["campership"] = $paymentinfo["promoname"];
    }

    return $regarray;
}

function get_total_cart_cost() {
    global $_SESSION;
    $cost = 0;

    foreach ($_SESSION["payment_cart"] as $item) {
        $cost += $item->cost;
    }

    return $cost;
}

function get_total_cart_owed() {
    global $_SESSION;
    $owed = 0;

    foreach ($_SESSION["completed_registrations"] as $reg) {
        $owed += $reg["total_owed"];
    }

    return $owed;
}

function show_post_registration_page() {
    global $_SESSION;

    $return = "";

    // Show a registration Status page.
    $cart = get_post_registration_cart_status();

    // Money is owed on at least 1 cart item.
    if ($total_cart_owed = get_total_cart_owed()) {
        // Do we plan to pay now?
        if ($total_pay_now = get_total_cart_cost()) {
            // Choose a method to pay.
            $return .= '
                <h2>Make Payment Now</h2>
                Your registrations are complete pending payment.
                <br />
                Click a Payment method below to pay for your registration fees.
                <br /><br />
                <div style="text-align:center;">
                    ' . make_paypal_button($_SESSION["payment_cart"], get_event_paypal_info()) . '
                </div>';
        } else {
            $return .= '
            <h2>Registration Complete</h2>
            Your registrations are complete pending payment.
            <br />
            You have not chosen to pay anything at this time.
            We have sent out payment information emails to your address.  Please refer to this information in order to make payments.
            ';
        }
    }

    return $cart . '
    <div class="registration_payment_area">
    ' . $return . '
    </div>';
}

function get_post_registration_cart_status() {
    global $_SESSION, $CFG, $USER;

    $cartitems = "";
    foreach ($_SESSION["payment_cart"] as $item) {
        $reg = $_SESSION["completed_registrations"][$item->regid];
        $status = $reg["total_owed"] > 0 ? "Pending Payment" : "Complete";
        $allowed_in_page = "";
        $event = get_event($reg["eventid"]);
        if ($event['allowinpage'] !== 0) {
            if (is_logged_in() && $event['pageid'] != $CFG->SITEID) {
                change_page_subscription($event['pageid'], $USER->userid);
                $allowed_in_page = '
                    <div class="registration_allowed_in_page">
                        This registration has granted you access into a private event page.
                        This area contains specific information about the event.<br />
                        <a href="/page/' . $event['pageid'] . '">Go to Event Page</a>
                    </div>';
            }
        }

        try {
            $facebookbuttons = \facebook_share_button($event, $reg["camper_name_first"]);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $facebookbuttons = "";
        }

        $cartitems .= '
        <div class="registration_cart_item">
            <div class="registration_cart_item_name">' . $facebookbuttons . $item->description . $allowed_in_page .'</div>
            <div class="registration_cart_item_price">' . $status . '</div>
        </div>';
    }

    $return = '
    <div class="registration_cart">
        <div class="registration_cart_title">
            Registration Review
        </div>
        ' . $cartitems . '
    </div>';

    return $return;
}

function get_event_paypal_info() {
    global $_SESSION;

    $cartitems = "";
    foreach ($_SESSION["payment_cart"] as $item) {
        $reg = $_SESSION["completed_registrations"][$item->regid];
        $event = get_event($reg["eventid"]);
        if (isset($event["paypal"])) {
            return $event["paypal"];
        }
    }

    return "";
}