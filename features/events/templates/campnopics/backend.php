<?php
/***************************************************************************
 * backend.php - Backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 12/05/07
 * $Revision: .6
 ***************************************************************************/
if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'config.php')) {
		$sub .= '../';
	}
	include($sub . 'config.php');
}
include($CFG->dirroot . '/pages/header.php');
if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

//Retrieve from Javascript
$postorget = isset($_GET["action"]) ? $_GET : $_POST;
$postorget = isset($postorget["action"]) ? $postorget : "";

$MYVARS->GET = $postorget;
if ($postorget != "") {
	$action = $postorget["action"];
	$action(); //Go to the function that was called.
}

update_user_cookie();

function register() {
global $CFG, $MYVARS, $USER, $error;

if (!defined('COMLIB')) include_once($CFG->dirroot . '/lib/comlib.php');

	$eventid = clean_myvar_req("eventid", "int");
	$event = get_event($eventid);
	$template = get_event_template($event['template_id']);

	$MYVARS->GET["cart_total"] = $MYVARS->GET["total_owed"] != 0 ? $MYVARS->GET["total_owed"] + $MYVARS->GET["paypal_amount"] : $MYVARS->GET["paypal_amount"];
	$MYVARS->GET["total_owed"] = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];

	$formlist = explode(";", $template['formlist']);

	$i=0;
	while (isset($formlist[$i])) {
		$element = explode(":", $formlist[$i]);
		$reg[$element[0]] = $MYVARS->GET[$element[0]];
		$i++;
	}
	$error = "";

	if ($regid = enter_registration($MYVARS->GET["eventid"], $reg, $MYVARS->GET["email"])) //successful registration
	{
		echo '<center><div style="width:90%">You have successfully registered for ' . $event['name'] . '.<br />';

		if ($error != "") echo $error . "<br />";

		if ($event['allowinpage'] !=0)
		{
			if (is_logged_in() && $event['pageid'] != $CFG->SITEID)
			{
				change_page_subscription($event['pageid'], $USER->userid);
				echo 'You have been automatically allowed into this events web page.  This page contain specific information about this event.';
			}
		}

		if ($event['fee_full'] != 0)
		{
			$items = isset($MYVARS->GET["items"]) ? $MYVARS->GET["items"] . "**" . $regid . "::" . $MYVARS->GET["Camper_Name"] . " - " . $event["name"] . "::" . $MYVARS->GET["paypal_amount"] : $regid . "::" . $MYVARS->GET["Camper_Name"] . " - " . $event["name"] . "::" . $MYVARS->GET["paypal_amount"];
			echo '<div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="' . $MYVARS->GET["cart_total"] . '" />
				 <input type="hidden" name="items" id="items" value="' . $items . '" /></div>';

			$items = explode("**", $items);
			$i=0;
			while (isset($items[$i])) {
				$itm = explode("::", $items[$i]);
				$cart_items = [];
				$cart_items[$i] = (object)[
					"regid" => $itm[0],
					"description" => $itm[1],
					"cost" => $itm[2],
				];
				$i++;
			}

			if ($MYVARS->GET['payment_method'] == "PayPal")
			{
				echo '<br />
				To register a <strong>different</strong> child:  Select the week ' . common_weeks($event, true, "week1", $regid) . '.<br />
				To register the <strong>same child</strong> for a <strong>different week</strong>:  Select the week ' . common_weeks($event, false, "week2", $regid) . '.<br />
				<br />
				If you would like to pay the <span style="color:blue;font-size:1.25em;">$' . $MYVARS->GET["cart_total"] . '</span> fee now, click the Paypal button below.
				<center>
				' . make_paypal_button($cart_items, $event['paypal']) . '
				</center>
				<br /><br />
				Thank you for registering for this event. ';
			}
			else
			{
				echo '<br />
				To register a <strong>different</strong> child:  Select the week ' . common_weeks($event, true, "week1", $regid) . '.<br />
				To register the <strong>same child</strong> for a <strong>different week</strong>:  Select the week ' . common_weeks($event, false, "week2", $regid) . '.<br />
				<br />
				If you are done with the registration process, please make out your <br />
				check or money order in the amount of <span style="color:blue;font-size:1.25em;">$' . $MYVARS->GET["cart_total"] . '</span> payable to <strong>' . $event["payableto"] . '</strong> and send it to <br /><br />
				<center>
				' . $event['checksaddress'] . '.
				</center>
				<br /><br />
				Thank you for registering for this event.
				';
			}
		}

		$touser->fname = get_db_field("value", "events_registrations_values", "regid=$regid AND elementname='Camper_Name'");
		$touser->lname = "";
		$touser->email = get_db_field("email", "events_registrations", "regid=$regid");
		$fromuser->email = $event['email'];
		$fromuser->fname = $CFG->sitename;
		$fromuser->lname = "";
		$message = registration_email($regid, $touser);

		if (send_email($touser, $fromuser, "Camp Wabashi Registration", $message)) {
			send_email($fromuser, $fromuser, "Camp Wabashi Registration", $message);
		}
		else{ echo "<br /><br />Registration Email NOT Sent."; }

	}
	else //failed registration
	{
		$MYVARS->GET["cart_total"] = $MYVARS->GET["cart_total"] - $MYVARS->GET["paypal_amount"];
		echo '<center><div style="width:60%"><span class="error_text">Your registration for ' . $event['name'] . ' has failed. </span><br /> ' . $error . '</div>';
		if (isset($MYVARS->GET["items"])) //other registrations have already occured
		{
			if ($event['fee_full'] != 0)
			{
				$items = $MYVARS->GET["items"];
				echo '<div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="' . $MYVARS->GET["cart_total"] . '" />
					 <input type="hidden" name="items" id="items" value="' . $items . '" /></div>';

				$items = explode("**", $items);
				$i=0;
				while (isset($items[$i])) {
					$itm = explode("::", $items[$i]);
					$cart_items = [];
					$cart_items[$i] = (object)[
						"regid" => $itm[0],
						"description" => $itm[1],
						"cost" => $itm[2],
					];
					$i++;
				}

				if ($MYVARS->GET['payment_method'] == "PayPal")
				{
					echo '<br />
					To register a child: Select the week ' . common_weeks($event, true, "week1", "") . '.<br />
					<br />
					If you would like to pay the <span style="color:blue;font-size:1.25em;">$' . $MYVARS->GET["cart_total"] . '</span> fee now, click the Paypal button below.
					<center>
					' . make_paypal_button($cart_items, $event['paypal']) . '
					</center>
					<br /><br />
					Thank you for registering for this event. ';
				}
				else
				{
					echo '<br />
					To register a child:  Select the week ' . common_weeks($event, true, "week1", $regid) . '.<br />
					<br />
					If you are done with the registration process, please make out your <br />
					check or money order in the amount of <span style="color:blue;font-size:1.25em;">$' . $MYVARS->GET["cart_total"] . '</span> payable to <strong>' . $event["payableto"] . '</strong> and send it to <br /><br />
					<center>
					' . $event['checksaddress'] . '.
					</center>
					<br /><br />
					Thank you for registering for this event.
					';
				}
			}
		}
	}
}

