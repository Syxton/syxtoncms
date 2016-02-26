<?php
/***************************************************************************
 * backend.php - Backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 08/16/2013
 * $Revision: 0.1.4
 ***************************************************************************/
if(!isset($CFG)){ include('../../../../config.php'); } 
include($CFG->dirroot . '/pages/header.php');

if(!isset($EVENTSLIB)) include_once($CFG->dirroot . '/features/events/eventslib.php');

callfunction();

update_user_cookie();

function register(){
global $CFG,$MYVARS,$USER,$error;
error_reporting(E_ERROR | E_PARSE); //keep warnings from showing

    //Facebook keys
    $keys->app_key = '350430668323766';
    $keys->app_secret = '7c43774dbcf542b0700e338bc5625296';
  
    if(!isset($COMLIB)){ include_once($CFG->dirroot.'/lib/comlib.php'); }
    $eventid = $MYVARS->GET["eventid"];
	$event = get_db_row("SELECT * FROM events WHERE eventid = '$eventid'");
	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='".$event['template_id']."'");
    
    //Total up the registration bill
	$MYVARS->GET["cart_total"] = $MYVARS->GET["total_owed"] != 0 ? $MYVARS->GET["total_owed"] + $MYVARS->GET["owed"] : $MYVARS->GET["owed"]; // Existing cart
	$MYVARS->GET["total_owed"] = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"]; // Marked for payment now

    //Prepare names
    $MYVARS->GET["Name_First"] = ucfirst($MYVARS->GET["Name_First"]);
    $MYVARS->GET["Name_Last"] = ucfirst($MYVARS->GET["Name_Last"]);
	$MYVARS->GET["Name"] = $MYVARS->GET["Name_Last"] . ", " . $MYVARS->GET["Name_First"]; 
    
    //Format phone numbers 
    $MYVARS->GET["Phone"] = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["Phone"])), 2);

    //Go through entire template form list
	$formlist = explode(";",$template['formlist']);
    foreach($formlist as $formelements){
        $element = explode(":",$formelements);
        $reg[$element[0]] = isset($MYVARS->GET[$element[0]]) ? $MYVARS->GET[$element[0]] : ""; 
    }
	$error = "";
	
	if($regid = enter_registration($eventid, $reg, $MYVARS->GET["email"])){ //successful registration
        
        if($error != ""){ echo $error . "<br />"; }
        
		if($event['allowinpage'] !=0){
			if(is_logged_in() && $event['pageid'] != $CFG->SITEID){ 
				subscribe_to_page($event['pageid'], $USER->userid);
				echo 'You have been automatically allowed into this events\' web page.  This page contain specific information about this event.';
			}
		}
		
		if($event['fee_full'] != 0){ // Not free event
			$items = !empty($MYVARS->GET["items"]) ? $MYVARS->GET["items"] . "**" . $regid . "::" . $MYVARS->GET["Name"] . " - " . $event["name"] . "::" . $MYVARS->GET["owed"] : $regid . "::" . $MYVARS->GET["Name"] . " - " . $event["name"] . "::" . $MYVARS->GET["owed"];
			echo '<div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="'.$MYVARS->GET["cart_total"].'" />
				 <input type="hidden" name="items" id="items" value="'.$items.'" /></div>';
			
			$items = explode("**",$items);
            $i=0;
            foreach($items as $item){
                $itm = explode("::",$item);
				$cart_items[$i]->regid = $itm[0];
				$cart_items[$i]->description = $itm[1];
				$cart_items[$i]->cost = $itm[2];     
                $i++;           
            }
			                
			if($MYVARS->GET['payment_method'] == "PayPal"){ //Event paid by paypal
				echo '<div style="margin:auto;width:90%;text-align:center;"><h3>You have successfully added: '.$MYVARS->GET["Name_First"].' for '.$event['name'] . ' to your cart.</h3>';
        		
                echo '<br />Your expected full payment is <span style="color:blue;font-size:1.25em;">$'.number_format($event['fee_full'],2).'</span>';
                echo '<br />You have opted to pay:  <span style="color:blue;font-size:1.25em;">$'.number_format($MYVARS->GET["cart_total"],2).'</span> right now.';
                execute_db_sql("UPDATE events_registrations SET verified='1' WHERE regid='$regid'");
                if($MYVARS->GET["cart_total"] < $event['fee_full']){
                    echo '<br /><h3 style="background-color: black; color: yellow">You\'re not finished yet...</h3>
                            Payment must be made to finalize your registrations.<br />
                            Click the Paypal button below to finish registering or pay in full when you show up to the event.
      				    <br /><br />
                        <div style="text-align:center;">
        				'.make_paypal_button($cart_items,$event['paypal']).'
        				</div>';    
                }
                    
                //Send pending registration email
                $touser->fname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Name_First'");
        		$touser->lname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Name_Last'");
        		$touser->email = get_db_field("email","events_registrations","regid='$regid'");
        		$fromuser->email = $CFG->siteemail;
        		$fromuser->fname = $CFG->sitename;
        		$fromuser->lname = "";    
                $message = registration_pending_email($regid, $touser);
        		if(send_email($touser,$fromuser,null,"Father's Coaching Weekend Registration Pending", $message)){
        			send_email($fromuser,$fromuser,null,"Father's Coaching Weekend Registration Pending", $message);
        		}
			}else{ //Event paid by check
                echo '<div style="margin:auto;width:90%;text-align:center;"><h3>You have successfully registered '.$MYVARS->GET["Name_First"].' for '.$event['name'] . '.</h3>';
                
                echo '<br />Your expected full payment is <span style="color:blue;font-size:1.25em;">$'.number_format($event['fee_full'],2).'</span>';
           
                execute_db_sql("UPDATE events_registrations SET verified='1' WHERE regid='$regid'");
                
                //Send registration email
                $touser->fname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Name_First'");
        		$touser->lname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Name_Last'");
        		$touser->email = get_db_field("email","events_registrations","regid='$regid'");
        		$fromuser->email = $CFG->siteemail;
        		$fromuser->fname = $CFG->sitename;
        		$fromuser->lname = "";
        		$message = registration_email($regid, $touser);
        		if(send_email($touser,$fromuser,null,"Father's Coaching Weekend Registration", $message)){
        			send_email($fromuser,$fromuser,null,"Father's Coaching Weekend Registration", $message);
        		}
                
				echo '<br />To finish the registration process, please make out a <br />
				check or money order in the amount of <span style="color:blue;font-size:1.25em;">$'.number_format($event['fee_full'],2).'</span> payable to <strong>'.$event["payableto"].'</strong> and pay <br />
                when you show up to register.';
			}
		}else{ // Support for a free event
            echo '<div style="margin:auto;width:90%;text-align:center;"><h3>You have successfully registered '.$MYVARS->GET["Name_First"].' for '.$event['name'] . '.</h3>';
            execute_db_sql("UPDATE events_registrations SET verified='1' WHERE regid='$regid'");
                
            //Send registration email
            $touser->fname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Name_First'");
    		$touser->lname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Name_Last'");
    		$touser->email = get_db_field("email","events_registrations","regid='$regid'");
    		$fromuser->email = $CFG->siteemail;
    		$fromuser->fname = $CFG->sitename;
    		$fromuser->lname = "";
    		$message = registration_email($regid, $touser);
    		if(send_email($touser,$fromuser,null,"Father's Coaching Weekend Registration", $message)){
    			send_email($fromuser,$fromuser,null,"Father's Coaching Weekend Registration", $message);
    		}
        }
		
        //Facebook share button
        echo '<br />' . facebook_share_button($eventid,$MYVARS->GET["Name_First"],$keys);
        
        echo '</div>';
	}else{ //failed registration
		$MYVARS->GET["cart_total"] = $MYVARS->GET["cart_total"] - $MYVARS->GET["owed"];
		echo '<div style="text-align:center;"><div style="width:60%;margin:auto;"><span class="error_text">Your registration for '.$event['name'].' has failed. </span><br /> '.$error . '</div>';	
		if(isset($MYVARS->GET["items"])){ //other registrations have already occured
			if($event['fee_full'] != 0){
                if(!empty($MYVARS->GET["items"])){
                    $items = $MYVARS->GET["items"];
    				echo '<div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="'.$MYVARS->GET["cart_total"].'" />
    					 <input type="hidden" name="items" id="items" value="'.$items.'" /></div>';
    				
    				$items = explode("**",$items);
                    $i=0;
                    foreach($items as $item){
    					$itm = explode("::",$item);
    					$cart_items[$i]->regid = $itm[0];
    					$cart_items[$i]->description = $itm[1];
    					$cart_items[$i]->cost = $itm[2];                    
                        $i++;
                    }
    				
    				if($MYVARS->GET['payment_method'] == "PayPal"){
    					echo '<br />
    					If you would like to pay the <span style="color:blue;font-size:1.25em;">$'.$MYVARS->GET["cart_total"].'</span> fee now, click the Paypal button below.
    					<br /><br />
                        <div style="text-align:center;">
    					   '.make_paypal_button($cart_items,$event['paypal']).'
    					</div>';	
    				}else{ //Pay by check
    					echo '<br />
    					If you are done with the registration process, please make out your <br />
    					check or money order in the amount of <span style="color:blue;font-size:1.25em;">$'.$MYVARS->GET["cart_total"].'</span> payable to <b>'.$event["payableto"].'</b> and send it to <br /><br />
    					<br />
                        <div style="text-align:center;">
    					   '.$event['checksaddress'].'.  
    					</div>';
    				}
                    
                    echo "<br /><br /><strong>Thank you for registering for this event.</strong>";	                   
                }
			}
		}
        echo "</div>";
	}
}
?>