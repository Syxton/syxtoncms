<?php
/***************************************************************************
* donate_ajax.php - donate feature ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.0.7
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

if (!defined('DONATELIB')) { include_once($CFG->dirroot . '/features/donate/donatelib.php'); }

update_user_cookie();

callfunction();

function select_campaign_form() {
global $CFG, $MYVARS, $USER;
	 $featureid = clean_myvar_opt("featureid", "int", false); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	 echo select_campaign_forms($featureid, $pageid);
}

function add_or_manage_form() {
global $CFG, $MYVARS, $USER;
	 $featureid = clean_myvar_opt("featureid", "int", false); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	 echo add_or_manage_forms($featureid, $pageid);
}

function new_campaign_form() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_req("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$campaign_id = clean_myvar_opt("campaign_id", "int", false);

	if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$title = $goal = $description = $email = $token = $button = $yes_selected = $no_selected = "";
	if ($campaign_id) { // Editing a campaign.
		if ($c = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id = ||campaign_id||", ["campaign_id" => $campaign_id])) {
			$button = "Edit";
			$title = $c["title"];
			$goal = number_format($c["goal_amount"], 2, ".", "");
			$description = $c["goal_description"];
			$email = $c["paypal_email"];
			$token = $c["token"];
			$no_selected = $c["shared"] == "1" ? "" : "selected";
			$yes_selected =$c["shared"] == "1" ? "selected" : "";
		}
	} else {
		$button = "Start";
	}

	echo fill_template("tmp/donate.template", "back_to_campaign_form", "donate", ["featureid" => $featureid, "pageid" => $pageid]);
	$params = [
		"title" => $title,
		"titlereq" => error_string('donate_req_title:donate'),
		"titlehelp" => get_help("donate_title:donate"),
		"goal" => $goal,
		"goalreq" => error_string('donate_req_goal:donate'),
		"goalhelp" => get_help("donate_goal:donate"),
		"description" => $description,
		"descreq" => error_string('donate_req_description:donate'),
		"deschelp" => get_help("donate_description:donate"),
		"email" => $email,
		"emailreq" => error_string('valid_req_email'),
		"emailerror" => error_string('valid_email_invalid'),
		"emailhelp" => get_help("donate_paypal_email:donate"),
		"token" => $token,
		"tokenreq" => error_string('donate_req_token:donate'),
		"tokenhelp" => get_help("donate_token:donate"),
		"noselected" => $no_selected,
		"yesselected" => $yes_selected,
		"sharedhelp" => get_help("donate_shared:donate"),
		"button" => $button,
		"validationscript" => create_validation_script("campaign_form", "ajaxapi_old('/features/donate/donate_ajax.php','add_new_campaign','&campaign_id=$campaign_id&featureid=$featureid&pageid=$pageid&email=' + encodeURIComponent($('#email').val()) + '&token=' + encodeURIComponent($('#token').val()) + '&title=' + encodeURIComponent($('#title').val()) + '&goal=' + encodeURIComponent($('#goal').val()) + '&description=' + encodeURIComponent($('#description').val()) + '&shared=' + encodeURIComponent($('#shared').val()),function() { var returned = trim(xmlHttp.responseText).split('**'); if (returned[0] == 'true') { $('#new_campaign_div').html(returned[1]);} else { $('#error_div').html(returned[1])}});", true),
	];
	$content = fill_template("tmp/donate.template", "add_edit_form", "donate", $params);
	echo format_popup($content, "Start a Donation Campaign", "auto", "0");
}

function add_new_campaign() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$campaign_id = clean_myvar_opt("campaign_id", "int", false);
	$goal = clean_myvar_opt("goal", "float", 0.00);
	$description = clean_myvar_opt("description", "string", "");
	$email = clean_myvar_opt("email", "string", "");
	$token = clean_myvar_opt("token", "string", "");
	$title = clean_myvar_opt("title", "string", "");
	$shared = clean_myvar_opt("shared", "int", 0);

	$params = ["pageid" => $pageid, "title" => $title, "goal" => $goal, "description" => $description, "email" => $email, "token" => $token, "shared" => $shared, "datestarted" => get_timestamp(), "metgoal" => 0];

	if ($campaign_id) { // UPDATE
		$params["campaign_id"] = $campaign_id;
		$SQL = fetch_template("dbsql/donate.sql", "update_campaign", "donate");
		if (execute_db_sql($SQL, $params)) { // Edit made
			echo "true**<h1>Campaign Edited</h1>";
		} else {
			echo "false**An error has occurred, please try again later.";
		}
	 } else { // INSERT NEW
		$SQL = fetch_template("dbsql/donate.sql", "insert_campaign", "donate");
		if ($campaign_id = execute_db_sql($SQL, $params)) { //New campaign made
			//Save campaign ID in instance
			execute_db_sql(fetch_template("dbsql/donate.sql", "save_campaignid", "donate"), ["campaign_id" => $campaign_id, "donate_id" => $featureid]);
			echo "true**<h1>Campaign Started</h1>";
		} else {
			echo "false**An error has occurred, please try again later.";
		}
	}
}


function join_campaign_form() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	$content = fill_template("tmp/donate.template", "back_to_campaign_form", "donate", ["featureid" => $featureid, "pageid" => $pageid]);
	$content .= '<center><h1>Join a Campaign</h1></center>';
	$SQL = "SELECT * FROM donate_campaign WHERE origin_page='$pageid' AND campaign_id NOT IN (SELECT campaign_id FROM donate_instance WHERE donate_id IN (SELECT featureid FROM pages_features WHERE pageid='$pageid' AND feature='donate')) OR shared='1'";
	if ($result = get_db_result($SQL)) {
		$content .= '<select id="campaign_id">';
		while ($row = fetch_row($result)) {
			$content .= '<option value="' . $row["campaign_id"] . '">' . $row["title"] . "</option>";
		}
		$content .= '</select> <button onclick="ajaxapi_old(\'/features/donate/donate_ajax.php\',\'join_campaign\',\'&campaign_id=\'+$(\'#campaign_id\').val()+\'&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); });">Join Campaign</button>';
	} else {
		$content .= '<br /><br /><div style="text-align:center;">There are no active campaigns available.</div>';
	}
	echo $content;
}

function join_campaign() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$campaign_id = clean_myvar_opt("campaign_id", "int", false);

	if ($campaign_id) { //Campaign ID chosen
		//Save campaign ID in instance
		execute_db_sql(fetch_template("dbsql/donate.sql", "save_campaignid", "donate"), ["campaign_id" => $campaign_id, "donate_id" => $featureid]);
		echo "<h1>Campaign Joined</h1>
					You can now accept donations for your chosen campaign.
				";
	} else {
		echo "Could not join campaign.";
	}
}

function add_offline_donations_form() {
global $CFG, $MYVARS, $USER;
	 $featureid = clean_myvar_opt("featureid", "int", false);
	 $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	 if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	 echo '<a class="buttonlike" style="position: absolute;" href="javascript: void(0);" onclick="ajaxapi_old(\'/features/donate/donate_ajax.php\',\'add_or_manage_form\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); });">Back</a>';
	 $content = '
				<div class="formDiv" id="new_donation_div">
  			<form id="donation_form">
  				<fieldset class="formContainer">
					<div class="rowContainer">
  						<label class="rowTitle" for="amount">Donation Amount $</label>
						<input type="text" id="amount" name="amount" value="0.00" data-rule-required="true" data-rule-number="true" data-rule-min="0.01" data-msg-required="' . error_string('donate_req_amount:donate') . '" data-msg-min="' . error_string('donate_req_min:donate') . '" />
						<div class="tooltipContainer info">' . get_help("donate_amount:donate") . '</div>
					</div>
  					<div class="rowContainer">
  						<label class="rowTitle" class="rowTitle" for="name">Name</label>
						<input type="text" id="name" name="name" value="Anonymous" />
						<div class="tooltipContainer info">' . get_help("donate_name:donate") . '</div>
  					</div>
						  <br />
						  <input class="submit" name="submit" type="submit" value="Add Donation" style="margin: auto;display:block;" />
						  <div id="error_div"></div>
  				</fieldset>
  			</form>
  		</div>';
	 echo '<div id="donation_script" style="display:none">' . create_validation_script("donation_form" , "ajaxapi_old('/features/donate/donate_ajax.php','add_offline_donation','&featureid=$featureid&pageid=$pageid&amount=' + encodeURIComponent($('#amount').val()) + '&name=' + encodeURIComponent($('#name').val()),function() { var returned = trim(xmlHttp.responseText).split('**'); if (returned[0] == 'true') { $('#donation_display').html(returned[1]);} else { $('#error_div').html(returned[1])}});", true) . "</div>";
	 echo format_popup($content, "Start a Donation Campaign", "auto", "0");
}

function add_offline_donation() {
	$featureid = clean_myvar_opt("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$name = clean_myvar_opt("name", "string", "Anonymous");
	$amount = number_format(clean_myvar_opt("amount", "float", 0.00), 2, ".", "");

	$campaign = get_db_row(fetch_template("dbsql/donate.sql", "get_donation_campaign", "donate"), ["donate_id" => $featureid]);

	$params = [];
	$params["campaign_id"] = $campaign["campaign_id"];
	$params["name"] = $name;
	$params["amount"] = $amount;
	$params["paypal_TX"] = 'Offline';
	$params["timestamp"] = get_timestamp();
	$SQL = fetch_template("dbsql/donate.sql", "insert_donation", "donate");
	execute_db_sql($SQL, $params);

	echo "true**" . add_or_manage_forms($featureid, $pageid);
}

function manage_donations_form() {
global $CFG, $MYVARS, $USER;
	 $featureid = clean_myvar_opt("featureid", "int", false);
	 $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	 $content = '';
	 echo '<a class="buttonlike" style="position: absolute;" href="javascript: void(0);" onclick="ajaxapi_old(\'/features/donate/donate_ajax.php\',\'add_or_manage_form\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); });">Back</a>';
	 $content .= '<div>';
	 $campaign = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id IN (SELECT campaign_id FROM donate_instance WHERE donate_id='$featureid')");
	 if ($result = get_db_result("SELECT * FROM donate_donations WHERE campaign_id='" . $campaign["campaign_id"] . "' ORDER BY timestamp DESC")) {
		  $content .= '<table class="donation_table">
						  <tr><th style="width:55px"><strong>Type</strong></th><th><strong>Name</strong></th><th><strong>Amount</strong></th><th style="width:80px"><strong>Date</strong></th><th><strong>Paypal TX</strong></th><th style="width:20px"></th><th style="width:20px"></td></tr>';
		  $i = 1;
		  while ($row = fetch_row($result)) {
				$type = $row["paypal_TX"] == "Offline" ? "Offline" : "Paypal";
				$tx = $row["paypal_TX"] == "Offline" ? "--" : $row["paypal_TX"];
				$name = $row["name"] == "" ? "Anonymous" : $row["name"];

				//Edit and Delete buttons
				$edit = 'ajaxapi_old(\'/features/donate/donate_ajax.php\',\'edit_donation_form\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '&donationid=' . $row["donationid"] . '\',function() { simple_display(\'donation_display\'); loaddynamicjs(\'donation_script\'); });';
				$delete = 'if (confirm(\'Are you sure you want to delete this donation record?\')) { ajaxapi_old(\'/features/donate/donate_ajax.php\',\'delete_donation\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '&donationid=' . $row["donationid"] . '\',function() { simple_display(\'donation_display\'); }); }';

				$content .= '
					 <tr>
						  <td>' . $type . '</td>
						  <td>' . $name . '</td>
						  <td>$' . number_format($row["amount"],2,".", "") . '</td>
						  <td>' . date('m/d/Y', $row["timestamp"]+get_offset()) . '</td>
						  <td>' . $tx . '</td>
						  <td><button title="Edit Donation" class="alike" onclick="' . $edit . '">' . icon("pencil") . '</button></td>
						  <td><button title="Delete Donation" class="alike" onclick="' . $delete . '">' . icon("trash") . '</button></td>
					 </tr>';
				$i++;
		  }
		  $content .= '</table>';
	 } else {
		  $content .= 'No donations have been made yet . ';
	 }

	 $content .= '</div>';
	 echo format_popup($content, 'Manage Donations', "auto", "0");
}

function edit_donation_form() {
global $CFG, $MYVARS, $USER;
	$donationid = clean_myvar_req("donationid", "int");
	$featureid = clean_myvar_opt("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	 if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	 $row = get_db_row("SELECT * FROM donate_donations WHERE donationid='$donationid'");
		echo '<a class="buttonlike" style="position: absolute;" href="javascript: void(0);" onclick="ajaxapi_old(\'/features/donate/donate_ajax.php\',\'manage_donations_form\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); });">Back</a>';
		$content = '
				<div class="formDiv" id="new_donation_div">
  			<form id="donation_form">
  				<fieldset class="formContainer">
					<div class="rowContainer">
						<label class="rowTitle" for="campaign_id">Donated to:</label>
						<select id="campaign_id" name="campaign_id" data-rule-required="true">';
						if ($result = get_db_result("SELECT * FROM donate_campaign WHERE shared=1 OR campaign_id='" . $row["campaign_id"] . "'")) {
								$selected = $row["campaign_id"];
								while ($c = fetch_row($result)) {
									$select = $selected == $c["campaign_id"] ? "selected" : "";
									$content .= '<option value="' . $c["campaign_id"] . '" ' . $select . '>' . $c["title"] . '</option>';
								}
						}
		$content .= '</select>
						<div class="tooltipContainer info">' . get_help("donate_campaign:donate") . '</div><br />
					</div>
					<div class="rowContainer">
						<label class="rowTitle" for="amount">Donation Amount $</label>
						<input type="text" id="amount" name="amount" value="' . number_format($row["amount"],2,".", "") . '" data-rule-required="true" data-rule-number="true" data-rule-min="0.01" data-msg-required="' . error_string('donate_req_amount:donate') . '" data-msg-min="' . error_string('donate_req_min:donate') . '" />
						<div class="tooltipContainer info">' . get_help("donate_amount:donate") . '</div><br />
					</div>
					<div class="rowContainer">
						<label class="rowTitle" for="name">Name</label>
						<input type="text" id="name" name="name" value="' . $row["name"] . '" />
						<div class="tooltipContainer info">' . get_help("donate_name:donate") . '</div><br />
					</div>
					<div class="rowContainer">
						<label class="rowTitle" for="paypal_TX">Paypal TX</label>
						<input type="text" id="paypal_TX" paypal_TX="name" value="' . $row["paypal_TX"] . '" />
						<div class="tooltipContainer info">' . get_help("donate_paypaltx:donate") . '</div><br />
					</div>
					<br />
					<input class="submit" name="submit" type="submit" value="Save" style="margin: auto;display:block;" />
					<div id="error_div"></div>
  				</fieldset>
  			</form>
  		</div>';
	 echo '<div id="donation_script" style="display:none">' . create_validation_script("donation_form" , "ajaxapi_old('/features/donate/donate_ajax.php','edit_donation_save','&donationid=$donationid&featureid=$featureid&pageid=$pageid&amount=' + encodeURIComponent($('#amount').val()) + '&name=' + encodeURIComponent($('#name').val()) + '&campaign_id=' + encodeURIComponent($('#campaign_id').val()) + '&paypal_TX=' + encodeURIComponent($('#paypal_TX').val()),function() { var returned = trim(xmlHttp.responseText).split('**'); if (returned[0] == 'true') { $('#donation_display').html(returned[1]);} else { $('#error_div').html(returned[1])}});", true) . "</div>";
	 echo format_popup($content, "Edit Donation", "auto", "0");
}

function edit_donation_save() {
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$donationid = clean_myvar_req("donationid", "int");
	$featureid = clean_myvar_opt("featureid", "int", false);
	$name = clean_myvar_opt("name", "string", "Anonymous");
	$campaign_id = clean_myvar_opt("campaign_id", "int", false);
	$paypal_TX = clean_myvar_opt("paypal_TX", "string", "Offline");
	$amount = clean_myvar_opt("amount", "float", 0.00);

	$params = ["amount" => $amount, "name" => $name, "paypal_TX" => $paypal_TX, "campaign_id" => $campaign_id, "donationid" => $donationid];
	execute_db_sql(fetch_template("dbsql/donate.sql", "update_donation", "donate"), $params);
	echo "true**";
	manage_donations_form();
}

function delete_donation() {
	$donationid = clean_myvar_req("donationid", "int");
	execute_db_sql(fetch_template("dbsql/donate.sql", "delete_donation", "donate"), ["donationid" => $donationid]);
	manage_donations_form();
}
?>