function common_weeks($event, $included = true, $id = "", $regid = "") {
	global $CFG, $USER, $PAGE;
	$returnme = '<select id="' . $id . '">';
	$time = get_timestamp();
	$siteviewable = $event["pageid"] == $CFG->SITEID ? " OR siteviewable = '1' AND confirmed = '1'" : "";

	$SQL = "SELECT e.* FROM events e WHERE (e.template_id=" . $event["template_id"] . " AND (e.pageid='" . $event["pageid"] . "' $siteviewable)) AND e.start_reg < '$time' AND e.stop_reg > ($time - 86400) AND (e.max_users=0 OR (e.max_users != 0 AND e.max_users > (SELECT COUNT(*) FROM events_registrations er WHERE er.eventid=e.eventid)))";

	$events = get_db_result($SQL);

	while ($evnt = fetch_row($events))
	{
		$returnme .= !$included && $evnt['eventid'] == $event['eventid'] ? "" : '<option value="' . $evnt['eventid'] . '">' . $evnt['name'] . '</option>';
	}

	$returnme = $returnme == '<select id="' . $id . '">' ? '<span style="color:red;"> There are no weeks available. </span>' : $returnme . '</select> and click <a href="javascript:onclick=show_form_again(document.getElementById(\'' . $id . '\').value,\'' . $regid . '\', 0);"><strong>Continue</strong></a>';
	return $returnme;
}

function show_form_again() {
	include("template.php");
}

?>