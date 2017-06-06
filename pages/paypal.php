<?php
/***************************************************************************
* paypal.php - Paypal PDT page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 10/02/07
* Revision: 0.2.0
***************************************************************************/
 
include('header.php');
echo '  <script type="text/javascript">var dirfromroot = "'.$CFG->directory.'";</script>
        <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'ajax&amp;f=siteajax.js,events.js"></script>
        <link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'styles/styles_main.css" />
        <input id="lasthint" type="hidden" />';

if(!isset($EVENTSLIB)){ include_once($CFG->dirroot . '/features/events/eventslib.php'); }
	
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
if(!$fp){
// HTTP ERROR
}else{
	fputs ($fp, $header . $req);
	// read the body data
	$res = '';
	$headerdone = false;
	while(!feof($fp)) {
    	$line = fgets ($fp, 1024);
    	if(strcmp($line, "\r\n") == 0){
        	// read the header
        	$headerdone = true;
    	}elseif($headerdone){
        	// header has been read. now read the contents
        	$res .= $line;
    	}
	}
	// parse the data
	$lines = explode("\n", $res);
	$keyarray = array();
	if(strcmp ($lines[0], "SUCCESS") == 0) {
    	for ($i=1; $i<count($lines);$i++){
        	list($key,$val) = explode("=", $lines[$i]);
        	$keyarray[urldecode($key)] = urldecode($val);
    	}
    	// check the payment_status is Completed
    	// check that txn_id has not been previously processed
    	// check that receiver_email is your Primary PayPal email
    	// check that payment_amount/payment_currency are correct
    	// process payment
    	if(!empty($keyarray['item_number']) && $keyarray['item_number'] == "DONATE"){
    		echo "Your transaction has been completed, and a receipt for your donation has been emailed to you.<br>You may log into your account at <a href='https://www.paypal.com'>www.paypal.com</a> to view details of this transaction.<br>";
    		$SQL = "INSERT INTO donate_donations (campaign_id,paypal_TX,amount,timestamp) VALUES('".$keyarray["custom"]."','".$keyarray['txn_id']."','".$keyarray["payment_gross"]."',".get_timestamp().")";
            echo $SQL;
            if(!get_db_row("SELECT * FROM donate_donations WHERE paypal_TX='".$keyarra["txn_id"]."'")){
                execute_db_sql($SQL);    
            }
            echo print_donation($keyarray);
    	}else{
    		if(!get_db_row("SELECT * FROM logfile WHERE feature='events' AND description='Paypal' AND info='".$keyarray['txn_id']."'")){
    			$regids = $keyarray['custom'];
    			$regids = explode(":",$regids);
    			$i=0;
    			while(isset($regids[$i])){
    			    $rid = $regids[$i];
    				$paid = get_db_field("value", "events_registrations_values", "elementname='paid' AND regid='$rid'");
    				$SQL = "UPDATE events_registrations_values SET value=".($paid + $keyarray["mc_gross_".($i+1)])." WHERE elementname='paid' AND regid='$rid'";
    				execute_db_sql($SQL);
                    
                    $eventid = get_db_field("eventid","events_registrations_values","regid='$rid'"); // Get eventid.
                    $minimum = get_db_field("fee_min", "events", "eventid='$eventid'");
                    $verified = get_db_field("verified", "events_registrations", "regid='$rid'");
                    
                    if ($paid >= $minimum) {
                        if (empty($verified)) { // Not already verified.
                            // If payment is made, it is no longer in queue.
                            $SQL = "UPDATE events_registrations SET verified='1' WHERE regid='$rid'";
                			execute_db_sql($SQL);
        
                            $touser = new stdClass();
                            $touser->fname = get_db_field("value", "events_registrations_values", "regid='$rid' AND elementname='Camper_Name_First'");
                    		$touser->lname = get_db_field("value", "events_registrations_values", "regid='$rid' AND elementname='Camper_Name_Last'");
                    		$touser->email = get_db_field("email","events_registrations","regid='$rid'");
                    		
                            $fromuser = new stdClass();
                            $fromuser->email = $CFG->siteemail;
                    		$fromuser->fname = $CFG->sitename;
                    		$fromuser->lname = "";
                    		$message = registration_email($rid, $touser);
                    		if (send_email($touser, $fromuser, null, $CFG->sitename . " Registration", $message)) {
                    			send_email($fromuser, $fromuser, null, $CFG->sitename . " Registration", $message);
                    		}   
                        }
                    } else {
                        $SQL = "UPDATE events_registrations SET verified='0' WHERE regid='$rid'";
            			execute_db_sql($SQL);
                    }
    				$i++;
    			}
    			
    			//Log
    			log_entry('events', $keyarray['txn_id'], "Paypal");
    		}
    	echo "Your transaction has been completed, and a receipt for your purchase has been emailed to you.<br>You may log into your account at <a href='https://www.paypal.com'>www.paypal.com</a> to view details of this transaction.<br>";
    	echo print_cart($keyarray);
    	}
	}elseif (strcmp ($lines[0], "FAIL") == 0){
    	//Log
    	log_entry('events', $lines[0], "Paypal (failed)");
	}
    fclose ($fp);
}

function print_cart($items){
global $CFG;
	$i=0; $returnme = '<a href="'.$CFG->wwwroot.'">Go back to '.$CFG->sitename.'</a><br /><br /><table style="border-collapse:collapse;width:60%; margin-right:auto; margin-left:auto;"><tr><td colspan=2><b>What you have paid for:</b></td></tr>';
	while($i < $items["num_cart_items"]){
		$returnme .= '<tr style="background-color:#FFF1FF;"><td style="text-align:left; font-size:.8em;">'.$items["item_name".($i+1)] . '</td><td style="text-align:left; padding:10px; font-size:.8em;">' . '$' . $items["mc_gross_".($i+1)] . '</td></tr><tr><td colspan="2"></td></tr>';
		$i++;
	}
	$returnme .= '<tr><td style="text-align:right;"><b>Total</b></td><td style="border-top: 1px solid gray;text-align:left;padding:10px; font-size:.8em;">$' . $items["mc_gross"] . '</td></tr><tr><td style="text-align:right;"><b>Paid</b></td><td style="text-align:left;padding:10px; font-size:.8em;">$' . $items["payment_gross"] . '</td></tr></table>';
	return $returnme;
}

function print_donation($items){
global $CFG;
	$i=0; $returnme = '<a href="'.$CFG->wwwroot.'">Go back to '.$CFG->sitename.'</a><br /><br /><table style="border-collapse:collapse;width:60%; margin-right:auto; margin-left:auto;"><tr><td colspan=2><b>Thank you for donating to '.$CFG->sitename.'<br />What you have donated:</b></td></tr>';
	while($i < $items["num_cart_items"]){
		$returnme .= '<tr style="background-color:#FFF1FF;"><td style="text-align:left; font-size:.8em;">'.$items["item_name".($i+1)] . '</td><td style="text-align:left; padding:10px; font-size:.8em;">' . '$' . $items["mc_gross_".($i+1)] . '</td></tr><tr><td colspan="2"></td></tr>';
		$i++;
	}
	$returnme .= '<tr><td style="text-align:right;"><b>Donated</b></td><td style="text-align:left;padding:10px; font-size:.8em;">$' . $items["payment_gross"] . '</td></tr></table>';
	return $returnme;
}
?>