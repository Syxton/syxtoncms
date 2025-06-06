<?php
/***************************************************************************
 * lib.php - library for template functions
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 5/02/2025
 * Revision: 2.0.1
 ***************************************************************************/

define("TEMP_PROPS", [
    "GENDERS" => [
        '' => 'Select One...',
        'Male' => 'Male',
        'Female' => 'Female',
    ],
    "SHIRTSIZES" => [
        '' => 'Select One...',
        'Youth XS' => 'Youth XS',
        'Youth S' => 'Youth S',
        'Youth M' => 'Youth M',
        'Youth L' => 'Youth L',
        'Youth XL' => 'Youth XL',
        'Adult S' => 'Adult S',
        'Adult M' => 'Adult M',
        'Adult L' => 'Adult L',
        'Adult XL' => 'Adult XL',
        'Adult 2X' => 'Adult 2X',
        'Adult 3X' => 'Adult 3X',
        'Adult 4X' => 'Adult 4X',
        'Adult 5X' => 'Adult 5X',
    ],
    "STATES" => [
        '' => 'Select One...',
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District Of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ],
]);

/**
 * Creates a unique hash for a given registration based on the event id, name, and birthdate.
 *
 * @param int $eventid The id of the event.
 * @param string $name The name of the registrant.
 * @param string $birthdate The birthdate of the registrant.
 * @return string The unique hash for the registration.
 */
function create_unique_registration_hash($eventid, $name, $birthdate) {
    return hash("sha256", $eventid . $name . $birthdate);
}

function print_registration_cart($checkout = false) {
    global $_SESSION, $error;

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
        , true, true);
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

        // New entry, save unique hash.
        $_SESSION['registrations'][$key]->hash = create_unique_registration_hash($event["eventid"], $camper_names["full"], $reg["camper_birth_date"]);

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

        $item_name = '<div class="registration_cart_item_info">' . $camper_names["full"] . " - " . $event['name'] . " Registration</div>";

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
    $SQL = fetch_template("templates/camp_2025/dbsql/camp2025.sql", "alreadyregistered", "events");
    $already_registered = get_db_count($SQL, [
        "eventid" => $eventid,
        "name" => $name,
        "birthdate" => $birthdate,
    ]);
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

    return fill_template("templates/camp_2025/tmp/camp2025.template", "checkout_form", "events", [
        "add_options" => registration_add_options($registrations),
        "copy_options" => registration_copy_options($registrations),
    ]);
}

function registration_add_options($registrations) {
    global $PAGE;

    $add_options = "";
    $events = [];
    foreach ($registrations as $registration) {
        $reg = $registration->GET;
        $reg["event"] = get_event($reg["eventid"]);

        // Get all events that are registerable.
        $registerable_events = get_currently_registerable_events($PAGE->id);
        while ($event = fetch_row($registerable_events)) {
            // Skip event if it isn't using the same template.
            if ($event["template_id"] !== $reg["event"]["template_id"]) {
                continue;
            }

            // Skip if we have already done this event.
            if (isset($events[$event["eventid"]])) {
                continue;
            }

            $add_options .= '
                <button id="add_new_button" onclick="show_form_again(' . $event["eventid"] . ', false);">
                    ' . icon("plus") . ' <span>Register for ' . $event["name"] . '</span>
                </button><br /><br />';

            // Remember that we have done this event.
            $events[$event["eventid"]] = $event;
        }
    }

    if (!empty($add_options)) {
        return '
        <br />
        <div class="centered">
            <strong>Add another registration</strong>
            <br /><br />
            ' . $add_options . '
        </div>';
    }

    return "";
}

