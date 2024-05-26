<?php
/***************************************************************************
* events_ajax.php - Events backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.2.2
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

update_user_cookie();

callfunction();

//See if the date given is open for a requested event
function request_date_open() {
global $CFG;
	$featureid = clean_myvar_req("featureid", "int");
	$startdate = clean_myvar_req("startdate", "int");
	$enddate = clean_myvar_opt("enddate", "int", false);

	if ($featureid) {
		$pageid = get_db_field("pageid", "pages_features", "feature = 'events' AND featureid = ||featureid||", ["featureid" => $featureid]);

		//Only site events need to be set to confirm=1
		$confirm = $pageid == $CFG->SITEID ? "confirmed = 1 AND" : "pageid = $pageid AND";

		if (!$settings = fetch_settings("events", $featureid, $pageid)) {
  			save_batch_settings(default_settings("events", $pageid, $featureid));
  			$settings = fetch_settings("events", $featureid, $pageid);
  		}

		$locationid = $settings->events->$featureid->allowrequests->setting;

		if ($enddate) { //check all dates between startdate and enddate
			$startdate = strtotime($startdate) + get_offset(); $enddate = strtotime($enddate) + get_offset();
			//get all events at location between dates
			$SQL = "SELECT * FROM events
					WHERE $confirm location = ||location||
					AND (
							(||startdate|| >= event_begin_date AND ||startdate|| <= event_end_date)
							OR
							(||enddate|| >= event_begin_date AND ||enddate|| <= event_end_date)
							OR (||startdate|| <= event_begin_date AND ||enddate|| >= event_end_date)
					)";
			if ($getdates = get_db_count($SQL, ["location" => $locationid, "startdate" => $startdate, "enddate" => $enddate])) {
				echo "false";
			} else { echo "true";}
		} else { //only check a single day for an opening
			$startdate = strtotime($startdate) + get_offset();
			//get all events at location on date
			$SQL = "SELECT *
					FROM events
					WHERE $confirm location = ||location||
					AND (||startdate|| >= event_begin_date AND ||startdate|| <= event_end_date)";
			if ($getdates = get_db_count($SQL, ["location" => $locationid, "startdate" => $startdate])) {
				echo "false";
			} else { echo "true"; }
		}
	}
}

function event_request() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_req("featureid", "int");
	$contact_name = clean_myvar_req("name", "string");
	$contact_email = clean_myvar_req("email", "string");
	$contact_phone = clean_myvar_req("phone", "string");
	$event_name = clean_myvar_req("event_name", "string");
	$startdate = clean_myvar_req("startdate", "int");
	$enddate = clean_myvar_opt("enddate", "int", $startdate);
	$participants = clean_myvar_req("participants", "int");
	$description = clean_myvar_req("description", "html");

	$startdate = strtotime($startdate);
	$enddate = strtotime($enddate);

	$SQL = "INSERT INTO events_requests
					(featureid,contact_name,contact_email,contact_phone,event_name,startdate,enddate,participants,description,votes_for,votes_against)
					VALUES('$featureid','$contact_name','$contact_email','$contact_phone','$event_name','$startdate','$enddate','$participants','$description', 0, 0)";
	//Save the request
	if ($reqid = execute_db_sql($SQL)) {
		$from->email = $CFG->siteemail;
		$from->fname = $CFG->sitename;
		$from->lname = "";

		//Requesting email setup
		$contact->email = $contact_email;
		if (strstr($contact_name, " ")) {
			$name = explode(" ", $contact_name);
			$contact->fname = $name[0];
			$contact->lname = $name[1];
		} else {
			$contact->fname = $contact_name;
			$contact->lname = "";
		}
		$request_info = get_request_info($reqid);
		$subject = $CFG->sitename . " Event Request Received";
		$event_name = stripslashes($event_name);
		$message = "<strong>Thank you for submitting a request for us to host your event: $event_name.</strong><br />
					<br />
					We will look over the details that you provided and respond shortly. <br />
					Through the approval process you may receive additional questions about your event.<br />
					$request_info";

		//Send email to the requester letting them know we received the request
		send_email($contact, $from, $subject, $message);

		if (isset($featureid)) {
			//Get feature request settings
			$pageid = get_db_field("pageid", "pages_features", "feature='events' AND featureid=$featureid");
			if (!$settings = fetch_settings("events", $featureid, $pageid)) {
	  				  save_batch_settings(default_settings("events", $pageid, $featureid));
	  				  $settings = fetch_settings("events", $featureid, $pageid);
			}

			$subject = $CFG->sitename . " Event Request";

			// Get and send to email list
			$emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);

			foreach ($emaillist as $emailuser) {
				//Each message must has an md5'd email address so I know if a person has voted or not
				$voteid = md5($emailuser);
				$message = '<p>A new event request has been submitted. Your input is required to approve the event. This email contains specific links designed for you to be able to submit and view questions as well as vote for this event\'s approval.</p>
							<p>Do <strong>not </strong>delete this email.<strong>&nbsp; Please review the following event request.</strong></p>
							<br />
							<a href="' . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_vote&reqid=' . $reqid . '&approve=1&voteid=' . $voteid . '">Approve</a>
							&nbsp;
							<a href="' . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_vote&reqid=' . $reqid . '&approve=0&voteid=' . $voteid . '">Deny</a>
							&nbsp;
							<a href="' . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_question&reqid=' . $reqid . '&voteid=' . $voteid . '">View/Ask Questions</a><br />'.
							$request_info;

				$thisuser->email = $emailuser;
				$thisuser->fname = "";
				$thisuser->lname = "";
				send_email($thisuser, $from, $subject, $message);
			}
		}
		echo '<div style="width:100%;text-align:center;">
				<strong>Your request has been sent.</strong>
			  </div>
			  <div>
				<br />
				You should receive an email shortly informing you of the event approval process.<br />
				<br />
				Thank you.
			  </div>';

	} else {
	  echo "Failed to make request";
	}
}

function prepare_email_list($emaillist) {
	$replacechars = [", ", " ", "\t", "\r"];
	$emaillist = str_replace($replacechars, "\n", $emaillist);
	$emaillist = str_replace("\n\n", "\n", $emaillist);
	return explode("\n", $emaillist);
}

function valid_voter($pageid, $featureid, $voteid) {
	$validvote = false;

	if (!$settings = fetch_settings("events", $featureid, $pageid)) {
  		save_batch_settings(default_settings("events", $pageid, $featureid));
  		$settings = fetch_settings("events", $featureid, $pageid);
	}
	$locationid = $settings->events->$featureid->allowrequests->setting;

	//Get email list to check and see if the voteid matches one
	$emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);

	foreach ($emaillist as $emailuser) {
		if (md5($emailuser) == $voteid) {
			$validvote = true;
		}
	}
	return $validvote;
}

//Save question
function request_question_send() {
global $CFG, $MYVARS;
	$reqid = clean_myvar_req("reqid", "int");
	$voteid = clean_myvar_req("voteid", "string");
	$questiontext = clean_myvar_req("question", "string");

	$question = dbescape(strip_tags(trim($questiontext, " \n\r\t"), '<a><em><u><img><br>'));
	//Make sure request exists and get the featureid
	if ($featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
		//Get feature request settings
		$pageid = get_db_field("pageid", "pages_features", "feature = 'events' AND featureid = ||featureid||", ["featureid" => $featureid]);
		if (!$settings = fetch_settings("events", $featureid, $pageid)) {
	  		save_batch_settings(default_settings("events", $pageid, $featureid));
	  		$settings = fetch_settings("events", $featureid, $pageid);
		}
		$locationid = $settings->events->$featureid->allowrequests->setting;
		$request = get_db_row("SELECT * FROM events_requests WHERE reqid = ||reqid||", ["reqid" => $reqid]);
		//Allowed to ask questions
		if (valid_voter($pageid, $featureid, $voteid)) {
			$SQL = "INSERT INTO events_requests_questions
						(reqid, question, answer, question_time, answer_time)
						VALUES(||reqid||, ||question||, '', ||qtime||, '0')";
			if ($qid = execute_db_sql($SQL, ["reqid" => $reqid, "question" => $question, "qtime" => get_timestamp()])) { //Question is saved.  Now send it to everyone.
				$subject = $CFG->sitename . " Event Request Question";
				$message = '<strong>A question has been asked about the event (' . stripslashes($request["event_name"]) . ').</strong><br />
				<br />
				' . trim($question,"\n\r\t") . '<br />
				<br />
				<a href="' . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_question&amp;reqid=' . $reqid . '&amp;voteid=' . $voteid . '">
					View/Ask Questions
				</a>';

				$from->email = $CFG->siteemail;
				$from->fname = $CFG->sitename;
				$from->lname = "";

				$emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);
				foreach ($emaillist as $emailuser) {
					//Let everyone know the event has been questioned
					$thisuser->email = $emailuser;
					$thisuser->fname = "";
					$thisuser->lname = "";
					send_email($thisuser, $from, false, $subject, $message);
				}

				$message .= '<br /><br />
							<a href="' . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_answer&amp;qid=' . $qid . '&amp;reqid=' . $reqid . '">
								Answer Question
							</a>';

				$contact->email = $request["contact_email"];
				if (strstr(stripslashes($request["contact_name"]), " ")) {
					$name = explode(" ",stripslashes($request["contact_name"]));
					$contact->fname = $name[0];
					$contact->lname = $name[1];
				} else {
					$contact->fname = stripslashes($request["contact_name"]);
					$contact->lname = "";
				}

				//Send email to the requester letting them know a question has been raised
				send_email($contact, $from, $subject, $message);

				echo request_question(true);
			} else {
			  error_string("generic_db_error");
			}
		} else {
		  echo error_string("generic_permissions");
		}
	} else {
	  echo error_string("invalid_old_request:events");
	}
}

//Save question
function request_answer_send() {
global $CFG;
	$reqid = clean_myvar_req("reqid", "int");
	$qid = clean_myvar_req("qid", "int");
	$answer = clean_myvar_req("answer", "string");
	$answer = dbescape(strip_tags(trim($answer, " \n\r\t"),'<a><em><u><img><br>'));

	//Make sure request exists and get the featureid
	if ($featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
		//Get feature request settings
		$pageid = get_db_field("pageid", "pages_features", "feature = 'events' AND featureid = ||featureid||", ["featureid" => $featureid]);
		if (!$settings = fetch_settings("events", $featureid, $pageid)) {
	  		save_batch_settings(default_settings("events", $pageid, $featureid));
	  		$settings = fetch_settings("events", $featureid, $pageid);
		}
		$request = get_db_row("SELECT * FROM events_requests WHERE reqid = ||reqid||", ["reqid" => $reqid]);

		$SQL = "UPDATE events_requests_questions set answer = |answer|||, answer_time = ||answer_time|| WHERE id = ||id||";
		if (execute_db_sql($SQL, ["answer" => $answer, "answer_time" => get_timestamp(), "id" => $qid])) { // Question is saved.  Now send it to everyone.
			$subject = $CFG->sitename . " Event Request Answer";
			$message = '<strong>An answer to a question has been recieved about the event (' . stripslashes($request["event_name"]) . ').</strong><br />
			<br />
			<strong>Question:</strong> ' . get_db_field("question", "events_requests_questions", "id = ||id||", ["id" => $qid]) . '<br />
			<br />
			<strong>Answer:</strong>' . get_db_field("answer", "events_requests_questions", "id = ||id||", ["id" => $qid]);

			$from->email = $CFG->siteemail;
			$from->fname = $CFG->sitename;
			$from->lname = "";

			$emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);
			foreach ($emaillist as $emailuser) {
				//Let everyone know the event has been questioned
				$thisuser->email = $emailuser;
				$thisuser->fname = "";
				$thisuser->lname = "";
				send_email($thisuser, $from, $subject, $message);
			}
			echo request_answer(true);
		} else {
		  error_string("generic_db_error");
		}
	} else {
	  echo error_string("invalid_old_request:events");
	}
}

function confirm_events_relay() {
	confirm_event();
}

function delete_events_relay() {
	$eventid = clean_myvar_req("eventid", "int");
	delete_event($eventid);
}

//Request question form
function request_question($refresh=false) {
global $CFG, $MYVARS;
	$reqid = clean_myvar_req("reqid", "int");
	$voteid = clean_myvar_req("voteid", "string");

	//Make sure request exists and get the featureid
	if ($featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
		//Get feature request settings
		$pageid = get_db_field("pageid", "pages_features", "feature = 'events' AND featureid = ||featureid||", ["featureid" => $featureid]);
		if (!$settings = fetch_settings("events", $featureid, $pageid)) {
	  		save_batch_settings(default_settings("events", $pageid, $featureid));
	  		$settings = fetch_settings("events", $featureid, $pageid);
		}
		$locationid = $settings->events->$featureid->allowrequests->setting;

		//Allowed to ask questions
		if (valid_voter($pageid, $featureid, $voteid)) {
			//Print out question form
			if (!$refresh) {
			   echo '<html><head><title>Event Request Question Page</title>';
			   echo js_code_wrap('var dirfromroot = "' . $CFG->directory . '";');
			   echo get_js_tags(["siteajax"]);
			   echo get_css_tags(["main"]);
			   echo '</head><body><div id="question_form">';
			}
			echo '<h2>Questions Regarding Event Request</h2>' . get_request_info($reqid);
			if (!$refresh) {
				echo'
						<table style="width:100%">
							<tr>
								<td><br />';
							  echo get_editor_box();
								  echo ' <div style="width:100%;text-align:center">
										  <input type="button" value="Send Question"
											  onclick="ajaxapi(\'/features/events/events_ajax.php\',
															   \'request_question_send\',
															   \'&amp;voteid=' . $voteid . '&amp;reqid=' . $reqid . '&amp;question=\'+ escape(' . get_editor_value_javascript() . '),
															   function() { simple_display(\'question_form\');}
											  );"
										  />
										 </div>
							  </td>
							</tr>
						</table>';
			}

			echo '<h3>Previous Questions</h3>';

			//Print out previous questions and answers
			if ($results = get_db_result("SELECT *
											FROM events_requests_questions
										   WHERE reqid = ||reqid||
										ORDER BY question_time", ["reqid" => $reqid])) {
				while ($row = fetch_row($results)) {
					echo '<div style="background-color:Aquamarine;padding:4px;"><strong>' . $row['question'] . '</strong></div>';
					if ($row["answer"] == "") { //Not answered
						echo '<div style="background-color:PaleTurquoise;padding:4px;">Question has not been responed to at this time.</div><br /><br />';
					} else { //Print answer
						echo '<div style="background-color:Gold;padding:4px;">' . $row['answer'] . '</div><br /><br />';
					}
				}
			} else {
			  echo "No questions have been asked yet.";
			}
			if (!$refresh) { echo '</div></body></html>'; }
		} else {
		  echo error_string("generic_permissions");
		}
	} else {
	  echo error_string("invalid_old_request:events");
	}
}

//Request answer form
function request_answer($refresh=false) {
global $CFG;
	$reqid = clean_myvar_req("reqid", "int");
	$qid = clean_myvar_req("qid", "int");

	//Make sure request exists and get the featureid
	if ($featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
		//Get feature request settings
		$pageid = get_db_field("pageid", "pages_features", "feature = 'events' AND featureid = ||featureid||", ["featureid" => $featureid]);
		if (!$settings = fetch_settings("events", $featureid, $pageid)) {
	  		save_batch_settings(default_settings("events", $pageid, $featureid));
	  		$settings = fetch_settings("events", $featureid, $pageid);
		}
		$locationid = $settings->events->$featureid->allowrequests->setting;

		//Allowed to ask questions
		//Print out question form
		if (!$refresh) {
			echo '<html><head><title>Event Request Question Page</title>';
			echo js_code_wrap('var dirfromroot = "' . $CFG->directory . '";');
			echo get_js_tags(["siteajax"]);
			echo get_js_tags(["main"]);
			echo '</head><body><div id="answer_form">';
		 }

		echo '<h2>Questions Regarding Event Request</h2>' . get_request_info($reqid);

		$answer = get_db_field("answer", "events_requests_questions", "id = ||id||", ["id" => $qid]);
		if (!$refresh) {
			echo'<table style="width:100%">
					<tr>
						<td>
							<br />
							<div style="background-color:Aquamarine;padding:4px;">
								<strong>Question: ' . get_db_field("question", "events_requests_questions", "id = ||id||", ["id" => $qid]) . '</strong>
							</div>
							<br />
							' . get_editor_box(["initialvalue" => $answer]) . '
							<div style="width:100%;text-align:center">
								<input type="button" value="Send Answer"
									onclick="ajaxapi(\'/features/events/events_ajax.php\',
													 \'request_answer_send\',
													 \'&qid=' . $qid . '&reqid=' . $reqid . '&answer=\'+ escape(' . get_editor_value_javascript() . '),
													 function() { simple_display(\'answer_form\');}
									);"
								/>
							</div>
						</td>
					</tr>
				</table>';
		}

		echo '<h3>Previous Questions</h3>';

		//Print out previous questions and answers
		$mod = ""; $params = ["reqid" => $reqid];
		if (!$refresh) {
			$mod = "AND id != ||id||";
			$params += ["id" => $qid];
		}

		if ($results = get_db_result("SELECT *
										FROM events_requests_questions
										WHERE reqid = ||reqid||
										$mod
										ORDER BY question_time", $params)) {
			while ($row = fetch_row($results)) {
				echo    '<div style="background-color:Aquamarine;padding:4px;">
							<strong>' . $row['question'] . '</strong>
						</div>';
				if ($row["answer"] == "") { //Not answered
					echo    '<div style="background-color:PaleTurquoise;padding:4px;overflow:hidden;">
								<strong>
									<a href="' . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_answer&amp;qid=' . $row['id'] . '&amp;reqid=' . $reqid . '">
										Answer Question
									</a>
								</strong>
							</div><br /><br />';
				} else { //Print answer
					echo    '<div style="background-color:Gold;padding:4px;overflow:hidden;">
								' . $row['answer'] . '<br />
								<strong>
									<a style="float:right" href="' . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_answer&amp;qid=' . $row['id'] . '&amp;reqid=' . $reqid . '">
										Update Answer
									</a>
								</strong>
							</div><br /><br />';
				}
			}
		} else { echo "No other questions have been asked yet."; }
		if (!$refresh) { echo '</div></body></html>'; }
	} else { echo error_string("invalid_old_request:events"); }
}

//Function is called on email votes
function request_vote() {
global $CFG, $MYVARS;
	$reqid = clean_myvar_req("reqid", "int");
	$newvote = clean_myvar_req("approve", "int");
	$voteid = clean_myvar_req("voteid", "string");

	$stance = $newvote ? "approve" : "deny";
	echo "<html><title>Event Request has been voted on.</title><body>";
	//Make sure request exists and get the featureid
	if ($featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
		//Get feature request settings
		$pageid = get_db_field("pageid", "pages_features", "feature = 'events' AND featureid = ||featureid||", ["featureid" => $featureid]);

		if (!$settings = fetch_settings("events", $featureid, $pageid)) {
	  		save_batch_settings(default_settings("events", $pageid, $featureid));
	  		$settings = fetch_settings("events", $featureid, $pageid);
		}
		$locationid = $settings->events->$featureid->allowrequests->setting;

		if (valid_voter($pageid, $featureid, $voteid)) {
			//See if the person has already voted
			if ($row = get_db_row("SELECT * FROM events_requests
									WHERE reqid = ||reqid||
									AND voted LIKE ||voted||", ["reqid" => $reqid, "voted" => "%:$voteid;%"])) { //Person has already voted
				$voted = explode("::", $row["voted"]);
				foreach ($voted as $vote) {
					$vote = trim($vote, ":");
					$entry = explode(";", $vote);
					if ($entry[0] == $voteid) { //This is the vote that needs removed
						if ($entry[1] == $newvote) { //Same vote, nothing else needs done
							echo "You have already voted to $stance this event.";
						} else { //They have changed their vote.
							$oldvote = $entry[1];
							//Remove old vote
							$p = ["reqid" => $reqid, "voteid" => $voteid, "newvote" => ":$voteid;$newvote:", "oldvote" => ":$voteid;$oldvote:"];

							// Update vote record.
							execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_change_vote", "events"), $p);

							if ($newvote) { // Remove 1 from against and add 1 to for
								execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_recalculate", "events", ["approve" => true]), ["reqid" => $reqid]);
							} else { //Remove 1 from for and add 1 to against    
								execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_recalculate", "events", ["approve" => false]), ["reqid" => $reqid]);
							}
							echo "You have changed your vote to $stance.";
						}
					}
				}
			} else { //New vote
				// Update vote record.
				$p = ["reqid" => $reqid, "voteid" => $voteid, "newvote" => ":$voteid;$newvote:"];
				execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_new_vote", "events"), $p);

				if ($newvote == "1") { // Add 1 to the for column
					execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_calculate", "events", ["approve" => true]), ["reqid" => $reqid]);
				} else { // Add 1 to the against column
					execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_calculate", "events", ["approve" => false]), ["reqid" => $reqid]);
				}
				echo "You have voted to $stance this event.";
			}

			//See if the voting on this request is finished
			if ($request = get_db_row(fetch_template("dbsql/events.sql", "get_events_requests", "events"), ["reqid" => $reqid])) {
				$from = new \stdClass;
				$from->email = $CFG->siteemail;
				$from->fname = $CFG->sitename;
				$from->lname = "";

				//Requesting email setup
				$contact = new \stdClass;
				$contact->email = $request["contact_email"];
				if (strstr(stripslashes($request["contact_name"]), " ")) {
					$name = explode(" ",stripslashes($request["contact_name"]));
					$contact->fname = $name[0];
					$contact->lname = $name[1];
				} else {
					$contact->fname = stripslashes($request["contact_name"]);
					$contact->lname = "";
				}
				$request_info = get_request_info($reqid);
				$subject = $CFG->sitename . " Event Request Received";
				$message = "<strong>Thank you for submitting a request for us to host your event: " . $request["event_name"] . ".</strong><br />
							<br />
							We will look over the details that you provided and respond shortly.<br />
							Through the approval process you may receive additional questions about your event. $request_info";

				if ($request["votes_for"] == $settings->events->$featureid->requestapprovalvotes->setting &&
					$request["votes_against"] < $settings->events->$featureid->requestdenyvotes->setting) {
					//Event Approved
					$subject = $CFG->sitename . " Event Request Approved";
					$message = '<strong>The event (' . stripslashes($request["event_name"]) . ') has been approved by a vote of ' . $request["votes_for"] . ' to ' . $request["votes_against"] . '.</strong>
					<br /><br />'.
					$request_info;

					//Send email to the requester letting them know we denied the request
					send_email($contact, $from, $subject, $message);

					$emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);
					foreach ($emaillist as $emailuser) {
						//Let everyone know the event has been denied
						$thisuser->email = $emailuser;
						$thisuser->fname = "";
						$thisuser->lname = "";
						send_email($thisuser, $from, $subject, $message);
					}

					//Make event
					$MYVARS->GET["pageid"] = $pageid;
					$MYVARS->GET["featureid"] = $featureid;
					$eventid = convert_to_event($request, $locationid);

					//Confirm event
					$siteviewable = $pageid == $CFG->SITEID ? "1" : "0"; //if feature is on site, then yes...otherwise no.
					confirm_event($pageid, $eventid, $siteviewable);

					//Delete event request
					execute_db_sql(fetch_template("dbsql/events.sql", "delete_events_requests", "events"), ["reqid" => $reqid]);
					execute_db_sql(fetch_template("dbsql/events.sql", "delete_events_requests_questions", "events"), ["reqid" => $reqid]);

				} elseif ($request["votes_for"] < $settings->events->$featureid->requestapprovalvotes->setting
						  &&
						  $request["votes_against"] == $settings->events->$featureid->requestdenyvotes->setting) {
					//Event Denied
					$subject = $CFG->sitename . " Event Request Denied";
					$message = '<strong>The event (' . stripslashes($request["event_name"]) . ') has been denied by a vote of ' . $request["votes_for"] . ' to ' . $request["votes_against"] . '.</strong>
					<br /><br />'.
					$request_info;

					//Send email to the requester letting them know we denied the request
					send_email($contact, $from, $subject, $message);

					$emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);
					foreach ($emaillist as $emailuser) {
						//Let everyone know the event has been denied
						$thisuser->email = $emailuser;
						$thisuser->fname = "";
						$thisuser->lname = "";
						send_email($thisuser, $from, $subject, $message);
					}

					//Delete event request
					execute_db_sql(fetch_template("dbsql/events.sql", "delete_events_requests", "events"), ["reqid" => $reqid]);
					execute_db_sql(fetch_template("dbsql/events.sql", "delete_events_requests_questions", "events"), ["reqid" => $reqid]);
				} elseif ($request["votes_for"] > $settings->events->$featureid->requestapprovalvotes->setting) {
					echo "Event has already been approved.  Thank you for voting.";
				}
			}

		} else { echo error_string("generic_permissions"); }
	} else {
		echo error_string("invalid_old_request:events");
	}
	echo "</body></html>";
}

function convert_to_event($request, $location) {
global $CFG, $MYVARS;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$MYVARS->GET["event_name"] = $request["event_name"];
	$MYVARS->GET["contact"] = $request["contact_name"];
	$MYVARS->GET["email"] = $request["contact_email"];
	$MYVARS->GET["phone"] = $request["contact_phone"];
	$MYVARS->GET["location"] = $location;

	$MYVARS->GET["category"] = "1"; //Placed in general category...can be changed later
	$MYVARS->GET["siteviewable"] = $pageid == $CFG->SITEID ? "1" : "0"; //if feature is on site, then yes...otherwise no.

	$MYVARS->GET["description"] = $request["description"]; //event description

	$MYVARS->GET["multiday"] = $request["startdate"] == $request["enddate"] ? "0" : "1";

	$MYVARS->GET["event_begin_date"] = date(DATE_RFC822, $request["startdate"]);
	$MYVARS->GET["event_end_date"] = $MYVARS->GET["multiday"] == "1" ? date(DATE_RFC822, $request["enddate"]) : $MYVARS->GET["event_begin_date"];
	$MYVARS->GET["allday"] = "1"; $MYVARS->GET["reg"] = "0"; $MYVARS->GET["fee"] = "0";

	return submit_new_event(true);
}

function get_request_info($reqid) {
global $CFG;
	if ($request = get_db_row("SELECT * FROM events_requests WHERE reqid='$reqid'")) {
		return '<br />
		<strong>Event Name:</strong> ' . stripslashes($request["event_name"]) . '<br />
		<strong>Contact Name:</strong> ' . stripslashes($request["contact_name"]) . '<br />
		<strong>Contact Email:</strong> ' . $request["contact_email"] . '<br />
		<strong>Contact Phone:</strong> ' . $request["contact_phone"] . '<br />
		<strong>Estimated Participants:</strong> ' . $request["participants"] . '<br />
		<strong>Start Date:</strong> ' . date('l jS \of F Y', $request["startdate"]) . '<br />
		<strong>End Date:</strong> ' . date('l jS \of F Y', $request["enddate"]) . '<br />
		<strong>Description:</strong> ' . stripslashes($request["description"]) . '<br />';
	}
}

function print_registration() {
global $CFG, $MYVARS, $USER;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$eventid = dbescape($MYVARS->GET["eventid"]);
	$template_id = dbescape($MYVARS->GET["template_id"]);
	$eventname = urldecode($MYVARS->GET["eventname"]);
	$regid = dbescape($MYVARS->GET["regid"]);
	$online_only = isset($MYVARS->GET["online_only"]) ? dbescape($MYVARS->GET["online_only"]) : false;
	$printarea = "";
	$returnme = '<link rel="stylesheet" type="text/css" href="' . $CFG->wwwroot . '/styles/print.css">';
	$returnme .= '<a class="dontprint" href="javascript: void(0);"
					onclick="$(\'#loading_overlay\').show();
								ajaxapi(\'/features/events/events_ajax.php\',
										\'show_registrations\',
										\'&amp;eventid=' . $eventid . '\',
										function() {
											if (xmlHttp.readyState == 4) {
												simple_display(\'searchcontainer\');
												$(\'#loading_overlay\').hide();
												init_event_menu();
											}
										},
										true
								);">
					Back to ' . $eventname . ' registrants.
				</a>';

	if ($regid != "false") { //Print form for 1 registration
		$printarea .= printable_registration($regid, $eventid, $template_id);
	} else { //Batch print all registrations
		if ($registrations = get_db_result(get_registration_sort_sql($eventid, $online_only))) {
			while ($registration = fetch_row($registrations)) {
				$printarea .= $printarea == '' ? '<p style="font-size:.95em;" class="print">' : '<p style="font-size:.95em;" class="pagestart print">';
				$printarea .= printable_registration($registration["regid"], $eventid, $template_id);
				$printarea .= "</p>";
			}
		}
	}

	$returnme .= '<form>
					  <span class="dontprint">
						  <br /><br />
					  </span>
					  <input class="dontprint" type="button" value="Print" onclick="window.print();return false;" />
					  ' . $printarea . '
				  </form>';
	echo $returnme;
}

function get_registration_sort_sql($eventid, $online_only=false) {
	if ($online_only) { $online_only = "AND e.manual = 0"; }
	$SQL = "SELECT e.*"; $orderby = "";

	$sort_info = get_db_row("SELECT e.eventid,b.orderbyfield,b.folder FROM events as e
								JOIN events_templates as b ON b.template_id=e.template_id
								WHERE eventid='$eventid'");

 		if ($sort_info["folder"] == "none") { //form template
		$sort_elements=explode(",", $sort_info["orderbyfield"]);$i=0;
		while (isset($sort_elements[$i])) {
			$SQL .= ",(SELECT value FROM events_registrations_values
							WHERE elementid='$sort_elements[$i]' AND regid=v.regid) as val$i";
			$orderby .= $orderby == "" ? "val$i" : ",val$i";
			$i++;
		}
		$SQL .= " FROM events_registrations as e
					JOIN events_registrations_values as v ON (e.regid=v.regid)
					WHERE e.eventid='$eventid' $online_only
					GROUP BY regid
					ORDER BY val0 LIKE '%Reserved%' DESC, $orderby";
 		} else { //custom template
		$sort_elements=explode(",", $sort_info["orderbyfield"]);$i=0;
		while (isset($sort_elements[$i])) {
			$SQL .= ",(SELECT value FROM events_registrations_values
							WHERE elementname='$sort_elements[$i]' AND regid=v.regid) as val$i";
			$orderby .= $orderby == "" ? "val$i" : ",val$i";
			$i++;
		}
		$SQL .= " FROM events_registrations as e
					JOIN events_registrations_values as v ON (e.regid=v.regid)
					WHERE e.eventid='$eventid' $online_only
					GROUP BY regid
					ORDER BY val0 LIKE '%Reserved%' DESC, $orderby";
 		}

return $SQL;
}

function printable_registration($regid, $eventid, $template_id) {
	$returnme = '<div>';
	$template = get_event_template($template_id);
	if ($template["folder"] == "none") {
		if ($template_forms = get_db_result("SELECT * FROM events_templates_forms
												WHERE template_id='$template_id'
												ORDER BY sort")) {
	  		while ($form_element = fetch_row($template_forms)) {
	  			if ($form_element["type"] == "payment") {
	  				if ($values = get_db_result("SELECT * FROM events_registrations_values
													WHERE regid='$regid' AND elementid='" . $form_element["elementid"] . "'
													ORDER BY entryid")) {
	  					$i = 0; $values_display = explode(",", $form_element["display"]);
	  					while ($value = fetch_row($values)) {
								$returnme .= '<br />
											<div style="display:inline-block;width:150px;vertical-align: top;">
												<strong>' . $values_display[$i] . '</strong>
											</div>
											<div style="display:inline-block;max-width: 400px;padding-left:5px;">
												' . stripslashes($value["value"]) . '
											</div>';
	  						$i++;
						}
					}
				} else {
						$value = get_db_row("SELECT * FROM events_registrations_values
											WHERE regid='$regid'
												AND elementid='" . $form_element["elementid"] . "'");
						$returnme .= '<br />
									<div style="display:inline-block;width:150px;vertical-align: top;">
										<strong>' . $form_element["display"] . '</strong>
									</div>
									<div style="display:inline-block;max-width: 400px;padding-left:5px;">
										' . stripslashes($value["value"]) . '
									</div>';
				}
			}
		}
	} else {
		$template_forms = explode(";", $template["formlist"]);
		$i=0;
		while (!empty($template_forms[$i])) {
	  		$form = explode(":", $template_forms[$i]);
	  		$value = get_db_row("SELECT * FROM events_registrations_values
									WHERE regid='$regid'
										AND elementname='" . $form[0]."'");
	  		$returnme .=   '<br />
							<div style="display:inline-block;width:150px;vertical-align: top;">
								<strong>' . $form[2] . '</strong>
							</div>
							<div style="display:inline-block;max-width: 400px;padding-left:5px;">
								' . stripslashes($value["value"]) . '
							</div>';
	  		$i++;
		}
	}

	// Payment info
	if ($values = get_db_result("SELECT * FROM events_registrations_values WHERE regid='$regid' AND elementname='tx' ORDER BY entryid")) {
		while ($value = fetch_row($values)) {
			$params = unserialize($value["value"]);
			$returnme .= '<br />
							<div style="display:inline-block;width:150px;vertical-align: top;">
								<strong>Paypal TX</strong>
							</div>
							<div style="display:inline-block;max-width: 400px;padding-left:5px;">
								&nbsp;$' . stripslashes($params["amount"] . " on " . date("m/d/Y", $params["date"]) . " - " . $params["txid"]) . '
							</div>';
			$i++;
		}
	}

	$returnme .= "</div>";

	return $returnme;
}

function save_reg_changes() {
global $CFG, $MYVARS, $USER;
	$eventid = clean_var_opt("eventid", "int", false);
	$regid = clean_var_opt("regid", "int", false);
	$reg_eventid = clean_var_opt("reg_eventid", "int", false);

	try {
		start_db_transaction();
		// Changing core registration values
		if ($reg_eventid && get_event($reg_eventid)) {
			$reg_email = clean_var_opt("reg_email", "string", "");
			$reg_code = clean_var_opt("reg_code", "string", "");

			execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_event_info", "events"), ["regid" => $regid, "eventid" => $reg_eventid, "email" => $reg_email, "code" => $reg_code]);
			execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_event", "events"), ["regid" => $regid, "eventid" => $reg_eventid]);
		}

		// Create temp table
		if (execute_db_sql(fetch_template("dbsql/events.sql", "reg_copy_create_temptable", "events"))) {
			if ($entries = get_db_result(fetch_template("dbsql/events.sql", "get_registration_values", "events"), ["regid" => $regid])) {
				$SQL = '';
				while ($entry = fetch_row($entries)) {
					if (isset($MYVARS->GET[$entry["entryid"]])) {
						$SQL .= $SQL == "" ? "('" . $entry["entryid"] . "','" . clean_var_opt($entry["entryid"], "string", "") . "')" : ", ('" . $entry["entryid"] . "', '" . clean_var_opt($entry["entryid"], "string", "") . "')";
					}
				}
				$SQL = "INSERT INTO temp_updates (entryid,newvalue) VALUES" . $SQL;
			}

			if (execute_db_sql($SQL)) {
				if (execute_db_sql(fetch_template("dbsql/events.sql", "reg_update_from_temptable", "events"), ["regid" => $regid])) {
					// check paid status
					$paid = get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname = 'paid'", ["regid" => $regid]);
					$payment_method = get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname = 'payment_method'", ["regid" => $regid]);
					$minimum = get_db_field("fee_min", "events", "eventid = ||eventid||", ["eventid" => $eventid]);
					$verified = get_db_field("verified", "events_registrations", "regid = ||regid||", ["regid" => $regid]);
					if ($paid >= $minimum) {
						if (empty($verified)) { // Not already verified.
							// If payment is made, it is no longer in queue.
							execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_status", "events"), ["regid" => $regid, "verified" => 1]);

							$touser = new \stdClass;
							$touser->fname = get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname='Camper_Name_First'", ["regid" => $regid]);
							$touser->lname = get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname='Camper_Name_Last'", ["regid" => $regid]);
							$touser->email = get_db_field("email", "events_registrations", "regid = ||regid||", ["regid" => $regid]);

							$fromuser = new \stdClass;
							$fromuser->email = $CFG->siteemail;
							$fromuser->fname = $CFG->sitename;
							$fromuser->lname = "";
							$message = registration_email($regid, $touser);
							if (send_email($touser, $fromuser, $CFG->sitename . " Registration", $message)) {
								send_email($fromuser, $fromuser, $CFG->sitename . " Registration", $message);
							}
						}
					} else {
						if ($payment_method = "Campership") {
							execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_status", "events"), ["regid" => $regid, "verified" => 1]);
						} else {
							execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_status", "events"), ["regid" => $regid, "verified" => 0]);
						}
					}
					echo "Saved";
				}
			}
		}
		commit_db_transaction();
		echo "Saved Changes";
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
		trigger_error($e->getMessage(), E_USER_WARNING);
	}
}

function delete_registration_info() {
global $CFG, $MYVARS, $USER;
	$regid = clean_myvar_req("regid", "int");
	try {
		start_db_transaction();
		$params = [
			"file" => "dbsql/events.sql",
			"feature" => "events",
			"subsection" => ["delete_registration", "delete_registration_values"],
		];
		execute_db_sqls(fetch_template_set($params), ["regid" => $regid]);
		commit_db_transaction();
		echo "Registration deleted";
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
		trigger_error($e->getMessage(), E_USER_WARNING);
	}
}

function resend_registration_email() {
global $CFG, $MYVARS, $USER;
	$regid = isset($MYVARS->GET["regid"]) ? dbescape($MYVARS->GET["regid"]) : false;
	$eventid = isset($MYVARS->GET["eventid"]) ? dbescape($MYVARS->GET["eventid"]) : false;
	$pageid = isset($MYVARS->GET["pageid"]) ? dbescape($MYVARS->GET["pageid"]) : false;

	$link = '<a title="Return to Registration list" href="javascript: void(0)"
				onclick="$(\'#loading_overlay\').show();
						 ajaxapi(\'/features/events/events_ajax.php\',
								 \'show_registrations\',
								 \'&amp;eventid=' . $eventid . '\',
								 function() {
									if (xmlHttp.readyState == 4) {
										simple_display(\'searchcontainer\');
										$(\'#loading_overlay\').hide();
										init_event_menu();
									}
								 },
								 true
						 );
						 return false;
				">Back to Registration List
			 </a>';
	if (!empty($regid) && !empty($eventid)) {
		$event = get_event($eventid);

		$touser = (object)[
			'email' => get_db_field("email", "events_registrations", "regid='$regid'"),
			'fname' => $registrant_name = get_registrant_name($regid),
			'lname' => "",
		];

		$fromuser = (object)[
			'email' => $event['email'],
			'fname' => $CFG->sitename,
			'lname' => "",
		];

		$message = registration_email($regid, $touser);
		if (send_email($touser, $fromuser, $event["name"] . " Registration", $message)) {
			echo $link . '<br /><br />
						<div style="text-align:center">
							Registration Email sent
						</div>';
		} else {
			echo $link . '<br /><br />
						<div style="text-align:center">
							Email could not be sent: Registrant\'s email might not be set
						</div>';
		}
	} else {
		echo $link . '<br /><br />
					  <div style="text-align:center">
							Email could not be sent: Check for incorrect registration information
					  </div>';
	}
}

function get_registration_info() {
global $CFG, $MYVARS, $USER;
	$eventid = dbescape($MYVARS->GET["eventid"]);
	$event = get_event($eventid);
	$template_id = $event["template_id"];
	$eventname = $event["name"];
	$pageid = $event["pageid"];
	$regid = isset($MYVARS->GET["regid"]) ? dbescape($MYVARS->GET["regid"]) : false;

	$returnme = '<a href="javascript: void(0);"
					onclick="$(\'#loading_overlay\').show();
							ajaxapi(\'/features/events/events_ajax.php\',
									\'show_registrations\',
									\'&amp;eventid=' . $eventid . '\',
									function() {
										if (xmlHttp.readyState == 4) {
											simple_display(\'searchcontainer\');
											$(\'#loading_overlay\').hide();
											init_event_menu();
										}
									},
									true
							);
					">
					Back to ' . $eventname . ' registrants.
				</a><br /><br />';

	// Get all events beginning 3 months in the past, to a year in the future.
	$today = get_timestamp();
	
	$events = get_db_result("SELECT eventid, (CONCAT(FROM_UNIXTIME(e.event_begin_date , '%Y'), ' ', e.name)) AS name
							   FROM events e
							  WHERE e.confirmed = 1
								AND e.template_id = '$template_id'
								AND e.start_reg > 0
								AND ((e.event_begin_date - $today) < 31560000 && (e.event_begin_date - $today) > -7776000)");


	// If similar events exist, display the copy registration form.
	if (!empty((array) $events)) {
		// Open copy form.
		$copy_form_code = '
			if ($(\'#copy_reg_to\').val() > 0) { 
				$(\'#loading_overlay\').show();
				ajaxapi(\'/features/events/events_ajax.php\',
						\'copy_registration\',
						\'&amp;regid=' . $regid . '\' + create_request_string(\'copy_form\'),
						function() {
							if (xmlHttp.readyState == 4) {
								ajaxapi(\'/features/events/events_ajax.php\',
										\'show_registrations\',
										\'&amp;eventid=\'+$(\'#copy_reg_to\').val(),
										function() {
											if (xmlHttp.readyState == 4) {
												simple_display(\'searchcontainer\');
												$(\'#loading_overlay\').hide();
												init_event_menu();
											}
										}
								);
							}
						},
						true
				); 
			}
			return false;';

		$selectproperties = [
			"properties" => [
				"name" => "copy_reg_to",
				"id" => "copy_reg_to",
				"style" => "width:300px;",
			],
			"values" => $events,
			"valuename" => "eventid",
			"displayname" => "name",
			"firstoption" => "",
			"exclude" => $eventid,
		];
		$returnme .= '
			<h3>Copy Registration</h3>
			<form name="copy_form" onsubmit="' . $copy_form_code . '">
				<table style="border-top: 1px solid grey;">
					<tr>
						<td style="width: 125px;">
							Copy To 
						</td>
						<td>
							' . make_select($selectproperties) . '
						</td>
					</tr>
					<tr>
						<td colspan="2" style="text-align: right">
							<input type="submit" value="Copy Registration" />
							<br /><br />
						</td>
					</tr>
				</table>
			</form>';
	}


	$edit_form_code = '
		$(\'#loading_overlay\').show();
		ajaxapi(\'/features/events/events_ajax.php\',
				\'save_reg_changes\',
				\'&amp;regid=' . $regid . '&amp;eventid=' . $eventid . '\' + create_request_string(\'reg_form\'),
				function() {
					if (xmlHttp.readyState == 4) {
						ajaxapi(\'/features/events/events_ajax.php\',
								\'show_registrations\',
								\'&amp;eventid=' . $eventid . '&amp;sel=' . $regid . '\',
								function() {
									if (xmlHttp.readyState == 4) {
										simple_display(\'searchcontainer\');
										$(\'#loading_overlay\').hide();
										init_event_menu();
									}
								}
						);
					}
				},
				true
		);
		return false;';
	// Open save form.
	$returnme .= '<h3>Edit Registration Values</h3>
				  <form name="reg_form" onsubmit="' . $edit_form_code . '">';
	
	$returnme .= '<table style="border-top: 1px solid grey;">';
	// Top Save button.
	$returnme .= '<tr><td colspan="2" style="text-align: center"><input type="submit" value="Save Changes" /></td></tr>';

	$reg_status = get_db_field("verified", "events_registrations", "regid='$regid'");
	$reg_status = !$reg_status ? "Pending" : "Completed";
	$returnme .= '<tr><td>Status </td><td>' . $reg_status . '</td></tr>';

	if ($events) {
		db_goto_row($events); // reset pointer to use it again.
		$p = [
			"properties" => [
				"name" => "reg_eventid",
				"id" => "reg_eventid",
				"style" => "width:420px;",
			],
			"values" => $events,
			"valuename" => "eventid",
			"displayname" => "name",
			"selected" => $eventid,
		];
		$returnme .= '
			<tr>
				<td>
					Event 
				</td>
				<td>
					' . make_select($p) . '
				</td>
			</tr>';
	} else {
		$returnme .= '
			<tr>
				<td>
					Event 
				</td>
				<td>
					<input id="reg_eventid" name="reg_eventid" type="hidden" value="' . $eventid . '" />
					' . $event["name"] . '
				</td>
			</tr>';
	}

	
	$event_reg = get_db_row("SELECT * FROM events_registrations WHERE regid='$regid'");
	$returnme .= '<tr><td>Email </td><td>' .
					'<input id="reg_email" name="reg_email" type="text" size="45" value="' . stripslashes($event_reg["email"]) . '" />' .
				 '</td></tr>';
	$returnme .= '<tr><td>Pay Code </td><td>' .
					'<input id="reg_code" name="reg_code" type="text" size="45" value="' . stripslashes($event_reg["code"]) . '" />' .
				 '</td></tr>';

	$template = get_event_template($template_id);
	if ($template["folder"] == "none") {
		if ($template_forms = get_db_result("SELECT * FROM events_templates_forms
												WHERE template_id='$template_id'
												ORDER BY sort")) {
	  		while ($form_element = fetch_row($template_forms)) {
	  			if ($form_element["type"] == "payment") {
	  				if ($values = get_db_result("SELECT * FROM events_registrations_values
													WHERE regid='$regid'
														AND elementid='" . $form_element["elementid"] . "'
													ORDER BY entryid")) {
	  					$i = 0; $values_display = explode(",", $form_element["display"]);
	  					while ($value = fetch_row($values)) {
								$returnme .= '<tr><td>' . $values_display[$i] . ' </td><td><input id="' . $value["entryid"] . '" name="' . $value["entryid"] . '" type="text" size="45" value="' . stripslashes($value["value"]) . '" /></td></tr>';
	  						$i++;
						}
					}
				} else {
						$value = get_db_row("SELECT * FROM events_registrations_values
											WHERE regid='$regid'
												AND elementid='" . $form_element["elementid"] . "'");
						$returnme .= '<tr><td>' . $form_element["display"] . ' </td><td><input id="' . $value["entryid"] . '" name="' . $value["entryid"] . '" type="text" size="45" value="' . stripslashes($value["value"]) . '" /></td></tr>';
				}
			}
		}
	} else {
		$template_forms = explode(";", trim($template["formlist"], ';'));
		$i = 0;
		while (isset($template_forms[$i])) {
	  		$form = explode(":", $template_forms[$i]);

	  		$value = get_db_row("SELECT *
								   FROM events_registrations_values
								  WHERE regid = '$regid'
									AND elementname = '" . $form[0] . "'");

			$val = "";
			$entryid = "";
			if (!$value) { // No value so we create a blank.
				$SQL = "INSERT INTO events_registrations_values
									(regid, value, eventid, elementname)
							 VALUES ('$regid', '', '$eventid', '" . $form[0] . "')";
				$entryid = execute_db_sql($SQL);
			  } else {
				$val = stripslashes($value["value"]);
				$entryid = $value["entryid"];
			}

			$formfield = '<input id="' . $entryid . '" name="' . $entryid . '" type="text" size="45" value="' . $val . '" />';

			$returnme .= '<tr>
							<td>' . 
								$form[2] . '
							</td>
							<td>
								' . $formfield . '
							</td>
						</tr>';
	  		$i++;
		}
	}
	// Bottom Save button.
	$returnme .= '<tr><td colspan="2" style="text-align: center"><input type="submit" value="Save Changes" /></td></tr>';
	$returnme .= '</table></form>';
	echo '<div style="padding:15px 5px">' . $returnme . '</div>';
}

function add_blank_registration($eventid = false, $reserveamount = 1) {
global $CFG, $MYVARS, $USER;
	$eventid = clean_myvar_opt("eventid", "int", $eventid);
	$reserveamount = clean_myvar_opt("reserveamount", "int", $reserveamount);

	$event = get_event($eventid);
	$template_id = $event["template_id"];
	$eventname = $event["name"];
	$pageid = $event["pageid"];

	$reserved = 0;
	$return = [];
	while ($reserved < $reserveamount) {
		$SQL = "";$SQL2 = "";
		if ($regid = execute_db_sql("INSERT INTO events_registrations
									(eventid,date,code,manual)
									VALUES('$eventid','" . get_timestamp() . "','" . uniqid("", true) . "',1)")) {
			$template = get_event_template($template_id);
			if ($template["folder"] == "none") {
				if ($template_forms = get_db_result("SELECT * FROM events_templates_forms
														WHERE template_id='$template_id'
														ORDER BY sort")) {
						while ($form_element = fetch_row($template_forms)) {
							if ($form_element["type"] == "payment") {
								$SQL2 .= $SQL2 == "" ? "" : ",";
								$SQL2 .= "('$regid','" . $form_element["elementid"] . "', '','$eventid','total_owed'),('$regid'," . $form_element["elementid"] . ", '','$eventid','paid'),('$regid','" . $form_element["elementid"] . "', '','$eventid','payment_method')";
						} else {
								$SQL2 .= $SQL2 == "" ? "" : ",";
								$value = $form_element["nameforemail"] == 1 ? "Reserved" : "";
								$SQL2 .= "('$regid'," . $form_element["elementid"] . ",'$value','$eventid','" . $form_element["elementname"] . "')";
							}
					}
				  }
				  $SQL2 = "INSERT INTO events_registrations_values
							(regid,elementid,value,eventid,elementname)
							VALUES" . $SQL2;
			  } else {
				  $template_forms = explode(";", trim($template["formlist"], ';'));
				foreach ($template_forms as $formset) {
					$form = explode(":", $formset);
						$value = strstr($template["registrant_name"], $form[0]) ? "Reserved" : "";
					$SQL2 .= $SQL2 == "" ? "" : ",";
					$SQL2 .= "('$regid','$value','$eventid','" . $form[0]."')";
				}

				$SQL2 = "INSERT INTO events_registrations_values
							(regid,value,eventid,elementname)
							VALUES" . $SQL2;
			  }

			  if (execute_db_sql($SQL2)) { 
				$return[$reserved] = $regid;
			  } else {
				execute_db_sql("DELETE FROM events_registrations
									WHERE regid='$regid'");
				$return[$reserved] = false;
			}
		  } else { $return[$reserved] = false; }
		  $reserved++;
	}
	if (isset($MYVARS->GET["eventid"])) {
		foreach ($return as $key => $val) {
			echo "Reserved spot #". ($key + 1);
			echo $val ? ": Success <br />" : " Failed <br />";
		}
	} else {
		return $return;
	}
}

function copy_registration($regid = false, $eventid = false) {
	global $CFG, $MYVARS, $USER;
	$regid = !$regid ? clean_myvar_req("regid", "int") : $regid;
	$eventid = !$eventid ? clean_myvar_req("copy_reg_to", "int") : $eventid;

	$oldreg = get_db_row("SELECT * FROM events_registrations WHERE regid='$regid'");
	$event = get_event($eventid);
	$blankreg = add_blank_registration($eventid);

	if (isset($blankreg[0])) {
		$newregid = $blankreg[0];
		$SQL = "SELECT * FROM events_registrations_values WHERE regid='$regid'";

		if ($registration_values = get_db_result($SQL)) {
			while ($registration_value = fetch_row($registration_values)) {
				$SQL = 'UPDATE events_registrations_values 
						   SET value = "' . $registration_value["value"] . '"
						 WHERE regid = "' . $newregid . '"
						   AND elementname = "' . $registration_value["elementname"] . '"';
				execute_db_sql($SQL);
			}

			// Reset payment info.
			$SQL = 'UPDATE events_registrations_values
					   SET value = "0"
					 WHERE regid = "' . $newregid . '"
					   AND elementname = "paid"';
			execute_db_sql($SQL);

			$reg = get_db_row("SELECT * FROM events_registrations WHERE regid='$newregid'");
			$total_owed = $reg["date"] < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
			$total_owed = empty($total_owed) ? $event["fee_full"] : $total_owed;

			$SQL = 'UPDATE events_registrations_values
					   SET value = "' . $total_owed . '"
					 WHERE regid = "' . $newregid . '"
					   AND elementname = "total_owed"';
			execute_db_sql($SQL);

			$SQL = 'UPDATE events_registrations
					   SET email = "' . $oldreg["email"] . '"
					 WHERE regid = "' . $newregid . '"';
			execute_db_sql($SQL);
		}
	}
}

function show_registrations() {
global $CFG, $MYVARS, $USER;
	$eventid = clean_myvar_req("eventid", "int");
	$event = get_event($eventid);
	$template_id = $event["template_id"];
	$eventname = $event["name"];
	$pageid = $event["pageid"];
	$selected = clean_myvar_opt("sel", "int", false);
	$initial_display = $selected ? "" : "display:none;";
	$returnme = '<h2>' . stripslashes($eventname) . '</h2>';

	//Print all registration button
	$returnme .= '<span style="width:50%;text-align:right;float:right;">
					<a href="javascript: void(0);"
					   onclick="$(\'#loading_overlay\').show();
								ajaxapi(\'/features/events/events_ajax.php\',
										\'print_registration\',
										\'&amp;regid=false&amp;pageid=' . $pageid . '&amp;eventname=' . urlencode($eventname) . '&amp;eventid=' . $eventid . '&amp;template_id=' . $template_id . '\',
										function() {
											if (xmlHttp.readyState == 4) {
												simple_display(\'searchcontainer\');
												$(\'#loading_overlay\').hide();
											}
										},
										true
								);">
					   Print All Registrations
					</a>
				</span><br />';

	//Print online registrations only button
	$returnme .= '<span style="width:50%;text-align:right;float:right;">
					<a href="javascript: void(0);"
					   onclick="$(\'#loading_overlay\').show();
								ajaxapi(\'/features/events/events_ajax.php\',
										\'print_registration\',
										\'&amp;regid=false&amp;pageid=' . $pageid . '&amp;eventname=' . urlencode($eventname) . '&amp;online_only=1&amp;eventid=' . $eventid . '&amp;template_id=' . $template_id . '\',
										function() {
											if (xmlHttp.readyState == 4) {
												simple_display(\'searchcontainer\');
												$(\'#loading_overlay\').hide();
											}
										},
										true
								);">
						Print Online Registrations
					</a>
				</span><br />';

	if ($registrants = get_db_result(get_registration_sort_sql($eventid))) {
		$i = 0;
		$values = new \stdClass;
		while ($registrant = fetch_row($registrants)) {
			$values->$i = new \stdClass;
			$values->$i->name = ($i + 1) . " - " . get_registrant_name($registrant["regid"]);
			$values->$i->name .= !empty($registrant["verified"]) ? '' : ' [PENDING]';
			$values->$i->regid = $registrant["regid"];
			$i++;
		}

		$params = [
			"properties" => [
				"name" => "registrants",
				"id" => "registrants",
				"style" => "width: 240px",
				"onchange" => 'if ($(this).val().length > 0) { $(\'#event_menu_button\').show(); } else { $(\'#event_menu_button\').hide(); }',
			],
			"values" => $values,
			"valuename" => "regid",
			"displayname" => "name",
			"selected" => $selected,
			"firstoption" => "",
		];

		$returnme .= '<style>
						#event_menu {
							display: none;
							width: 180px;
							border: 1px solid grey;
							font-size: .8em;
							padding: 0px;
							list-style: none;
							background-color: whitesmoke;
							white-space: nowrap;
							position: absolute;
							top: 70px;
						}
						#event_menu li {
							padding: 5px;
						}
						#event_menu li:hover {
							background-color: silver;
						}
					</style>
					<div style="vertical-align:top;">
						<table>
							<tr>
								<td>
									<span style="font-size:.8em">Edit Registration of </span>
									' . make_select($params) . '
								</td>
								<td>
									<a id="event_menu_button" title="Menu" style="' . $initial_display . '" href="javascript: void(0);">
										<img src="' . $CFG->wwwroot . '/images/down.gif" alt="Menu" />
									</a>
									<ul id="event_menu">' .
										'<li>
											<a title="Edit Registration" href="javascript: void(0);"
												onclick="if (document.getElementById(\'registrants\').value != \'\') {
															$(\'#loading_overlay\').show();
															ajaxapi(\'/features/events/events_ajax.php\',
																	\'get_registration_info\',
																	\'&amp;eventid=' . $eventid . '&amp;regid=\'+document.getElementById(\'registrants\').value,
																	function() {
																		if (xmlHttp.readyState == 4) {
																			simple_display(\'searchcontainer\');
																			$(\'#loading_overlay\').hide();
																		}
																	},
																	true
															);
														}">
												<img src="' . $CFG->wwwroot . '/images/edit.png" /> Edit Registration
											</a>
										</li>' .
										'<li>
											<a title="Delete Registration" href="javascript: void(0);"
												onclick="if (document.getElementById(\'registrants\').value != \'\' && confirm(\'Do you want to delete this registration?\')) {
															$(\'#loading_overlay\').show();
															ajaxapi(\'/features/events/events_ajax.php\',
																	\'delete_registration_info\',
																	\'&amp;regid=\'+document.getElementById(\'registrants\').value,
																	function() { do_nothing(); }
															);
															ajaxapi(\'/features/events/events_ajax.php\',
																	\'show_registrations\',
																	\'&amp;eventid=' . $eventid . '\',
																	function() {
																		if (xmlHttp.readyState == 4) {
																			simple_display(\'searchcontainer\');
																			$(\'#loading_overlay\').hide();
																			init_event_menu();
																		}
																	},
																	true
															);
														}">
												<img src="' . $CFG->wwwroot . '/images/delete.png" /> Delete Registration
											</a>
										</li>' .
										'<li>
											<a title="Send Registration Email" href="javascript: void(0);"
												onclick="if (document.getElementById(\'registrants\').value != \'\') {
															$(\'#loading_overlay\').show();
															ajaxapi(\'/features/events/events_ajax.php\',
																	\'resend_registration_email\',
																	\'&amp;pageid=' . $pageid . '&amp;eventid=' . $eventid . '&amp;regid=\'+document.getElementById(\'registrants\').value,
																	function() {
																		if (xmlHttp.readyState == 4) {
																			simple_display(\'searchcontainer\');
																			$(\'#loading_overlay\').hide();
																		}
																	},
																	true
															);
														}">
												<img src="' . $CFG->wwwroot . '/images/mail.png" /> Send Registration Email
											</a>
										</li>' .
									'</ul>' .
						'       </td>
							</tr>
						</table>
					</div><br />';
	}
	$returnme .= '<div>
					A reserved registration will show in the above list.<br />
					<span>Reserve <input type="text" size="2" maxlength="2" id="reserveamount" value="1" onchange="if (IsNumeric(this.value) && this.value > 0) {} else {this.value=1;}" /> Spot(s): </span>
					<a href="javascript: void(0);" onclick="$(\'#loading_overlay\').show();
															ajaxapi(\'/features/events/events_ajax.php\',
																	\'add_blank_registration\',
																	\'&amp;pageid=' . $pageid . '&amp;reserveamount=\'+document.getElementById(\'reserveamount\').value+\'&amp;eventname=' . urlencode($eventname) . '&amp;eventid=' . $eventid . '&amp;template_id=' . $template_id . '\',
																	function() { do_nothing(); }
															);
															ajaxapi(\'/features/events/events_ajax.php\',
																	\'show_registrations\',
																	\'&amp;eventid=' . $eventid . '\',
																	function() {
																		if (xmlHttp.readyState == 4) {
																			simple_display(\'searchcontainer\');
																			$(\'#loading_overlay\').hide();
																			init_event_menu();
																		}
																	},
																	true
															);">
						<img title="Reserve Spot(s)" style="vertical-align:bottom;" onclick="blur()" src="' . $CFG->wwwroot . '/images/reserve.gif" />
					</a>
				</div>';
	echo '<div style="font-size:.9em;padding:15px 5px;">' . $returnme . '</div>';
}

function eventsearch() {
global $CFG, $MYVARS, $USER;
	if (!defined('SEARCH_PERPAGE')) { define('SEARCH_PERPAGE', 8); }
	$userid = $USER->userid; $searchstring = "";
	$searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
	//no search words given
	if ($searchwords == "") {
		$searchwords = '%';
	}
	echo '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';
	//is a site admin
	$admin = is_siteadmin($userid) ? true : false;
	//Create the page limiter
	$pagenum = clean_myvar_opt("pagenum", "int", 0);

	$firstonpage = SEARCH_PERPAGE * $pagenum;
	$limit = " LIMIT $firstonpage," . SEARCH_PERPAGE;
	$words = explode(" ", $searchwords);
	$i = 0;
	while (isset($words[$i])) {
		$searchpart = "(name LIKE '%" . dbescape($words[$i]) . "%')";
		$searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
		$i++;
	}

	if ($MYVARS->GET["pageid"] == $CFG->SITEID) {
		$SQL = "SELECT * FROM events
					WHERE (pageid=" . dbescape($MYVARS->GET["pageid"]) . "
							OR (siteviewable=1 AND confirmed=1))
						AND start_reg !=''
						AND (" . $searchstring . ")
					ORDER BY event_begin_date DESC";
	} else {
		$SQL = "SELECT * FROM events
					WHERE pageid=" . dbescape($MYVARS->GET["pageid"]) . "
						AND start_reg !=''
						AND (" . $searchstring . ")
					ORDER BY event_begin_date DESC";
	}

	$total = get_db_count($SQL); //get the total for all pages returned.
	$SQL .= $limit; //Limit to one page of return.
	$count = $total > (($pagenum + 1) * SEARCH_PERPAGE) ? SEARCH_PERPAGE : $total - (($pagenum) * SEARCH_PERPAGE); //get the amount returned...is it a full page of results?
	$events = get_db_result($SQL);
	$amountshown = $firstonpage + SEARCH_PERPAGE < $total ? $firstonpage + SEARCH_PERPAGE : $total;
	$prev = $pagenum > 0 ? '<a href="javascript: void(0);" onclick="$(\'#loading_overlay\').show();
																	ajaxapi(\'/features/events/events_ajax.php\',
																			\'eventsearch\',
																			\'&amp;pageid=' . $MYVARS->GET["pageid"] . '&pagenum=' . ($pagenum - 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
																			function() {
																				if (xmlHttp.readyState == 4) {
																					simple_display(\'searchcontainer\');
																					$(\'#loading_overlay\').hide();
																				}
																			},
																			true
																	);"
							onmouseup="this.blur()">
								<img src="' . $CFG->wwwroot . '/images/prev.gif" title="Previous Page" alt="Previous Page">
							</a>' : "";
	$info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
	$next = $firstonpage + SEARCH_PERPAGE < $total ? '<a href="javascript: void(0);" onclick="$(\'#loading_overlay\').show();
																										ajaxapi(\'/features/events/events_ajax.php\',
																												\'eventsearch\',
																												\'&amp;pageid=' . $MYVARS->GET["pageid"] . '&amp;pagenum=' . ($pagenum + 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
																												function() {
																													if (xmlHttp.readyState == 4) {
																														simple_display(\'searchcontainer\');
																														$(\'#loading_overlay\').hide();
																													}
																												},
																												true
																										);"
																onmouseup="this.blur()">
																	<img src="' . $CFG->wwwroot . '/images/next.gif" title="Next Page" alt="Next Page">
																</a>' : "";
	$header = $body = "";
	if ($count > 0) {
		while ($event = fetch_row($events)) {
	  		$export = "";
			$header = $header == "" ? '<table style="width:100%;"><tr><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><p>' : $header;

			if ($event["start_reg"] > 0) {
				$regcount = get_db_count("SELECT * FROM events_registrations
											WHERE eventid='" . $event['eventid'] . "'");
				$limit = $event['max_users'] == "0" ? "&#8734;" : $event['max_users'];
				//GET EXPORT CSV BUTTON
				if (user_is_able($USER->userid, "exportcsv", $event["pageid"])) {
					  $export = '<a href="javascript: void(0)" onclick="ajaxapi(\'/features/events/events_ajax.php\',
																			  \'export_csv\',
																			  \'&pageid=' . $event["pageid"] . '&eventid=' . $event['eventid'] . '\',
																			  function() { run_this();}
																		);">
									<img src="' . $CFG->wwwroot . '/images/csv.png" title="Export ' . $regcount . '/' . $limit . ' Registrations" alt="Export ' . $regcount . ' Registrations" />
								</a>';}
		 		}

			$body .= '<tr style="height:30px;border:3px solid white;font-size:.9em;">
						<td style="width:40%;padding:5px;font-size:.85em;white-space:nowrap;">
							<a href="javascript: void(0)" onclick="$(\'#loading_overlay\').show();
																	ajaxapi(\'/features/events/events_ajax.php\',
																			\'show_registrations\',
																			\'&eventid=' . $event["eventid"] . '\',
																			function() {
																				if (xmlHttp.readyState == 4) {
																					simple_display(\'searchcontainer\');
																					$(\'#loading_overlay\').hide();
																					init_event_menu();
																				}
																			},
																			true
																	);"
							onmouseup="this.blur()">
								' . $event["name"] . '
							</a>
						</td>
						<td style="width:20%;padding:5px;font-size:.75em;">
							' . date("m/d/Y", $event["event_begin_date"]) . ' ' . $export . '
						</td>
						<td style="text-align:right;padding:5px;">
							<a href="mailto:' . $event["email"] . '" />' . $event["contact"] . '</a>
						</td>
					</tr>';
		}
		$body = '<table style="background-color:#F3F6FB;width:100%;border-collapse:collapse;">' . $body . '</table>';
	} else {
		echo '<span class="error_text" class="centered_span">No matches found.</span>';
	}
	echo $header . $body;
}

function lookup_reg() {
global $CFG, $MYVARS, $USER;
	$code = clean_myvar_opt("code", "string", "");

	$SQL = "SELECT * FROM events_registrations WHERE code = '$code'";

	if (strlen($code) > 5 && $registration = get_db_row($SQL)) {
		if ($event = get_db_row("SELECT * FROM events
									WHERE eventid=" . $registration["eventid"] . "
										AND fee_full != 0")) {
			echo "<h3>Registration Found</h3> ";
			$registrant_name = get_registrant_name($registration["regid"]);
			echo "<b>Event: " . $event["name"] . " - $registrant_name's Registration</b>";
			$total_owed = get_db_field("value", "events_registrations_values", "regid=" . $registration["regid"] . " AND elementname='total_owed'");
			if (empty($total_owed)) {
				$total_owed = $registration["date"] < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
			}
			$paid = get_db_field("value", "events_registrations_values", "regid=" . $registration["regid"] . " AND elementname='paid'");
			$paid = empty($paid) ? "0.00" : $paid;
			$remaining = $total_owed - $paid;
			$registrant_name = get_registrant_name($registration["regid"]);

			echo "<br /><br />Total Owed:  $" . number_format($total_owed,2) . "<br />";
			echo "Amount Paid:  $" . number_format($paid,2) . "<br />";
			echo "<b>Remaining Balance:  $" . number_format($remaining,2) . "</b><br />";

			if ($remaining > 0) {
				$item[0] = new \stdClass;
				$item[0]->description = "Event: " . $event["name"] . " - $registrant_name's Registration - Remaining Balance Payment";
				$item[0]->cost = $remaining;
				$item[0]->regid = $registration["regid"];
				echo '<br />' . make_paypal_button($item, $event["paypal"]);
			}
		} else {
			echo "<center><h3>We are unable to provide payment options for this registration id.</h3></center>";
		}
	} else {
		echo '<div style="text-align:center;"><br /><br /><strong>No registration found.</strong></div>';
	}
}

function pick_registration() {
global $CFG, $MYVARS, $USER, $error;
	if (!defined('COMLIB')) { include_once($CFG->dirroot . '/lib/comlib.php'); }
	$reginfo = $MYVARS->GET;
	$eventid = clean_myvar_req("eventid", "int", false);
	$payment_amount = clean_myvar_opt("payment_amount", "string", false);
	$total_owed = clean_myvar_opt("total_owed", "string", false);
	$email = clean_myvar_opt("email", "string", false);
	$payment_method = clean_myvar_opt("payment_method", "string", false);
	$items = clean_myvar_opt("items", "string", false);

	$event = get_event($eventid);
	if ($event['fee_full'] !== 0 && $payment_amount) {
		$cart_total = !empty($total_owed) ? $total_owed + $payment_amount : $payment_amount;
		$reginfo["cart_total"] = $cart_total;
		$reginfo["total_owed"] = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
	}

	$error = "";
	if ($regid = enter_registration($event['eventid'], $reginfo, $email)) { //successful registration
		echo '<center><div style="width:70%">You have successfully registered for ' . $event['name'] . '.<br />';

		if ($error != "") { echo $error . "<br />";}

		if ($event['allowinpage'] != 0) {
			if (is_logged_in() && $event['pageid'] != $CFG->SITEID) {
				subscribe_to_page($event['pageid'], $USER->userid);
				echo 'You have been automatically allowed into this events web page.  This page contain specific information about this event . ';
			}

			if ($event['fee_full'] != 0) {
				$registrant_name = get_registrant_name($regid);
				$items = $items ? "$items**$regid::Event: " . $event["name"] . " - $registrant_name's Registration::$payment_amount" : $regid . "::Event: " . $event["name"] . " - $registrant_name's Registration::$payment_amount";
				echo '<div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="' . $cart_total . '" />
					 <input type="hidden" name="items" id="items" value="' . $items . '" /></div>';

				$items = explode("**", $items);
				$i = 0;
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

				if ($payment_method == "PayPal") {
					echo '<br />
					If you would like to pay the <span style="color:blue;font-size:1.25em;">$' . $cart_total . '</span> fee now, click the Paypal button below.
					<center>
					' . make_paypal_button($cart_items, $event['paypal']) . '
					</center>
					<br /><br />
					Your registration will be complete upon payment. ';
				} else {
					echo '<br />
					If you are done with the registration process, please make out your <br />
					check or money order in the amount of <span style="color:blue;font-size:1.25em;">$' . $cart_total . '</span> payable to <b>' . $event["payableto"] . '</b> and send it to <br /><br />
					<center>
					' . $event['checksaddress'] . '.
					</center>
					<br /><br />
					Thank you for registering for this event.
					';
				}
				echo '</div></center>';
			}
		}

		$touser = (object)[
			'email' => get_db_field("email", "events_registrations", "regid='$regid'"),
			'fname' => $registrant_name = get_registrant_name($regid),
			'lname' => "",
		];

		$fromuser = (object)[
			'email' => $event['email'],
			'fname' => $CFG->sitename,
			'lname' => "",
		];

		$message = registration_email($regid, $touser);
		send_email($touser, $fromuser, $event["name"] . " Registration", $message);

		log_entry("events", $eventid, "Registered for Event"); // Log
	} else { //failed registration
		log_entry("events", $eventid, "Failed Event Registration"); // Log
		echo '<center><div style="width:60%"><span class="error_text">Your registration for ' . $event['name'] . ' has failed. </span><br /> ' . $error . '</div>';
	}
}

function delete_limit() {
global $CFG, $MYVARS;
	$template_id = clean_myvar_opt("template_id", "int", false);
	$limit_type = clean_myvar_opt("limit_type", "string", false);
	$limit_num = clean_myvar_opt("limit_num", "int", 0);
	$hard_limits = clean_myvar_opt("hard_limits", "string", false);
	$soft_limits = clean_myvar_opt("soft_limits", "string", false);
	$hidden_variable1 = $hidden_variable2 = "";

	$returnme = "";
	if (!$template_id) { echo $returnme;}

	$template = get_event_template($template_id);

	if ($hard_limits) { // There are some hard limits
		$limits_array = explode("*", $hard_limits);
		$i = 0;
		$returnme .= "<br /><b>Hard Limits</b> <br />";
		$alter = 0;
		while (isset($limits_array[$i])) {
			if (!($limit_type == "hard_limits" && $limit_num == $i)) {
				$limit = explode(":", $limits_array[$i]);
				$displayname = get_template_field_displayname($template["template_id"], $limit[0]);
				$returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . ' <a href="javascript:void(0);" onclick="delete_limit(\'hard_limits\',\'' . ($i - $alter) . '\');">Delete</a><br />';
				$hidden_variable1 .= $hidden_variable1 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
			} else {  $alter++; }
			$i++;
		}
	}

	if ($hidden_variable1 == "") { $returnme = ""; }
	$returnme2 = "";
	if ($soft_limits) { // There are some soft limits
		$limits_array = explode("*", $soft_limits);
		$i = 0;
		$returnme2 .= "<br /><b>Soft Limits</b> <br />";
		$alter = 0;
		while (isset($limits_array[$i])) {
			if (!($limit_type == "soft_limits" && $limit_num == $i)) {
				$limit = explode(":", $limits_array[$i]);
				$displayname = get_template_field_displayname($template["template_id"], $limit[0]);
				$returnme2 .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . ' <a href="javascript:void(0);" onclick="delete_limit(\'soft_limits\',\'' . ($i - $alter) . '\');">Delete</a><br />';
				$hidden_variable2 .= $hidden_variable2 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
			} else { $alter++; }
			$i++;
		}
	}

	if ($hidden_variable2 == "") { $returnme2 = ""; }

	echo $returnme . $returnme2 . '<input type="hidden" id="hard_limits" value="' . $hidden_variable1 . '" /><input type="hidden" id="soft_limits" value="' . $hidden_variable2 . '" />';
}

function add_custom_limit() {
	$template_id = clean_myvar_opt("template_id", "int", false);
	$hard_limits = clean_myvar_opt("hard_limits", "string", false);
	$soft_limits = clean_myvar_opt("soft_limits", "string", false);
	$hidden_variable1 = $hidden_variable2 = "";

	$returnme = "";
	if (!$template_id) { emptyreturn(); }

	$template = get_event_template($template_id);

	if ($hard_limits) { // There are some hard limits
		$limits_array = explode("*", $hard_limits);
		$i = 0;
		$returnme .= "<br /><b>Hard Limits</b> <br />";
		while (isset($limits_array[$i])) {
			$limit = explode(":", $limits_array[$i]);
			if (isset($limit[3])) {
				if ($template["folder"] == "none") {
					$displayname = get_db_field("display", "events_templates_forms", "elementid=" . $limit[0]);
				} else {
					$displayname = $limit[0];
				}
	
				$returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . ' <a href="javascript:void(0);" onclick="delete_limit(\'hard_limits\',\'' . $i . '\');">Delete</a><br />';
				$hidden_variable1 .= $hidden_variable1 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];             
			}
			$i++;
		}
	}

	if ($soft_limits) { // There are some soft limits
		$limits_array = explode("*", $soft_limits);
		$i = 0;
		$returnme .= "<br /><b>Soft Limits</b> <br />";
		while (isset($limits_array[$i])) {
			$limit = explode(":", $limits_array[$i]);

			if ($template["folder"] == "none") {
				$displayname = get_db_field("display", "events_templates_forms", "elementid=" . $limit[0]);
			} else {
				$displayname = $limit[0];
			}

			$returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . ' <a href="javascript:void(0);" onclick="delete_limit(\'soft_limits\',\'' . $i . '\');">Delete</a><br />';
			$hidden_variable2 .= $hidden_variable2 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
			$i++;
		}
	}

	echo $returnme . '<input type="hidden" id="hard_limits" value="' . $hidden_variable1 . '" /><input type="hidden" id="soft_limits" value="' . $hidden_variable2 . '" />';
}

function get_limit_form() {
	$template_id = clean_myvar_req("template_id", "int");
	$template = get_event_template($template_id);

	if ($template) {
		if ($template["folder"] == "none") {
			$SQL = "SELECT *
					FROM events_templates_forms
					WHERE template_id = ||template_id||
					AND type != 'payment'";
			$values = get_db_result($SQL, ["template_id" => $template_id]);
		} else {
			$values = [];
			$formlist = explode(";", $template['formlist']);
			foreach ($formlist as $f) {
				$el = explode(":", $f);
				if (isset($el[2]) && $el[1] != "Pay") {
					$values[] = [
						"elementid" => $el[0],
						"type" => $el[1],
						"display" => $el[2],
					];
				}
			}
		}
	}

	$params = [
		"properties" => [
			"name" => "custom_limit_fields",
			"id" => "custom_limit_fields",
			"style" => "margin: 0px",
		],
		"values" => $values,
		"valuename" => "elementid",
		"displayname" => "display",
	];
	$fields = make_select($params);

	$params = [
		"properties" => [
			"name" => "operators",
			"id" => "operators",
			"style" => "margin: 0px",
		],
		"values" => [
			["elementid" => "eq", "display" => "equal to"],
			["elementid" => "neq", "display" => "not equal to"],
			["elementid" => "lk", "display" => "similar to"],
			["elementid" => "nlk", "display" => "not similar to"],
			["elementid" => "gt", "display" => "greater than"],
			["elementid" => "gteq", "display" => "greater than or equal to"],
			["elementid" => "lt", "display" => "less than"],
			["elementid" => "lteq", "display" => "less than or equal to"],
		],
		"valuename" => "elementid",
		"displayname" => "display",
	];
	$operators = make_select($params);

	echo '
	<br />
	<table style="margin:0px 0px 0px 50px;">
		<tr>
			<td class="field_input" colspan="2">
				Place a limit of <input id="custom_limit_num" type="text" size="3" style="margin: 0px" /> registrations
				<br />where ' . $fields . ' is ' . $operators . ' <input id="custom_limit_value" type="text" />
				<br /><br />
			</td>
		</tr>
		<tr>
			<td class="sub_field_title" style="width: 75px;">
				Soft Limit:
			</td>
			<td class="field_input">
			<select id="custom_limit_sorh"><option value="0">No</option><option value="1">Yes</option></select>
			' . get_hint_box("input_event_custom_limit_sorh:events") . '
			</td>
		</tr>
	</table>
	<div style="text-align: center;width: 360px;white-space: normal;margin: auto;">
		<span style="display:inline-block;" id="custom_limit_fields_error" class="error_text"></span>
		<span style="display:inline-block;" id="custom_limit_value_error" class="error_text"></span>
		<span style="display:inline-block;" id="custom_limit_num_error" class="error_text"></span>
		<span style="display:inline-block;" id="custom_limit_sorh_error" class="error_text"></span><br />
		<input type="button" value="Add" onclick="add_custom_limit();" />
	</div>
	' . js_code_wrap('prepareInputsForHints();');
}

function add_new_location() {
global $USER;
	$eventid = clean_myvar_opt("eventid", "int", false);
	$name = clean_myvar_req("name", "string");
	$add1 = clean_myvar_req("add1", "string");
	$add2 = clean_myvar_req("add2", "string");
	$zip = clean_myvar_req("zip", "string");
	$phone = clean_myvar_opt("phone", "string", "");
	$shared = clean_myvar_opt("shared", "int", 0);

	$SQL = "INSERT INTO events_locations (location, address_1, address_2, zip, phone, userid, shared)
	VALUES(||location||, ||address_1||, ||address_2||, ||zip||, ||phone||, ||userid||, ||shared||)";

	$params = [
		"location" => $name,
		"address_1" => $add1,
		"address_2" => $add2,
		"zip" => $zip,
		"phone" => $phone,
		"userid" => $USER->userid,
		"shared" => $shared,
	];
	$id = execute_db_sql($SQL, $params);

	log_entry("events", $name, "Added Location");
	echo get_my_locations($USER->userid, $id, $eventid);
}

function submit_new_event($request=false) {
global $CFG, $MYVARS;
	date_default_timezone_set(date_default_timezone_get());
	$eventid = clean_myvar_opt("eventid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", false);
	if (!$pageid && $eventid) {
		$pageid = get_db_field("pageid", "events", "eventid = ||eventid||", ["eventid" => $eventid]);
	}
	$name = clean_myvar_req("event_name", "string");
	$contact = clean_myvar_req("contact", "string");
	$email = clean_myvar_req("email", "string");
	$phone = clean_myvar_req("phone", "string");
	$location = clean_myvar_req("location", "string");
	$category = clean_myvar_req("category", "int");
	$siteviewable = clean_myvar_opt("siteviewable", "int", 0);
	$byline = clean_myvar_req("byline", "string");
	$description = clean_myvar_req("description", "html");
	$multiday = clean_myvar_opt("multiday", "bool", false);
	$workers = clean_myvar_opt("workers", "bool", false);

	//strtotime php5 fixes
	$event_begin_date = clean_myvar_req("event_begin_date", "string");
	if ($event_begin_date) {
		$event_begin_date = strtotime($event_begin_date);
	}

	$event_end_date = clean_myvar_opt("event_end_date", "string", $event_begin_date);
	if ($event_end_date) {
		$event_end_date = $multiday == "1" ? strtotime($event_end_date) : $event_begin_date;
	}

	$allday = clean_myvar_opt("allday", "bool", true);
	$event_begin_time = !$allday ? clean_myvar_req("begin_time", "string") : '';
	$event_end_time = !$allday ? clean_myvar_req("end_time", "string") : '';

	$reg = clean_myvar_opt("reg", "bool", false);
	$allowinpage = clean_myvar_opt("allowinpage", "int", 0);
	$allowinpage = $reg && $allowinpage ? $pageid : 0; // if a user will be enrolled in the page after online registration

	$max_users = clean_myvar_opt("max", "int", 0);
	$max_users = $reg && $max_users ? $max_users : 0;
	$hard_limits = clean_myvar_opt("hard_limits", "string", false);
	$hard_limits = $reg && $hard_limits ? $hard_limits : "0";
	$soft_limits = clean_myvar_opt("soft_limits", "string", false);
	$soft_limits = $reg && $soft_limits ? $soft_limits : "0";

	// strtotime php5 fixes
	$start_reg = clean_myvar_opt("start_reg", "string", false);
	if ($start_reg) {
		if (strstr($start_reg, "/")) {
			$startr = explode("/", $start_reg);
			$start_reg = $reg ? strtotime("$startr[1]/$startr[2]/$startr[0]") : 0;
		} else {
			$start_reg = $reg ? strtotime($start_reg) : 0;
		}
	} else { $start_reg = 0; }

	$stop_reg = clean_myvar_opt("stop_reg", "string", false);
	if ($stop_reg) {
		if (strstr($stop_reg, "/")) {
			$stopr = explode("/", $stop_reg);
			$stop_reg = $reg ? strtotime("$stopr[1]/$stopr[2]/$stopr[0]") : 0;
		} else {
			$stop_reg = $reg ? strtotime($stop_reg) : 0;
		}
	} else { $stop_reg = 0; }

	$template_id = clean_myvar_opt("template", "int", 0);
	$template_id = $reg ? $template_id : 0;

	$fee = clean_myvar_opt("fee", "bool", false);
	$fee_full = !$fee ? 0 : clean_myvar_opt("full_fee", "int", 0);
	$fee_min = !$fee ? 0 : clean_myvar_opt("min_fee", "int", 0);
	$sale_fee = !$fee ? 0 : clean_myvar_opt("sale_fee", "int", 0);
	$sale_fee = !$sale_fee ? 0 : $sale_fee;

	$sale_end = clean_myvar_opt("sale_end", "string", false);
	if ($sale_end) {
		if (strstr($sale_end, "/")) {
			$se = explode("/", $sale_end);
			$sale_end = $sale_fee != '0' ? strtotime("$se[1]/$se[2]/$se[0]") : 0;
		} else {
			$sale_end = $sale_fee != '0' ? strtotime($sale_end) : 0;
		}
	} else { $sale_end = 0; }

	$payableto = !$fee ? '' : clean_myvar_opt("payableto", "html", "");
	$checksaddress = !$fee ? '' : clean_myvar_opt("checksaddress", "html", "");
	$paypal = !$fee ? '' : clean_myvar_opt("paypal", "html", "");

	$confirmed = 3;
	$caleventid = 0;

	if (!$eventid) { // New Event
		try {
			start_db_transaction();
			$SQL = fetch_template("dbsql/events.sql", "insert_event", "events");
			$params = [
				"pageid" => $pageid, "template_id" => $template_id, "name" => $name, "category" => $category, "location" => $location,
				"allowinpage" => $allowinpage, "start_reg" => $start_reg, "stop_reg" => $stop_reg, "max_users" => $max_users, "event_begin_date" => $event_begin_date,
				"event_begin_time" => $event_begin_time, "event_end_date" => $event_end_date, "event_end_time" => $event_end_time, "confirmed" => $confirmed,
				"siteviewable" => $siteviewable, "allday" => $allday, "caleventid" => $caleventid, "byline" => $byline, "description" => $description, "fee_min" => $fee_min,
				"fee_full" => $fee_full, "payableto" => $payableto, "checksaddress" => $checksaddress, "paypal" => $paypal, "sale_fee" => $sale_fee, "sale_end" => $sale_end,
				"contact" => $contact, "email" => $email, "phone" => $phone, "hard_limits" => $hard_limits, "soft_limits" => $soft_limits, "workers" => $workers,
			];
			if ($eventid = execute_db_sql($SQL, $params)) {
				refresh_calendar_events($eventid);
				$MYVARS->GET["eventid"] = $eventid;
				save_template_settings($template_id, $MYVARS->GET);

				if ($request) { return $eventid; }

				log_entry("events", $eventid, "Event Added");
				if (!$request) { echo "Event Added"; }
			} else {
				if (!$request) {
					// Log event error
					log_entry("events", 0, "Event could NOT be added");

					throw new \Exception("Event could NOT be added");
				}
			}

			if ($pageid == $CFG->SITEID && $eventid) {
				confirm_event($pageid, $eventid, true);
			}

			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		}
	} else {
		try {
			start_db_transaction();
			$SQL = fetch_template("dbsql/events.sql", "update_event", "events");
			$params = [
				"eventid" => $eventid, "template_id" => $template_id, "name" => $name, "category" => $category, "location" => $location,
				"allowinpage" => $allowinpage, "start_reg" => $start_reg, "stop_reg" => $stop_reg, "max_users" => $max_users, "event_begin_date" => $event_begin_date,
				"event_begin_time" => $event_begin_time, "event_end_date" => $event_end_date, "event_end_time" => $event_end_time, "confirmed" => $confirmed,
				"siteviewable" => $siteviewable, "allday" => $allday, "byline" => $byline, "description" => $description, "fee_min" => $fee_min,
				"fee_full" => $fee_full, "payableto" => $payableto, "checksaddress" => $checksaddress, "paypal" => $paypal, "sale_fee" => $sale_fee, "sale_end" => $sale_end,
				"contact" => $contact, "email" => $email, "phone" => $phone, "hard_limits" => $hard_limits, "soft_limits" => $soft_limits, "workers" => $workers,
			];
			if (execute_db_sql($SQL, $params)) {
				refresh_calendar_events($eventid);

				//Delete old template settings info just in case things have changed
				execute_db_sql("DELETE
								FROM settings
								WHERE type = 'events_template'
								AND extra = ||extra||", ["extra" => $eventid]);

				save_template_settings($template_id, $MYVARS->GET);

				log_entry("events", $eventid, "Event Edited");
				if (!$request) { echo "Event Edited"; }
			} else {
				if (!$request) {
					log_entry("events", $eventid, "Event could NOT be edited");
					echo "Event could NOT be Edited";
				}
			}

			if ($pageid == $CFG->SITEID && $eventid) {
				confirm_event($pageid, $eventid, true);
			}
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		}
	}
}

function get_end_time() {
	$starttime = clean_myvar_opt("starttime", "int", false);
	$endtime = clean_myvar_opt("endtime", "int", false);
	$limit = clean_myvar_opt("limit", "int", false);

	// event is not a multi day event and endtime is already set
	if ($limit == 1 && $endtime) {
		echo get_possible_times("end_time", $endtime, $starttime);
	} elseif ($limit == 1) {
		echo get_possible_times("end_time", false, $starttime);
	} elseif ($limit == 0 && $endtime) {
		echo get_possible_times("end_time", $endtime);
	} elseif ($limit == 0) {
		echo get_possible_times("end_time");
	}
}

function unique() {
	$eventid = clean_myvar_req("eventid", "int");
	$elementid = clean_myvar_req("elementid", "int");
	$value = clean_myvar_req("value", "string");

	if (is_unique("events_registrations_values", "elementid='$elementid' AND eventid='$eventid' AND value='$value'")) {
		echo "false";
	}
	echo "true";
}

function unique_relay() {
global $CFG, $MYVARS;
	$table = clean_myvar_req("table", "string");
	$key = clean_myvar_req("key", "string");
	$value = clean_myvar_req("value", "string");

	if (is_unique($table, "$key='$value'")) {
		echo "false";
	}
	echo "true";
}

function add_location_form() {
global $USER, $CFG, $MYVARS;
	$eventid = clean_myvar_req("eventid", "int");
	$formtype = clean_myvar_req("formtype", "string");

	switch($formtype) {
		case "new":
			echo new_location_form($eventid);
			break;
		case "existing":
			echo location_list_form($eventid);
			break;
	}
}

function copy_location() {
global $USER;
	$eventid = clean_myvar_req("eventid", "int");
	$location = clean_myvar_req("location", "int");
	execute_db_sql("UPDATE events_locations
					SET userid = CONCAT(userid,||userid||)
					WHERE id = ||id||", ["id" => $location, "userid" => $USER->userid . ","]);
	echo get_my_locations($USER->userid, $location, $eventid);
}

function get_location_details() {
	$location = clean_myvar_req("location", "int");
	$row = get_db_row("SELECT * FROM events_locations WHERE id = ||id||", ["id" => $location]);

	$returnme = '
	<b>' . $row['location'] . '</b><br />
	' . $row['address_1'] . '<br />
	' . $row['address_2'] . '  ' . $row['zip'] . '<br />
	' . $row['phone'];
	echo $returnme;
}

function export_csv() {
global $CFG, $USER;
	if (!defined('FILELIB')) { include_once ($CFG->dirroot . '/lib/filelib.php'); }
	date_default_timezone_set(date_default_timezone_get());
	$CSV = "Registration Date,Contact Email";
	$eventid = clean_myvar_req("eventid", "int");
	$event = get_event($eventid);
	$template = get_event_template($event['template_id']);

	if ($template['folder'] != "none") {
		$formlist = explode(";", $template['formlist']);
		$sortby = "elementname";
		$i = 0;
		while (isset($formlist[$i])) {
			$element = explode(":", $formlist[$i]);
			$CSV .= "," . $element[2];
			$i++;
		}
		$CSV .= "\n";
	} else {
		$formlist = get_db_result("SELECT * FROM events_templates_forms
										WHERE template_id='" . $event['template_id'] . "'
										ORDER BY sort");
		$sortby = "elementid";
		while ($form = fetch_row($formlist)) {
			$CSV .= "," . $form["display"];
		}
		$CSV .= "\n";
	}

	if ($registrations = get_db_result("SELECT * FROM events_registrations
											WHERE eventid='$eventid'
												AND queue='0'
												AND verified='1'")) {
		while ($regid = fetch_row($registrations)) {
			$row = date("F j g:i a", $regid["date"]) . "," . $regid["email"];
			$values = get_db_result("SELECT * FROM events_registrations_values
										WHERE regid='" . $regid["regid"] . "'
										ORDER BY entryid");
			$reorder = [];
			while ($value = fetch_row($values)) {
				$reorder[$value[$sortby]] = $value;
			}
			if ($template['folder'] != "none") {
				$i=0;$formlist = explode(";", $template['formlist']);
					while (isset($formlist[$i])) {
	  				$element = explode(":", $formlist[$i]);
					$row .= ',"' . $reorder[$element[0]]["value"] . '"';
	  				$i++;
	  			}
			} else {
					$formlist = get_db_result("SELECT * FROM events_templates_forms
												WHERE template_id='" . $event['template_id'] . "'
												ORDER BY sort");
				$sortby = "elementid";
	  			while ($form = fetch_row($formlist)) {
					$row .= ',"' . $reorder[$form[$sortby]]["value"] . '"';
	  			}
			}
			$CSV .= $row . "\n";
		}
	}

	if ($registrations = get_db_result("SELECT *
										FROM events_registrations
										WHERE eventid = ||eventid||
										AND queue = 0
										AND verified = 0", ["eventid" => $eventid])) {
		$CSV .= "\nPENDING\n";
		while ($regid = fetch_row($registrations)) {
			$row = date("F j g:i a", $regid["date"]) . "," . $regid["email"];
			$values = get_db_result("SELECT *
									 FROM events_registrations_values
									 WHERE regid = ||regid||
									 ORDER BY entryid", ["regid" => $regid["regid"]]);
			$reorder = [];
			while ($value = fetch_row($values)) {
				$reorder[$value[$sortby]] = $value;
			}
			if ($template['folder'] != "none") {
				$i=0;$formlist = explode(";", $template['formlist']);
					while (isset($formlist[$i])) {
	  				$element = explode(":", $formlist[$i]);
					$row .= ',"' . $reorder[$element[0]]["value"] . '"';
	  				$i++;
	  			}
			} else {
					$formlist = get_db_result("SELECT *
											   FROM events_templates_forms
											   WHERE template_id = ||template_id||
											   ORDER BY sort", ["template_id" => $event['template_id']]);
				$sortby = "elementid";
	  			while ($form = fetch_row($formlist)) {
					$row .= ',"' . $reorder[$form[$sortby]]["value"] . '"';
	  			}
			}
			$CSV .= $row . "\n";
		}
	}

	if ($registrations = get_db_result("SELECT *
										FROM events_registrations
										WHERE eventid = ||eventid||
										AND queue = 1", ["eventid" => $eventid])) {
		$CSV .= "\nQUEUE\n";
		while ($regid = fetch_row($registrations)) {
			$row = date("F j g:i a", $regid["date"]) . "," . $regid["email"];
			$values = get_db_result("SELECT *
									 FROM events_registrations_values
									 WHERE regid = ||regid||
									 ORDER BY entryid", ["regid" => $regid["regid"]]);
			$reorder = [];
			while ($value = fetch_row($values)) {
				$reorder[$value[$sortby]] = $value;
			}
			if ($template['folder'] != "none") {
				$i=0;$formlist = explode(";", $template['formlist']);
					while (isset($formlist[$i])) {
	  				$element = explode(":", $formlist[$i]);
					$row .= ',"' . $reorder[$element[0]]["value"] . '"';
	  				$i++;
	  			}
			} else {
					$formlist = get_db_result("SELECT *
											   FROM events_templates_forms
											   WHERE template_id = ||template_id||
											   ORDER BY sort", ["template_id" => $event['template_id']]);
				$sortby = "elementid";
	  			while ($form = fetch_row($formlist)) {
					$row .= ',"' . $reorder[$form[$sortby]]["value"] . '"';
	  			}
			}
			$CSV .= $row . "\n";
		}
	}
	echo get_download_link("export.csv", $CSV);
}

function show_template_settings() {
	$templateid = clean_myvar_req("templateid", "int");
	$eventid = clean_myvar_opt("eventid", "int", false);
	echo get_template_settings_form($templateid, $eventid);
}

function send_facebook_message() {
global $CFG, $MYVARS;
	// Require facebook library.
	require_once ($CFG->dirroot . '/features/events/facebook/facebook.php');

	$info = unserialize(base64_decode($MYVARS->GET["info"]));

	$eventid = $info[0];
	$name = $info[1];
	$config = $info[2];

	$event = get_event($eventid);
	$facebook = new Facebook($config);

	if ($user_id = $facebook->getUser()) {
	  // We have a user ID, so probably a logged in user.
		try {
			$ret_obj = $facebook->api('/me/feed', 'POST',
			[
				'link' => $CFG->wwwroot,
				'message' => $name . ' has registered to attend ' . $event["name"] . '!',
			]);
			echo js_code_wrap('window.location = "//www.facebook.com";');
		} catch(FacebookApiException $e) {
			echo js_code_wrap('jwindow.close();');
		}
	}
}

function templatesearch() {
global $CFG, $MYVARS, $USER;
	if (!defined('SEARCH_PERPAGE')) { define('SEARCH_PERPAGE', 8); }
	$userid = $USER->userid; $searchstring = "";
	$searchwords = trim($MYVARS->GET["searchwords"]);
	//no search words given
	if ($searchwords == "") {
		$searchwords = '%';
	}
	echo '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';
	//is a site admin
	$admin = is_siteadmin($userid) ? true : false;
	//Create the page limiter
	$pagenum = clean_myvar_opt("pagenum", "int", 0);

	$firstonpage = SEARCH_PERPAGE * $pagenum;
	$limit = " LIMIT $firstonpage," . SEARCH_PERPAGE;
	$words = explode(" ", $searchwords);
	$i = 0;
	while (isset($words[$i])) {
		$searchpart = "(name LIKE '%" . dbescape($words[$i]) . "%')";
		$searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
		$i++;
	}

	$SQL = "SELECT *
			FROM events_templates
			WHERE (" . $searchstring . ")
			ORDER BY name";

	$total = get_db_count($SQL); //get the total for all pages returned.
	$SQL .= $limit; //Limit to one page of return.
	$count = $total > (($pagenum + 1) * SEARCH_PERPAGE) ? SEARCH_PERPAGE : $total - (($pagenum) * SEARCH_PERPAGE); //get the amount returned...is it a full page of results?
	$results = get_db_result($SQL);
	$amountshown = $firstonpage + SEARCH_PERPAGE < $total ? $firstonpage + SEARCH_PERPAGE : $total;
	$prev = $pagenum > 0 ? '<a href="javascript: void(0);" onclick="$(\'#loading_overlay\').show();
																	ajaxapi(\'/features/events/events_ajax.php\',
																			\'templatesearch\',
																			\'&amp;pagenum=' . ($pagenum - 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
																			function() {
																				if (xmlHttp.readyState == 4) {
																					simple_display(\'searchcontainer\');
																					$(\'#loading_overlay\').hide();
																				}
																			},
																			true
																	);"
							onmouseup="this.blur()">
								<img src="' . $CFG->wwwroot . '/images/prev.gif" title="Previous Page" alt="Previous Page">
							</a>' : "";
	$info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
	$next = $firstonpage + SEARCH_PERPAGE < $total ? '<a onmouseup="this.blur()" href="javascript: void(0);" onclick="$(\'#loading_overlay\').show();
																																ajaxapi(\'/features/events/events_ajax.php\',
																																		\'templatesearch\',
																																		\'&amp;pagenum=' . ($pagenum + 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
																																		function() {
																																			if (xmlHttp.readyState == 4) {
																																				simple_display(\'searchcontainer\');
																																				$(\'#loading_overlay\').hide();
																																			}
																																		},
																																		true
																																);">
																	<img src="' . $CFG->wwwroot . '/images/next.gif" title="Next Page" alt="Next Page">
																</a>' : "";
	$header = $body = "";
	if ($count > 0) {
		$body .= '<tr style="height:30px;border:3px solid white;font-size:.9em;">
						<th style="text-align:left;width:40%;padding:5px;white-space:nowrap;">
							Name
						</th>
						<th style="width:20%;padding:5px;">
							Type
						</th>
						<th>
							Settings
						</th>
						<th style="width: 10%;text-align:center;padding:5px;">
							Activated
						</th>
					</tr>';
		while ($template = fetch_row($results)) {
	  		$export = "";
			$header = $header == "" ? '<table style="width:100%;"><tr><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><p>' : $header;

			$type = $template["folder"] == "none" ? "DB" : "FOLDER";

			if (!empty($template["activated"])) { // ACTIVE
				$status = '<a href="javascript: void(0)" onclick="ajaxapi(\'/features/events/events_ajax.php\',
																		  \'change_template_status\',
																		  \'&amp;status=1&amp;template_id=' . $template["template_id"] . '\',
																		  function() {
																			$(\'#loading_overlay\').show();
																			ajaxapi(\'/features/events/events_ajax.php\',
																					\'templatesearch\',
																					\'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $MYVARS->GET["searchwords"] . '\'),
																					function() {
																						if (xmlHttp.readyState == 4) {
																							simple_display(\'searchcontainer\');
																							$(\'#loading_overlay\').hide();
																						}
																					},
																					true
																			);
																		  });">
									<img src="' . $CFG->wwwroot . '/images/checked.gif" title="Deactivate Template" alt="Deactivate Template" />
								</a>';
		 		} else { // NOT ACTIVE
				$status = '<a href="javascript: void(0)" onclick="ajaxapi(\'/features/events/events_ajax.php\',
																		  \'change_template_status\',
																		  \'&amp;status=0&amp;template_id=' . $template["template_id"] . '\',
																		  function() {
																			$(\'#loading_overlay\').show();
																			ajaxapi(\'/features/events/events_ajax.php\',
																					\'templatesearch\',
																					\'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $MYVARS->GET["searchwords"] . '\'),
																					function() {
																						if (xmlHttp.readyState == 4) {
																							simple_display(\'searchcontainer\');
																							$(\'#loading_overlay\').hide();
																						}
																					},
																					true
																			);
																		  });">
					<img src="' . $CFG->wwwroot . '/images/inactive.gif" title="Activate Template" alt="Activate Template" />
				</a>';
		 		}

			$configure = "";
			$global_settings = fetch_settings("events_template_global", $template["template_id"]);
			if (!empty((array) $global_settings)) {
				$params = [
					"title" => "Edit Template Settings",
					"path" => action_path("events") . "global_template_settings&amp;templateid=" . $template["template_id"],
					"closefirst" => true,
					"width" => "640px",
					"height" => "600px",
					"image" => $CFG->wwwroot . "/images/settings.png",
				];
				$configure .= make_modal_links($params);
			}

			$body .= '<tr style="height:30px;border:3px solid white;font-size:.75em;">
						<td style="width:40%;padding:5px;white-space:nowrap;">
							' . $template["name"] . '
						</td>
						<td style="width:20%;padding:5px;text-align:center;">
							' . $type . '
						</td>
						<td style="text-align:center;padding:5px;">
							' . $configure . '
						</td>
						<td style="text-align:center;padding:5px;">
							' . $status . '
						</td>
					</tr>';
		}
		$body = '<table style="background-color:#F3F6FB;width:100%;border-collapse:collapse;">' . $body . '</table>';
	} else {
		echo '<span class="error_text" class="centered_span">No matches found.</span>';
	}
	echo $header . $body;
}

function change_template_status() {
	$template_id = clean_myvar_req("template_id", "int");
	$status = clean_myvar_opt("status", "string", "");

	$status = $MYVARS->GET["status"];

	if (is_numeric($template_id)) {
		if ($status == "1") {
			execute_db_sql("UPDATE events_templates SET activated='0' WHERE template_id = ||template_id||", ["template_id" => $template_id]);
		} else if ($status == "0") {
			execute_db_sql("UPDATE events_templates SET activated='1' WHERE template_id = ||template_id||", ["template_id" => $template_id]);
		} else {
			// Failed
		}
	}
}

function change_bgcheck_status() {
	$pageid = get_pageid();
	$staffid = clean_myvar_opt("staffid", "int", false);
	$date = clean_myvar_opt("bgcdate", "int", false);

	if ($pageid && $staffid && $date) {
		execute_db_sql("UPDATE events_staff SET bgcheckpassdate = ||bgcheckpassdate||, bgcheckpass = '1' WHERE staffid = ||staffid|| AND pageid = ||pageid||", ["staffid" => $staffid, "pageid" => $pageid, "bgcheckpassdate" => $date]);
		execute_db_sql("UPDATE events_staff_archive SET bgcheckpassdate = ||bgcheckpassdate||, bgcheckpass = '1' WHERE staffid = ||staffid|| AND year='" . date('Y') . "' AND pageid = ||pageid||", ["staffid" => $staffid, "pageid" => $pageid, "bgcheckpassdate" => $date]);
	} elseif ($pageid && $staffid && !$date) {
		execute_db_sql("UPDATE events_staff SET bgcheckpassdate = 0, bgcheckpass = '' WHERE staffid = ||staffid|| AND pageid = ||pageid||", ["staffid" => $staffid, "pageid" => $pageid]);
		execute_db_sql("UPDATE events_staff_archive SET bgcheckpassdate = 0, bgcheckpass = '' WHERE staffid = ||staffid|| AND year = '" . date('Y') . "' AND pageid = ||pageid||", ["staffid" => $staffid, "pageid" => $pageid]);
	} else {
		echo "Failed";
	}
}

function sendstaffemails() {
global $CFG, $USER;
	$stafflist = trim(clean_myvar_opt("stafflist", "string", ""));
	$stafflist = preg_split("/\r\n|\n|\r/", $stafflist);
	$sendemails = clean_myvar_opt("sendemails", "bool", false);

	if (!empty($stafflist)) {
		$subject = "$CFG->sitename Staff Process";
		$protocol = get_protocol();
		$staffcomstatus = [];
		$staffapproved = [];

		$emailnotice = new \stdClass;
		$emailnotice->email = $CFG->siteemail;
		$emailnotice->fname = $CFG->sitename;
		$emailnotice->lname = "";

		$m2 = "<br />I hope this email finds you well.<br />
		<p><strong>If you are receiving this, it is because we have been notified that you have been selected to be on staff this year.</strong>&nbsp; <strong>Please do the following ASAP.&nbsp;&nbsp; You must complete this staff application to be a " . date("Y") . " staff member. </strong></p>
		<ul>
		<li>Go to <a href='" . $protocol.$CFG->wwwroot . "'>$CFG->sitename</a> and sign in or signup for an account and login.&nbsp; It's easy and free.&nbsp;&nbsp;<strong> <br />Do not log in as someone else and fill out the application. </strong></li>
		<li>Once you are logged into the site, you will find a button labeled <strong>Staff Apply</strong>.&nbsp; Fill out the staff application and submit.</li>
		<li>If you have previously applied, the information from your previous application should already be filled in. Please update any information as needed.</li>
		<li>If you are 18 years of age or older, once you complete your staff application you will be given an opportunity to follow a link to complete the Background Authorization Form. This background check will be valid for the next 5 years and will not need to be done every year.</li>
		<li>If you have completed a background check previously you can also send an email to " . $CFG->siteemail . " giving permission to renew your background check.</li>
		</ul><br /><br />";

		foreach ($stafflist as $email) {
			$name = "";
			$email = trim($email);
			if (filter_var($email, FILTER_VALIDATE_EMAIL)) { // It is an email address, so let's get an email ready.
				$user = get_db_row("SELECT * FROM users WHERE LOWER(email) LIKE LOWER(||email||)", ["email" => "%$email%"]);
				$contact = new \stdClass;
				$contact->email = $email;
				$contact->fname = "";
				$contact->lname = "";

				if ($user) {
					$name = $user["fname"] . " " . $user["lname"] . " ";
					$m1 = "Hello $name!,";
					$contact->fname = $user["fname"];
					$contact->lname = $user["lname"];
					$archive = get_db_row("SELECT * FROM events_staff WHERE userid = ||userid|| LIMIT 1", ["userid" => $user["userid"]]);
					$status = staff_status($archive);
				} else {
					$m1 = "Hello future team member!,";
					$status = staff_status(false, false);
				}

				if (!empty($status)) {
					$m3 = "<strong>Current Status:</strong><br />" . print_status($status);

					// Send email to the requester letting them know we received the request.
					if (!empty($sendemails)) {
						send_email($contact, $emailnotice, $subject, $m1.$m2.$m3);
						$staffcomstatus[] = "$name($email) contacted.";
					} else {
						$staffcomstatus[] = "$name($email) <strong> Requires: " . implode(", ", array_column($status, 'tag')) . "</strong>";
					}
				} else {
					$staffapproved[] = "$name($email) is <strong> APPROVED</strong>";
				}
			} else {
				if (strlen($email) > 4) {
					$staffcomstatus[] = "$email is not a valid email address.";
				}
			}
		}
	}

	echo implode('<br />', $staffcomstatus) . "<br /><br />" . implode('<br />', $staffapproved);
}

function appsearch() {
global $CFG, $MYVARS, $USER;
	if (!defined('SEARCH_PERPAGE')) { define('SEARCH_PERPAGE', 8); }
	$userid = $USER->userid; $searchstring = "";
	$searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
	//no search words given
	if ($searchwords == "") {
		$searchwords = '%';
	}
	echo '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';
	//is a site admin
	$admin = is_siteadmin($userid) ? true : false;
	//Create the page limiter
	$pagenum = clean_myvar_opt("pagenum", "int", 0);

	$firstonpage = SEARCH_PERPAGE * $pagenum;
	$limit = " LIMIT $firstonpage," . SEARCH_PERPAGE;
	$words = explode(" ", $searchwords);
	$i = 0;
	while (isset($words[$i])) {
		$searchpart = "(name LIKE '%" . dbescape($words[$i]) . "%')";
		$searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
		$i++;
	}

	$pageid = get_pageid();
	$SQL = "SELECT * FROM events_staff
				WHERE (" . $searchstring . ") AND pageid='$pageid'
				ORDER BY name";

	$total = get_db_count($SQL); //get the total for all pages returned.
	$SQL .= $limit; //Limit to one page of return.
	$count = $total > (($pagenum + 1) * SEARCH_PERPAGE) ? SEARCH_PERPAGE : $total - (($pagenum) * SEARCH_PERPAGE); //get the amount returned...is it a full page of results?
	$results = get_db_result($SQL);
	$amountshown = $firstonpage + SEARCH_PERPAGE < $total ? $firstonpage + SEARCH_PERPAGE : $total;
	$prev = $pagenum > 0 ? '<a href="javascript: void(0);" onclick="$(\'#loading_overlay\').show();
																	ajaxapi(\'/features/events/events_ajax.php\',
																			\'appsearch\',
																			\'&amp;pagenum=' . ($pagenum - 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
																			function() {
																				if (xmlHttp.readyState == 4) {
																					simple_display(\'searchcontainer\');
																					$(\'#loading_overlay\').hide();
																				}
																			},
																			true
																	);"
							onmouseup="this.blur()">
								<img src="' . $CFG->wwwroot . '/images/prev.gif" title="Previous Page" alt="Previous Page">
							</a>' : "";
	$info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
	$next = $firstonpage + SEARCH_PERPAGE < $total ? '<a onmouseup="this.blur()" href="javascript: void(0);" onclick="$(\'#loading_overlay\').show();
																																ajaxapi(\'/features/events/events_ajax.php\',
																																		\'appsearch\',
																																		\'&amp;pagenum=' . ($pagenum + 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
																																		function() {
																																			if (xmlHttp.readyState == 4) {
																																				simple_display(\'searchcontainer\');
																																				$(\'#loading_overlay\').hide();
																																			}
																																		},
																																		true
																																);">
																	<img src="' . $CFG->wwwroot . '/images/next.gif" title="Next Page" alt="Next Page">
																</a>' : "";
	$header = $body = "";
	if ($count > 0) {
		$body .= '<tr style="height:30px;border:3px solid white;font-size:.9em;">
						<td style="width:40%;padding:5px;font-size:.85em;white-space:nowrap;">
							Name
						</td>
						<td style="padding:5px;font-size:.85em;">
							Status
						</td>
						<td style="width:125px;text-align:right;padding:5px;font-size:.85em;">
							Date / Edit
						</td>
					</tr>';
		while ($staff = fetch_row($results)) {
	  		$export = "";
			$header = $header == "" ? '<table style="width:100%;"><tr><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><p>' : $header;
			$button = '<a href="javascript: void(0)" onclick="if ($(\'#bgcheckdate_' . $staff["staffid"] . '\').prop(\'disabled\')) { $(\'#bgcheckdate_' . $staff["staffid"] . '\').prop(\'disabled\', false); } else { ajaxapi(\'/features/events/events_ajax.php\',
																	  \'change_bgcheck_status\',
																	  \'&amp;bgcdate=\'+$(\'#bgcheckdate_' . $staff["staffid"] . '\').val()+\'&amp;staffid=' . $staff["staffid"] . '\',
																	  function() {
																		$(\'#loading_overlay\').show();
																		ajaxapi(\'/features/events/events_ajax.php\',
																				\'appsearch\',
																				\'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $MYVARS->GET["searchwords"] . '\'),
																				function() {
																					if (xmlHttp.readyState == 4) {
																						simple_display(\'searchcontainer\');
																						$(\'#loading_overlay\').hide();
																					}
																				},
																				true
																		);
																	  }); }">
				<img style="vertical-align: middle;" src="' . $CFG->wwwroot . '/images/manage.png" title="Edit Background Check Date" alt="Edit Background Check Date" />
			</a>';

			$applookup = '$(\'#loading_overlay\').show();
								ajaxapi(\'/features/events/events_ajax.php\',
										\'show_staff_app\',
										\'&amp;staffid=' . $staff["staffid"] . '&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $MYVARS->GET["searchwords"] . '\'),
										function() {
											if (xmlHttp.readyState == 4) {
												simple_display(\'searchcontainer\');
												$(\'#loading_overlay\').hide();
											}
										},
										true
								);';
			$bgcheckdate = empty($staff["bgcheckpassdate"]) ? '' : date('m/d/Y', $staff["bgcheckpassdate"]);
			$status = staff_status($staff);
			$body .= '<tr style="height:30px;border:3px solid white;font-size:.9em;">
						<td style="padding:5px;font-size:.85em;white-space:nowrap;">
							<a href="javascript: void(0);" onclick="' . $applookup . '">' . $staff["name"] . '</a><br />
							<span style="font-size:.9em">' . get_db_field("email", "users",'userid="' . $staff["userid"] . '"') . '</span>
						</td>
						<td style="padding:5px;font-size:.75em;">
							' . print_status($status) . '
						</td>
						<td style="text-align:right;padding:5px;">
							<input style="width: 100px;margin: 0;" type="text" disabled="disabled" id="bgcheckdate_' . $staff["staffid"] . '" name="bgcheckdate_' . $staff["staffid"] . '" value="' . $bgcheckdate . '" />' . $button . '
						</td>
					</tr>';
		}
		$body = '<table style="background-color:#F3F6FB;width:100%;border-collapse:collapse;">' . $body . '</table>';
	} else {
		echo '<span class="error_text" class="centered_span">No matches found.</span>';
	}
	echo $header . $body;
}

function show_staff_app() {
global $CFG, $MYVARS, $USER;
	$staffid = clean_myvar_opt("staffid", "int", false);
	$searchwords = trim($MYVARS->GET["searchwords"]);
	$year = isset($MYVARS->GET["year"]) ? trim($MYVARS->GET["year"]) : date("Y");
	$pagenum = clean_myvar_opt("pagenum", "int", 0);
	echo '<link rel="stylesheet" type="text/css" href="' . $CFG->wwwroot . '/styles/print.css">';
	echo '<a class="dontprint" title="Return to Staff Applications" href="javascript: void(0)"
				onclick="$(\'#loading_overlay\').show();
						 ajaxapi(\'/features/events/events_ajax.php\',
								 \'appsearch\',
								 \'&amp;pagenum=' . $pagenum . '&amp;searchwords=' . $searchwords . '\',
								 function() {
									if (xmlHttp.readyState == 4) {
										simple_display(\'searchcontainer\');
										$(\'#loading_overlay\').hide();
										init_event_menu();
									}
								 },
								 true
						 );
						 return false;
				">Return to Staff Applications
			 </a>';
	$applookup = 'if ($(this).val() > 0) { $(\'#loading_overlay\').show();
									ajaxapi(\'/features/events/events_ajax.php\',
											\'show_staff_app\',
											\'&amp;staffid=' . $staffid . '&amp;year=\'+$(this).val()+\'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
											function() {
												if (xmlHttp.readyState == 4) {
													simple_display(\'searchcontainer\');
													$(\'#loading_overlay\').hide();
												}
											},
											true
									); }';
	if ($archive = get_db_result("SELECT * FROM events_staff_archive WHERE staffid='$staffid' ORDER BY year")) {
		$i = 0;
		$values = new \stdClass;
		while ($vals = fetch_row($archive)) {
			$values->$i = new \stdClass;
			$values->$i->year = $vals["year"];
			$i++;
		}
		$params = [
			"properties" => [
				"name" => "year",
				"id" => "year",
				"onchange" => $applookup,
			],
			"values" => $values,
			"valuename" => "year",
			"selected" => $year,
			"firstoption" => "",
		];
		echo "<br />" . make_select($params) . "<br />";
	}

	if ($row = get_db_row("SELECT * FROM events_staff_archive WHERE staffid='$staffid' AND year='$year'")) {
		 echo '   <input style="float:right;" class="dontprint" type="button" value="Print" onclick="window.print();return false;" />
				<p style="font-size:.95em;" class="print">
					' . staff_application_form($row, true) . '
				</p>
		  ';
	} else {
		echo "<h3>No Application on Record</h3>";
	}
}

function event_save_staffapp() {
global $CFG, $MYVARS, $USER;
	$userid = dbescape($USER->userid);
	$staffid = clean_myvar_opt("staffid", "int", false);

	// Get pageid.
	$pageid = get_pageid();

	if (!empty($pageid)) {
		$name = dbescape(nameize(clean_myvar_opt("name", "string", "")));
		$phone = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", clean_myvar_opt("phone", "string", ""))), 2);
		$phone = dbescape($phone);
		$dateofbirth = dbescape(strtotime(clean_myvar_opt("dateofbirth", "string", "")));
		$address = dbescape($MYVARS->GET["address"]);
		$agerange = dbescape($MYVARS->GET["agerange"]);
		$cocmember = dbescape($MYVARS->GET["cocmember"]);
		$congregation = dbescape($MYVARS->GET["congregation"]);
		$priorwork = dbescape($MYVARS->GET["priorwork"]);
		$q1_1 = dbescape($MYVARS->GET["q1_1"]);
		$q1_2 = dbescape($MYVARS->GET["q1_2"]);
		$q1_3 = dbescape($MYVARS->GET["q1_3"]);
		$q2_1 = dbescape($MYVARS->GET["q2_1"]);
		$q2_2 = dbescape($MYVARS->GET["q2_2"]);
		$q2_3 = dbescape($MYVARS->GET["q2_3"]);
		$parentalconsent = dbescape(nameize($MYVARS->GET["parentalconsent"]));
		$parentalconsentsig = dbescape($MYVARS->GET["parentalconsentsig"]);
		$workerconsent = dbescape(nameize($MYVARS->GET["workerconsent"]));
		$workerconsentsig = dbescape($MYVARS->GET["workerconsentsig"]);
		$workerconsentdate = dbescape(strtotime($MYVARS->GET["workerconsentdate"]));

		$ref1name = dbescape(nameize($MYVARS->GET["ref1name"]));
		$ref1relationship = dbescape($MYVARS->GET["ref1relationship"]);
		$ref1phone = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["ref1phone"])), 2);
		$ref1phone = dbescape($ref1phone);

		$ref2name = dbescape(nameize($MYVARS->GET["ref2name"]));
		$ref2relationship = dbescape($MYVARS->GET["ref2relationship"]);
		$ref2phone = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["ref2phone"])), 2);
		$ref2phone = dbescape($ref2phone);

		$ref3name = dbescape(nameize($MYVARS->GET["ref3name"]));
		$ref3relationship = dbescape($MYVARS->GET["ref3relationship"]);
		$ref3phone = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["ref3phone"])), 2);
		$ref3phone = dbescape($ref3phone);

		if (!empty($staffid)) {
			$SQL = "UPDATE events_staff SET userid='$userid',pageid='$pageid',name='$name',phone='$phone',dateofbirth='$dateofbirth',address='$address',
						agerange='$agerange',cocmember='$cocmember',congregation='$congregation',priorwork='$priorwork',
						q1_1='$q1_1',q1_2='$q1_2',q1_3='$q1_3',q2_1='$q2_1',q2_2='$q2_2',q2_3='$q2_3',
						parentalconsent='$parentalconsent',parentalconsentsig='$parentalconsentsig',
						workerconsent='$workerconsent',workerconsentsig='$workerconsentsig',workerconsentdate='$workerconsentdate',
						ref1name='$ref1name',ref1relationship='$ref1relationship',ref1phone='$ref1phone',
						ref2name='$ref2name',ref2relationship='$ref2relationship',ref2phone='$ref2phone',
						ref3name='$ref3name',ref3relationship='$ref3relationship',ref3phone='$ref3phone'
						WHERE staffid='$staffid'";
			execute_db_sql($SQL);
			$subject = "Application Updated";
		} else {
			$SQL = "INSERT INTO events_staff
						(userid,pageid,name,phone,dateofbirth,address,agerange,cocmember,congregation,priorwork,q1_1,q1_2,q1_3,q2_1,q2_2,q2_3,parentalconsent,parentalconsentsig,workerconsent,workerconsentsig,workerconsentdate,ref1name,ref1relationship,ref1phone,ref2name,ref2relationship,ref2phone,ref3name,ref3relationship,ref3phone,bgcheckpass,bgcheckpassdate)
						VALUES('$userid','$pageid','$name','$phone','$dateofbirth','$address','$agerange','$cocmember','$congregation','$priorwork','$q1_1','$q1_2','$q1_3','$q2_1','$q2_2','$q2_3','$parentalconsent','$parentalconsentsig','$workerconsent','$workerconsentsig','$workerconsentdate','$ref1name','$ref1relationship','$ref1phone','$ref2name','$ref2relationship','$ref2phone','$ref3name','$ref3relationship','$ref3phone', '',0)";
			$success = execute_db_sql($SQL);
			$subject = "Application Complete";
		}

		// Save the request
		if ($success) {
			$staffid = !empty($staffid) ? $staffid : $success;
			$staff = get_db_row("SELECT * FROM events_staff WHERE staffid='$staffid'");

			if (get_db_row("SELECT * FROM events_staff_archive WHERE staffid='$staffid' AND pageid='$pageid' AND year='" . date("Y") . "'")) {
				$SQL = "UPDATE events_staff_archive SET name='$name',phone='$phone',dateofbirth='$dateofbirth',address='$address',
					agerange='$agerange',cocmember='$cocmember',congregation='$congregation',priorwork='$priorwork',
					q1_1='$q1_1',q1_2='$q1_2',q1_3='$q1_3',q2_1='$q2_1',q2_2='$q2_2',q2_3='$q2_3',
					parentalconsent='$parentalconsent',parentalconsentsig='$parentalconsentsig',
					workerconsent='$workerconsent',workerconsentsig='$workerconsentsig',workerconsentdate='$workerconsentdate',
					ref1name='$ref1name',ref1relationship='$ref1relationship',ref1phone='$ref1phone',
					ref2name='$ref2name',ref2relationship='$ref2relationship',ref2phone='$ref2phone',
					ref3name='$ref3name',ref3relationship='$ref3relationship',ref3phone='$ref3phone',
					bgcheckpass='" . $staff["bgcheckpass"] . "',bgcheckpassdate='" . $staff["bgcheckpassdate"] . "'
					WHERE staffid='$staffid' AND year='" . date("Y") . "' AND pageid='$pageid'";
				execute_db_sql($SQL);
			} else {
				$SQL = "INSERT INTO events_staff_archive
							(staffid,userid,pageid,year,name,phone,dateofbirth,address,agerange,cocmember,congregation,priorwork,q1_1,q1_2,q1_3,q2_1,q2_2,q2_3,parentalconsent,parentalconsentsig,workerconsent,workerconsentsig,workerconsentdate,ref1name,ref1relationship,ref1phone,ref2name,ref2relationship,ref2phone,ref3name,ref3relationship,ref3phone,bgcheckpass,bgcheckpassdate)
							VALUES('$staffid','$userid','$pageid','" . date("Y") . "','$name','$phone','$dateofbirth','$address','$agerange','$cocmember','$congregation','$priorwork','$q1_1','$q1_2','$q1_3','$q2_1','$q2_2','$q2_3','$parentalconsent','$parentalconsentsig','$workerconsent','$workerconsentsig','$workerconsentdate','$ref1name','$ref1relationship','$ref1phone','$ref2name','$ref2relationship','$ref2phone','$ref3name','$ref3relationship','$ref3phone','" . $staff["bgcheckpass"] . "'," . $staff["bgcheckpassdate"] . ")";
				execute_db_sql($SQL);
			}

		 		// Log
				log_entry("event", $pageid, $subject);

			$emailnotice = new \stdClass;
			$emailnotice->email = $CFG->siteemail;
			$emailnotice->fname = $CFG->sitename;
			$emailnotice->lname = "";

			//Requesting email setup
			$name = stripslashes($name);
			$message = "<strong>$name has applied to work</strong>";

			//Send email to the requester letting them know we received the request
			send_email($emailnotice, $emailnotice, $subject, $message);

			$backgroundchecklink = '';
			$featureid = "*";
			if (!$settings = fetch_settings("events", $featureid, $pageid)) {
	  				  save_batch_settings(default_settings("events", $pageid, $featureid));
	  				  $settings = fetch_settings("events", $featureid, $pageid);
	  			}

			$linkurl = $settings->events->$featureid->bgcheck_url->setting;

			$status = empty($staff["bgcheckpass"]) ? false : (time()-$staff["bgcheckpassdate"] > ($settings->events->$featureid->bgcheck_years->setting * 365 * 24 * 60 * 60) ? false : true);

			$eighteen = 18 * 365 * 24 * 60 * 60; // 18 years in seconds
			$backgroundchecklink = ((time() - $dateofbirth) < $eighteen) || ($status || empty($linkurl)) ? '' : '
			   <br /><br />
			   If you have not already done so, please complete a background check.<br />
			   <h2><a href="' . $linkurl . '">Submit a background check</a></h2>';

			echo "<div style='text-align:center;'><h1>$subject</h1>$backgroundchecklink</div>";
		} else {
			echo "<div style='text-align:center;'><h1>Failed to save application.</h1></div>";
		}
	} else {
		echo "<div style='text-align:center;'><h1>The page has timed out and the form could not be save for security reasons.  Please try again.</h1></div>";
	}

}

function export_staffapp() {
global $MYVARS, $CFG, $USER;
	$year = dbescape($MYVARS->GET["year"]);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
		if (!defined('FILELIB')) { include_once ($CFG->dirroot . '/lib/filelib.php'); }
	$fields = [
		"STATUS",
		"Name",
		"Email",
		"Date of Birth",
		"Phone",
		"Address",
		"Age Range",
		"Church of Christ Member",
		"Congregation",
		"Has Worked at Camp",
		"Been arrested for any reason?",
		"Been convicted of, or pleaded guilty or no contest to, any crime?",
		"Engaged in, or been accused of, any child molestation, exploitation, or abuse?",
		"Having any traits or tendencies that could pose any threat to children, youth, or others?",
		"Any reason why you should not work with children, youth, or others?",
		"Explain",
		"Parental Consent Name",
		"Parental Consent Signed",
		"Worker Consent Name",
		"Worker Consent Signed",
		"Worker Consent Date",
		"Ref1 Name",
		"Ref1 Phone",
		"Ref1 Relationship",
		"Ref2 Name",
		"Ref2 Phone",
		"Ref2 Relationship",
		"Ref3 Name",
		"Ref3 Phone",
		"Ref3 Relationship",
		"Background Check",
		"Background Check Date",
	];
	$CSV = '"' . implode('","', $fields) . "\"\n";

	$SQL = "SELECT name,userid,phone,dateofbirth,address,agerange,cocmember,congregation,
				   priorwork,q1_1,q1_2,q1_3,q2_1,q2_2,q2_3,parentalconsent,
				   parentalconsentsig,workerconsent,workerconsentsig,workerconsentdate,
				   ref1name,ref1relationship,ref1phone,ref2name,ref2relationship,ref2phone,
				   ref3name,ref3relationship,ref3phone,bgcheckpass,bgcheckpassdate
				   FROM events_staff_archive WHERE pageid='$pageid' AND year='$year' ORDER BY name";
	if ($applications = get_db_result($SQL)) {
			  while ($app = fetch_row($applications)) {
			$status = staff_status($app);
			$status = empty($status) ? ["APPROVED"] : $status;
			$email = get_db_field("email", "users", "userid='" . $app["userid"] . "'");
			$app["agerange"] = $app["agerange"] == 0 ? "under 18" : ($app["agerange"] == 1 ? "18 - 25" : "26 or older");
			$app["cocmember"] = $app["cocmember"] == 0 ? "No" : "Yes";
			$app["priorwork"] = $app["priorwork"] == 0 ? "No" : "Yes";
			$app["q1_1"] = $app["q1_1"] == 0 ? "No" : "Yes";
			$app["q1_2"] = $app["q1_2"] == 0 ? "No" : "Yes";
			$app["q1_3"] = $app["q1_3"] == 0 ? "No" : "Yes";
			$app["q2_1"] = $app["q2_1"] == 0 ? "No" : "Yes";
			$app["q2_2"] = $app["q2_2"] == 0 ? "No" : "Yes";
			$app["parentalconsentsig"] = $app["parentalconsentsig"] == "on" ? "Signed" : "";
			$app["workerconsentsig"] = $app["workerconsentsig"] == "on" ? "Signed" : "";
			$app["bgcheckpass"] = $app["bgcheckpass"] == 0 ? "No" : "Yes";
			$CSV .= '"' . implode(" | " , array_column($status, 'full')).
					'","' . $app["name"].
					'","' . $email.
					'","' . date('m/d/Y', $app["dateofbirth"]).
					'","' . $app["phone"].
					'","' . $app["address"].
					'","' . $app["agerange"].
					'","' . $app["cocmember"].
					'","' . $app["congregation"].
					'","' . $app["priorwork"].
					'","' . $app["q1_1"].
					'","' . $app["q1_2"].
					'","' . $app["q1_3"].
					'","' . $app["q2_1"].
					'","' . $app["q2_2"].
					'","' . $app["q2_3"].
					'","' . $app["parentalconsent"].
					'","' . $app["parentalconsentsig"].
					'","' . $app["workerconsent"].
					'","' . $app["workerconsentsig"].
					'","' . date('m/d/Y', $app["workerconsentdate"]).
					'","' . $app["ref1name"].
					'","' . $app["ref1phone"].
					'","' . $app["ref1relationship"].
					'","' . $app["ref2name"].
					'","' . $app["ref2phone"].
					'","' . $app["ref2relationship"].
					'","' . $app["ref3name"].
					'","' . $app["ref1phone"].
					'","' . $app["ref3relationship"].
					'","' . $app["bgcheckpass"].
					'","' . (!empty($app["bgcheckpassdate"]) ? date('m/d/Y', $app["bgcheckpassdate"]) : '').
					'"' . "\n";
		  }
	}
	echo get_download_link("staffapps($year).csv", $CSV);
}
?>
