<?php
if(!isset($CFG)){ include('../../config.php'); }
include_once($CFG->dirroot . '/lib/header.php');
if(!isset($EVENTSLIB)){ include_once($CFG->dirroot . '/features/events/eventslib.php'); }

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
if(function_exists('get_magic_quotes_gpc')) {
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
 
// STEP 2: Post IPN data back to paypal to validate
$pp_hostname = $CFG->paypal ? 'www.paypal.com' : 'www.sandbox.paypal.com';
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
if( !($res = curl_exec($ch)) ) {
    // error_log("Got " . curl_error($ch) . " when processing IPN data");
    //Log
    log_entry('events', "Got " . curl_error($ch) . " when processing IPN data", "Paypal (failed)");
    
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
	if(!get_db_row("SELECT * FROM logfile WHERE feature='events' AND description='Paypal' AND info='".$keyarray['txn_id']."'")){
		$regids = $keyarray['custom'];
		$regids = explode(":",$regids);
		$i=0;
		while(isset($regids[$i])){
			$paid = get_db_field("value", "events_registrations_values", "elementname='paid' AND regid=".$regids[$i]);
			$SQL = "UPDATE events_registrations_values SET value='".((float) $paid + (float) $keyarray["mc_gross_".($i+1)])."' WHERE elementname='paid' AND regid='".$regids[$i]."'";
			execute_db_sql($SQL);
            
            //If payment is made, it is no longer in queue
            $SQL = "UPDATE events_registrations SET verified='1' WHERE regid='".$regids[$i]."'";
			execute_db_sql($SQL);
            $touser = new stdClass();
            $touser->fname = get_db_field("value", "events_registrations_values", "regid='".$regids[$i]."' AND elementname='Camper_Name_First'");
    		$touser->lname = get_db_field("value", "events_registrations_values", "regid='".$regids[$i]."' AND elementname='Camper_Name_Last'");
    		$touser->email = get_db_field("email","events_registrations","regid='".$regids[$i]."'");
    		
		$fromuser = new stdClass();
		$fromuser->email = $CFG->siteemail;
    		$fromuser->fname = $CFG->sitename;
    		$fromuser->lname = "";
    		$message = registration_email($regids[$i], $touser);
    		if(send_email($touser,$fromuser,null,"Camp Wabashi Registration", $message)){
    			send_email($fromuser,$fromuser,null,"Camp Wabashi Registration", $message);
    		}
			$i++;
		}

		//Log
		log_entry('events', $keyarray['txn_id'], "Paypal");
	}
    
} else if (strcmp ($res, "INVALID") == 0) {
    //Log
    log_entry('events', $res, "Paypal (failed)");
}else{
    //Log
    log_entry('events', $res, "Paypal (none)");
}
//
//
//
//
//if(!isset($CFG)){ include('../../config.php'); }
//include_once($CFG->dirroot . '/lib/header.php');
//
//// read the post from PayPal system and add 'cmd'
//$req = 'cmd=_notify-validate';
//
//foreach ($_POST as $key => $value){
//    $value = urlencode(stripslashes($value));
//    $req .= "&$key=$value";
//}
//
//// post back to PayPal system to validate
//$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
//$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
//$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
//
//if($CFG->paypal){ $fp = fsockopen ('www.paypal.com', 80, $errno, $errstr, 30);
//}else{ $fp = fsockopen ('www.sandbox.paypal.com', 80, $errno, $errstr, 30); }
//
////$fp = fsockopen ('ssl://www.paypal.com', 443, $errno, $errstr, 30);
//
//if(!$fp){
//// HTTP ERROR
//}else{
//	fputs($fp, $header . $req);
//		while(!feof($fp)){
//		$res = fgets ($fp, 1024);
//		if (strcmp ($res, "VERIFIED") == 0) {
//			// check the payment_status is Completed
//			// check that txn_id has not been previously processed
//			// check that receiver_email is your Primary PayPal email
//			// check that payment_amount/payment_currency are correct
//			// process payment
//			
//			$keyarray = $_POST;
//			if(!get_db_row("SELECT * FROM logfile WHERE feature='events' AND description='Paypal' AND info='".$keyarray['txn_id']."'")){
//				$regids = $keyarray['custom'];
//				$regids = explode(":",$regids);
//				$i=0;
//				while(isset($regids[$i])){
//					$paid = get_db_field("value", "events_registrations_values", "elementname='paid' AND regid=".$regids[$i]);
//					$SQL = "UPDATE events_registrations_values SET value=".($paid + $keyarray["mc_gross_".($i+1)])." WHERE elementname='paid' AND regid=".$regids[$i];
//					execute_db_sql($SQL);
//					$i++;
//				}
//
//				//Log
//				log_entry('events', $keyarray['txn_id'], "Paypal");
//			}
//		}elseif (strcmp ($res, "INVALID") == 0){
//    		//Log
//    		log_entry('events', $res, "Paypal (failed)");
//	  	}
//	}
//fclose ($fp);
//}
?>