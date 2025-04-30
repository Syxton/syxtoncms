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
    <body style="padding: 25px;">
        <div id="paypal-button-container" class="paypal-button-container" style="width: 90%"></div>
        <!-- Containers for Card Fields hosted by PayPal -->
        <div id="card-form" class="card_container" style="padding: 0">
            <div id="card-name-field-container"></div>
            <div id="card-number-field-container"></div>
            <div id="card-expiry-field-container"></div>
            <div id="card-cvv-field-container"></div>


            <div>
                <label for="card-billing-address-line-1">Billing Address</label>
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
                    type="text"
                    id="card-billing-address-admin-area-line-1"
                    name="card-billing-address-admin-area-line-1"
                    autocomplete="off"
                    placeholder="Admin area line 1"
                />
            </div>
            <div>
                <input
                    type="text"
                    id="card-billing-address-admin-area-line-2"
                    name="card-billing-address-admin-area-line-2"
                    autocomplete="off"
                    placeholder="Admin area line 2"
                />
            </div>
            <div>
                <input
                    type="text"
                    id="card-billing-address-country-code"
                    name="card-billing-address-country-code"
                    autocomplete="off"
                    placeholder="Country code"
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

            <br /><br />
            <button id="card-field-submit-button" type="button">
                Pay now with Card
            </button>
        </div>
        <p id="result-message"></p>
        <script src="app.js"></script>
    </body>
</html>