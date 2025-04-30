<?php
/***************************************************************************
* paylib.php - Payment library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/02/2025
* Revision: 0.0.1
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define("PAYLIB", true);

$PAY = (object)[];

/**
 * Initialize the payment cart session with a single item.
 *
 * This function clears any existing payment cart in the session and
 * sets it with the provided item details.
 *
 * @param object[] $item An array containing a single item object with properties:
 *                       - regid: Registration ID of the item.
 *                       - description: Description of the item.
 *                       - cost: Cost of the item.
 */
function make_payment_cart_session($item) {
    global $_SESSION;

    // Clear any existing payment cart.
    unset($_SESSION["payment_cart"]);

    // Set the payment cart with the provided item details.
    $_SESSION["payment_cart"] = [
        (object) [
            "id" => $item[0]->regid,
            "description" => $item[0]->description,
            "cost" => $item[0]->cost,
        ],
    ];
}

/**
 * Calculate the total cost of items in the payment cart.
 *
 * This function iterates through each item in the payment cart stored
 * in the session and accumulates the total cost of all items.
 *
 * @return float The total cost of all items in the payment cart.
 */
function get_total_to_be_paid() {
    global $_SESSION;
    $cost = 0;

    // Check if the payment cart is set in the session
    if (isset($_SESSION["payment_cart"])) {
        // Iterate through each item in the payment cart session.
        foreach ($_SESSION["payment_cart"] as $item) {
            // Accumulate the cost of each item.
            $cost += $item->cost;
        }
    }

    return $cost;
}

/**
 * Return the HTML for the payment form.
 *
 * This function returns an iframe pointing to the frontend.php script
 * with the total cost of items in the payment cart as a parameter.
 *
 * @return string The HTML for the payment form.
 */
function get_payment_form() {
    global $CFG;

    // Sum of Session variable "payment_cart"
    $total = get_total_to_be_paid();

    return '<iframe id="payment_frame" onload="resizeCaller(this.id);" src="' . $CFG->wwwroot . '/scripts/payments/frontend.php?total=' . $total . '" style="width: 99%;border: 0;"></iframe>';
}

/**
 * Search the payment cart for a payment with a given ID.
 *
 * This function will search the payment cart stored in the session
 * for a payment with the given ID. It will return the value of the
 * payment if found, or 0 otherwise.
 *
 * @param int $id The ID of the payment to search for.
 * @param object[] $cart The payment cart to search.
 *
 * @return int The value of the payment if found, or 0 otherwise.
 */
function search_paid_cart_for_payment($id, $cart) {
    // Iterate through each item in the payment cart.
    foreach ($cart as $item) {
        // If the ID of the item matches the given ID, return the value.
        if ($item->i == $id) {
            return $item->v;
        }
    }

    // If no matching payment is found, return 0.
    return 0;
}
?>