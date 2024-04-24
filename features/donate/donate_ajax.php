<?php
/***************************************************************************
* donate_ajax.php - donate feature ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/09/2013
* Revision: 1.0.7
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); } 
if (!isset($donateLIB)) { include_once($CFG->dirroot . '/features/donate/donatelib.php'); }

update_user_cookie();

callfunction();

function select_campaign_form() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']); 
    echo select_campaign_forms($featureid, $pageid);  
}

function add_or_manage_form() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']); 
    echo add_or_manage_forms($featureid, $pageid);  
}

function new_campaign_form() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']); 
    $campaign_id = empty($MYVARS->GET['campaign_id']) ? false : dbescape($MYVARS->GET['campaign_id']);
    if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }   
    
    $c = $campaign_id ? get_db_row("SELECT * FROM donate_campaign WHERE campaign_id='$campaign_id'") : false;
    $title = $campaign_id ? $c["title"] : "";
    $goal = $campaign_id ? number_format($c["goal_amount"],2,".", "") : "";
    $description = $campaign_id ? $c["goal_description"] : "";
    $email = $campaign_id ? $c["paypal_email"] : "";
    $token = $campaign_id ? $c["token"] : "";
    $no_selected = $campaign_id ? ($c["shared"] == "1" ? "" : "selected") : "";
    $yes_selected = $campaign_id ? ($c["shared"] == "1" ? "selected" : "") : "";
    $startoredit = $campaign_id ? "Edit" : "Start";
    
    echo '
        <style>
            .rowContainer label{
                margin: 5px;
                display: inline-block;
                min-width: 150px;
                vertical-align: top;
            }
            .rowContainer input, .rowContainer textarea, .rowContainer select{
                float: initial;
                margin-right: 0px;
            }
            .info {
                margin: -5px 2px 10px 4px;
                float: initial;
            }
            .tooltipContainer {
                padding: 10px 20px 7px 20px;
            }
        </style>';
        
    echo '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'select_campaign_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function() { simple_display(\'donation_display\'); });">Back</a><br /><br />';   
    $content = '
            <div class="formDiv" id="new_campaign_div">
    		<form id="campaign_form">
    			<fieldset class="formContainer">
    				<div class="rowContainer">
    					<label for="title">Campaign Name</label><input type="text" id="title" name="title" value="'.$title.'" data-rule-required="true" data-msg-required="'.get_error_message('donate_req_title:donate').'" /><div class="tooltipContainer info">'.get_help("donate_title:donate").'</div><br />
    				</div>
                    <div class="rowContainer">
    					<label for="title">Goal Amount $</label><input type="text" id="goal" name="goal" value="'.$goal.'" data-rule-required="true" data-rule-number="true"  data-rule-min="0" data-msg-required="'.get_error_message('donate_req_goal:donate').'" /><div class="tooltipContainer info">'.get_help("donate_goal:donate").'</div><br />
    	  			</div>
    				<div class="rowContainer">
    					<label for="description">Goal Description</label><textarea type="text" id="description" name="description" data-rule-required="true" data-msg-required="'.get_error_message('donate_req_description:donate').'">'.$description.'</textarea><div class="tooltipContainer info">'.get_help("donate_description:donate").'</div><br />
    	  			</div>
      				<div class="rowContainer">
    					<label for="email">Paypal Email Address</label><input type="text" id="email" name="email" value="'.$email.'" data-rule-required="true" data-rule-email="true" data-msg-required="'.get_error_message('valid_req_email').'" data-msg-email="'.get_error_message('valid_email_invalid').'" /><div class="tooltipContainer info">'.get_help("donate_paypal_email:donate").'</div><br />
    				</div>
                    <div class="rowContainer">
    					<label for="email">Paypal PDT token</label><input type="text" id="token" name="token" value="'.$token.'" data-rule-required="true" data-msg-required="'.get_error_message('donate_req_token:donate').'" /><div class="tooltipContainer info">'.get_help("donate_token:donate").'</div><br />
    				</div>
    		  		<div class="rowContainer">
                        <label for="shared">Share Campaign</label>
                        <select id="shared" name="shared" data-rule-required="true">
                            <option value="">Select One...</option>
                            <option value="0" '.$no_selected.'>Not Shared</option>
                            <option value="1" '.$yes_selected.'>Shared</option>
                        </select>
                        <div class="tooltipContainer info">'.get_help("donate_shared:donate").'</div><br />
                    </div>
                    <br />
                    <input class="submit" name="submit" type="submit" value="'.$startoredit.' Campaign" style="margin: auto;display:block;clear:both;" />
                    <div id="error_div"></div>	
    			</fieldset>
    		</form>
    	</div>';
    echo '<div id="donation_script" style="display:none">' . create_validation_script("campaign_form" , "ajaxapi('/features/donate/donate_ajax.php','add_new_campaign','&campaign_id=$campaign_id&featureid=$featureid&pageid=$pageid&email=' + escape($('#email').val()) + '&token=' + escape($('#token').val()) + '&title=' + escape($('#title').val()) + '&goal=' + escape($('#goal').val()) + '&description=' + escape($('#description').val()) + '&shared=' + escape($('#shared').val()),function() { var returned = trim(xmlHttp.responseText).split('**'); if (returned[0] == 'true') { $('#new_campaign_div').html(returned[1]);} else { $('#error_div').html(returned[1])}});", true) . "</div>";
    echo format_popup($content,'Start a Donation Campaign',"380px");
}

function add_new_campaign() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']); 
    $campaign_id = empty($MYVARS->GET['campaign_id']) ? false : dbescape($MYVARS->GET['campaign_id']);
    $goal = dbescape($MYVARS->GET['goal']); $description = dbescape($MYVARS->GET['description']);
    $email = dbescape($MYVARS->GET['email']); $token = dbescape($MYVARS->GET['token']);
    $title = dbescape($MYVARS->GET['title']); $shared = dbescape($MYVARS->GET['shared']);
    
    if ($campaign_id) { //UPDATE
        $SQL = "UPDATE donate_campaign SET title='$title',goal_amount='$goal',goal_description='$description',paypal_email='$email',token='$token',shared='$shared' WHERE campaign_id='$campaign_id'";
        if (execute_db_sql($SQL)) { //edit made
            echo "true**<h1>Campaign Edited</h1>";
        } else {
            echo "false**An error has occurred, please try again later.";    
        }         
    } else { //INSERT NEW
        $SQL = "INSERT INTO donate_campaign (origin_page,title,goal_amount,goal_description,paypal_email,token,shared,datestarted,metgoal) VALUES('$pageid','$title','$goal','$description','$email','$token','$shared','".get_timestamp()."','0')";
        if ($campaign_id = execute_db_sql($SQL)) { //New campaign made
            //Save campaign ID in instance
            execute_db_sql("UPDATE donate_instance SET campaign_id=$campaign_id WHERE donate_id=$featureid");
            echo "true**<h1>Campaign Started</h1>";
        } else {
            echo "false**An error has occurred, please try again later.";    
        }    
    }
    
}


function join_campaign_form() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
    echo '<center><h1>Join a Campaign</h1></center>';
    echo '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'select_campaign_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function() { simple_display(\'donation_display\'); });">Back</a><br /><br />';   
    $SQL = "SELECT * FROM donate_campaign WHERE origin_page='$pageid' AND campaign_id NOT IN (SELECT campaign_id FROM donate_instance WHERE donate_id IN (SELECT featureid FROM pages_features WHERE pageid='$pageid' AND feature='donate')) OR shared='1'";
    if ($result = get_db_result($SQL)) {
        echo '<select id="campaign_id">';
        while ($row = fetch_row($result)) {
            echo '<option value="'.$row["campaign_id"].'">'.$row["title"]."</option>";    
        }
        echo '</select> <button onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'join_campaign\',\'&campaign_id=\'+$(\'#campaign_id\').val()+\'&featureid='.$featureid.'&pageid='.$pageid.'\',function() { simple_display(\'donation_display\'); });">Join Campaign</button>';
    } else {
        echo "There are no active campaigns available.";
    }
}

function join_campaign() {
global $CFG, $MYVARS, $USER;    
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
    $campaign_id = dbescape($MYVARS->GET['campaign_id']); 
    
    if ($campaign_id) { //Campaign ID chosen
        //Save campaign ID in instance
        execute_db_sql("UPDATE donate_instance SET campaign_id=$campaign_id WHERE donate_id=$featureid");
        echo "<h1>Campaign Joined</h1>
                You can now accept donations for your chosen campaign.
             ";
    } else {
        echo "Could not join campaign.";    
    }    
}

function add_offline_donations_form() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']); 
    if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }   
    echo '
        <style>
            .rowContainer label{
                margin: 5px;
            }
            .rowContainer input{
                margin-right: 0px;
            }
            .info {
                margin: -5px 2px 10px 4px;
            }
            .tooltipContainer {
                padding: 10px 20px 7px 20px;
            }
        </style>';
        
    echo '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'add_or_manage_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function() { simple_display(\'donation_display\'); });">Back</a><br /><br />';   
    $content = '
            <div class="formDiv" id="new_donation_div">
    		<form id="donation_form">
    			<fieldset class="formContainer">
                    <div class="rowContainer">
    					<label for="amount">Donation Amount $</label><input type="text" id="amount" name="amount" value="0.00" data-rule-required="true" data-rule-number="true" data-rule-min="0.01" data-msg-required="'.get_error_message('donate_req_amount:donate').'" data-msg-min="'.get_error_message('donate_req_min:donate').'" /><div class="tooltipContainer info">'.get_help("donate_amount:donate").'</div><br />
    	  			</div>
    				<div class="rowContainer">
    					<label for="name">Name</label><input type="text" id="name" name="name" value="Anonymous" /><div class="tooltipContainer info">'.get_help("donate_name:donate").'</div><br />
    				</div>
                    <br />
                    <input class="submit" name="submit" type="submit" value="Add Donation" style="margin: auto;display:block;" />
                    <div id="error_div"></div>	
    			</fieldset>
    		</form>
    	</div>';
    echo '<div id="donation_script" style="display:none">' . create_validation_script("donation_form" , "ajaxapi('/features/donate/donate_ajax.php','add_offline_donation','&featureid=$featureid&pageid=$pageid&amount=' + escape($('#amount').val()) + '&name=' + escape($('#name').val()),function() { var returned = trim(xmlHttp.responseText).split('**'); if (returned[0] == 'true') { $('#donation_display').html(returned[1]);} else { $('#error_div').html(returned[1])}});", true) . "</div>";
    echo format_popup($content,'Start a Donation Campaign',"380px");    
}

function add_offline_donation() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']); 
    $amount = dbescape(number_format($MYVARS->GET['amount'],2,".", "")); $name = $MYVARS->GET['name'];
    
    $campaign = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id IN (SELECT campaign_id FROM donate_instance WHERE donate_id='$featureid')");	
	
    $name = $name == "" || strtolower($name) == "anonymous" ? "" : dbescape($name);
    $SQL = "INSERT INTO donate_donations (campaign_id,name,paypal_TX,amount,timestamp) VALUES('".$campaign["campaign_id"]."','$name','Offline','$amount','".get_timestamp()."')";
    execute_db_sql($SQL);   
    
    echo "true**".add_or_manage_forms($featureid, $pageid);
}

function manage_donations_form() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']); 
    $content = '';
    echo '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'add_or_manage_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function() { simple_display(\'donation_display\'); });">Back</a><br /><br />';   
    $content .= '<div style="max-height:340px;overflow-x:hidden;overflow-y:auto;">';
    $campaign = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id IN (SELECT campaign_id FROM donate_instance WHERE donate_id='$featureid')");	    
    if ($result = get_db_result("SELECT * FROM donate_donations WHERE campaign_id='".$campaign["campaign_id"]."' ORDER BY timestamp DESC")) {
        $content .= '<table style="font-size: 10px;width:100%;border-collapse: collapse;">
                    <tr><td style="width:55px"><strong>Type</strong></td><td><strong>Name</strong></td><td><strong>Amount</strong></td><td style="width:80px"><strong>Date</strong></td><td><strong>Paypal TX</strong></td><td style="width:20px"></td><td style="width:20px"></td></tr>';
        $i = 1;
        while ($row = fetch_row($result)) {
            $bg = $i % 2 == 0 ? "silver" : "white";
            $type = $row["paypal_TX"] == "Offline" ? "Offline" : "Paypal";
            $tx = $row["paypal_TX"] == "Offline" ? "--" : $row["paypal_TX"];
            $name = $row["name"] == "" ? "Anonymous" : $row["name"];
            
            //Edit and Delete buttons
            $edit = 'ajaxapi(\'/features/donate/donate_ajax.php\',\'edit_donation_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'&donationid='.$row["donationid"].'\',function() { simple_display(\'donation_display\'); loaddynamicjs(\'donation_script\'); });';
            $delete = 'if (confirm(\'Are you sure you want to delete this donation record?\')) { ajaxapi(\'/features/donate/donate_ajax.php\',\'delete_donation\',\'&featureid='.$featureid.'&pageid='.$pageid.'&donationid='.$row["donationid"].'\',function() { simple_display(\'donation_display\'); }); }';
            
            $content .= '
                <tr style="border:1px solid gainsboro;background-color: '.$bg.'">
                    <td>'.$type.'</td>
                    <td>'.$name.'</td>
                    <td>$'.number_format($row["amount"],2,".", "").'</td>
                    <td>'.date('m/d/Y', $row["timestamp"]+get_offset()).'</td>
                    <td>'.$tx.'</td>
                    <td><a href="javascript: void(0);" onclick="'.$edit.'"><img src="'.$CFG->wwwroot.'/images/edit.png" /></a></td>
                    <td><a href="javascript: void(0);" onclick="'.$delete.'"><img src="'.$CFG->wwwroot.'/images/delete.png" /></a></td>
                </tr>';       
            $i++; 
        }    
        $content .= '</table>';
    } else {
        $content .= 'No donations have been made yet.';
    }
    
    $content .= '</div>';
    echo format_popup($content,'Manage Donations',"380px");    
}

function edit_donation_form() {
global $CFG, $MYVARS, $USER;
    $donationid = dbescape($MYVARS->GET['donationid']);  
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']); 
    if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }
      
    $row = get_db_row("SELECT * FROM donate_donations WHERE donationid='$donationid'");
       
    echo '
        <style>
            .rowContainer label{
                margin: 5px;
            }
            .rowContainer input{
                margin-right: 0px;
            }
            .info {
                margin: -5px 2px 10px 4px;
            }
            .tooltipContainer {
                padding: 10px 20px 7px 20px;
            }
        </style>';
        
    echo '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'manage_donations_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function() { simple_display(\'donation_display\'); });">Back</a><br /><br />';   
    $content = '
            <div class="formDiv" id="new_donation_div">
    		<form id="donation_form">
    			<fieldset class="formContainer">
                    <div class="rowContainer">
                        <label for="campaign_id">Donated to:</label>
                        <select id="campaign_id" name="campaign_id" data-rule-required="true">';
                        if ($result = get_db_result("SELECT * FROM donate_campaign WHERE shared=1 OR campaign_id='".$row["campaign_id"]."'")) {
                            $selected = $row["campaign_id"];
                            while ($c = fetch_row($result)) {
                                $select = $selected == $c["campaign_id"] ? "selected" : "";
                                $content .= '<option value="'.$c["campaign_id"].'" '.$select.'>'.$c["title"].'</option>';    
                            }
                        }  
    $content .= '       </select>
                        <div class="tooltipContainer info">'.get_help("donate_campaign:donate").'</div><br />
                    </div>
    
                    <div class="rowContainer">
    					<label for="amount">Donation Amount $</label><input type="text" id="amount" name="amount" value="'.number_format($row["amount"],2,".", "").'" data-rule-required="true" data-rule-number="true" data-rule-min="0.01" data-msg-required="'.get_error_message('donate_req_amount:donate').'" data-msg-min="'.get_error_message('donate_req_min:donate').'" /><div class="tooltipContainer info">'.get_help("donate_amount:donate").'</div><br />
    	  			</div>
    				<div class="rowContainer">
    					<label for="name">Name</label><input type="text" id="name" name="name" value="'.$row["name"].'" /><div class="tooltipContainer info">'.get_help("donate_name:donate").'</div><br />
    				</div>
                    <div class="rowContainer">
    					<label for="paypal_TX">Paypal TX</label><input type="text" id="paypal_TX" paypal_TX="name" value="'.$row["paypal_TX"].'" /><div class="tooltipContainer info">'.get_help("donate_paypaltx:donate").'</div><br />
    				</div>
                    <br />
                    <input class="submit" name="submit" type="submit" value="Save" style="margin: auto;display:block;" />
                    <div id="error_div"></div>	
    			</fieldset>
    		</form>
    	</div>';
    echo '<div id="donation_script" style="display:none">' . create_validation_script("donation_form" , "ajaxapi('/features/donate/donate_ajax.php','edit_donation_save','&donationid=$donationid&featureid=$featureid&pageid=$pageid&amount=' + escape($('#amount').val()) + '&name=' + escape($('#name').val()) + '&campaign_id=' + escape($('#campaign_id').val()) + '&paypal_TX=' + escape($('#paypal_TX').val()),function() { var returned = trim(xmlHttp.responseText).split('**'); if (returned[0] == 'true') { $('#donation_display').html(returned[1]);} else { $('#error_div').html(returned[1])}});", true) . "</div>";
    echo format_popup($content,'Edit Donation',"380px");    
}

function edit_donation_save() {
global $CFG, $MYVARS, $USER;
    $featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
    $donationid = dbescape($MYVARS->GET['donationid']);$name = $MYVARS->GET['name']; $campaign_id = dbescape($MYVARS->GET['campaign_id']); 
    $paypal_TX = $MYVARS->GET['paypal_TX']; $amount = dbescape(number_format($MYVARS->GET['amount'],2,".", ""));
    
    $name = $name == "" || strtolower($name) == "anonymous" ? "" : dbescape($name);
    $paypal_TX = $paypal_TX == "" || strtolower($paypal_TX) == "offline" ? "Offline" : dbescape($paypal_TX);
    
    execute_db_sql("UPDATE donate_donations SET amount='$amount', name='$name',paypal_TX='$paypal_TX',campaign_id='$campaign_id' WHERE donationid='$donationid'");
    echo "true**";
    manage_donations_form();    
}

function delete_donation() {
global $CFG, $MYVARS, $USER;
    $donationid = dbescape($MYVARS->GET['donationid']);
    execute_db_sql("DELETE FROM donate_donations WHERE donationid='$donationid'");
    manage_donations_form();     
}
?>