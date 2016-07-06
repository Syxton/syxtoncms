<?php
if (!isset($CFG)) { include('../../config.php'); }
include_once($CFG->dirroot . '/lib/header.php');

// STEP 1: read POST data
// Reading POSTed data directly from $_POST causes serialization issues with array data in the POST.
// Instead, read raw POST data from the input stream. 
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();
foreach ($raw_post_array as $keyval) {
    $keyval = explode ('=', $keyval);
    if (count($keyval) == 2) {
        $myPost[$keyval[0]] = urldecode($keyval[1]);
    }
}
// read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
$req = 'cmd=_notify-validate';
if (function_exists('get_magic_quotes_gpc')) {
   $get_magic_quotes_exists = true;
}

foreach ($myPost as $key => $value) {        
   if($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) { 
        $value = urlencode(stripslashes($value)); 
   } else {
        $value = urlencode($value);
   }
   $req .= "&$key=$value";
}

// STEP 2: POST IPN data back to PayPal to validate
if ($CFG->paypal) { 
    $ch = curl_init('https://www.paypal.com/cgi-bin/webscr');
} else { 
    $ch = curl_init('https://www.sandbox.paypal.com/cgi-bin/websc'); 
}

curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

// In wamp-like environments that do not come bundled with root authority certificates,
// please download 'cacert.pem' from "http://curl.haxx.se/docs/caextract.html" and set 
// the directory path of the certificate as shown below:
// curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
if (!($res = curl_exec($ch))) {
    // error_log("Got " . curl_error($ch) . " when processing IPN data");
    curl_close($ch);
    exit;
}
curl_close($ch);
 
// STEP 3: Inspect IPN validation result and act accordingly
if (strcmp ($res, "VERIFIED") == 0) {
    // The IPN is verified, process it:
    // check whether the payment_status is Completed
    // check that txn_id has not been previously processed
    // check that receiver_email is your Primary PayPal email
    // check that payment_amount/payment_currency are correct
    // process the notification
    // assign posted variables to local variables
    
    $txid = $_POST['txn_id'];
	if (!get_db_row("SELECT * FROM logfile WHERE feature='events' AND description='Paypal' AND info='$txid'")) {
		$regids = $_POST['custom'];
		$regids = explode(":",$regids);
		$i=0;
		while (!empty($regids[$i]) && isset($_POST["mc_gross_".($i+1)])) {
            $regid = dbescape($regids[$i]);
            $add = $_POST["mc_gross_".($i+1)];
            
            // Update paid amount field.
            $paid = get_db_field("value", "events_registrations_values", "elementname='paid' AND regid='$regid'");
			$SQL = "UPDATE events_registrations_values SET value='".($paid + $add)."' WHERE elementname='paid' AND regid='$regid'";
			execute_db_sql($SQL);
            
            // Make an entry for this transaction that links it to this registration.
            $eventid = get_db_field("eventid","events_registrations_values","regid='$regid'"); // Get eventid
            $params = array("date" => get_timestamp(),
                            "amount" => $add,
                            "txid" => $txid);
            $SQL = "INSERT INTO events_registrations_values (eventid, elementname, regid, value) VALUES('$eventid','tx','$regid','".dbescape(serialize($params))."')";
            if(!execute_db_sql($SQL)) {
                error_log("PAYPAL DEBUG: " . $SQL);
            }
            $i++;
		}

		//Log
		log_entry('events', $txid, "Paypal");
	}
} elseif (strcmp ($res, "INVALID") == 0) {
    // IPN invalid, log for manual investigation
    //Log
    log_entry('events', $res . $_POST['custom'], "Paypal (failed)");
}
?>


