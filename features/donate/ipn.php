<?php
if (!isset($CFG)) {
    $sub = '../';
    while (!file_exists($sub . 'config.php')) {
        $sub .= '../';
    }
    include($sub . 'config.php'); 
}
include_once($CFG->dirroot . '/lib/header.php');

// STEP 1: Read POST data

// reading posted data from directly from $_POST causes serialization
// issues with array data in POST
// reading raw POST data from input stream instead.
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = [];
foreach ($raw_post_array as $keyval) {
  $keyval = explode ('=', $keyval);
  if (count($keyval) == 2)
     $myPost[$keyval[0]] = urldecode($keyval[1]);
}
// read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-validate';
if (function_exists('get_magic_quotes_gpc')) {
   $get_magic_quotes_exists = true;
}
foreach ($myPost as $key => $value) {
   $value = urlencode(stripslashes($value));
   $req .= "&$key=$value";
}

// STEP 2: Post IPN data back to paypal to validate
$pp_hostname = $CFG->paypal ? 'ipnpb.paypal.com' : 'ipnpb.sandbox.paypal.com';
$paypal_link = "https://$pp_hostname/cgi-bin/webscr";

$ch = curl_init($paypal_link);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: Close']);

// In wamp like environments that do not come bundled with root authority certificates,
// please download 'cacert.pem' from "http://curl.haxx.se/docs/caextract.html" and set the directory path
// of the certificate as shown below.

curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
if ( !($res = curl_exec($ch)) ) {
    curl_close($ch);
    exit;
}
curl_close($ch);

// STEP 3: Inspect IPN validation result and act accordingly
$req = str_replace("&", "||", $req);  // Make it a nice list in case we want to email it to ourselves for reporting

if (strcmp($res, "VERIFIED") == 0) {
	// check whether the payment_status is Completed
	// check that txn_id has not been previously processed
	// check that receiver_email is your Primary PayPal email
	// check that payment_amount/payment_currency are correct
	// process payment
	// assign posted variables to local variables

	if ($_SERVER['REQUEST_METHOD'] != "POST") {
		die("No Post Variables");
	}

	$item_name = $_POST['item_name'];
	$item_number = $_POST['item_number'];
	$payment_status = $_POST['payment_status'];
	$payment_currency = $_POST['mc_currency'];
	$receiver_email = $_POST['receiver_email'];

	$payment = clean_var_opt($_POST['mc_gross'], "float", 0.00);
	$txn_id = clean_var_opt($_POST['txn_id'], "string", "");
	$campaign_id = clean_var_req($_POST['custom'], "int");
   $payer_email =clean_var_opt($_POST['payer_email'], "string", "");
   $fname = clean_var_opt($_POST['firstname'], "string", false);
	$lname = clean_var_opt($_POST['lastname'], "string", false);
	$name = !$fname && !$lname ? $payer_email : ($fname && $lname ? $fname . ' ' . $lname : ($fname ? $fname : $lname));

	if ($campaign_id) {
		if (!get_db_row("SELECT * FROM logfile WHERE feature = 'donate' AND description = 'Paypal' AND info = ||info||", ["info" => $txn_id])) {
			if (!get_db_row("SELECT * FROM donate_donations WHERE paypal_TX = ||paypal_TX||", ["paypal_TX" => $txn_id])) {
				$params = [];
				$params["campaign_id"] = $campaign_id;
				$params["name"] = $name;
				$params["amount"] = $payment;
				$params["paypal_TX"] = $txn_id;
				$params["timestamp"] = get_timestamp();
				execute_db_sql(fetch_template("dbsql/donate.sql", "insert_donation", "donate"), $params);

				$c = get_db_row(fetch_template("dbsql/donate.sql", "get_campaign", "donate"), ["campaign_id" => $campaign_id]);
				if ($c["metgoal"] == "0") {
					$sum = get_db_field("SUM(amount)", "donate_donations", "campaign_id = ||campaign_id||", ["campaign_id" => $campaign_id]);
					if ($sum >= $c["goal_amount"]) {
						execute_db_sql("UPDATE donate_campaign SET metgoal = 1 WHERE campaign_id = ||campaign_id||", ["campaign_id" => $campaign_id]);
						log_entry('donate', $campaign_id, "Campaign Goal Met");
					}
				}

				log_entry('donate', $txn_id, "Paypal");

				// Mail yourself the details.
				mail($c["paypal_email"], "Donation Made", "A donation of $" . $payment." has been made to the " . $c["title"] . " donation campaign.", "From: " . $c["paypal_email"]);
			} else {
				echo "This donation has already been processed.";
			}
		}
	}
} else if (strcmp($res, "INVALID") == 0) {
	log_entry('donate', $res, "Paypal (failed)");
}
?>
