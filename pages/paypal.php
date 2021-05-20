<?php
/***************************************************************************
* paypal.php - Paypal PDT page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 10/02/07
* Revision: 0.2.0
***************************************************************************/

include('header.php');

$params = array("dirroot" => $CFG->directory, "directory" => (empty($CFG->directory) ? '' : $CFG->directory . '/'), "wwwroot" => $CFG->wwwroot);
echo template_use("templates/page.template", $params, "page_js_css");

if (!isset($EVENTSLIB)) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

// read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-synch';
$tx_token = $_GET['tx'];
$auth_token = $CFG->paypal_auth;
$req .= "&tx=$tx_token&at=$auth_token";
// post back to PayPal system to validate
$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
if($CFG->paypal){ $fp = fsockopen ('www.paypal.com', 80, $errno, $errstr, 30);
}else{ $fp = fsockopen ('www.sandbox.paypal.com', 80, $errno, $errstr, 30);}

// If possible, securely post back to paypal using HTTPS
// Your PHP server will need to be SSL enabled
// $fp = fsockopen ('ssl://www.sandbox.paypal.com', 443, $errno, $errstr, 30);
if (!$fp) {
// HTTP ERROR
} else {
	fputs ($fp, $header . $req);
	// read the body data
	$res = '';
	$headerdone = false;
	while (!feof($fp)) {
    	$line = fgets ($fp, 1024);
    	if (strcmp($line, "\r\n") == 0) {
        	// read the header
        	$headerdone = true;
    	} elseif ($headerdone) {
        	// header has been read. now read the contents
        	$res .= $line;
    	}
	}
	// parse the data
	$lines = explode("\n", $res);
	$keyarray = array();
	if (strcmp ($lines[0], "SUCCESS") == 0) {
  	for ($i=1; $i<count($lines);$i++){
      	list($key,$val) = explode("=", $lines[$i]);
      	$keyarray[urldecode($key)] = urldecode($val);
  	}
  	// check the payment_status is Completed
  	// check that txn_id has not been previously processed
  	// check that receiver_email is your Primary PayPal email
  	// check that payment_amount/payment_currency are correct
  	// process payment
  	if (!empty($keyarray['item_number']) && $keyarray['item_number'] == "DONATE") {
  		$SQL = "SELECT *
                FROM donate_donations
               WHERE paypal_TX ='".$keyarra["txn_id"]."'"
      if (!get_db_row($SQL)) {
          $SQL = "INSERT INTO donate_donations
                             (campaign_id, paypal_TX, amount, timestamp)
                       VALUES('".$keyarray["custom"]."','".$keyarray['txn_id']."','".$keyarray["payment_gross"]."',".get_timestamp().")";
          execute_db_sql($SQL);
      }
      echo template_use("templates/paypal.template", array("type" => "donation"), "transaction_complete");
      echo print_cart($keyarray, true);
  	} else {
      $SQL = "SELECT *
                FROM logfile
               WHERE feature = 'events'
                 AND description = 'Paypal'
                 AND info = '".$keyarray['txn_id']."'";
  		if (!get_db_row($SQL)){
  			$regids = $keyarray['custom'];
  			$regids = explode(":",$regids);
  			$i=0;
  			while (isset($regids[$i])) {
          $rid = $regids[$i];
  				$paid = get_db_field("value", "events_registrations_values", "elementname='paid' AND regid='$rid'");
  				$SQL = "UPDATE events_registrations_values
                     SET value = ".($paid + $keyarray["mc_gross_".($i+1)])."
                   WHERE elementname = 'paid'
                     AND regid = '$rid'";
  				execute_db_sql($SQL);

          $eventid = get_db_field("eventid","events_registrations_values","regid='$rid'"); // Get eventid.
          $minimum = get_db_field("fee_min", "events", "eventid='$eventid'");
          $verified = get_db_field("verified", "events_registrations", "regid='$rid'");

          if ($paid >= $minimum) {
            if (empty($verified)) { // Not already verified.
              // If payment is made, it is no longer in queue.
              $SQL = "UPDATE events_registrations
                         SET verified = '1'
                       WHERE regid = '$rid'";
      			  execute_db_sql($SQL);

              $touser = new \stdClass;
              $touser->fname = get_db_field("value", "events_registrations_values", "regid='$rid' AND elementname='Camper_Name_First'");
          		$touser->lname = get_db_field("value", "events_registrations_values", "regid='$rid' AND elementname='Camper_Name_Last'");
          		$touser->email = get_db_field("email","events_registrations","regid='$rid'");

              $fromuser = new \stdClass;
              $fromuser->email = $CFG->siteemail;
          		$fromuser->fname = $CFG->sitename;
          		$fromuser->lname = "";
          		$message = registration_email($rid, $touser);
          		if (send_email($touser, $fromuser, null, $CFG->sitename . " Registration", $message)) {
          			send_email($fromuser, $fromuser, null, $CFG->sitename . " Registration", $message);
          		}
            }
          } else {
            $SQL = "UPDATE events_registrations
                       SET verified = '0'
                     WHERE regid = '$rid'";
  			    execute_db_sql($SQL);
          }
  				$i++;
  			}

  			//Log
  			log_entry('events', $keyarray['txn_id'], "Paypal");
  		}
    echo template_use("templates/paypal.template", array("type" => "transaction"), "transaction_complete");
  	echo print_cart($keyarray);
  	}
	} elseif (strcmp ($lines[0], "FAIL") == 0) {
    //Log
    log_entry('events', $lines[0], "Paypal (failed)");
	}
  fclose ($fp);
}

function print_cart($items, $donation = false) {
global $CFG;
	$i = 0; $cartitems = "";
  while ($i < $items["num_cart_items"]) {
    $params = array("itemname" => $items["item_name".($i+1)] , "itemprice" => $items["mc_gross_".($i+1)]);
		$cartitems .= template_use("templates/paypal.template", $params, "print_cart_row_template");
		$i++;
	}
  $params = array("wwwroot" => $CFG->wwwroot, "sitename" => $CFG->sitename, "cartitems" => $cartitems, "items" => $items, "type" => (!$donation ? "paid for" : "donated"));
  return template_use("templates/paypal.template", array("type" => "transaction"), "print_cart_template");
}
?>
