<?php

declare(strict_types=1);

/*
 * PaypalServerSdkLib
 *
 * This file was automatically generated by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace PaypalServerSdkLib\Controllers;

use Core\Request\Parameters\BodyParam;
use Core\Request\Parameters\HeaderParam;
use Core\Request\Parameters\QueryParam;
use Core\Request\Parameters\TemplateParam;
use Core\Response\Types\ErrorType;
use CoreInterfaces\Core\Request\RequestMethod;
use PaypalServerSdkLib\Exceptions\ErrorException;
use PaypalServerSdkLib\Http\ApiResponse;
use PaypalServerSdkLib\Models\Order;
use PaypalServerSdkLib\Models\OrderAuthorizeResponse;

class OrdersController extends BaseController
{
    /**
     * Creates an order. Merchants and partners can add Level 2 and 3 data to payments to reduce risk and
     * payment processing costs. For more information about processing payments, see checkout or multiparty
     * checkout. Note: For error handling and troubleshooting, see Orders v2 errors.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function createOrder(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::POST, '/v2/checkout/orders')
            ->auth('Oauth2')
            ->parameters(
                HeaderParam::init('Content-Type', 'application/json'),
                BodyParam::init($options)->extract('body'),
                HeaderParam::init('PayPal-Mock-Response', $options)->extract('paypalMockResponse'),
                HeaderParam::init('PayPal-Request-Id', $options)->extract('paypalRequestId'),
                HeaderParam::init('PayPal-Partner-Attribution-Id', $options)->extract('paypalPartnerAttributionId'),
                HeaderParam::init('PayPal-Client-Metadata-Id', $options)->extract('paypalClientMetadataId'),
                HeaderParam::init('Prefer', $options)->extract('prefer', 'return=minimal'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'Request is not well-formed, syntactically incorrect, or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The requested action could not be performed, semantically incorrect, or fa' .
                    'iled business validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(Order::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Shows details for an order, by ID. Note: For error handling and troubleshooting, see Orders v2
     * errors.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function getOrder(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::GET, '/v2/checkout/orders/{id}')
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('id', $options)->extract('id'),
                HeaderParam::init('PayPal-Mock-Response', $options)->extract('paypalMockResponse'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                QueryParam::init('fields', $options)->extract('fields')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('404', ErrorType::init('The specified resource does not exist.', ErrorException::class))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(Order::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Updates an order with a `CREATED` or `APPROVED` status. You cannot update an order with the
     * `COMPLETED` status. To make an update, you must provide a `reference_id`. If you omit this value
     * with an order that contains only one purchase unit, PayPal sets the value to `default` which enables
     * you to use the path: \"/purchase_units/@reference_id=='default'/{attribute-or-object}\". Merchants
     * and partners can add Level 2 and 3 data to payments to reduce risk and payment processing costs. For
     * more information about processing payments, see checkout or multiparty checkout. Note: For error
     * handling and troubleshooting, see Orders v2 errors. Patchable attributes or objects: Attribute Op
     * Notes intent replace payer replace, add Using replace op for payer will replace the whole payer
     * object with the value sent in request. purchase_units replace, add purchase_units[].custom_id
     * replace, add, remove purchase_units[].description replace, add, remove purchase_units[].payee.email
     * replace purchase_units[].shipping.name replace, add purchase_units[].shipping.email_address replace,
     * add purchase_units[].shipping.phone_number replace, add purchase_units[].shipping.options replace,
     * add purchase_units[].shipping.address replace, add purchase_units[].shipping.type replace, add
     * purchase_units[].soft_descriptor replace, remove purchase_units[].amount replace purchase_units[].
     * items replace, add, remove purchase_units[].invoice_id replace, add, remove purchase_units[].
     * payment_instruction replace purchase_units[].payment_instruction.disbursement_mode replace By
     * default, disbursement_mode is INSTANT. purchase_units[].payment_instruction.
     * payee_receivable_fx_rate_id replace, add, remove purchase_units[].payment_instruction.platform_fees
     * replace, add, remove purchase_units[].supplementary_data.airline replace, add, remove
     * purchase_units[].supplementary_data.card replace, add, remove application_context.
     * client_configuration replace, add
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function patchOrder(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::PATCH, '/v2/checkout/orders/{id}')
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('id', $options)->extract('id'),
                HeaderParam::init('Content-Type', 'application/json'),
                HeaderParam::init('PayPal-Mock-Response', $options)->extract('paypalMockResponse'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                BodyParam::init($options)->extract('body')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'Request is not well-formed, syntactically incorrect, or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('404', ErrorType::init('The specified resource does not exist.', ErrorException::class))
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The requested action could not be performed, semantically incorrect, or fa' .
                    'iled business validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Payer confirms their intent to pay for the the Order with the given payment source.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function confirmOrder(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(
            RequestMethod::POST,
            '/v2/checkout/orders/{id}/confirm-payment-source'
        )
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('id', $options)->extract('id'),
                HeaderParam::init('Content-Type', 'application/json'),
                HeaderParam::init('PayPal-Client-Metadata-Id', $options)->extract('paypalClientMetadataId'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                HeaderParam::init('Prefer', $options)->extract('prefer', 'return=minimal'),
                BodyParam::init($options)->extract('body')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'Request is not well-formed, syntactically incorrect, or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init('Authorization failed due to insufficient permissions.', ErrorException::class)
            )
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The requested action could not be performed, semantically incorrect, or fa' .
                    'iled business validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('An internal server error has occurred.', ErrorException::class))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(Order::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Authorizes payment for an order. To successfully authorize payment for an order, the buyer must
     * first approve the order or a valid payment_source must be provided in the request. A buyer can
     * approve the order upon being redirected to the rel:approve URL that was returned in the HATEOAS
     * links in the create order response. Note: For error handling and troubleshooting, see Orders v2
     * errors.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function authorizeOrder(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::POST, '/v2/checkout/orders/{id}/authorize')
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('id', $options)->extract('id'),
                HeaderParam::init('Content-Type', 'application/json'),
                HeaderParam::init('PayPal-Mock-Response', $options)->extract('paypalMockResponse'),
                HeaderParam::init('PayPal-Request-Id', $options)->extract('paypalRequestId'),
                HeaderParam::init('Prefer', $options)->extract('prefer', 'return=minimal'),
                HeaderParam::init('PayPal-Client-Metadata-Id', $options)->extract('paypalClientMetadataId'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                BodyParam::init($options)->extract('body')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'Request is not well-formed, syntactically incorrect, or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init(
                    'The authorized payment failed due to insufficient permissions.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('404', ErrorType::init('The specified resource does not exist.', ErrorException::class))
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The requested action could not be performed, semantically incorrect, or fa' .
                    'iled business validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('An internal server error has occurred.', ErrorException::class))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(OrderAuthorizeResponse::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Captures payment for an order. To successfully capture payment for an order, the buyer must first
     * approve the order or a valid payment_source must be provided in the request. A buyer can approve the
     * order upon being redirected to the rel:approve URL that was returned in the HATEOAS links in the
     * create order response. Note: For error handling and troubleshooting, see Orders v2 errors.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function captureOrder(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::POST, '/v2/checkout/orders/{id}/capture')
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('id', $options)->extract('id'),
                HeaderParam::init('Content-Type', 'application/json'),
                HeaderParam::init('PayPal-Mock-Response', $options)->extract('paypalMockResponse'),
                HeaderParam::init('PayPal-Request-Id', $options)->extract('paypalRequestId'),
                HeaderParam::init('Prefer', $options)->extract('prefer', 'return=minimal'),
                HeaderParam::init('PayPal-Client-Metadata-Id', $options)->extract('paypalClientMetadataId'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                BodyParam::init($options)->extract('body')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'Request is not well-formed, syntactically incorrect, or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init(
                    'The authorized payment failed due to insufficient permissions.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('404', ErrorType::init('The specified resource does not exist.', ErrorException::class))
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The requested action could not be performed, semantically incorrect, or fa' .
                    'iled business validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('An internal server error has occurred.', ErrorException::class))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(Order::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Adds tracking information for an Order.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function createOrderTracking(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::POST, '/v2/checkout/orders/{id}/track')
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('id', $options)->extract('id'),
                HeaderParam::init('Content-Type', 'application/json'),
                BodyParam::init($options)->extract('body'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'Request is not well-formed, syntactically incorrect, or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init('Authorization failed due to insufficient permissions.', ErrorException::class)
            )
            ->throwErrorOn('404', ErrorType::init('The specified resource does not exist.', ErrorException::class))
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The requested action could not be performed, semantically incorrect, or fa' .
                    'iled business validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('An internal server error has occurred.', ErrorException::class))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(Order::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Updates or cancels the tracking information for a PayPal order, by ID. Updatable attributes or
     * objects: Attribute Op Notes items replace Using replace op for items will replace the entire items
     * object with the value sent in request. notify_payer replace, add status replace Only patching status
     * to CANCELLED is currently supported.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function updateOrderTracking(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(
            RequestMethod::PATCH,
            '/v2/checkout/orders/{id}/trackers/{tracker_id}'
        )
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('id', $options)->extract('id'),
                TemplateParam::init('tracker_id', $options)->extract('trackerId'),
                HeaderParam::init('Content-Type', 'application/json'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                BodyParam::init($options)->extract('body')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'Request is not well-formed, syntactically incorrect, or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init('Authorization failed due to insufficient permissions.', ErrorException::class)
            )
            ->throwErrorOn('404', ErrorType::init('The specified resource does not exist.', ErrorException::class))
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The requested action could not be performed, semantically incorrect, or fa' .
                    'iled business validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('An internal server error has occurred.', ErrorException::class))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }
}
