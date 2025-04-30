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

require $CFG->dirroot . "/scripts/payments/vendor/autoload.php";

use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Logging\LoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\RequestLoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\ResponseLoggingConfigurationBuilder;
use Psr\Log\LogLevel;
use PaypalServerSdkLib\Models\Builders\OrderRequestBuilder;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Models\Builders\PurchaseUnitRequestBuilder;
use PaypalServerSdkLib\Models\Builders\AmountWithBreakdownBuilder;
use PaypalServerSdkLib\Models\Builders\AmountBreakdownBuilder;
use PaypalServerSdkLib\Models\Builders\MoneyBuilder;
use PaypalServerSdkLib\Models\Builders\ItemBuilder;
use PaypalServerSdkLib\Models\ItemCategory;
use PaypalServerSdkLib\Models\Builders\ShippingDetailsBuilder;
use PaypalServerSdkLib\Models\Builders\ShippingNameBuilder;
use PaypalServerSdkLib\Models\Builders\ShippingOptionBuilder;
use PaypalServerSdkLib\Models\ShippingType;
use PaypalServerSdkLib\Models\Builders\PaymentSourceBuilder;
use PaypalServerSdkLib\Models\Builders\CardRequestBuilder;
use PaypalServerSdkLib\Models\Builders\CardAttributesBuilder;
use PaypalServerSdkLib\Models\Builders\CardVerificationBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Models\Builders\PaypalWalletBuilder;
use PaypalServerSdkLib\Models\Builders\PaypalWalletExperienceContextBuilder;
use PaypalServerSdkLib\Models\ShippingPreference;
use PaypalServerSdkLib\Models\PaypalExperienceLandingPage;
use PaypalServerSdkLib\Models\PaypalExperienceUserAction;

$PAYPAL_CLIENT_ID = $CFG->paypal_client_id;
$PAYPAL_CLIENT_SECRET = $CFG->paypal_client_secret;

$client = PaypalServerSdkClientBuilder::init()
    ->clientCredentialsAuthCredentials(
        ClientCredentialsAuthCredentialsBuilder::init(
            $PAYPAL_CLIENT_ID,
            $PAYPAL_CLIENT_SECRET
        )
    )
    ->environment(Environment::SANDBOX)
    ->build();


function handleResponse($response)
{
    $jsonResponse = json_decode($response->getBody(), true);
    return [
        "jsonResponse" => $jsonResponse,
        "httpStatusCode" => $response->getStatusCode(),
    ];
}

$endpoint = $_SERVER["REQUEST_URI"];
if ($endpoint === "/") {
    try {
        $response = [
            "message" => "Server is running",
        ];
        header("Content-Type: application/json");
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
        http_response_code(500);
    }
}

/**
 * Create an order to start the transaction.
 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_create
 */
function createOrder($cart) {
    global $client, $_SESSION;

    $items = []; $simplecart = [];
    foreach($_SESSION["payment_cart"] as $item) {
        $items[] = ItemBuilder::init(
            (string) $item->description,
            MoneyBuilder::init(
                'USD',
                (string) $item->cost
            )->build(),
            '1',
        )
        ->description((string) $item->description)
        ->sku($item->id)->build();

        // Create a simplified cart to reference when payment is complete.  Limit of 256 characters
        $simplecart[] = [
            "i" => $item->id, // i for reference id (events use regid)
            "v" => $item->cost, // v for value (usually the price of the item)
        ];
    }

    $orderBody = [
        'body' => OrderRequestBuilder::init(
            CheckoutPaymentIntent::CAPTURE,
            [
                PurchaseUnitRequestBuilder::init(
                    AmountWithBreakdownBuilder::init(
                        'USD',
                        (string) get_total_to_be_paid(),
                    )->breakdown(
                        AmountBreakdownBuilder::init()
                            ->itemTotal(
                                MoneyBuilder::init(
                                    'USD',
                                    (string) get_total_to_be_paid(),
                                )->build()
                            )->build()
                    )->build()
                )
                ->referenceId(json_encode((object) ["t" => "events", "c" => $simplecart])) // t for type, c for cart
                ->items($items)->build()
            ]
        )->build(),
        'prefer' => 'return=minimal'
    ];

    $apiResponse = $client->getOrdersController()->createOrder($orderBody);

    return handleResponse($apiResponse);
}

if (strstr($endpoint, "action=orders")) {
    $data = json_decode(file_get_contents("php://input"), true);
    $cart = $data["cart"];
    header("Content-Type: application/json");
    try {
        $orderResponse = createOrder($cart);
        echo json_encode($orderResponse["jsonResponse"]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
        http_response_code(500);
    }
}


/**
 * Capture payment for the created order to complete the transaction.
 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_capture
 */
function captureOrder($orderID) {
    global $client;

    $captureBody = [
        "id" => $orderID,
    ];

    $apiResponse = $client->getOrdersController()->captureOrder($captureBody);

    return handleResponse($apiResponse);
}

if (strstr($endpoint, "action=capture")) {
    $orderID = $_GET["orderID"];
    header("Content-Type: application/json");
    try {
        $captureResponse = captureOrder($orderID);
        error_log(json_encode($captureResponse));
        echo json_encode($captureResponse["jsonResponse"]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
        http_response_code(500);
    }
}

/**
 * Authorizes payment for an order.
 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_authorize
 */
function authorizeOrder($orderID) {
    global $client;

    $authorizeBody = [
        "id" => $orderID,
    ];

    $apiResponse = $client
        ->getOrdersController()
        ->authorizeOrder($authorizeBody);

    return handleResponse($apiResponse);
}

if (strstr($endpoint, "action=authorize")) {
    $urlSegments = explode("/", $endpoint);
    end($urlSegments); // Will set the pointer to the end of array
    $orderID = prev($urlSegments);
    header("Content-Type: application/json");
    try {
        $authorizeResponse = authorizeOrder($orderID);
        echo json_encode($authorizeResponse["jsonResponse"]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
        http_response_code(500);
    }
}

/**
 * Captures an authorized payment, by ID.
 * @see https://developer.paypal.com/docs/api/payments/v2/#authorizations_capture
 */
function captureAuthorize($authorizationId) {
    global $client;

    $captureAuthorizeBody = [
        "authorizationId" => $authorizationId,
    ];

    $apiResponse = $client
        ->getPaymentsController()
        ->captureAuthorize($captureAuthorizeBody);

    return handleResponse($apiResponse);
}

if (strstr($endpoint, "action=captureAuthorize")) {
    $urlSegments = explode("/", $endpoint);
    end($urlSegments); // Will set the pointer to the end of array
    $authorizationId = prev($urlSegments);
    header("Content-Type: application/json");
    try {
        $captureAuthResponse = captureAuthorize($authorizationId);
        echo json_encode($captureAuthResponse["jsonResponse"]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
        http_response_code(500);
    }
}