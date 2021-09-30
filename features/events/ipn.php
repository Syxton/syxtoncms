<?php
if (!isset($CFG)) { include('../../config.php'); }
include_once($CFG->dirroot . '/lib/header.php');
if (!isset($EVENTSLIB)) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

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
  log_entry('events', "Got " . curl_error($ch) . " when processing IPN data", "Paypal (failed)"); // Log
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

	$keyarray = $_POST;
  $txid = $_POST['txn_id'];
	if (!empty($txid) && !get_db_row("SELECT * FROM logfile WHERE feature='events' AND description='Paypal' AND info='$txid'")) {
		$regids = $keyarray['custom'];
		$regids = explode(":",$regids);
		$i=0;
		while (isset($regids[$i])) {
      $regid = dbescape($regids[$i]);
      $add = $_POST["mc_gross_".($i+1)];
			$paid = get_db_field("value", "events_registrations_values", "elementname='paid' AND regid='$regid'");
			$SQL = "UPDATE events_registrations_values SET value='".((float) $paid + (float) $add)."' WHERE elementname='paid' AND regid='$regid'";
			execute_db_sql($SQL);

      // If payment is made, it is no longer in queue.
      $SQL = "UPDATE events_registrations SET verified='1' WHERE regid='$regid'";
			execute_db_sql($SQL);

      // Make an entry for this transaction that links it to this registration.
      $eventid = get_db_field("eventid","events_registrations_values","regid='$regid'"); // Get eventid.
      $params = array("date" => get_timestamp(),
                    "amount" => $add,
                      "txid" => $txid);
      $SQL = "INSERT INTO events_registrations_values (eventid, elementname, regid, value) VALUES('$eventid','tx','$regid','".dbescape(serialize($params))."')";
      execute_db_sql($SQL);

      $touser = new \stdClass;
      $touser->fname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name_First'");
  		$touser->lname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name_Last'");
  		$touser->email = get_db_field("email","events_registrations","regid='$regid'");

      $fromuser = new \stdClass;
      $fromuser->email = $CFG->siteemail;
  		$fromuser->fname = $CFG->sitename;
  		$fromuser->lname = "";

      if (!empty($touser->email)) {
        $message = registration_email($regids[$i], $touser);
        send_email($fromuser, $fromuser, null, $CFG->sitename . " Registration", $message);
    		send_email($touser, $fromuser, null, $CFG->sitename . " Registration", $message);
      }
			$i++;
		}
		log_entry('events', $keyarray['txn_id'], "Paypal"); // Log
	}
} else if (strcmp ($res, "INVALID") == 0) {
  log_entry('events', $res, "Paypal (failed)"); // Log
} else {
  log_entry('events', $res, "Paypal (none)"); // Log
}
?>
