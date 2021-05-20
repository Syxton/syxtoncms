<?php
if (!isset($CFG)) { include('../../config.php'); }
include_once($CFG->dirroot . '/lib/header.php');

// STEP 1: Read POST data

// reading posted data from directly from $_POST causes serialization
// issues with array data in POST
// reading raw POST data from input stream instead.
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();
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
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

// In wamp like environments that do not come bundled with root authority certificates,
// please download 'cacert.pem' from "http://curl.haxx.se/docs/caextract.html" and set the directory path
// of the certificate as shown below.

curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
if ( !($res = curl_exec($ch)) ) {
    // error_log("Got " . curl_error($ch) . " when processing IPN data");
    curl_close($ch);
    exit;
}
curl_close($ch);

// STEP 3: Inspect IPN validation result and act accordingly
$req = str_replace("&", "||", $req);  // Make it a nice list in case we want to email it to ourselves for reporting

if (strcmp ($res, "VERIFIED") == 0) {
    // check whether the payment_status is Completed
    // check that txn_id has not been previously processed
    // check that receiver_email is your Primary PayPal email
    // check that payment_amount/payment_currency are correct
    // process payment
    // assign posted variables to local variables

    $item_name = $_POST['item_name'];
    $item_number = $_POST['item_number'];
    $payment_status = $_POST['payment_status'];
    $payment = $_POST['mc_gross'];
    $payment_currency = $_POST['mc_currency'];
    $txn_id = $_POST['txn_id'];
    $custom = $_POST['custom'];
    $receiver_email = $_POST['receiver_email'];
    $payer_email = $_POST['payer_email'];
    $name = !empty($_POST["firstname"]) && !empty($_POST["lastname"]) ? $_POST["firstname"].' '.$_POST["lastname"] : $payer_email;
    if ($_SERVER['REQUEST_METHOD'] != "POST") {
        die("No Post Variables");
    }

    if (!empty($custom)) {
        if (!get_db_row("SELECT * FROM logfile WHERE feature='donate' AND description='Paypal' AND info='$txn_id'")) {
            $SQL = "INSERT INTO donate_donations (campaign_id,name,paypal_TX,amount,timestamp) VALUES('$custom','$name','$txn_id','$payment',".get_timestamp().")";
            if (!get_db_row("SELECT * FROM donate_donations WHERE paypal_TX='$txn_id'")) {
                execute_db_sql($SQL);

                $c = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id='$custom'");
                if ($c["metgoal"] == "0") {
                    $sum = get_db_field("SUM(amount)","donate_donations","campaign_id='$custom'");
                    if ($sum >= $c["goal_amount"]) {
                        execute_db_sql("UPDATE donate_campaign SET metgoal=1 WHERE campaign_id='$custom'");
                        //Log
                        log_entry('donate', $custom, "Campaign Goal Met");
                    }
                }

                // Log.
                log_entry('donate', $txn_id, "Paypal");

                // Mail yourself the details.
                mail($c["paypal_email"], "Donation Made", "A donation of $".$payment." has been made to the ".$c["title"]." donation campaign.", "From: ".$c["paypal_email"]);
            } else {
                echo "This donation has already been processed.";
            }
        }
    }
} else if (strcmp ($res, "INVALID") == 0) {
    //Log
   	log_entry('donate', $res, "Paypal (failed)");
}
?>
