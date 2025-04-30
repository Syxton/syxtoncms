<?php
/***************************************************************************
* paypal.php - Paypal PDT page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.2.0
***************************************************************************/

if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}

echo fill_template("tmp/page.template", "page_js_css", false, ["dirroot" => $CFG->directory]);

if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

// read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-synch';
$tx_token = $_GET['tx'];
$auth_token = $CFG->paypal_auth;
$req .= "&tx=$tx_token&at=$auth_token";
// post back to PayPal system to validate
$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
if ($CFG->paypal) { $fp = fsockopen ('www.paypal.com', 80, $errno, $errstr, 30);
} else { $fp = fsockopen ('www.sandbox.paypal.com', 80, $errno, $errstr, 30);}

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
	$keyarray = [];
	if (strcmp ($lines[0], "SUCCESS") == 0) {
		for ($i=1; $i<count($lines);$i++) {
    		[$key, $val] = explode("=", $lines[$i]);
    		$keyarray[urldecode($key)] = urldecode($val);
		}
		// check the payment_status is Completed
		// check that txn_id has not been previously processed
		// check that receiver_email is your Primary PayPal email
		// check that payment_amount/payment_currency are correct
		// process payment
		if (!empty($keyarray['item_number']) && $keyarray['item_number'] == "DONATE") {
			$SQL = "SELECT * FROM donate_donations WHERE paypal_TX = ||paypal_TX||";
			if (!get_db_row($SQL, ["paypal_TX" => $keyarray["txn_id"]])) {
				$params = [];
				$params["campaign_id"] = clean_var_req($keyarray["custom"], "int");
				$params["name"] = "Online Donation";
				$params["amount"] = clean_var_opt($keyarray['payment_gross'], "float", 0.00);
				$params["paypal_TX"] = clean_var_opt($keyarray['txn_id'], "string", "");
				$params["timestamp"] = get_timestamp();
				$SQL = fetch_template("dbsql/donate.sql", "insert_donation", "donate");
				execute_db_sql($SQL, $params);
			}
			echo fill_template("tmp/paypal.template", "transaction_complete", false, ["type" => "donation"]);
			echo print_cart($keyarray, true);
		} else {
			$SQL = fetch_template("dbsql/events.sql", "find_paypal_transfer", "events");
			if (!get_db_row($SQL, ["info" => $keyarray['txn_id']])) {
				$regids = $keyarray['custom'];
				$regids = explode(":", $regids);
				$i = 0;
				while (isset($regids[$i])) {
          			$rid = $regids[$i];
					$paid = (float) get_reg_paid($rid);
					$params = [
						"regid" => $rid,
						"value" => $paid + $keyarray["mc_gross_" . ($i + 1)],
						"elementname" => "paid",
					];
					execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_value", "events"), $params);

					$eventid = get_db_field("eventid", "events_registrations_values", "regid = |||regid||", ["regid" => $rid]);
					$minimum = get_db_field("fee_min", "events", "eventid = |||eventid||", ["eventid" => $eventid]);
					$verified = get_db_field("verified", "events_registrations", "regid = |||regid||", ["regid" => $rid]);

					if ($paid >= $minimum) {
						if (!$verified) { // Not already verified.
							// If payment is made, it is no longer in queue.
							execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_status", "events"), ["regid" => $rid, "verified" => 1]);

							$touser = new \stdClass;
							$touser->fname = get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname='Camper_Name_First'", ["regid" => $rid]);
							$touser->lname = get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname='Camper_Name_Last'", ["regid" => $rid]);
							$touser->email = get_db_field("email", "events_registrations", "regid = ||regid||", ["regid" => $rid]);

							$fromuser = new \stdClass;
							$fromuser->email = $CFG->siteemail;
							$fromuser->fname = $CFG->sitename;
							$fromuser->lname = "";
							$message = registration_email($rid, $touser);
							if (send_email($touser, $fromuser, $CFG->sitename . " Registration", $message)) {
								send_email($fromuser, $fromuser, $CFG->sitename . " Registration", $message);
							}
						}
					} else {
						execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_status", "events"), ["regid" => $rid, "verified" => 0]);
					}
					$i++;
				}
				log_entry('events', $keyarray['txn_id'], "Paypal");
			}
    		echo fill_template("tmp/paypal.template", "transaction_complete", false, ["type" => "transaction"]);
			echo print_cart($keyarray);
		}
	} elseif (strcmp($lines[0], "FAIL") == 0) {
		log_entry('events', $lines[0], "Paypal (failed)");
	}
	fclose ($fp);
}

function print_cart($items, $donation = false) {
global $CFG;
	$i = 0;
	$cartitems = "";
	while ($i < $items["num_cart_items"]) {
		$params = ["itemname" => $items["item_name" . ($i + 1)], "itemprice" => $items["mc_gross_" . ($i + 1)]];
		$cartitems .= fill_template("tmp/paypal.template", "print_cart_row_template", false, $params);
		$i++;
	}
	$params = ["wwwroot" => $CFG->wwwroot, "sitename" => $CFG->sitename, "cartitems" => $cartitems, "items" => $items, "type" => (!$donation ? "paid for" : "donated")];
	return fill_template("tmp/paypal.template", "print_cart_template", false, $params);
}
?>