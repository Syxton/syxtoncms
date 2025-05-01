<?php
    if (!isset($CFG)) {
        $sub = '../';
        while (!file_exists($sub . 'config.php')) {
            $sub .= '../';
        }
        include($sub . 'config.php');
    }
    include_once($CFG->dirroot . '/lib/header.php');
    if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

    $total = clean_param_opt($_GET, "total", "float",0);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link
            rel="stylesheet"
            type="text/css"
            href="https://www.paypalobjects.com/webstatic/en_US/developer/docs/css/cardfields.css"
        />
        <title>PayPal Advanced Integration</title>
        <script
            src="https://www.paypal.com/sdk/js?client-id=<?php echo $CFG->paypal_client_id; ?>&buyer-country=US&currency=USD&components=buttons,card-fields&enable-funding=venmo"
            data-sdk-integration-source="developer-studio"
        ></script>
    </head>
    <body style="padding: 0 25px;text-align: center;font-family: PayPalOpen-Regular, Helvetica, Arial, 'Liberation Sans', sans-serif;">
        <input type="hidden" id="total" value="<?php echo $total; ?>">
        <div id="paypal-button-container" class="paypal-button-container" style="width: 90%;padding: 5px;box-sizing: border-box;"></div>
        <!-- Containers for Card Fields hosted by PayPal -->
        <br />
        <label for="card-form" style="font-weight: bold;">
            Pay by Credit or Debit Card
        </label>
        <div id="card-form" class="card_container" style="padding: 0; text-align: center;">
            <div id="card-name-field-container"></div>
            <div id="card-number-field-container"></div>
            <div id="card-expiry-field-container"></div>
            <div id="card-cvv-field-container"></div>
            <br />
            <div>
                <label for="card-billing-address-line-1" style="font-weight: bold;">
                    Billing Address
                </label>
                <input
                    type="text"
                    id="card-billing-address-line-1"
                    name="card-billing-address-line-1"
                    autocomplete="off"
                    placeholder="Address line 1"
                />
            </div>
            <div>
                <input
                    type="text"
                    id="card-billing-address-line-2"
                    name="card-billing-address-line-2"
                    autocomplete="off"
                    placeholder="Address line 2"
                />
            </div>
            <div>
                <input
                    type="hidden"
                    id="card-billing-address-admin-area-line-1"
                    name="card-billing-address-admin-area-line-1"
                    autocomplete="off"
                    placeholder="Admin area line 1"
                />
            </div>
            <div>
                <input
                    type="hidden"
                    id="card-billing-address-admin-area-line-2"
                    name="card-billing-address-admin-area-line-2"
                    autocomplete="off"
                    placeholder="Admin area line 2"
                />
            </div>
            <div>
                <input
                    type="hidden"
                    id="card-billing-address-country-code"
                    name="card-billing-address-country-code"
                    autocomplete="off"
                    placeholder="Country code"
                    value="US"
                />
            </div>
            <div>
                <input
                    type="text"
                    id="card-billing-address-postal-code"
                    name="card-billing-address-postal-code"
                    autocomplete="off"
                    placeholder="Postal/zip code"
                />
            </div>
            <br />
            <button id="card-field-submit-button" type="button">
                Pay now with Card
            </button>
        </div>
        <br />
        <p id="result-message" style="margin: 0;padding: 5px;box-sizing: border-box;text-align: center;"></p>
        <script src="app.js"></script>
    </body>
</html>