function registration_copy_options($registrations) {
    global $PAGE;

    // Loop through current registrations in cart to make a list of events/users already in cart.
    $optionsArray = [];
    foreach ($registrations as $registration) {
        $reg = $registration->GET;
        $name = get_camper_names($reg);
        $reg["event"] = get_event($reg["eventid"]);

        // Get all events that are registerable.
        $registerable_events = get_currently_registerable_events($PAGE->id);
        while ($event = fetch_row($registerable_events)) {
            // Skip if this combo of user and event is already in the cart.
            $hash = create_unique_registration_hash($event["eventid"], $name["full"], $reg["camper_birth_date"]);
            if (find_registration_hash($registrations, $hash)) {
                continue;
            }

            // Skip event if it isn't using the same template.
            if ($event["template_id"] !== $reg["event"]["template_id"]) {
                continue;
            }

            // Skip event that this user is already registered for.
            if (already_registered($event["eventid"], $name["full"], $reg["camper_birth_date"])) {
                continue;
            }

            // Skip if this event/user combo is already an option.
            if (isset($optionsArray[$hash])) {
                continue;
            }

            $optionsArray[$registration->hash] = [
                "event" => $event,
                "name" => $name,
            ];
        }
    }

    // Make options for each set of current registration user -> event.
    // process options array.
    $options = "";
    foreach ($optionsArray as $key => $option) {
        $options .= '
            <option value="' . $option["event"]["eventid"] . '|' . $key . '">
                ' . $option["name"]["full"] . ' -> ' . $option["event"]["name"] . '
            </option>';
    }

    if (!empty($options)) {
        return fill_template("templates/camp_2025/tmp/camp2025.template", "copy_options", "events", [
            "options" => $options,
        ]);
    }

    return "";
}

function registration_cart_wrapper($registrations, $checkout = false, $empty = false) {
    global $_SESSION;

    $cartinfo = print_registration_cart_items_html($registrations);
    $cartinfo .= print_registration_cart_total_html($registrations);

    $checkout_button = ""; $checkout_form = "";
    if (!$empty) {
        if (!$checkout) {
            $checkout_button = get_registration_checkout_button();
        } else {
            $checkout_form = print_checkout_form($registrations);
        }
    }

    return '
    <div class="registration_cart">
        <div class="registration_cart_title">
            Registration Cart
        </div>
            ' . $cartinfo . '
            ' . $checkout_button . '
    </div>
    ' . $checkout_form;
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

    return fill_template("templates/camp_2025/tmp/camp2025.template", "go_to_checkout", "events", []);
}

