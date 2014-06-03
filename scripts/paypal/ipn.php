<?php
if(!isset($CFG)){ include('../../config.php'); }
include_once($CFG->dirroot . '/lib/header.php');

// read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-validate';

foreach ($_POST as $key => $value){
    $value = urlencode(stripslashes($value));
    $req .= "&$key=$value";
}

// post back to PayPal system to validate
$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

if($CFG->paypal){ $fp = fsockopen ('www.paypal.com', 80, $errno, $errstr, 30);
}else{ $fp = fsockopen ('www.sandbox.paypal.com', 80, $errno, $errstr, 30); }

//$fp = fsockopen ('ssl://www.paypal.com', 443, $errno, $errstr, 30);

if(!$fp){
// HTTP ERROR
}else{
	fputs($fp, $header . $req);
		while(!feof($fp)){
		$res = fgets ($fp, 1024);
		if (strcmp ($res, "VERIFIED") == 0) {
			// check the payment_status is Completed
			// check that txn_id has not been previously processed
			// check that receiver_email is your Primary PayPal email
			// check that payment_amount/payment_currency are correct
			// process payment
			
			$keyarray = $_POST;
			if(!get_db_row("SELECT * FROM logfile WHERE feature='events' AND description='Paypal' AND info='".$keyarray['txn_id']."'")){
				$regids = $keyarray['custom'];
				$regids = explode(":",$regids);
				$i=0;
				while(isset($regids[$i])){
					$paid = get_db_field("value", "events_registrations_values", "elementname='paid' AND regid=".$regids[$i]);
					$SQL = "UPDATE events_registrations_values SET value=".($paid + $keyarray["mc_gross_".($i+1)])." WHERE elementname='paid' AND regid=".$regids[$i];
					execute_db_sql($SQL);
					$i++;
				}

				//Log
				log_entry('events', $keyarray['txn_id'], "Paypal");
			}
		}elseif (strcmp ($res, "INVALID") == 0){
    		//Log
    		log_entry('events', $res, "Paypal (failed)");
	  	}
	}
fclose ($fp);
}
?>


