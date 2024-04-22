<?php
/***************************************************************************
* donate.php - donate page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 1.7.3
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); }
    if (!isset($donateLIB)) { include_once($CFG->dirroot . '/features/donate/donatelib.php'); }
    
    header_remove('X-Frame-Options');    
        
    callfunction();
        
    echo '</body></html>';    
}

function donate_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "donate";

	//Default Settings	
	$default_settings = default_settings($feature,$pageid,$featureid);
	$setting_names = get_setting_names($default_settings);
    
	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature,$featureid,$pageid)) {
        echo make_settings_page($setting_names,$settings,$default_settings,$feature,$featureid,$pageid);
	} else { //No Settings found...setup default settings			
		if (make_or_update_settings_array($settings_array)) { donate_settings(); }
	}	
}

function editcampaign() {
global $CFG, $MYVARS, $USER;
$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
    $returnme = '<div id="donation_display">';
    $returnme .= select_campaign_forms($featureid, $pageid);
    $returnme .= '</div>';
    echo $returnme;
}

function managedonations() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
    $returnme = '<div id="donation_display">';
    $returnme .= add_or_manage_forms($featureid, $pageid);
    $returnme .= '</div>';

    echo $returnme; 
}

function thankyou() {
global $CFG;
    $redirect = js_code_wrap('window.location = "' . $CFG->wwwroot . '";');

    echo main_body(true);
    
    if (!empty($_GET['cm'])) {
        $c = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id='".$_GET['cm']."'");
        $auth_token = $c["token"]; 
        
        $pp_hostname = $CFG->paypal ? 'www.paypal.com' : 'www.sandbox.paypal.com';
         
        // read the post from PayPal system and add 'cmd'
        $req = 'cmd=_notify-synch';
         
        $tx_token = $_GET['tx'];
        $req .= "&tx=$tx_token&at=$auth_token";
         
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "//$pp_hostname/cgi-bin/webscr");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
        //set cacert.pem verisign certificate path in curl using 'CURLOPT_CAINFO' field here,
        //if your server does not bundled with default verisign certificates.
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Host: $pp_hostname"));
        $res = curl_exec($ch);
        curl_close($ch);
                
        if (!$res) {
            //HTTP ERROR
            echo $redirect;
        } else {
             // parse the data
            $lines = explode("\n", $res);
            $keyarray = [];
            if (strcmp ($lines[0], "SUCCESS") == 0) {
                for ($i=1; $i<count($lines);$i++) {
                    list($key,$val) = explode("=", $lines[$i]);
                    $keyarray[urldecode($key)] = urldecode($val);
                }
                // check the payment_status is Completed
                // check that txn_id has not been previously processed
                // check that receiver_email is your Primary PayPal email
                // check that payment_amount/payment_currency are correct
                // process payment
                echo js_code_wrap('setTimeout(function() { window.location = "' . $CFG->wwwroot . '"; },10000);');
                echo '
                <div style="width: 640px;text-align:center;margin:auto">
                    <h1>Thank You!</h1>
                    Your transaction has been completed, and a receipt for your donation has been emailed to you.
                    <br />You may log into your account at <a href="//www.paypal.com">www.paypal.com</a> to view details of this transaction.
                </div>
                ';  
            }
            else if (strcmp ($lines[0], "FAIL") == 0) {
                // log for manual investigation
                echo $redirect;
            } else {
                echo $redirect;
            }
        }
    } else {
         echo $redirect;
    }
}
?>