function get_promo_code_form($event, $hash) {
    global $_SESSION;

    $checkout = clean_myvar_opt("checkout", "bool", false);
    ajaxapi([
        "id" => "applypromo_" . $hash,
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "applypromo",
            "code" => "js||$('#promo_code_" . $hash . "').val()||js", // Code to send.
            "hash" => $hash, // The registration hash.
            "checkout" => $checkout,
        ],
        "display" => "refreshableregcart",
        "ondone" => "$('.registration_cart').show();",
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

    return fill_template("templates/camp_2025/tmp/camp2025.template", "promo_form", "events", [
        "hash" => $hash,
        "promocode" => $promocode,
        "promoname" => $promoname,
    ]);
}

function get_promo_code_match($eventid, $code) {
    $setid = get_db_field("setting", "settings", "type='events_template' AND extra = ||extra|| AND setting_name='template_setting_promocode_set'", ["extra" => $eventid]);

    if (!$promo_code_set = get_promocode_set_array($setid)) {
        return false;
    }

    foreach ($promo_code_set as $promo_code) {
        if ($promo_code["code"] === $code) {
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

/**
 * Remove duplicate registrations from the cart.
 *
 * @param array $registrations The registrations to add to the cart.
 *
 * @return array The registrations with duplicates removed.
 */
function remove_duplicate_registrations($registrations) {
    global $error;

    // Reverse the array of registrations so we can loop through it in reverse.
    $reversed = array_reverse($registrations);

    // Create an empty array to hold the cleaned registrations.
    $clean_registrations = [];

    // Loop through the reversed array of registrations.
    foreach ($reversed as $key => $reg) {
        // Check if the registration already exists in the cart.
        if (find_registration_hash($clean_registrations, $reg->hash)) {
            // If it does, set an error message.
            $error = "You have already added this registration to your cart.";
            continue;
        }

        // If it doesn't, add it to the cleaned array.
        $clean_registrations[] = (object) [
            "hash" => $reg->hash,
            "item" => $reg->item,
            "GET" => $reg->GET,
        ];
    }

    // Reverse the cleaned array and return it.
    return array_reverse($clean_registrations);
}

function find_registration_hash($array, $hash) {
    foreach ($array as $reg) {
        if ($reg->hash == $hash) {
            return $reg;
        }
    }
    return false;
}

function print_registration_cart_items_html($registrations) {
    $return = "";

    foreach ($registrations as $reg) {
        $delete = false;
        $promo_code_form = false;
        $hash = false;
        $pay_on_item = "";
        $item = $reg->item;

        $checkout = clean_myvar_opt("checkout", "bool", false);
        $item_price = clean_param_opt($item, "price", "float", "--");
        if ($item_price !== "--") {
            if (isset($reg->item) &&
                isset($reg->hash) &&
                isset($reg->GET) &&
                isset($reg->GET["eventid"])) {
                $event = get_event($reg->GET["eventid"]);

                if ($checkout && $item_price > 0) {
                    $minimumpayment = empty($event["fee_min"]) ? 0 : $event["fee_min"];
                    $pay_on_item = fill_template("templates/camp_2025/tmp/camp2025.template", "pay_on_item", "events", [
                        "pay_options" => make_fee_options(
                            $minimumpayment,
                            $item_price,
                            "payment_amount_" . $reg->hash,
                            'class="payment_amounts" style="width:100px"
                            onchange="calculate_payment_amount();"',
                            $event['sale_end'],
                            $event['sale_fee']
                        ),
                    ]);
                }

                // Create delete button.
                $delete = true;
                $hash = $reg->hash;

                $setid = get_db_field("setting", "settings", "type='events_template' AND extra = ||extra|| AND setting_name='template_setting_promocode_set'", ["extra" => $event["eventid"]]);
                if ($setid > 0) { // A promocode set is selected for this event.
                    $promo_code_form = get_promo_code_form($event, $reg->hash);
                }
            }

            $item_price = '$' . number_format($item_price, 2, ".", "");
        }

        $return .= fill_template("templates/camp_2025/tmp/camp2025.template", "cart_item", "events", [
            "delete" => $delete,
            "hash" => $hash,
            "name" => $item['name'],
            "price" => $item_price,
            "promo_form" => $promo_code_form,
            "pay_on" => $pay_on_item,
        ]);
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

    return fill_template("templates/camp_2025/tmp/camp2025.template", "registration_cart_total", "events", [
        "total" => number_format($total, 2, ".", ""),
    ]);
}

function get_camper_names($reg) {
    // Prepare names
    $names = [];
    $camper_name_middle = clean_param_opt($reg, "camper_name_middle", "string", "");
    $names["middle"] = empty($camper_name_middle) ? '' : " " . nameize($camper_name_middle);
    $names["first"] = nameize(clean_param_opt($reg, "camper_name_first", "string", ""));
    $names["last"] = nameize(clean_param_opt($reg, "camper_name_last", "string", ""));
    $names["full"] = $names["last"] . ", " . trim($names["first"]) . " " . trim($names["middle"]);
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
                $value = clean_param_opt($regarray, $key, "string", "");
                $regarray[$key] = nameize($value);
                break;
            case "camper_name_middle":
                $value = clean_param_opt($regarray, $key, "string", "");
                $value = nameize($value);
                $regarray[$key] = str_replace(".", "", $value);
                break;
            case "parent_phone1":
            case "parent_phone2":
            case "parent_phone3":
            case "parent_phone4":
                $value = clean_param_opt($regarray, $key, "string", "");
                $regarray[$key] = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $value)), 2);
                break;
            default:
                $value = clean_param_opt($regarray, $key, "string", "");
                $regarray[$key] = trim($value);
        }
    }

    // Second phase is using cleaned values to generate some special fields.
    $regarray["camper_name"] = $regarray["camper_name_last"] . ", " . $regarray["camper_name_first"] . " " . $regarray["camper_name_middle"];

    // Turn shirt size index into value.
    if (is_numeric($regarray["camper_shirt_size"])) {
        $sizes = TEMP_PROPS["SHIRTSIZES"];
        $keys = array_keys($sizes);

        if (isset($sizes[$keys[$regarray["camper_shirt_size"]]])) {
            $regarray["camper_shirt_size"] = $sizes[$keys[$regarray["camper_shirt_size"]]];
        }
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

function get_registration_for_autofill($regid) {
    $event = get_event_from_regid($regid);

    $temp = (object) [
        "regid" => $regid,
        "event" => $event,
        "GET" => ["eventid" => $event["eventid"]],
    ];
    $SQL = fetch_template("dbsql/events.sql", "get_registration_values", "events");
    if ($values = get_db_result($SQL, ["regid" => $regid])) {
        while ($val = fetch_row($values)) {
            $temp->GET[strtolower($val["elementname"])] = $val["value"];
        }

        return $temp;
    }
    return false;
}

function get_like_event_autofill_registrations($data = []) {
    global $USER;
    $dbreg = [];

    $event = clean_param_req($data, "event", "array");

    $templates = "WHERE folder = 'camp_new' OR folder = 'camp_2025'";
    $SQL = fetch_template("dbsql/events.sql", "get_templates_event_registrations_by_email", "events", ["templates" => $templates]);
    if ($registrations = get_db_result($SQL, ["email" => $USER->email])) {
        while ($registration = fetch_row($registrations)) {
            if ($registration["eventid"] === $event["eventid"]) {
                continue;
            }

            if ($temp = get_registration_for_autofill($registration["regid"])) {
                $dbreg[] = $temp;
            }
        }
    }

    return $dbreg;
}

/**
 * Show a registration status page.
 *
 * This function displays a page that presents the status of each registration in the cart.
 * If any amount is owed for the cart items, it also provides instructions for making a payment.
 *
 * @return string The HTML for the registration status page.
 */
function show_post_registration_page() {

    // Retrieve the registration status details for items in the cart.
    $cart = get_post_registration_cart_status();

    // Calculate the total amount owed and the total amount to be paid now.
    $total_cart_owed = get_total_cart_owed();
    $total_pay_now = get_total_to_be_paid();

    // Check if money is owed on at least one cart item.
    if ($total_cart_owed > 0) {
        $title = getlang("title_pending", "/features/events/templates/camp_2025");
        $subtitle = getlang("subtitle_pending", "/features/events/templates/camp_2025");

        // Check if any payment is chosen to be made now.
        if ($total_pay_now > 0) {
            $message = getlang("message_paynow", "/features/events/templates/camp_2025", [
                "paynow" => number_format($total_pay_now, 2),
                "form" => get_payment_form(),
            ]);
        } else {
            // No payment chosen to be made at this time.
            $message = getlang("message_paylater", "/features/events/templates/camp_2025");
        }
    } else {
        // No outstanding payments required.
        $title = getlang("title_complete", "/features/events/templates/camp_2025");
        $subtitle = getlang("subtitle_complete", "/features/events/templates/camp_2025");
        $message_nopay = getlang("message_nopay", "/features/events/templates/camp_2025");
    }

    // Return the combined HTML content for the registration status page.
    return $cart . fill_template("templates/camp_2025/tmp/camp2025.template", "show_post_registration_page", "events", [
        "title" => $title,
        "subtitle" => $subtitle,
        "message" => $message,
    ]);
}

function get_post_registration_cart_status() {
    global $_SESSION, $CFG, $USER;

    $cartitems = "";
    foreach ($_SESSION["payment_cart"] as $item) {
        $reg = $_SESSION["completed_registrations"][$item->id];
        $status = $reg["total_owed"] > 0 ? "Pending Payment" : "Complete";
        $allowed_in_page = "";
        $event = get_event($reg["eventid"]);
        if ($event['allowinpage'] !== 0) {
            if (is_logged_in() && $event['pageid'] != $CFG->SITEID) {
                change_page_subscription($event['pageid'], $USER->userid);
                $allowed_in_page = fill_template("templates/camp_2025/tmp/camp2025.template", "message_allowedin", "events", [
                    "www" => $CFG->wwwroot,
                    "pageid" => $event['pageid'],
                ]);
            }
        }

        try {
            $facebookbuttons = \facebook_share_button($event, $reg["camper_name_first"]);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $facebookbuttons = "";
        }

        $cartitems .= fill_template("templates/camp_2025/tmp/camp2025.template", "post_cart_item", "events", [
            "facebook" => $facebookbuttons,
            "description" => $item->description,
            "allowedin" => $allowed_in_page,
            "status" => $status,
        ]);
    }

    return fill_template("templates/camp_2025/tmp/camp2025.template", "post_cart", "events", [
        "cartitems" => $cartitems,
    ]);
}

function get_event_paypal_info() {
    global $_SESSION;

    $cartitems = "";
    foreach ($_SESSION["payment_cart"] as $item) {
        $reg = $_SESSION["completed_registrations"][$item->id];
        $event = get_event($reg["eventid"]);
        if (isset($event["paypal"])) {
            return $event["paypal"];
        }
    }

    return "";
}