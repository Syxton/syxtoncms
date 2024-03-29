<?php
/***************************************************************************
* events_ajax.php - Events backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/12/2021
* Revision: 2.2.2
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); }
if (!isset($EVENTSLIB)) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

update_user_cookie();

callfunction();

//See if the date given is open for a requested event
function request_date_open() {
global $CFG,$MYVARS,$USER;
    $featureid = $MYVARS->GET["featureid"];
    $startdate = $MYVARS->GET["startdate"];
    if (isset($featureid)) {
        $pageid = get_db_field("pageid","pages_features","feature='events' AND featureid=$featureid");

        //Only site events need to be set to confirm=1
        $confirm = $pageid == $CFG->SITEID ? "confirmed=1 AND" : "pageid=$pageid AND";

        if (!$settings = fetch_settings("events",$featureid,$pageid)) {
    		make_or_update_settings_array(default_settings("events",$pageid,$featureid));
    		$settings = fetch_settings("events",$featureid,$pageid);
    	}

        $locationid = $settings->events->$featureid->allowrequests->setting;

        if (isset($MYVARS->GET["enddate"])) { //check all dates between startdate and enddate
            $startdate = strtotime($startdate) + get_offset(); $enddate = strtotime($MYVARS->GET["enddate"]) + get_offset();
            //get all events at location between dates
            $SQL = "SELECT * FROM events
                        WHERE $confirm location='$locationid'
                            AND (
                                    ($startdate >= event_begin_date AND $startdate <= event_end_date)
                                    OR
                                    ($enddate >= event_begin_date AND $enddate <= event_end_date)
                                    OR ($startdate <= event_begin_date AND $enddate >= event_end_date)
                            )";
            //echo $SQL;
            if ($getdates = get_db_count($SQL)) {
                echo "false";
            } else { echo "true";}
        } else { //only check a single day for an opening
            $startdate = strtotime($startdate) + get_offset();
            //get all events at location on date
            $SQL = "SELECT * FROM events
                        WHERE $confirm location='$locationid'
                            AND ($startdate >= event_begin_date AND $startdate <= event_end_date)";
            //echo $SQL;
            if ($getdates = get_db_count($SQL)) {
                echo "false";
            } else { echo "true"; }
        }
    }
}

function event_request() {
global $CFG,$MYVARS,$USER;
    $featureid = dbescape($MYVARS->GET["featureid"]);
    $contact_name = dbescape($MYVARS->GET["name"]);
    $contact_email = dbescape($MYVARS->GET["email"]);
    $contact_phone = dbescape($MYVARS->GET["phone"]);
    $event_name = dbescape($MYVARS->GET["event_name"]);
    $startdate = strtotime(dbescape($MYVARS->GET["startdate"]));
    $enddate = $MYVARS->GET["enddate"] == "" ? $startdate : strtotime(dbescape($MYVARS->GET["enddate"]));
    $participants = dbescape($MYVARS->GET["participants"]);
    $description = dbescape($MYVARS->GET["description"]);

    $INSERTSQL = "INSERT INTO events_requests
                    (featureid,contact_name,contact_email,contact_phone,event_name,startdate,enddate,participants,description,votes_for,votes_against)
                    VALUES('$featureid','$contact_name','$contact_email','$contact_phone','$event_name','$startdate','$enddate','$participants','$description','0','0')";
    //Save the request
    if ($reqid = execute_db_sql($INSERTSQL)) {
        $from->email = $CFG->siteemail;
        $from->fname = $CFG->sitename;
        $from->lname = "";

        //Requesting email setup
        $contact->email = $contact_email;
        if (strstr($contact_name, " ")) {
            $name = explode(" ",$contact_name);
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
        send_email($contact, $from, false, $subject, $message);

        if (isset($featureid)) {
            //Get feature request settings
            $pageid = get_db_field("pageid","pages_features","feature='events' AND featureid=$featureid");
            if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        		    make_or_update_settings_array(default_settings("events", $pageid, $featureid));
        		    $settings = fetch_settings("events", $featureid, $pageid);
            }

            $subject = $CFG->sitename . " Event Request";

            //Get and send to email list
            $emaillist = $settings->events->$featureid->emaillistconfirm->setting;
            $emaillist = str_replace(array(","," ","\t","\r"), "\n", $emaillist);
            $emaillist = str_replace("\n\n", "\n", $emaillist);
            $emaillist = explode("\n", $emaillist);
            foreach ($emaillist as $emailuser) {
                //Each message must has an md5'd email address so I know if a person has voted or not
                $voteid = md5($emailuser);
                $message = '<p>A new event request has been submitted. Your input is required to approve the event. This email contains specific links designed for you to be able to submit and view questions as well as vote for this event\'s approval.</p>
                            <p>Do <strong>not </strong>delete this email.<strong>&nbsp; Please review the following event request.</strong></p>
                            <br />
                            <a href="'.$CFG->wwwroot.'/features/events/events_ajax.php?action=request_vote&amp;reqid='.$reqid.'&amp;approve=1&amp;voteid='.$voteid.'">Approve</a>
                            &nbsp;
                            <a href="'.$CFG->wwwroot.'/features/events/events_ajax.php?action=request_vote&amp;reqid='.$reqid.'&amp;approve=0&amp;voteid='.$voteid.'">Deny</a>
                            &nbsp;
                            <a href="'.$CFG->wwwroot.'/features/events/events_ajax.php?action=request_question&amp;reqid='.$reqid.'&amp;voteid='.$voteid.'">View/Ask Questions</a><br />'.
                            $request_info;

                $thisuser->email = $emailuser;
                $thisuser->fname = "";
                $thisuser->lname = "";
                send_email($thisuser, $from, false, $subject, $message);
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

function valid_voter($pageid, $featureid, $voteid) {
    $validvote = false;

    if (!$settings = fetch_settings("events",$featureid,$pageid)) {
    	make_or_update_settings_array(default_settings("events", $pageid, $featureid));
    	$settings = fetch_settings("events", $featureid, $pageid);
    }
    $locationid = $settings->events->$featureid->allowrequests->setting;

    //Get email list to check and see if the voteid matches one
    $emaillist = $settings->events->$featureid->emaillistconfirm->setting;
    $emaillist = str_replace(array(","," ","\t","\r"), "\n", $emaillist);
    $emaillist = str_replace("\n\n", "\n", $emaillist);
    $emaillist = explode("\n", $emaillist);

    foreach ($emaillist as $emailuser) {
        if (md5($emailuser) == $voteid) { $validvote = true;}
    }
    return $validvote;
}

//Save question
function request_question_send() {
global $CFG, $MYVARS;
    $reqid = $MYVARS->GET["reqid"];
    $voteid = $MYVARS->GET["voteid"];
    $question = dbescape(strip_tags(trim($MYVARS->GET["question"]," \n\r\t"),'<a><em><u><img><br>'));
    //Make sure request exists and get the featureid
    if ($featureid = get_db_field("featureid","events_requests","reqid=$reqid")) {
        //Get feature request settings
        $pageid = get_db_field("pageid","pages_features","feature='events' AND featureid=$featureid");
        if (!$settings = fetch_settings("events",$featureid,$pageid)) {
        	make_or_update_settings_array(default_settings("events", $pageid, $featureid));
        	$settings = fetch_settings("events", $featureid, $pageid);
        }
        $locationid = $settings->events->$featureid->allowrequests->setting;
        $request = get_db_row("SELECT * FROM events_requests WHERE reqid='$reqid'");
        //Allowed to ask questions
        if (valid_voter($pageid, $featureid, $voteid)) {
            $qtime = get_timestamp();
            $SQL = "INSERT INTO events_requests_questions
                        (reqid,question,answer,question_time,answer_time)
                        VALUES('$reqid','$question','','$qtime','0')";
            if ($qid = execute_db_sql($SQL)) { //Question is saved.  Now send it to everyone.
                $subject = $CFG->sitename . " Event Request Question";
                $message = '<strong>A question has been asked about the event ('.stripslashes($request["event_name"]).').</strong><br />
                <br />
                '.trim($question,"\n\r\t") . '<br />
                <br />
                <a href="'.$CFG->wwwroot.'/features/events/events_ajax.php?action=request_question&amp;reqid='.$reqid.'&amp;voteid='.$voteid.'">
                    View/Ask Questions
                </a>';

                $from->email = $CFG->siteemail;
                $from->fname = $CFG->sitename;
                $from->lname = "";

                $emaillist = $settings->events->$featureid->emaillistconfirm->setting;
                $emaillist = str_replace(array(","," ","\t","\r"),"\n",$emaillist);
                $emaillist = str_replace("\n\n","\n",$emaillist);
                $emaillist = explode("\n",$emaillist);

                foreach ($emaillist as $emailuser) {
                    //Let everyone know the event has been questioned
                    $thisuser->email = $emailuser;
                    $thisuser->fname = "";
                    $thisuser->lname = "";
                    send_email($thisuser,$from,false,$subject, $message);
                }

                $message .= '<br /><br />
                            <a href="'.$CFG->wwwroot.'/features/events/events_ajax.php?action=request_answer&amp;qid='.$qid.'&amp;reqid='.$reqid.'">
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
                send_email($contact,$from,false,$subject, $message);

                echo request_question(true);
            } else {
              get_error_message("generic_db_error");
            }
        } else {
          echo get_error_message("generic_permissions");
        }
    } else {
      echo get_error_message("invalid_old_request:events");
    }
}

//Save question
function request_answer_send() {
global $CFG, $MYVARS;
    $reqid = $MYVARS->GET["reqid"];
    $qid = $MYVARS->GET["qid"];
    $answer = dbescape(strip_tags(trim($MYVARS->GET["answer"]," \n\r\t"),'<a><em><u><img><br>'));
    //Make sure request exists and get the featureid
    if ($featureid = get_db_field("featureid", "events_requests", "reqid=$reqid")) {
        //Get feature request settings
        $pageid = get_db_field("pageid", "pages_features", "feature='events' AND featureid=$featureid");
        if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        	make_or_update_settings_array(default_settings("events", $pageid, $featureid));
        	$settings = fetch_settings("events", $featureid, $pageid);
        }
        $request = get_db_row("SELECT * FROM events_requests WHERE reqid='$reqid'");

        $atime = get_timestamp();
        $SQL = "UPDATE events_requests_questions set answer='$answer',answer_time='$atime' WHERE id=$qid";
        if (execute_db_sql($SQL)) { //Question is saved.  Now send it to everyone.
            $subject = $CFG->sitename . " Event Request Answer";
            $message = '<strong>An answer to a question has been recieved about the event ('.stripslashes($request["event_name"]).').</strong><br />
            <br />
            <strong>Question:</strong> '.stripslashes(get_db_field("question","events_requests_questions","id=$qid")).'<br />
            <br />
            <strong>Answer:</strong>'.stripslashes(get_db_field("answer","events_requests_questions","id=$qid"));

            $from->email = $CFG->siteemail;
            $from->fname = $CFG->sitename;
            $from->lname = "";

            $emaillist = $settings->events->$featureid->emaillistconfirm->setting;
            $emaillist = str_replace(array(","," ","\t","\r"),"\n",$emaillist);
            $emaillist = str_replace("\n\n","\n",$emaillist);
            $emaillist = explode("\n",$emaillist);

            foreach ($emaillist as $emailuser) {
                //Let everyone know the event has been questioned
                $thisuser->email = $emailuser;
                $thisuser->fname = "";
                $thisuser->lname = "";
                send_email($thisuser, $from, false, $subject, $message);
            }
            echo request_answer(true);
        } else {
          get_error_message("generic_db_error");
        }
    } else {
      echo get_error_message("invalid_old_request:events");
    }
}

function confirm_events_relay() {
global $CFG, $MYVARS;
    confirm_event();
}

function delete_events_relay() {
global $CFG, $MYVARS;
    delete_event();
}

//Request question form
function request_question($refresh=false) {
global $CFG, $MYVARS;
    $reqid = $MYVARS->GET["reqid"];
    $voteid = $MYVARS->GET["voteid"];

    //Make sure request exists and get the featureid
    if ($featureid = get_db_field("featureid", "events_requests", "reqid=$reqid")) {
        //Get feature request settings
        $pageid = get_db_field("pageid", "pages_features", "feature='events' AND featureid=$featureid");
        if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        	make_or_update_settings_array(default_settings("events", $pageid, $featureid));
        	$settings = fetch_settings("events", $featureid, $pageid);
        }
        $locationid = $settings->events->$featureid->allowrequests->setting;

        //Allowed to ask questions
        if (valid_voter($pageid, $featureid, $voteid)) {
            //Print out question form
            if (!$refresh) {
               echo '<html><head><title>Event Request Question Page</title>';
               echo js_code_wrap('var dirfromroot = "' . $CFG->directory . '";');
               echo get_js_tags(array("siteajax"));
               echo get_editor_javascript();
               echo get_css_tags(array("main"));
               echo '</head><body><div id="question_form">';
            }
            echo '<h2>Questions Regarding Event Request</h2>'.get_request_info($reqid);
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
                                                               \'&amp;voteid='.$voteid.'&amp;reqid='.$reqid.'&amp;question=\'+ escape('.get_editor_value_javascript().'),
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
                                           WHERE reqid=$reqid
                                        ORDER BY question_time")) {
                while ($row = fetch_row($results)) {
                    echo '<div style="background-color:Aquamarine;padding:4px;"><strong>'.$row['question'].'</strong></div>';
                    if ($row["answer"] == "") { //Not answered
                        echo '<div style="background-color:PaleTurquoise;padding:4px;">Question has not been responed to at this time.</div><br /><br />';
                    } else { //Print answer
                        echo '<div style="background-color:Gold;padding:4px;">'.$row['answer'].'</div><br /><br />';
                    }
                }
            } else {
              echo "No questions have been asked yet.";
            }
            if (!$refresh) { echo '</div></body></html>'; }
        } else {
          echo get_error_message("generic_permissions");
        }
    } else {
      echo get_error_message("invalid_old_request:events");
    }
}

//Request answer form
function request_answer($refresh=false) {
global $CFG, $MYVARS;
    $reqid = $MYVARS->GET["reqid"];
    $qid = $MYVARS->GET["qid"];

    //Make sure request exists and get the featureid
    if ($featureid = get_db_field("featureid", "events_requests", "reqid=$reqid")) {
        //Get feature request settings
        $pageid = get_db_field("pageid", "pages_features", "feature='events' AND featureid=$featureid");
        if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        	make_or_update_settings_array(default_settings("events", $pageid, $featureid));
        	$settings = fetch_settings("events", $featureid, $pageid);
        }
        $locationid = $settings->events->$featureid->allowrequests->setting;

        //Allowed to ask questions
        //Print out question form
        if (!$refresh) {
            echo '<html><head><title>Event Request Question Page</title>';
            echo js_code_wrap('var dirfromroot = "' . $CFG->directory . '";');
            echo get_js_tags(array("siteajax"));
            echo get_editor_javascript();
            echo get_css_tags(array("main"));
            echo '</head><body><div id="answer_form">';
         }

        echo '<h2>Questions Regarding Event Request</h2>'.get_request_info($reqid);

        $answer = get_db_field("answer","events_requests_questions","id=$qid");
        if (!$refresh) {
            echo'
          			<table style="width:100%">
          				<tr>
          					<td><br />
                              <div style="background-color:Aquamarine;padding:4px;">
                                  <strong>Question: '.get_db_field("question","events_requests_questions","id=$qid").'</strong>
                              </div>
                              <br />';
                              echo get_editor_box($answer);
				               echo ' <div style="width:100%;text-align:center">
                                <input type="button" value="Send Answer"
                                    onclick="ajaxapi(\'/features/events/events_ajax.php\',
                                                     \'request_answer_send\',
                                                     \'&amp;qid='.$qid.'&amp;reqid='.$reqid.'&amp;answer=\'+ escape('.get_editor_value_javascript().'),
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
        $mod = !$refresh ? "AND id!=$qid" : "";
        if ($results = get_db_result("SELECT * FROM events_requests_questions
                                        WHERE reqid=$reqid $mod
                                        ORDER BY question_time")) {
            while ($row = fetch_row($results)) {
                echo    '<div style="background-color:Aquamarine;padding:4px;">
                            <strong>'.$row['question'].'</strong>
                        </div>';
                if ($row["answer"] == "") { //Not answered
                    echo    '<div style="background-color:PaleTurquoise;padding:4px;overflow:hidden;">
                                <strong>
                                    <a href="'.$CFG->wwwroot.'/features/events/events_ajax.php?action=request_answer&amp;qid='.$row['id'].'&amp;reqid='.$reqid.'">
                                        Answer Question
                                    </a>
                                </strong>
                            </div><br /><br />';
                } else { //Print answer
                    echo    '<div style="background-color:Gold;padding:4px;overflow:hidden;">
                                '.$row['answer'].'<br />
                                <strong>
                                    <a style="float:right" href="'.$CFG->wwwroot.'/features/events/events_ajax.php?action=request_answer&amp;qid='.$row['id'].'&amp;reqid='.$reqid.'">
                                        Update Answer
                                    </a>
                                </strong>
                            </div><br /><br />';
                }
            }
        } else { echo "No other questions have been asked yet."; }
        if (!$refresh) { echo '</div></body></html>'; }
    } else { echo get_error_message("invalid_old_request:events"); }
}

//Function is called on email votes
function request_vote() {
global $CFG, $MYVARS;
    $reqid = $MYVARS->GET["reqid"];
    $approve = $MYVARS->GET["approve"];
    $voteid = $MYVARS->GET["voteid"];

    $stance = $approve == "1" ? "approve" : "deny";
    echo "<html><title>Event Request has been voted on.</title><body>";
    //Make sure request exists and get the featureid
    if ($featureid = get_db_field("featureid","events_requests","reqid=$reqid")) {
        //Get feature request settings
        $pageid = get_db_field("pageid","pages_features","feature='events' AND featureid=$featureid");

        if (!$settings = fetch_settings("events",$featureid,$pageid)) {
        	make_or_update_settings_array(default_settings("events",$pageid,$featureid));
        	$settings = fetch_settings("events",$featureid,$pageid);
        }
        $locationid = $settings->events->$featureid->allowrequests->setting;

        if (valid_voter($pageid,$featureid,$voteid)) {
            //See if the person has already voted
            if ($row = get_db_row("SELECT * FROM events_requests
                                    WHERE reqid='$reqid'
                                        AND voted LIKE '%:$voteid;%'")) { //Person has already voted
                $voted = explode("::",$row["voted"]);
                foreach ($voted as $vote) {
                    $vote = trim($vote,":");
                    $entry = explode(";",$vote);
                    if ($entry[0] == $voteid) { //This is the vote that needs removed
                        if ($entry[1] == $approve) { //Same vote, nothing else needs done
                            echo "You have already voted to $stance this event.";
                        } else { //They have changed their vote.
                            //Remove old vote
                            execute_db_sql("UPDATE events_requests
                                                SET voted = replace(voted, ':$voteid;$entry[1]:', ':$voteid;$approve:')
                                                WHERE reqid=$reqid;");

                            if ($approve == "1") { //Remove 1 from against and add 1 to for
                                execute_db_sql("UPDATE events_requests
                                                    SET votes_against = (votes_against - 1), votes_for = (votes_for + 1)
                                                    WHERE reqid=$reqid;");
                            } else { //Remove 1 from for and add 1 to against
                                execute_db_sql("UPDATE events_requests
                                                    SET votes_for = (votes_for - 1),votes_against = (votes_against + 1)
                                                    WHERE reqid=$reqid;");
                            }
                            echo "You have changed your vote to $stance.";
                        }
                    }
                }
            } else { //New vote
                execute_db_sql("UPDATE events_requests
                                    SET voted = CONCAT(voted,':$voteid;$approve:')
                                    WHERE reqid=$reqid;");
                if ($approve == "1") { //Remove 1 from against and add 1 to for
                    execute_db_sql("UPDATE events_requests
                                        SET votes_for = (votes_for + 1)
                                        WHERE reqid=$reqid;");
                } else { //Remove 1 from for and add 1 to against
                    execute_db_sql("UPDATE events_requests
                                        SET votes_against = (votes_against + 1)
                                        WHERE reqid=$reqid;");
                }
                echo "You have voted to $stance this event.";
            }

            //See if the voting on this request is finished
            if ($request = get_db_row("SELECT * FROM events_requests WHERE reqid='$reqid'")) {
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
                $message = "<strong>Thank you for submitting a request for us to host your event: ".$request["event_name"].".</strong><br />
                            <br />
                            We will look over the details that you provided and respond shortly.<br />
                            Through the approval process you may receive additional questions about your event. $request_info";

                if ($request["votes_for"] == $settings->events->$featureid->requestapprovalvotes->setting &&
                    $request["votes_against"] < $settings->events->$featureid->requestdenyvotes->setting) {
                    //Event Approved
                    $subject = $CFG->sitename . " Event Request Approved";
                    $message = '<strong>The event ('.stripslashes($request["event_name"]).') has been approved by a vote of '.$request["votes_for"].' to '.$request["votes_against"].'.</strong>
                    <br /><br />'.
                    $request_info;

                    //Send email to the requester letting them know we denied the request
                    send_email($contact,$from,false,$subject, $message);

                    $emaillist = $settings->events->$featureid->emaillistconfirm->setting;
                    $emaillist = str_replace(array(","," ","\t","\r"),"\n",$emaillist);
                    $emaillist = str_replace("\n\n","\n",$emaillist);
                    $emaillist = explode("\n",$emaillist);
                    foreach ($emaillist as $emailuser) {
                        //Let everyone know the event has been denied
                        $thisuser->email = $emailuser;
                        $thisuser->fname = "";
                        $thisuser->lname = "";
                        send_email($thisuser,$from,false,$subject, $message);
                    }

                    //Make event
                    $MYVARS->GET["pageid"] = $pageid;
                    $MYVARS->GET["featureid"] = $featureid;
                    $eventid = convert_to_event($request,$locationid);

                    //Confirm event
                    $siteviewable = $pageid == $CFG->SITEID ? "1" : "0"; //if feature is on site, then yes...otherwise no.
                    confirm_event($pageid,$eventid,$siteviewable);

                    //Delete event request
                    execute_db_sql("DELETE FROM events_requests
                                        WHERE reqid=$reqid");
                    execute_db_sql("DELETE FROM events_requests_questions
                                        WHERE reqid=$reqid");

                }elseif ($request["votes_for"] < $settings->events->$featureid->requestapprovalvotes->setting &&
                        $request["votes_against"] == $settings->events->$featureid->requestdenyvotes->setting) {
                    //Event Denied
                    $subject = $CFG->sitename . " Event Request Denied";
                    $message = '<strong>The event ('.stripslashes($request["event_name"]).') has been denied by a vote of '.$request["votes_for"].' to '.$request["votes_against"].'.</strong>
                    <br /><br />'.
                    $request_info;

                    //Send email to the requester letting them know we denied the request
                    send_email($contact,$from,false,$subject, $message);

                    $emaillist = $settings->events->$featureid->emaillistconfirm->setting;
                    $emaillist = str_replace(array(","," ","\t","\r"),"\n",$emaillist);
                    $emaillist = str_replace("\n\n","\n",$emaillist);
                    $emaillist = explode("\n",$emaillist);
                    foreach ($emaillist as $emailuser) {
                        //Let everyone know the event has been denied
                        $thisuser->email = $emailuser;
                        $thisuser->fname = "";
                        $thisuser->lname = "";
                        send_email($thisuser,$from,false,$subject, $message);
                    }

                    //Delete event request
                    execute_db_sql("DELETE FROM events_requests
                                        WHERE reqid=$reqid;");
                    execute_db_sql("DELETE FROM events_requests_questions
                                        WHERE reqid=$reqid");
                }elseif ($request["votes_for"] > $settings->events->$featureid->requestapprovalvotes->setting) {
                    echo "Event has already been approved.  Thank you for voting.";
                }
            }

        } else { echo get_error_message("generic_permissions"); }
    } else {
        echo get_error_message("invalid_old_request:events");
    }
    echo "</body></html>";
}

function convert_to_event($request,$location) {
global $CFG,$MYVARS;
    $pageid = $MYVARS->GET["pageid"];
	$MYVARS->GET["event_name"] = $request["event_name"];
	$MYVARS->GET["contact"] = $request["contact_name"];
	$MYVARS->GET["email"] = $request["contact_email"];
	$MYVARS->GET["phone"] = $request["contact_phone"];
    $MYVARS->GET["location"] = $location;

	$MYVARS->GET["category"] = "1"; //Placed in general category...can be changed later
	$MYVARS->GET["siteviewable"] = $pageid == $CFG->SITEID ? "1" : "0"; //if feature is on site, then yes...otherwise no.

    $MYVARS->GET["description"] = $request["description"]; //event description

	$MYVARS->GET["multiday"] = $request["startdate"] == $request["enddate"] ? "0" : "1";

    $MYVARS->GET["event_begin_date"] = date(DATE_RFC822,$request["startdate"]);
    $MYVARS->GET["event_end_date"] = $MYVARS->GET["multiday"] == "1" ? date(DATE_RFC822,$request["enddate"]) : $MYVARS->GET["event_begin_date"];
    $MYVARS->GET["allday"] = "1"; $MYVARS->GET["reg"] = "0"; $MYVARS->GET["fee"] = "0";

    return submit_new_event(true);
}

function get_request_info($reqid) {
global $CFG;
    if ($request = get_db_row("SELECT * FROM events_requests WHERE reqid='$reqid'")) {
        return '<br />
        <strong>Event Name:</strong> '.stripslashes($request["event_name"]).'<br />
        <strong>Contact Name:</strong> '.stripslashes($request["contact_name"]).'<br />
        <strong>Contact Email:</strong> '.$request["contact_email"].'<br />
        <strong>Contact Phone:</strong> '.$request["contact_phone"].'<br />
        <strong>Estimated Participants:</strong> '.$request["participants"].'<br />
        <strong>Start Date:</strong> '.date('l jS \of F Y',$request["startdate"]).'<br />
        <strong>End Date:</strong> '.date('l jS \of F Y',$request["enddate"]).'<br />
        <strong>Description:</strong> '.stripslashes($request["description"]).'<br />';
    }
}

function print_registration() {
global $CFG, $MYVARS, $USER;
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$eventid = dbescape($MYVARS->GET["eventid"]);
	$template_id = dbescape($MYVARS->GET["template_id"]);
	$eventname = urldecode($MYVARS->GET["eventname"]);
	$regid = dbescape($MYVARS->GET["regid"]);
	$online_only = isset($MYVARS->GET["online_only"]) ? dbescape($MYVARS->GET["online_only"]) : false;
    $printarea = "";
    $returnme = '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/styles/print.css">';
	$returnme .= '<a class="dontprint" href="javascript: void(0);"
                    onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                ajaxapi(\'/features/events/events_ajax.php\',
                                        \'show_registrations\',
                                        \'&amp;eventid='.$eventid.'\',
                                        function() {
                                            if (xmlHttp.readyState == 4) {
                                                simple_display(\'searchcontainer\');
                                                document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                init_event_menu();
                                            }
                                        },
                                        true
                                );">
                    Back to '.$eventname.' registrants.
                </a>';

	if ($regid != "false") { //Print form for 1 registration
		$printarea .= printable_registration($regid,$eventid,$template_id);
	} else { //Batch print all registrations
        if ($registrations = get_db_result(get_registration_sort_sql($eventid,$online_only))) {
			while ($registration = fetch_row($registrations)) {
				$printarea .= $printarea == '' ? '<p style="font-size:.95em;" class="print">' : '<p style="font-size:.95em;" class="pagestart print">';
				$printarea .= printable_registration($registration["regid"],$eventid,$template_id);
				$printarea .= "</p>";
			}
		}
	}

	$returnme .= '<form>
                      <span class="dontprint">
                          <br /><br />
                      </span>
                      <input class="dontprint" type="button" value="Print" onclick="window.print();return false;" />
                      '.$printarea.'
                  </form>';
	echo $returnme;
}

function get_registration_sort_sql($eventid,$online_only=false) {
    if ($online_only) { $online_only = "AND e.manual = 0"; }
    $SQL = "SELECT e.*"; $orderby = "";

    $sort_info = get_db_row("SELECT e.eventid,b.orderbyfield,b.folder FROM events as e
                                JOIN events_templates as b ON b.template_id=e.template_id
                                WHERE eventid='$eventid'");

   	if ($sort_info["folder"] == "none") { //form template
        $sort_elements=explode(",",$sort_info["orderbyfield"]);$i=0;
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
                    ORDER BY val0 LIKE '%Reserved%' DESC,$orderby";
   	} else { //custom template
        $sort_elements=explode(",",$sort_info["orderbyfield"]);$i=0;
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
                    ORDER BY val0 LIKE '%Reserved%' DESC,$orderby";
   	}

return $SQL;
}

function printable_registration($regid, $eventid, $template_id) {
	$returnme = '<div>';

	$SQL = "SELECT * FROM events_templates WHERE template_id='$template_id'";
	$template = get_db_row($SQL);
    if ($template["folder"] == "none") {
        if ($template_forms = get_db_result("SELECT * FROM events_templates_forms
                                                WHERE template_id='$template_id'
                                                ORDER BY sort")) {
        	while ($form_element = fetch_row($template_forms)) {
        		if ($form_element["type"] == "payment") {
        			if ($values = get_db_result("SELECT * FROM events_registrations_values
                                                    WHERE regid='$regid' AND elementid='".$form_element["elementid"]."'
                                                    ORDER BY entryid")) {
        				$i = 0; $values_display = explode(",", $form_element["display"]);
        				while ($value = fetch_row($values)) {
	        				$returnme .= '<br />
                                            <div style="display:inline-block;width:150px;vertical-align: top;">
                                                <strong>'.$values_display[$i].'</strong>
                                            </div>
                                            <div style="display:inline-block;max-width: 400px;padding-left:5px;">
                                                '.stripslashes($value["value"]).'
                                            </div>';
        					$i++;
						}
					}
				} else {
	        		$value = get_db_row("SELECT * FROM events_registrations_values
                                            WHERE regid='$regid'
                                                AND elementid='".$form_element["elementid"]."'");
	        		$returnme .= '<br />
                                    <div style="display:inline-block;width:150px;vertical-align: top;">
                                        <strong>'.$form_element["display"].'</strong>
                                    </div>
                                    <div style="display:inline-block;max-width: 400px;padding-left:5px;">
                                        '.stripslashes($value["value"]).'
                                    </div>';
				}
			}
        }
    } else {
        $template_forms = explode(";",$template["formlist"]);
        $i=0;
        while (!empty($template_forms[$i])) {
        	$form = explode(":",$template_forms[$i]);
        	$value = get_db_row("SELECT * FROM events_registrations_values
                                    WHERE regid='$regid'
                                        AND elementname='".$form[0]."'");
        	$returnme .=   '<br />
                            <div style="display:inline-block;width:150px;vertical-align: top;">
                                <strong>'.$form[2].'</strong>
                            </div>
                            <div style="display:inline-block;max-width: 400px;padding-left:5px;">
                                '.stripslashes($value["value"]).'
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
                                &nbsp;$'.stripslashes($params["amount"] . " on " . date("m/d/Y", $params["date"]) . " - " . $params["txid"]).'
                            </div>';
			$i++;
		}
	}

    $returnme .= "</div>";

	return $returnme;
}

function save_reg_changes() {
global $CFG, $MYVARS, $USER;
    $eventid = dbescape($MYVARS->GET["eventid"]);
	$regid = isset($MYVARS->GET["regid"]) ? dbescape($MYVARS->GET["regid"]) : false;

    // Changing core registration values
    $reg_eventid = dbescape($MYVARS->GET["reg_eventid"]);
    if (!empty($reg_eventid) && get_db_result("SELECT * FROM events WHERE eventid='$reg_eventid'")) {
        $reg_email = dbescape($MYVARS->GET["reg_email"]);
        $reg_code = dbescape($MYVARS->GET["reg_code"]);
        execute_db_sql("UPDATE events_registrations
                            SET eventid='$reg_eventid', email='$reg_email', code='$reg_code'
                            WHERE regid='$regid'");
        execute_db_sql("UPDATE events_registrations_values
                            SET eventid='$reg_eventid'
                            WHERE regid='$regid'");
    }

	$SQL = "SELECT * FROM events_registrations_values
                WHERE regid='$regid'
                ORDER BY entryid";
	if ($entries = get_db_result($SQL)) {
		$SQL2 = '';
		while ($entry = fetch_row($entries)) {
			$SQL2 .= $SQL2 == "" ? "('".$entry["entryid"]."','".addslashes(urldecode($MYVARS->GET[$entry["entryid"]]))."')" : ",('".$entry["entryid"]."','".addslashes(urldecode($MYVARS->GET[$entry["entryid"]]))."')";
		}

		$SQL1 = "CREATE TEMPORARY TABLE temp_updates (
	  	id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	  	entryid INT(11) UNSIGNED NOT NULL,
	  	newvalue LONGTEXT COLLATE 'utf8_general_ci')";

		$SQL2 = "INSERT INTO temp_updates (entryid,newvalue) VALUES" . $SQL2;

		$SQL3 = "UPDATE events_registrations_values e, temp_updates t
		SET e.value = t.newvalue WHERE e.entryid = t.entryid AND e.regid='$regid'";
	}

    if (execute_db_sql($SQL1)) {
        if (execute_db_sql($SQL2)) {
            if (execute_db_sql($SQL3)) {
                // check paid status
                $paid = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='paid'");
                $payment_method = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='payment_method'");
                $minimum = get_db_field("fee_min", "events", "eventid='$eventid'");
                $verified = get_db_field("verified", "events_registrations", "regid='$regid'");
                if ($paid >= $minimum) {
                    if (empty($verified)) { // Not already verified.
                        // If payment is made, it is no longer in queue.
                        $SQL = "UPDATE events_registrations SET verified='1' WHERE regid='$regid'";
            			execute_db_sql($SQL);

                        $touser = new \stdClass;
                        $touser->fname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name_First'");
                		$touser->lname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name_Last'");
                		$touser->email = get_db_field("email","events_registrations","regid='$regid'");

                        $fromuser = new \stdClass;
                        $fromuser->email = $CFG->siteemail;
                		$fromuser->fname = $CFG->sitename;
                		$fromuser->lname = "";
                		$message = registration_email($regid, $touser);
                		if (send_email($touser, $fromuser, null, $CFG->sitename . " Registration", $message)) {
                			send_email($fromuser, $fromuser, null, $CFG->sitename . " Registration", $message);
                		}
                    }
                } else {
                    if ($payment_method = "Campership") {
                        $SQL = "UPDATE events_registrations SET verified='1' WHERE regid='$regid'";
                    } else {
                        $SQL = "UPDATE events_registrations SET verified='0' WHERE regid='$regid'";
                    }
        			execute_db_sql($SQL);
                }
                echo "Saved";
            } else { echo $SQL3; }
        } else { echo $SQL2; }
    } else { echo $SQL1; }
}

function delete_registration_info() {
global $CFG, $MYVARS, $USER;
	$regid = isset($MYVARS->GET["regid"]) ? dbescape($MYVARS->GET["regid"]) : false;
	if (execute_db_sql("DELETE FROM events_registrations
                            WHERE regid='$regid'") &&
        execute_db_sql("DELETE FROM events_registrations_values
                            WHERE regid='$regid'")) {
        echo "Deleted Registration";
    } else { echo "Failed"; }
}

function resend_registration_email() {
global $CFG, $MYVARS, $USER;
	$regid = isset($MYVARS->GET["regid"]) ? dbescape($MYVARS->GET["regid"]) : false;
    $eventid = isset($MYVARS->GET["eventid"]) ? dbescape($MYVARS->GET["eventid"]) : false;
    $pageid = isset($MYVARS->GET["pageid"]) ? dbescape($MYVARS->GET["pageid"]) : false;

    $link = '<a title="Return to Registration list" href="javascript: void(0)"
                onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                         ajaxapi(\'/features/events/events_ajax.php\',
                                 \'show_registrations\',
                                 \'&amp;eventid='.$eventid.'\',
                                 function() {
                                    if (xmlHttp.readyState == 4) {
                                        simple_display(\'searchcontainer\');
                                        document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                        init_event_menu();
                                    }
                                 },
                                 true
                         );
                         return false;
                ">Back to Registration List
             </a>';
    if (!empty($regid) && !empty($eventid)) {
        $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");

        $touser = (object)array('email' => get_db_field("email", "events_registrations", "regid='$regid'"),
                                'fname' => $registrant_name = get_registrant_name($regid),
                                'lname' => "");

        $fromuser = (object)array('email' => $event['email'],
                                  'fname' => $CFG->sitename,
                                  'lname' => "");

		$message = registration_email($regid, $touser);
		if (send_email($touser, $fromuser, null, $event["name"] . " Registration", $message)) {
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
    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
	$template_id = $event["template_id"];
	$eventname = $event["name"];
    $pageid = $event["pageid"];
	$regid = isset($MYVARS->GET["regid"]) ? dbescape($MYVARS->GET["regid"]) : false;

	$returnme = '<a href="javascript: void(0);"
                    onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                            ajaxapi(\'/features/events/events_ajax.php\',
                                    \'show_registrations\',
                                    \'&amp;eventid='.$eventid.'\',
                                    function() {
                                        if (xmlHttp.readyState == 4) {
                                            simple_display(\'searchcontainer\');
                                            document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                            init_event_menu();
                                        }
                                    },
                                    true
                            );
                    ">
                    Back to '.$eventname.' registrants.
                </a><br /><br />';

    // Get all events beginning 3 months in the past, to a year in the future.
    $today = get_timestamp();
    $events = get_db_result("SELECT eventid, (CONCAT(FROM_UNIXTIME(e.event_begin_date , '%Y'), ' ', e.name)) AS name
                               FROM events e
                              WHERE e.confirmed = 1
                                AND e.template_id = '$template_id'
                                AND e.start_reg > 0
                                AND ((e.event_begin_date - $today) < 31560000 && (e.event_begin_date - $today) > -7776000)");

    // Open copy form.
    $copy_form_code = '
    if ($(\'#copy_reg_to\').val() > 0) { 
        document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
        ajaxapi(\'/features/events/events_ajax.php\',
                \'copy_registration\',
                \'&amp;regid='.$regid.'\' + create_request_string(\'copy_form\'),
                function() {
                    if (xmlHttp.readyState == 4) {
                        ajaxapi(\'/features/events/events_ajax.php\',
                                \'show_registrations\',
                                \'&amp;eventid=\'+$(\'#copy_reg_to\').val(),
                                function() {
                                    if (xmlHttp.readyState == 4) {
                                        simple_display(\'searchcontainer\');
                                        document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
    $returnme .= '<h3>Copy Registration</h3>
                  <form name="copy_form" onsubmit="'.$copy_form_code.'">';
    $returnme .= '<table style="border-top: 1px solid grey;">';
    $returnme .= '<tr><td style="width: 125px;">Copy To </td><td>' .
                    make_select("copy_reg_to", $events, "eventid", "name", false, "", true, 1, "width: 420px", "", $eventid) .
                 '</td></tr>
                  <tr><td colspan="2" style="text-align: right"><input type="submit" value="Copy Registration" /><br /><br /></td></tr>';
    
    $returnme .= '</table></form>';

    $edit_form_code = 'document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                       ajaxapi(\'/features/events/events_ajax.php\',
                               \'save_reg_changes\',
                               \'&amp;regid='.$regid.'&amp;eventid='.$eventid.'\' + create_request_string(\'reg_form\'),
                               function() {
                                   if (xmlHttp.readyState == 4) {
                                       ajaxapi(\'/features/events/events_ajax.php\',
                                               \'show_registrations\',
                                               \'&amp;eventid='.$eventid.'&amp;sel='.$regid.'\',
                                               function() {
                                                   if (xmlHttp.readyState == 4) {
                                                       simple_display(\'searchcontainer\');
                                                       document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
                  <form name="reg_form" onsubmit="'.$edit_form_code.'">';
    
    $returnme .= '<table style="border-top: 1px solid grey;">';
    // Top Save button.
    $returnme .= '<tr><td colspan="2" style="text-align: center"><input type="submit" value="Save Changes" /></td></tr>';

    $reg_status = get_db_field("verified", "events_registrations", "regid='$regid'");
    $reg_status = !$reg_status ? "Pending" : "Completed";
    $returnme .= '<tr><td>Status </td><td>' . $reg_status . '</td></tr>';

    db_goto_row($events); // reset pointer to use it again.
    $returnme .= '<tr><td>Event </td><td>' .
                    make_select("reg_eventid", $events, "eventid", "name", $eventid, "", false, 1, "width: 420px") .
                 '</td></tr>';
    
    $event_reg = get_db_row("SELECT * FROM events_registrations WHERE regid='$regid'");
    $returnme .= '<tr><td>Email </td><td>' .
                    '<input id="reg_email" name="reg_email" type="text" size="45" value="'.stripslashes($event_reg["email"]).'" />' .
                 '</td></tr>';
    $returnme .= '<tr><td>Pay Code </td><td>' .
                    '<input id="reg_code" name="reg_code" type="text" size="45" value="'.stripslashes($event_reg["code"]).'" />' .
                 '</td></tr>';

	$SQL = "SELECT * FROM events_templates WHERE template_id='$template_id'";
	$template = get_db_row($SQL);
    if ($template["folder"] == "none") {
        if ($template_forms = get_db_result("SELECT * FROM events_templates_forms
                                                WHERE template_id='$template_id'
                                                ORDER BY sort")) {
        	while ($form_element = fetch_row($template_forms)) {
        		if ($form_element["type"] == "payment") {
        			if ($values = get_db_result("SELECT * FROM events_registrations_values
                                                    WHERE regid='$regid'
                                                        AND elementid='".$form_element["elementid"]."'
                                                    ORDER BY entryid")) {
        				$i = 0; $values_display = explode(",", $form_element["display"]);
        				while ($value = fetch_row($values)) {
	        				$returnme .= '<tr><td>'.$values_display[$i].' </td><td><input id="'.$value["entryid"].'" name="'.$value["entryid"].'" type="text" size="45" value="'.stripslashes($value["value"]).'" /></td></tr>';
        					$i++;
						}
					}
				} else {
	        		$value = get_db_row("SELECT * FROM events_registrations_values
                                            WHERE regid='$regid'
                                                AND elementid='".$form_element["elementid"]."'");
	        		$returnme .= '<tr><td>'.$form_element["display"].' </td><td><input id="'.$value["entryid"].'" name="'.$value["entryid"].'" type="text" size="45" value="'.stripslashes($value["value"]).'" /></td></tr>';
				}
			}
        }
    } else {
        $template_forms = explode(";", trim($template["formlist"], ';'));
        $i=0;
        while (isset($template_forms[$i])) {
        	$form = explode(":",$template_forms[$i]);
        	$value = get_db_row("SELECT * FROM events_registrations_values
                                    WHERE regid='$regid'
                                        AND elementname='".$form[0]."'");
        	$returnme .= '<tr><td>'.$form[2].' </td><td><input id="'.$value["entryid"].'" name="'.$value["entryid"].'" type="text" size="45" value="'.stripslashes($value["value"]).'" /></td></tr>';
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
    $eventid = isset($MYVARS->GET["eventid"]) ? dbescape($MYVARS->GET["eventid"]) : $eventid;
    $reserveamount = isset($MYVARS->GET["reserveamount"]) ? dbescape($MYVARS->GET["reserveamount"]) : $reserveamount;

    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
	$template_id = $event["template_id"];
	$eventname = $event["name"];
    $pageid = $event["pageid"];

	$reserved = 0;
    $return = [];
	while ($reserved < $reserveamount) {
		$SQL = "";$SQL2 = "";
		if ($regid = execute_db_sql("INSERT INTO events_registrations
                                    (eventid,date,code,manual)
                                    VALUES('$eventid','".get_timestamp()."','".uniqid("",true)."',1)")) {
			$SQL = "SELECT * FROM events_templates WHERE template_id='$template_id'";
			$template = get_db_row($SQL);
		    if ($template["folder"] == "none") {
		        if ($template_forms = get_db_result("SELECT * FROM events_templates_forms
                                                        WHERE template_id='$template_id'
                                                        ORDER BY sort")) {
		        	while ($form_element = fetch_row($template_forms)) {
		        		if ($form_element["type"] == "payment") {
		    				$SQL2 .= $SQL2 == "" ? "" : ",";
		        			$SQL2 .= "('$regid','".$form_element["elementid"]."','','$eventid','total_owed'),('$regid',".$form_element["elementid"].",'','$eventid','paid'),('$regid','".$form_element["elementid"]."','','$eventid','payment_method')";
						} else {
		        			$SQL2 .= $SQL2 == "" ? "" : ",";
		        			$value = $form_element["nameforemail"] == 1 ? "Reserved" : "";
		        			$SQL2 .= "('$regid',".$form_element["elementid"].",'$value','$eventid','".$form_element["elementname"]."')";
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
					$SQL2 .= "('$regid','$value','$eventid','".$form[0]."')";
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
        foreach($return as $key => $val) {
            echo "Reserved spot #". ($key+1);
            echo $val ? ": Success <br />" : " Failed <br />";
        }
    } else {
        return $return;
    }
}

function copy_registration($regid = false, $eventid = false) {
    global $CFG, $MYVARS, $USER;
	$regid = !$regid ? dbescape($MYVARS->GET["regid"]) : $regid;
    $eventid = !$eventid ? dbescape($MYVARS->GET["copy_reg_to"]) : $eventid;

    $oldreg = get_db_row("SELECT * FROM events_registrations WHERE regid='$regid'");
    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
    $blankreg = add_blank_registration($eventid);

    if (isset($blankreg[0])) {
        $newregid = $blankreg[0];
        $SQL = "SELECT * FROM events_registrations_values WHERE regid='$regid'";

        if($registration_values = get_db_result($SQL)) {
            while ($registration_value = fetch_row($registration_values)) {
                $SQL = 'UPDATE events_registrations_values 
                           SET value = "' . $registration_value["value"] . '"
                         WHERE regid = "' . $newregid . '"
                           AND elementname = "' . $registration_value["elementname"] .'"';
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
	$eventid = dbescape($MYVARS->GET["eventid"]);

    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
	$template_id = $event["template_id"];
	$eventname = $event["name"];
    $pageid = $event["pageid"];
    $selected = empty($MYVARS->GET["sel"]) ? false : dbescape($MYVARS->GET["sel"]);
    $initial_display = $selected ? "" : "display:none;";
	$returnme = '<h2>'.stripslashes($eventname).'</h2>';

    //Print all registration button
    $returnme .= '<span style="width:50%;text-align:right;float:right;">
                    <a href="javascript: void(0);"
                       onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                ajaxapi(\'/features/events/events_ajax.php\',
                                        \'print_registration\',
                                        \'&amp;regid=false&amp;pageid='.$pageid.'&amp;eventname='.urlencode($eventname).'&amp;eventid='.$eventid.'&amp;template_id='.$template_id.'\',
                                        function() {
                                            if (xmlHttp.readyState == 4) {
                                                simple_display(\'searchcontainer\');
                                                document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
                       onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                ajaxapi(\'/features/events/events_ajax.php\',
                                        \'print_registration\',
                                        \'&amp;regid=false&amp;pageid='.$pageid.'&amp;eventname='.urlencode($eventname).'&amp;online_only=1&amp;eventid='.$eventid.'&amp;template_id='.$template_id.'\',
                                        function() {
                                            if (xmlHttp.readyState == 4) {
                                                simple_display(\'searchcontainer\');
                                                document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
			$values->$i->name = ($i+1) . " - " . get_registrant_name($registrant["regid"]);
            $values->$i->name .= !empty($registrant["verified"]) ? '' : ' [PENDING]';
			$values->$i->regid = $registrant["regid"];
			$i++;
		}

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
                                    '.make_select_from_array("registrants", $values, "regid", "name", $selected, "" , 'onchange="if ($(this).val().length > 0) { $(\'#event_menu_button\').show(); } else { $(\'#event_menu_button\').hide(); }"', true, 1, "width: 200px;") . '</td>' .
                        '       <td>
                                    <a id="event_menu_button" title="Menu" style="'.$initial_display.'" href="javascript: void(0);"><img src="'.$CFG->wwwroot.'/images/down.gif" alt="Menu" /></a>
                                    <ul id="event_menu">' .
                                        '<li>
                                            <a title="Edit Registration" href="javascript: void(0);"
                                                onclick="if (document.getElementById(\'registrants\').value != \'\') {
                                                            document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                            ajaxapi(\'/features/events/events_ajax.php\',
                                                                    \'get_registration_info\',
                                                                    \'&amp;eventid='.$eventid.'&amp;regid=\'+document.getElementById(\'registrants\').value,
                                                                    function() {
                                                                        if (xmlHttp.readyState == 4) {
                                                                            simple_display(\'searchcontainer\');
                                                                            document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                        }
                                                                    },
                                                                    true
                                                            );
                                                        }">
                                                <img src="'.$CFG->wwwroot.'/images/edit.png" /> Edit Registration
                                            </a>
                                        </li>' .
                                        '<li>
                                            <a title="Delete Registration" href="javascript: void(0);"
                                                onclick="if (document.getElementById(\'registrants\').value != \'\' && confirm(\'Do you want to delete this registration?\')) {
                                                            document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                            ajaxapi(\'/features/events/events_ajax.php\',
                                                                    \'delete_registration_info\',
                                                                    \'&amp;regid=\'+document.getElementById(\'registrants\').value,
                                                                    function() { do_nothing(); }
                                                            );
                                                            ajaxapi(\'/features/events/events_ajax.php\',
                                                                    \'show_registrations\',
                                                                    \'&amp;eventid='.$eventid.'\',
                                                                    function() {
                                                                        if (xmlHttp.readyState == 4) {
                                                                            simple_display(\'searchcontainer\');
                                                                            document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                            init_event_menu();
                                                                        }
                                                                    },
                                                                    true
                                                            );
                                                        }">
                                                <img src="'.$CFG->wwwroot.'/images/delete.png" /> Delete Registration
                                            </a>
                                        </li>' .
                                        '<li>
                                            <a title="Send Registration Email" href="javascript: void(0);"
                                                onclick="if (document.getElementById(\'registrants\').value != \'\') {
                                                            document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                            ajaxapi(\'/features/events/events_ajax.php\',
                                                                    \'resend_registration_email\',
                                                                    \'&amp;pageid='.$pageid.'&amp;eventid='.$eventid.'&amp;regid=\'+document.getElementById(\'registrants\').value,
                                                                    function() {
                                                                        if (xmlHttp.readyState == 4) {
                                                                            simple_display(\'searchcontainer\');
                                                                            document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                        }
                                                                    },
                                                                    true
                                                            );
                                                        }">
                                                <img src="'.$CFG->wwwroot.'/images/mail.gif" /> Send Registration Email
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
                    <a href="javascript: void(0);" onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                            ajaxapi(\'/features/events/events_ajax.php\',
                                                                    \'add_blank_registration\',
                                                                    \'&amp;pageid='.$pageid.'&amp;reserveamount=\'+document.getElementById(\'reserveamount\').value+\'&amp;eventname='.urlencode($eventname).'&amp;eventid='.$eventid.'&amp;template_id='.$template_id.'\',
                                                                    function() { do_nothing(); }
                                                            );
                                                            ajaxapi(\'/features/events/events_ajax.php\',
                                                                    \'show_registrations\',
                                                                    \'&amp;eventid='.$eventid.'\',
                                                                    function() {
                                                                        if (xmlHttp.readyState == 4) {
                                                                            simple_display(\'searchcontainer\');
                                                                            document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                            init_event_menu();
                                                                        }
                                                                    },
                                                                    true
                                                            );">
                        <img title="Reserve Spot(s)" style="vertical-align:bottom;" onclick="blur()" src="'.$CFG->wwwroot.'/images/reserve.gif" />
                    </a>
                </div>';
	echo '<div style="font-size:.9em;padding:15px 5px;">' . $returnme . '</div>';
}

function eventsearch() {
global $CFG, $MYVARS, $USER;
    $MYVARS->search_perpage = 8;
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
    $pagenum = isset($MYVARS->GET["pagenum"]) ? dbescape($MYVARS->GET["pagenum"]) : 0;
    $firstonpage = $MYVARS->search_perpage * $pagenum;
    $limit = " LIMIT $firstonpage," . $MYVARS->search_perpage;
    $words = explode(" ", $searchwords);
    $i = 0;
    while (isset($words[$i])) {
        $searchpart = "(name LIKE '%" . dbescape($words[$i]) . "%')";
        $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
        $i++;
    }

	if ($MYVARS->GET["pageid"] == $CFG->SITEID) {
		$SQL = "SELECT * FROM events
                    WHERE (pageid=".dbescape($MYVARS->GET["pageid"])."
                            OR (siteviewable=1 AND confirmed=1))
                        AND start_reg !=''
                        AND (" . $searchstring . ")
                    ORDER BY event_begin_date DESC";
	} else {
		$SQL = "SELECT * FROM events
                    WHERE pageid=".dbescape($MYVARS->GET["pageid"])."
                        AND start_reg !=''
                        AND (" . $searchstring . ")
                    ORDER BY event_begin_date DESC";
	}

    $total = get_db_count($SQL); //get the total for all pages returned.
    $SQL .= $limit; //Limit to one page of return.
    $count = $total > (($pagenum+1) * $MYVARS->search_perpage) ? $MYVARS->search_perpage : $total - (($pagenum) * $MYVARS->search_perpage); //get the amount returned...is it a full page of results?
    $events = get_db_result($SQL);
    $amountshown = $firstonpage + $MYVARS->search_perpage < $total ? $firstonpage + $MYVARS->search_perpage : $total;
    $prev = $pagenum > 0 ? '<a href="javascript: void(0);" onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                    ajaxapi(\'/features/events/events_ajax.php\',
                                                                            \'eventsearch\',
                                                                            \'&amp;pageid='.$MYVARS->GET["pageid"].'&pagenum=' . ($pagenum - 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
                                                                            function() {
                                                                                if (xmlHttp.readyState == 4) {
                                                                                    simple_display(\'searchcontainer\');
                                                                                    document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                                }
                                                                            },
                                                                            true
                                                                    );"
                            onmouseup="this.blur()">
                                <img src="' . $CFG->wwwroot . '/images/prev.gif" title="Previous Page" alt="Previous Page">
                            </a>' : "";
    $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
    $next = $firstonpage + $MYVARS->search_perpage < $total ? '<a href="javascript: void(0);" onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                                                        ajaxapi(\'/features/events/events_ajax.php\',
                                                                                                                \'eventsearch\',
                                                                                                                \'&amp;pageid='.$MYVARS->GET["pageid"].'&amp;pagenum=' . ($pagenum + 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
                                                                                                                function() {
                                                                                                                    if (xmlHttp.readyState == 4) {
                                                                                                                        simple_display(\'searchcontainer\');
                                                                                                                        document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
                                            WHERE eventid='" . $event['eventid']."'");
				$limit = $event['max_users'] == "0" ? "&#8734;" : $event['max_users'];
				//GET EXPORT CSV BUTTON
				if (user_has_ability_in_page($USER->userid, "exportcsv", $event["pageid"])) {
				    $export = '<a href="javascript: void(0)" onclick="ajaxapi(\'/features/events/events_ajax.php\',
                                                                              \'export_csv\',
                                                                              \'&amp;pageid=' . $event["pageid"] . '&amp;featureid=' . $event['eventid'] . '\',
                                                                              function() { run_this();}
                                                                        );">
                                    <img src="' . $CFG->wwwroot . '/images/csv.png" title="Export ' . $regcount . '/' . $limit . ' Registrations" alt="Export ' . $regcount . ' Registrations" />
                                </a>';}
           	}

			$body .= '<tr style="height:30px;border:3px solid white;font-size:.9em;">
                        <td style="width:40%;padding:5px;font-size:.85em;white-space:nowrap;">
                            <a href="javascript: void(0)" onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                    ajaxapi(\'/features/events/events_ajax.php\',
                                                                            \'show_registrations\',
                                                                            \'&amp;eventid='.$event["eventid"].'\',
                                                                            function() {
                                                                                if (xmlHttp.readyState == 4) {
                                                                                    simple_display(\'searchcontainer\');
                                                                                    document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
                            '.date("m/d/Y",$event["event_begin_date"]).' '.$export.'
                        </td>
                        <td style="text-align:right;padding:5px;">
                            <a href="mailto:'.$event["email"].'" />'.$event["contact"].'</a>
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
	$code = dbescape($MYVARS->GET["code"]);

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
	if (!isset($COMLIB)) { include_once($CFG->dirroot . '/lib/comlib.php');}

	$event = get_db_row("SELECT * FROM events WHERE eventid='".dbescape($MYVARS->GET["eventid"])."'");

    if ($event['fee_full'] != 0 && isset($MYVARS->GET["payment_amount"])) {
        $MYVARS->GET["cart_total"] = isset($MYVARS->GET["total_owed"]) && $MYVARS->GET["total_owed"] != 0 ? $MYVARS->GET["total_owed"] + $MYVARS->GET["payment_amount"] : $MYVARS->GET["payment_amount"];
        $MYVARS->GET["total_owed"] = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
    }


	$error = "";
	if ($regid = enter_registration($event['eventid'], $MYVARS->GET, $MYVARS->GET["email"])) { //successful registration
		echo '<center><div style="width:70%">You have successfully registered for ' . $event['name'] . '.<br />';

		if ($error != "") { echo $error . "<br />";}

		if ($event['allowinpage'] != 0) {
			if (is_logged_in() && $event['pageid'] != $CFG->SITEID) {
				subscribe_to_page($event['pageid'], $USER->userid);
				echo 'You have been automatically allowed into this events web page.  This page contain specific information about this event.';
			}

			if ($event['fee_full'] != 0) {
				$registrant_name = get_registrant_name($regid);
				$items = isset($MYVARS->GET["items"]) ? $MYVARS->GET["items"] . "**" . $regid . "::Event: " . $event["name"] . " - $registrant_name's Registration::" . $MYVARS->GET["payment_amount"] : $regid . "::Event: " . $event["name"] . " - $registrant_name's Registration::" . $MYVARS->GET["payment_amount"];
				echo '<div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="' . $MYVARS->GET["cart_total"] . '" />
					 <input type="hidden" name="items" id="items" value="' . $items . '" /></div>';

				$items = explode("**", $items);
				$i = 0;
				while (isset($items[$i])) {
					$itm = explode("::", $items[$i]);
					$cart_items[$i]->regid = $itm[0];
					$cart_items[$i]->description = $itm[1];
					$cart_items[$i]->cost = $itm[2];
					$i++;
				}

				if ($MYVARS->GET['payment_method'] == "PayPal") {
					echo '<br />
					If you would like to pay the <span style="color:blue;font-size:1.25em;">$' . $MYVARS->GET["cart_total"] . '</span> fee now, click the Paypal button below.
					<center>
					' . make_paypal_button($cart_items, $event['paypal']) . '
					</center>
					<br /><br />
					Your registration will be complete upon payment. ';
				} else {
					echo '<br />
					If you are done with the registration process, please make out your <br />
					check or money order in the amount of <span style="color:blue;font-size:1.25em;">$' . $MYVARS->GET["cart_total"] . '</span> payable to <b>' . $event["payableto"] . '</b> and send it to <br /><br />
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

        $touser = (object)array('email' => get_db_field("email", "events_registrations", "regid='$regid'"),
                                'fname' => $registrant_name = get_registrant_name($regid),
                                'lname' => "");

        $fromuser = (object)array('email' => $event['email'],
                                  'fname' => $CFG->sitename,
                                  'lname' => "");

		$message = registration_email($regid, $touser);
		send_email($touser, $fromuser, null, $event["name"] . " Registration", $message);

		log_entry("events", dbescape($MYVARS->GET["eventid"]), "Registered for Event"); //Log
	} else { //failed registration
		log_entry("events", dbescape($MYVARS->GET["eventid"]), "Failed Event Registration"); //Log
		echo '<center><div style="width:60%"><span class="error_text">Your registration for ' . $event['name'] . ' has failed. </span><br /> ' . $error . '</div>';
	}
}

function delete_limit() {
global $CFG, $MYVARS;
	$template_id = dbescape($MYVARS->GET["template_id"]);
	$limit_type = dbescape($MYVARS->GET["limit_type"]);
	$limit_num = dbescape($MYVARS->GET["limit_num"]);
	$hard_limits = dbescape($MYVARS->GET["hard_limits"]);
	$soft_limits = dbescape($MYVARS->GET["soft_limits"]);
    $hidden_variable1 = $hidden_variable2 = "";

	$returnme = "";
	if (!$templateid) { echo $returnme;}

	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='$template_id'");

	if ($hard_limits != "") { // There are some hard limits
		$limits_array = explode("*", $hard_limits);
		$i = 0;
		$returnme .= "<br /><b>Hard Limits</b> <br />";
		$alter = 0;
		while (isset($limits_array[$i])) {
			if (!($limit_type == "hard_limits" && $limit_num == $i)) {
				$limit = explode(":", $limits_array[$i]);
                $displayname = get_template_field_displayname($template["template_id"],$limit[0]);
				$returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . ' <a href="javascript:void(0);" onclick="delete_limit(\'hard_limits\',\'' . ($i - $alter) . '\');">Delete</a><br />';
				$hidden_variable1 .= $hidden_variable1 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
			} else {  $alter++; }
			$i++;
		}
	}

	if ($hidden_variable1 == "") { $returnme = ""; }
	$returnme2 = "";
	if ($soft_limits != "") { // There are some soft limits
		$limits_array = explode("*", $soft_limits);
		$i = 0;
		$returnme2 .= "<br /><b>Soft Limits</b> <br />";
		$alter = 0;
		while (isset($limits_array[$i])) {
			if (!($limit_type == "soft_limits" && $limit_num == $i)) {
				$limit = explode(":", $limits_array[$i]);
                $displayname = get_template_field_displayname($template["template_id"],$limit[0]);
				$returnme2 .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . ' <a href="javascript:void(0);" onclick="delete_limit(\'soft_limits\',\'' . ($i - $alter) . '\');">Delete</a><br />';
				$hidden_variable2 .= $hidden_variable2 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
			} else { $alter++; }
			$i++;
		}
	}

	if ($hidden_variable2 == "") { $returnme2 = ""; }

	echo $returnme . $returnme2 . '<input type="hidden" id="hard_limits" value="' . $hidden_variable1 . '" />' . '<input type="hidden" id="soft_limits" value="' . $hidden_variable2 . '" />';
}

function add_custom_limit() {
global $CFG, $MYVARS;
	$template_id = dbescape($MYVARS->GET["template_id"]);
	$hard_limits = dbescape($MYVARS->GET["hard_limits"]);
	$soft_limits = dbescape($MYVARS->GET["soft_limits"]);
    $hidden_variable1 = $hidden_variable2 = "";

	$returnme = "";
	if (!$template_id) { echo $returnme; }

	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='$template_id'");

	if ($hard_limits != "") { // There are some hard limits
		$limits_array = explode("*", $hard_limits);
		$i = 0;
		$returnme .= "<br /><b>Hard Limits</b> <br />";
		while (isset($limits_array[$i])) {
			$limit = explode(":", $limits_array[$i]);

			if ($template["folder"] == "none") {
				$displayname = get_db_field("display", "events_templates_forms", "elementid=" . $limit[0]);
			} else {
				$displayname = $limit[0];
			}

			$returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . ' <a href="javascript:void(0);" onclick="delete_limit(\'hard_limits\',\'' . $i . '\');">Delete</a><br />';
			$hidden_variable1 .= $hidden_variable1 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
			$i++;
		}
	}

	if ($soft_limits != "") { // There are some soft limits
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

	echo $returnme . '<input type="hidden" id="hard_limits" value="' . $hidden_variable1 . '" />' . '<input type="hidden" id="soft_limits" value="' . $hidden_variable2 . '" />';
}

function get_limit_form() {
global $CFG, $MYVARS, $USER;
	$template_id = dbescape($MYVARS->GET["template_id"]);
	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='$template_id'");
	$fields = '<select id="custom_limit_fields">';
	if ($template["folder"] == "none") {
		$SQL = "SELECT * FROM events_templates_forms
                    WHERE template_id='$template_id'
                        AND type != 'payment'";
		$forms = get_db_result($SQL);
		while ($form = fetch_row($forms)) {
			$fields .= '<option value="' . $form["elementid"] . '">' . $form["display"] . '</option>';
		}
	} else {
		$formlist = explode(";", $template['formlist']);

		$i = 0;
		while (isset($formlist[$i])) {
			$element = explode(":", $formlist[$i]);
			if (isset($element[1]) && $element[1] != "Pay") { $fields .= '<option value="' . $element[0] . '">' . $element[2] . '</option>'; }
			$i++;
		}
	}

	$fields .= '</select>';

	$operators = '	<select id="operators" style="width:100px;">
			<option value="eq">equal to</option>
			<option value="neq">not equal to</option>
			<option value="lk">similar to</option>
			<option value="nlk">not similar to</option>
			<option value="gt">greater than</option>
			<option value="gteq">greater than or equal to</option>
			<option value="lt">less than</option>
			<option value="lteq">less than or equal to</option>
			</select>';
	echo '
	<br />
	<table style="margin:0px 0px 0px 50px;">
		<tr>
			<td class="field_input" style="line-height:30px;">
				Limit to <input id="custom_limit_num" type="text" size=5/> registrations where ' . $fields . '<br />is ' . $operators . ' <input id="custom_limit_value" type="text" />
			</td>
		</tr>
	</table>
	<br />
	<table style="margin:0px 0px 0px 50px;">
		<tr>
			<td class="field_title" style="width:115px; background-color:buttonface;">
				Soft Limit:
			</td>
			<td class="field_input">
			<select id="custom_limit_sorh"><option value="0">No</option><option value="1">Yes</option></select>
			<span class="hint">' . get_help("input_event_custom_limit_sorh:events") . '<span class="hint-pointer">&nbsp;</span></span>
			</td>
		</tr>
	</table><center>
	<span id="custom_limit_fields_error" class="error_text"></span>
	<span id="custom_limit_value_error" class="error_text"></span>
	<span id="custom_limit_num_error" class="error_text"></span>
	<span id="custom_limit_sorh_error" class="error_text"></span><br />
	<input type="button" value="Add" onclick="add_custom_limit();" /></center>
    ' . js_code_wrap('prepareInputsForHints();');
}

function add_new_location() {
global $CFG, $MYVARS, $USER;
	$name = dbescape(urldecode($MYVARS->GET["name"]));
	$add1 = dbescape(urldecode($MYVARS->GET["add1"]));
	$add2 = dbescape(urldecode($MYVARS->GET["add2"]));
	$zip = dbescape(urldecode($MYVARS->GET["zip"]));
	$eventid = dbescape(urldecode($MYVARS->GET["eventid"]));

	$phone = isset($MYVARS->GET["phone"]) ? dbescape($MYVARS->GET["phone"]) : "";
	$shared = isset($MYVARS->GET["share"]) ? 1 : 0;

	$id = execute_db_sql("INSERT INTO events_locations
                            (location,address_1,address_2,zip,phone,userid,shared)
                            VALUES('" . addslashes($name) . "','" . addslashes($add1) . "','" . addslashes($add2) . "',$zip,'$phone','," . $USER->userid . ",',$shared)");

	//Log
	log_entry("events", $name, "Added Location");
	echo get_my_locations($USER->userid,$id,$eventid);
}

function submit_new_event($request=false) {
global $CFG, $MYVARS;
	date_default_timezone_set(date_default_timezone_get());
	$pageid = !isset($MYVARS->GET["eventid"]) ? dbescape($MYVARS->GET["pageid"]) : get_db_field("pageid", "events", "eventid=" . dbescape($MYVARS->GET["eventid"]));
	$name = dbescape((urldecode($MYVARS->GET["event_name"])));
	$contact = dbescape((urldecode($MYVARS->GET["contact"])));
	$email = dbescape(urldecode($MYVARS->GET["email"]));
	$phone = dbescape($MYVARS->GET["phone"]);
	$location = dbescape($MYVARS->GET["location"]);
	$category = dbescape($MYVARS->GET["category"]);
	$siteviewable = dbescape($MYVARS->GET["siteviewable"]);
	$byline = dbescape((urldecode($MYVARS->GET["byline"])));
    $description = dbescape((urldecode($MYVARS->GET["description"])));
	$multiday = dbescape($MYVARS->GET["multiday"]);
    $workers = dbescape($MYVARS->GET["workers"]);

    //strtotime php5 fixes
    if (isset($MYVARS->GET["event_begin_date"])) {
        $ebd = explode(" ",$MYVARS->GET["event_begin_date"]);
        $event_begin_date = strtotime("$ebd[0] $ebd[1] $ebd[2] $ebd[3] $ebd[4] $ebd[5]");
    } else { die("No Event Begin Date"); }

    if (isset($MYVARS->GET["event_end_date"])) {
        $eed = explode(" ",$MYVARS->GET["event_end_date"]);
        $event_end_date = $multiday == "1" ? strtotime("$eed[0] $eed[1] $eed[2] $eed[3] $eed[4] $eed[5]") : $event_begin_date;
    } else { $event_end_date = $event_begin_date; }

    $allday = dbescape($MYVARS->GET["allday"]);
	$event_begin_time = $allday != "1" ? dbescape($MYVARS->GET["begin_time"]) : '';
	$event_end_time = $allday != "1" ? dbescape($MYVARS->GET["end_time"]) : '';

	$reg = dbescape($MYVARS->GET["reg"]);
	$allowinpage = $reg == "1" && isset($MYVARS->GET["allowinpage"]) && $MYVARS->GET["allowinpage"] == "1" ? $pageid : '0'; //if a user will be enrolled in the page after online registration
	$max_users = $reg == "1" && isset($MYVARS->GET["max"]) ? dbescape($MYVARS->GET["max"]) : '0';
	$hard_limits = $reg == "1" && isset($MYVARS->GET["hard_limits"]) && $MYVARS->GET["hard_limits"] != "" ? dbescape($MYVARS->GET["hard_limits"]) : '0';
	$soft_limits = $reg == "1" && isset($MYVARS->GET["soft_limits"]) && $MYVARS->GET["soft_limits"] != "" ? dbescape($MYVARS->GET["soft_limits"]) : '0';

    //strtotime php5 fixes
    if (isset($MYVARS->GET["start_reg"])) {
        if (strstr($MYVARS->GET["start_reg"],"/")) {
            $startr = explode("/",$MYVARS->GET["start_reg"]);
            $start_reg = $reg == "1" && isset($MYVARS->GET["start_reg"]) ? strtotime("$startr[1]/$startr[2]/$startr[0]") : '0';
        } else {
            $startr = explode(" ",$MYVARS->GET["start_reg"]);
            $start_reg = $reg == "1" && isset($MYVARS->GET["start_reg"]) ? strtotime("$startr[0] $startr[1] $startr[2] $startr[3] $startr[4] $startr[5]") : '0';
        }

    } else { $start_reg = '0';}

    if (isset($MYVARS->GET["stop_reg"])) {
        if (strstr($MYVARS->GET["stop_reg"],"/")) {
            $stopr = explode("/",$MYVARS->GET["stop_reg"]);
            $stop_reg = $reg == "1" ? strtotime("$stopr[1]/$stopr[2]/$stopr[0]") : '0';
        } else {
            $stopr = explode(" ",$MYVARS->GET["stop_reg"]);
            $stop_reg = $reg == "1" ? strtotime("$stopr[0] $stopr[1] $stopr[2] $stopr[3] $stopr[4] $stopr[5]") : '0';
        }
    } else { $stop_reg = '0'; }

	$template_id = $reg == "1" ? dbescape($MYVARS->GET["template"]) : '0';

	$fee = dbescape($MYVARS->GET["fee"]);
	$fee_full = $fee != "1" ? '0' : dbescape($MYVARS->GET["full_fee"]);
	$fee_min = $fee != "1" ? '0' : dbescape($MYVARS->GET["min_fee"]);
	$sale_fee = $fee != "1" ? '0' : dbescape($MYVARS->GET["sale_fee"]);
	$sale_fee = $sale_fee == "" ? '0' : $sale_fee;

    if (isset($MYVARS->GET["sale_end"])) {
        if (strstr($MYVARS->GET["sale_end"],"/")) {
            $se = explode("/",$MYVARS->GET["sale_end"]);
            $sale_end = $sale_fee != '0' ? strtotime("$se[1]/$se[2]/$se[0]") : '0';
        } else {
            $se = explode(" ",$MYVARS->GET["sale_end"]);
            $sale_end = $sale_fee != '0' ? strtotime("$se[0] $se[1] $se[2] $se[3] $se[4] $se[5]") : '0';
        }
    } else { $sale_end = '0'; }

	$payableto = $fee != "1" ? '' : dbescape(urldecode($MYVARS->GET["payableto"]));
	$checksaddress = $fee != "1" ? '' : dbescape(urldecode($MYVARS->GET["checksaddress"]));
	$paypal = $fee != "1" ? '' : dbescape($MYVARS->GET["paypal"]);

	$confirmed = 3;
	$caleventid = 0;

	if (empty($MYVARS->GET["eventid"])) { //New Event

		$SQL = "INSERT INTO events
                            (pageid,template_id,name,category,location,allowinpage,start_reg,stop_reg,max_users,
    				        event_begin_date,event_begin_time,event_end_date,event_end_time,
    				        confirmed,siteviewable,allday,caleventid,byline,description,fee_min,fee_full,payableto,checksaddress,
    				        paypal,sale_fee,sale_end,contact,email,phone,hard_limits,soft_limits,workers)
                    VALUES('$pageid','$template_id','$name','$category','$location','$allowinpage','$start_reg','$stop_reg','$max_users',
                            '$event_begin_date','$event_begin_time','$event_end_date','$event_end_time',
                            '$confirmed','$siteviewable','$allday','$caleventid','$byline','$description','$fee_min','$fee_full','$payableto','$checksaddress',
                            '$paypal','$sale_fee','$sale_end','$contact','$email','$phone','$hard_limits','$soft_limits','$workers')";

		if ($eventid = execute_db_sql($SQL)) {
			$MYVARS->GET['eventid'] = $eventid;

            refresh_calendar_events($eventid);

            //Save any event template settings if necessary
            if ($template_id > 0) { //if a template is chosen
                //See if it should contain settings
                $settings = get_db_field("settings","events_templates","template_id='$template_id'");
                if (!empty($settings)) { //there are settings in this template
                    $settings = unserialize($settings);
                    foreach ($settings as $setting) { //save each setting with the default if no other is given
                        $current_setting = isset($MYVARS->GET[$setting['name']]) ? $MYVARS->GET[$setting['name']] : $setting['default'];
                        execute_db_sql("INSERT INTO settings (type,pageid,featureid,setting_name,setting,extra,defaultsetting) VALUES('events_template',false,false,'".$setting['name']."','".$current_setting."','".$eventid."','".$setting['default']."')");
                    }
                }
            }

            if ($request) { return $eventid; }

            //Log event added
    		log_entry("events", $eventid, "Event Added");
    		if (!$request) { echo "Event Added"; }
		} else {
            if (!$request) {
                //Log event error
                log_entry("events", 0, "Event could NOT be added");
                echo "Event could NOT be added <br /> <br /> $SQL";
            }
        }
	} else {
		$SQL = "UPDATE events SET
			         template_id='$template_id',name='$name',category='$category',location='$location',allowinpage='$allowinpage',
                     start_reg='$start_reg',stop_reg='$stop_reg',max_users='$max_users',
                     event_begin_date='$event_begin_date',event_begin_time='$event_begin_time',event_end_date='$event_end_date',event_end_time='$event_end_time',
                     sale_fee='$sale_fee',sale_end='$sale_end',contact='$contact',email='$email',phone='$phone',hard_limits='$hard_limits',soft_limits='$soft_limits',
                     siteviewable='$siteviewable',allday='$allday',byline='$byline',description='$description',paypal='$paypal',fee_min='$fee_min',fee_full='$fee_full',
                     payableto='$payableto',checksaddress='$checksaddress',confirmed='$confirmed',workers='$workers'
			WHERE eventid='" . dbescape($MYVARS->GET['eventid'])."'";

		if (execute_db_sql($SQL)) {

            refresh_calendar_events($MYVARS->GET['eventid']);

            //Delete old template settings info just in case things have changed
            execute_db_sql("DELETE FROM settings
                                WHERE type='events_template'
                                    AND extra='".$MYVARS->GET["eventid"]."'");
            //Save any event template settings if necessary
            if ($template_id > 0) { //if a template is chosen continue
                //See if it should contain settings
                $settings = get_db_field("settings","events_templates","template_id='$template_id'");
                if (!empty($settings)) { //there are settings in this template
                    $settings = unserialize($settings);
                    foreach ($settings as $setting) { //save each setting with the default if no other is given
                        $current_setting = isset($MYVARS->GET[$setting['name']]) ? $MYVARS->GET[$setting['name']] : $setting['default'];
                        execute_db_sql("INSERT INTO settings
                                            (type,pageid,featureid,setting_name,setting,extra,defaultsetting)
                                            VALUES('events_template',false,false,'".$setting['name']."','".$current_setting."','".$MYVARS->GET['eventid']."','".$setting['default']."')");
                    }
                }
            }

			//Log event update
			log_entry("events", dbescape($MYVARS->GET["eventid"]), "Event Edited");
			if (!$request) { echo "Event Edited"; }
		} else {
            if (!$request) {
                //Log event error
                log_entry("events", dbescape($MYVARS->GET["eventid"]), "Event could NOT be edited");
                echo "Event could NOT be Edited";
            }
        }
	}

	if ($pageid == $CFG->SITEID && !empty($MYVARS->GET['eventid'])) {
		confirm_event($pageid,$MYVARS->GET['eventid'],true);
	}
}

function get_end_time() {
global $CFG, $MYVARS;
	$starttime = dbescape($MYVARS->GET["starttime"]);
	$limit = dbescape($MYVARS->GET["limit"]);
	//event is not a multi day event and endtime is already set
	if ($limit == 1 && isset($MYVARS->GET["endtime"])) { echo get_possible_times("end_time", dbescape($MYVARS->GET["endtime"]), $starttime);
	}elseif ($limit == 1) { echo get_possible_times("end_time", null, $starttime);
	}elseif ($limit == 0 && isset($MYVARS->GET["endtime"])) { echo get_possible_times("end_time", dbescape($MYVARS->GET["endtime"]));
	}elseif ($limit == 0) { echo get_possible_times("end_time");}
}

function unique() {
global $CFG, $MYVARS;
	$eventid = dbescape($MYVARS->GET["eventid"]);
	$elementid = dbescape($MYVARS->GET["elementid"]);
	$value = dbescape($MYVARS->GET["value"]);

	if (is_unique("events_registrations_values", "elementid='$elementid' AND eventid='$eventid' AND value='$value'")) { echo "false";
	} else { echo "true";}
}

function unique_relay() {
global $CFG, $MYVARS;
	$table = dbescape($MYVARS->GET["table"]);
	$key = dbescape($MYVARS->GET["key"]);
	$value = dbescape($MYVARS->GET["value"]);

	if (is_unique($table, "$key='$value'")) { echo "false";
	} else { echo "true";}

}

function add_location_form() {
global $USER, $CFG, $MYVARS;
	$formtype = $MYVARS->GET["formtype"];
	switch($formtype) {
		case "new":
			echo new_location_form($MYVARS->GET["eventid"]);
			break;
		case "existing":
			echo location_list_form($MYVARS->GET["eventid"]);
			break;
	}
}

function copy_location() {
global $USER, $CFG, $MYVARS;
	$location = dbescape($MYVARS->GET["location"]);
	execute_db_sql("UPDATE events_locations SET userid = CONCAT(userid,'" . $USER->userid . ",') WHERE id='$location'");
	echo get_my_locations($USER->userid,$location,$MYVARS->GET["eventid"]);
}

function get_location_details() {
global $USER, $CFG, $MYVARS;
	$location = dbescape($MYVARS->GET["location"]);
	$row = get_db_row("SELECT * FROM events_locations WHERE id='$location'");

	$returnme = '
	<b>' . $row['location'] . '</b><br />
	' . $row['address_1'] . '<br />
	' . $row['address_2'] . '  ' . $row['zip'] . '<br />
	' . $row['phone'];
	echo $returnme;
}

function export_csv() {
global $MYVARS, $CFG, $USER;
	if (!isset($FILELIB)) { include_once ($CFG->dirroot . '/lib/filelib.php'); }
	date_default_timezone_set(date_default_timezone_get());
	$CSV = "Registration Date,Contact Email";
	$eventid = dbescape($MYVARS->GET["featureid"]);
	$event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='" . $event['template_id'] . "'");

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
            $reorder = array();
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

    if ($registrations = get_db_result("SELECT * FROM events_registrations
                                            WHERE eventid='$eventid'
                                                AND queue='0'
                                                AND verified='0'")) {
		$CSV .= "\nPENDING\n";
		while ($regid = fetch_row($registrations)) {
			$row = date("F j g:i a", $regid["date"]) . "," . $regid["email"];
			$values = get_db_result("SELECT * FROM events_registrations_values
                                        WHERE regid='" . $regid["regid"] . "'
                                        ORDER BY entryid");
            $reorder = array();
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

	if ($registrations = get_db_result("SELECT * FROM events_registrations
                                            WHERE eventid='$eventid'
                                                AND queue='1'")) {
		$CSV .= "\nQUEUE\n";
		while ($regid = fetch_row($registrations)) {
			$row = date("F j g:i a", $regid["date"]) . "," . $regid["email"];
			$values = get_db_result("SELECT * FROM events_registrations_values
                                        WHERE regid='" . $regid["regid"] . "'
                                        ORDER BY entryid");
            $reorder = array();
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
	echo get_download_link("export.csv",$CSV);
}

function show_template_settings() {
global $USER, $CFG, $MYVARS;
    echo get_template_settings($MYVARS->GET["templateid"],$MYVARS->GET["eventid"]);
}

function send_facebook_message() {
global $CFG, $MYVARS;
    require_once ($CFG->dirroot . '/features/events/facebook/facebook.php'); //'<path to facebook library, you uploaded>/facebook.php';
    $info = unserialize(base64_decode($MYVARS->GET["info"]));

    $config = array(
        'appId' => $info[2]->app_key,
        'secret' => $info[2]->app_secret,
    );

    $event = get_db_row("SELECT * FROM events WHERE eventid = '".$info[0]."'");
    $facebook = new Facebook($config);

    if ($user_id = $facebook->getUser()) {
      // We have a user ID, so probably a logged in user.
        try {
            $ret_obj = $facebook->api('/me/feed', 'POST',
                array(
                  'link' => $CFG->wwwroot,
                  'message' => $info[1] . ' has registered to attend '.$event["name"].'!'
             ));
            echo js_code_wrap('window.location = "//www.facebook.com";');
        } catch(FacebookApiException $e) {
            echo js_code_wrap('jwindow.close();');
        }
    }
}

function templatesearch() {
global $CFG, $MYVARS, $USER;
    $MYVARS->search_perpage = 8;
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
    $pagenum = isset($MYVARS->GET["pagenum"]) ? dbescape($MYVARS->GET["pagenum"]) : 0;
    $firstonpage = $MYVARS->search_perpage * $pagenum;
    $limit = " LIMIT $firstonpage," . $MYVARS->search_perpage;
    $words = explode(" ", $searchwords);
    $i = 0;
    while (isset($words[$i])) {
        $searchpart = "(name LIKE '%" . dbescape($words[$i]) . "%')";
        $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
        $i++;
    }

	$SQL = "SELECT * FROM events_templates
                WHERE (" . $searchstring . ")
                ORDER BY name";

    $total = get_db_count($SQL); //get the total for all pages returned.
    $SQL .= $limit; //Limit to one page of return.
    $count = $total > (($pagenum+1) * $MYVARS->search_perpage) ? $MYVARS->search_perpage : $total - (($pagenum) * $MYVARS->search_perpage); //get the amount returned...is it a full page of results?
    $results = get_db_result($SQL);
    $amountshown = $firstonpage + $MYVARS->search_perpage < $total ? $firstonpage + $MYVARS->search_perpage : $total;
    $prev = $pagenum > 0 ? '<a href="javascript: void(0);" onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                    ajaxapi(\'/features/events/events_ajax.php\',
                                                                            \'templatesearch\',
                                                                            \'&amp;pagenum=' . ($pagenum - 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
                                                                            function() {
                                                                                if (xmlHttp.readyState == 4) {
                                                                                    simple_display(\'searchcontainer\');
                                                                                    document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                                }
                                                                            },
                                                                            true
                                                                    );"
                            onmouseup="this.blur()">
                                <img src="' . $CFG->wwwroot . '/images/prev.gif" title="Previous Page" alt="Previous Page">
                            </a>' : "";
    $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
    $next = $firstonpage + $MYVARS->search_perpage < $total ? '<a onmouseup="this.blur()" href="javascript: void(0);" onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                                                                                ajaxapi(\'/features/events/events_ajax.php\',
                                                                                                                                        \'templatesearch\',
                                                                                                                                        \'&amp;pagenum=' . ($pagenum + 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
                                                                                                                                        function() {
                                                                                                                                            if (xmlHttp.readyState == 4) {
                                                                                                                                                simple_display(\'searchcontainer\');
                                                                                                                                                document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
                        <td style="width:20%;padding:5px;font-size:.75em;">
                            Type
                        </td>
                        <td style="text-align:right;padding:5px;font-size:.75em;">
                            Activated
                        </td>
                    </tr>';
        while ($template = fetch_row($results)) {
        	$export = "";
            $header = $header == "" ? '<table style="width:100%;"><tr><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><p>' : $header;

            $type = $template["folder"] == "none" ? "DB" : "EXTERNAL";

			if (!empty($template["activated"])) { // ACTIVE
                $status = '<a href="javascript: void(0)" onclick="ajaxapi(\'/features/events/events_ajax.php\',
                                                                          \'change_template_status\',
                                                                          \'&amp;status=1&amp;template_id='.$template["template_id"].'\',
                                                                          function() {
                                                                            document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                            ajaxapi(\'/features/events/events_ajax.php\',
                                                                                    \'templatesearch\',
                                                                                    \'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $MYVARS->GET["searchwords"] . '\'),
                                                                                    function() {
                                                                                        if (xmlHttp.readyState == 4) {
                                                                                            simple_display(\'searchcontainer\');
                                                                                            document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
                                                                          \'&amp;status=0&amp;template_id='.$template["template_id"].'\',
                                                                          function() {
                                                                            document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                            ajaxapi(\'/features/events/events_ajax.php\',
                                                                                    \'templatesearch\',
                                                                                    \'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $MYVARS->GET["searchwords"] . '\'),
                                                                                    function() {
                                                                                        if (xmlHttp.readyState == 4) {
                                                                                            simple_display(\'searchcontainer\');
                                                                                            document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                                        }
                                                                                    },
                                                                                    true
                                                                            );
                                                                          });">
                    <img src="' . $CFG->wwwroot . '/images/inactive.gif" title="Activate Template" alt="Activate Template" />
                </a>';
           	}

			$body .= '<tr style="height:30px;border:3px solid white;font-size:.9em;">
                        <td style="width:40%;padding:5px;font-size:.85em;white-space:nowrap;">
                            ' . $template["name"] . '
                        </td>
                        <td style="width:20%;padding:5px;font-size:.75em;">
                            ' . $type . '
                        </td>
                        <td style="text-align:right;padding:5px;">
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
global $USER, $CFG, $MYVARS;
    $template_id = $MYVARS->GET["template_id"];
    $status = $MYVARS->GET["status"];

    if (is_numeric($template_id)) {
        if ($status == "1") {
            execute_db_sql("UPDATE events_templates SET activated='0' WHERE template_id='$template_id'");
        } else if ($status == "0") {
            execute_db_sql("UPDATE events_templates SET activated='1' WHERE template_id='$template_id'");
        } else {
            // Failed
        }
    }
}

function change_bgcheck_status() {
global $USER, $CFG, $MYVARS;
    $pageid = $_COOKIE["pageid"];
    $staffid = $MYVARS->GET["staffid"];
    $date = strtotime($MYVARS->GET["bgcdate"]);

    if (is_numeric($pageid) && is_numeric($staffid) && is_numeric($date)) {
        execute_db_sql("UPDATE events_staff SET bgcheckpassdate='$date',bgcheckpass='1' WHERE staffid='$staffid' AND pageid='$pageid'");
        execute_db_sql("UPDATE events_staff_archive SET bgcheckpassdate='$date',bgcheckpass='1' WHERE staffid='$staffid' AND year='".date('Y')."' AND pageid='$pageid'");
    } else if (is_numeric($pageid) && is_numeric($staffid) && empty($MYVARS->GET["bgcdate"])) {
        execute_db_sql("UPDATE events_staff SET bgcheckpassdate=0,bgcheckpass='' WHERE staffid='$staffid' AND pageid='$pageid'");
        execute_db_sql("UPDATE events_staff_archive SET bgcheckpassdate=0,bgcheckpass='' WHERE staffid='$staffid' AND year='".date('Y')."' AND pageid='$pageid'");
    } else {
        echo "Failed";
    }
}

function sendstaffemails() {
global $CFG, $MYVARS, $USER;
    $stafflist = trim($MYVARS->GET["stafflist"]);
    $stafflist = preg_split("/\r\n|\n|\r/", $stafflist);
    $sendemails = filter_var($MYVARS->GET["sendemails"], FILTER_VALIDATE_BOOLEAN);;

    if (!empty($stafflist)) {
        $subject = "$CFG->sitename Staff Process";
        $protocol = get_protocol();
        $staffcomstatus = array();
        $staffapproved = array();

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
        <li>If you have completed a background check previously you can also send an email to ". $CFG->siteemail . " giving permission to renew your background check.</li>
        </ul><br /><br />";

        foreach ($stafflist as $email) {
            $name = "";
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) { // It is an email address, so let's get an email ready.
                $user = get_db_row("SELECT * FROM users WHERE LOWER(email) LIKE LOWER('%$email%')");
                $contact = new \stdClass;
                $contact->email = $email;
                $contact->fname = "";
                $contact->lname = "";

                if ($user) {
                    $name = $user["fname"] . " " . $user["lname"] . " ";
                    $m1 = "Hello $name!,";
                    $contact->fname = $user["fname"];
                    $contact->lname = $user["lname"];
                    $archive = get_db_row("SELECT * FROM events_staff WHERE userid='" . $user["userid"] . "' LIMIT 1");
                    $status = staff_status($archive);
                } else {
                    $m1 = "Hello future team member!,";
                    $status = staff_status(false, false);
                }

                if (!empty($status)) {
                    $m3 = "<strong>Current Status:</strong><br />" . print_status($status);

                    // Send email to the requester letting them know we received the request.
                    if (!empty($sendemails)) {
                        send_email($contact, $emailnotice, false, $subject, $m1.$m2.$m3);
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
    $MYVARS->search_perpage = 8;
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
    $pagenum = isset($MYVARS->GET["pagenum"]) ? dbescape($MYVARS->GET["pagenum"]) : 0;
    $firstonpage = $MYVARS->search_perpage * $pagenum;
    $limit = " LIMIT $firstonpage," . $MYVARS->search_perpage;
    $words = explode(" ", $searchwords);
    $i = 0;
    while (isset($words[$i])) {
        $searchpart = "(name LIKE '%" . dbescape($words[$i]) . "%')";
        $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
        $i++;
    }

    $pageid = $_COOKIE["pageid"];
	$SQL = "SELECT * FROM events_staff
                WHERE (" . $searchstring . ") AND pageid='$pageid'
                ORDER BY name";

    $total = get_db_count($SQL); //get the total for all pages returned.
    $SQL .= $limit; //Limit to one page of return.
    $count = $total > (($pagenum+1) * $MYVARS->search_perpage) ? $MYVARS->search_perpage : $total - (($pagenum) * $MYVARS->search_perpage); //get the amount returned...is it a full page of results?
    $results = get_db_result($SQL);
    $amountshown = $firstonpage + $MYVARS->search_perpage < $total ? $firstonpage + $MYVARS->search_perpage : $total;
    $prev = $pagenum > 0 ? '<a href="javascript: void(0);" onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                    ajaxapi(\'/features/events/events_ajax.php\',
                                                                            \'appsearch\',
                                                                            \'&amp;pagenum=' . ($pagenum - 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
                                                                            function() {
                                                                                if (xmlHttp.readyState == 4) {
                                                                                    simple_display(\'searchcontainer\');
                                                                                    document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                                }
                                                                            },
                                                                            true
                                                                    );"
                            onmouseup="this.blur()">
                                <img src="' . $CFG->wwwroot . '/images/prev.gif" title="Previous Page" alt="Previous Page">
                            </a>' : "";
    $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
    $next = $firstonpage + $MYVARS->search_perpage < $total ? '<a onmouseup="this.blur()" href="javascript: void(0);" onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                                                                                ajaxapi(\'/features/events/events_ajax.php\',
                                                                                                                                        \'appsearch\',
                                                                                                                                        \'&amp;pagenum=' . ($pagenum + 1) . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
                                                                                                                                        function() {
                                                                                                                                            if (xmlHttp.readyState == 4) {
                                                                                                                                                simple_display(\'searchcontainer\');
                                                                                                                                                document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
            $button = '<a href="javascript: void(0)" onclick="if ($(\'#bgcheckdate_'.$staff["staffid"].'\').prop(\'disabled\')) { $(\'#bgcheckdate_'.$staff["staffid"].'\').prop(\'disabled\', false); } else { ajaxapi(\'/features/events/events_ajax.php\',
                                                                      \'change_bgcheck_status\',
                                                                      \'&amp;bgcdate=\'+$(\'#bgcheckdate_'.$staff["staffid"].'\').val()+\'&amp;staffid='.$staff["staffid"].'\',
                                                                      function() {
                                                                        document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                                                        ajaxapi(\'/features/events/events_ajax.php\',
                                                                                \'appsearch\',
                                                                                \'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $MYVARS->GET["searchwords"] . '\'),
                                                                                function() {
                                                                                    if (xmlHttp.readyState == 4) {
                                                                                        simple_display(\'searchcontainer\');
                                                                                        document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                                                                    }
                                                                                },
                                                                                true
                                                                        );
                                                                      }); }">
                <img style="vertical-align: middle;" src="' . $CFG->wwwroot . '/images/manage.png" title="Edit Background Check Date" alt="Edit Background Check Date" />
            </a>';

            $applookup = 'document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                ajaxapi(\'/features/events/events_ajax.php\',
                                        \'show_staff_app\',
                                        \'&amp;staffid='.$staff["staffid"].'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $MYVARS->GET["searchwords"] . '\'),
                                        function() {
                                            if (xmlHttp.readyState == 4) {
                                                simple_display(\'searchcontainer\');
                                                document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                            }
                                        },
                                        true
                                );';
            $bgcheckdate = empty($staff["bgcheckpassdate"]) ? '' : date('m/d/Y', $staff["bgcheckpassdate"]);
            $status = staff_status($staff);
			$body .= '<tr style="height:30px;border:3px solid white;font-size:.9em;">
                        <td style="padding:5px;font-size:.85em;white-space:nowrap;">
                            <a href="javascript: void(0);" onclick="'.$applookup.'">'. $staff["name"] .'</a><br />
                            <span style="font-size:.9em">'.get_db_field("email","users",'userid="'.$staff["userid"].'"').'</span>
                        </td>
                        <td style="padding:5px;font-size:.75em;">
                            ' . print_status($status) . '
                        </td>
                        <td style="text-align:right;padding:5px;">
                            <input style="width: 100px;margin: 0;" type="text" disabled="disabled" id="bgcheckdate_'.$staff["staffid"].'" name="bgcheckdate_'.$staff["staffid"].'" value="'.$bgcheckdate.'" />' . $button . '
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
    $staffid = trim($MYVARS->GET["staffid"]);
    $searchwords = trim($MYVARS->GET["searchwords"]);
    $year = isset($MYVARS->GET["year"]) ? trim($MYVARS->GET["year"]) : date("Y");
    $pagenum = isset($MYVARS->GET["pagenum"]) ? dbescape($MYVARS->GET["pagenum"]) : 0;
    echo '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/styles/print.css">';
    echo '<a class="dontprint" title="Return to Staff Applications" href="javascript: void(0)"
                onclick="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                         ajaxapi(\'/features/events/events_ajax.php\',
                                 \'appsearch\',
                                 \'&amp;pagenum=' . $pagenum . '&amp;searchwords='. $searchwords . '\',
                                 function() {
                                    if (xmlHttp.readyState == 4) {
                                        simple_display(\'searchcontainer\');
                                        document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
                                        init_event_menu();
                                    }
                                 },
                                 true
                         );
                         return false;
                ">Return to Staff Applications
             </a>';
    $applookup = 'if ($(this).val() > 0) { document.getElementById(\'loading_overlay\').style.visibility=\'visible\';
                                    ajaxapi(\'/features/events/events_ajax.php\',
                                            \'show_staff_app\',
                                            \'&amp;staffid='.$staffid.'&amp;year=\'+$(this).val()+\'&amp;pagenum=' . $pagenum . '&amp;searchwords=\'+escape(\'' . $searchwords . '\'),
                                            function() {
                                                if (xmlHttp.readyState == 4) {
                                                    simple_display(\'searchcontainer\');
                                                    document.getElementById(\'loading_overlay\').style.visibility=\'hidden\';
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
        echo "<br />" . make_select_from_array("year",$values,"year","year",$year,"",'onchange="'.$applookup.'"',true) ."<br />";
    }

    if ($row = get_db_row("SELECT * FROM events_staff_archive WHERE staffid='$staffid' AND year='$year'")) {
	   echo '   <input style="float:right;" class="dontprint" type="button" value="Print" onclick="window.print();return false;" />
                <p style="font-size:.95em;" class="print">
                    '.staff_application_form($row, true).'
                </p>
          ';
    } else {
        echo "<h3>No Application on Record</h3>";
    }
}

function event_save_staffapp() {
global $CFG, $MYVARS, $USER;
    $userid = dbescape($USER->userid);
    $staffid = empty($MYVARS->GET["staffid"]) ? false : dbescape($MYVARS->GET["staffid"]);

    // Get pageid.
    $pageid = get_pageid();

    if (!empty($pageid)) {
        $name = dbescape(nameize($MYVARS->GET["name"]));
        $phone = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["phone"])), 2);
        $phone = dbescape($phone);
        $dateofbirth = dbescape(strtotime($MYVARS->GET["dateofbirth"]));
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
            $success = execute_db_sql($SQL);
            $subject = "Application Updated";
        } else {
            $SQL = "INSERT INTO events_staff
                        (userid,pageid,name,phone,dateofbirth,address,agerange,cocmember,congregation,priorwork,q1_1,q1_2,q1_3,q2_1,q2_2,q2_3,parentalconsent,parentalconsentsig,workerconsent,workerconsentsig,workerconsentdate,ref1name,ref1relationship,ref1phone,ref2name,ref2relationship,ref2phone,ref3name,ref3relationship,ref3phone,bgcheckpass,bgcheckpassdate)
                        VALUES('$userid','$pageid','$name','$phone','$dateofbirth','$address','$agerange','$cocmember','$congregation','$priorwork','$q1_1','$q1_2','$q1_3','$q2_1','$q2_2','$q2_3','$parentalconsent','$parentalconsentsig','$workerconsent','$workerconsentsig','$workerconsentdate','$ref1name','$ref1relationship','$ref1phone','$ref2name','$ref2relationship','$ref2phone','$ref3name','$ref3relationship','$ref3phone','',0)";
            $success = execute_db_sql($SQL);
            $subject = "Application Complete";
        }

        // Save the request
        if ($success) {
            $staffid = !empty($staffid) ? $staffid : $success;
            $staff = get_db_row("SELECT * FROM events_staff WHERE staffid='$staffid'");

            if (get_db_row("SELECT * FROM events_staff_archive WHERE staffid='$staffid' AND pageid='$pageid' AND year='".date("Y")."'")) {
                $SQL = "UPDATE events_staff_archive SET name='$name',phone='$phone',dateofbirth='$dateofbirth',address='$address',
                    agerange='$agerange',cocmember='$cocmember',congregation='$congregation',priorwork='$priorwork',
                    q1_1='$q1_1',q1_2='$q1_2',q1_3='$q1_3',q2_1='$q2_1',q2_2='$q2_2',q2_3='$q2_3',
                    parentalconsent='$parentalconsent',parentalconsentsig='$parentalconsentsig',
                    workerconsent='$workerconsent',workerconsentsig='$workerconsentsig',workerconsentdate='$workerconsentdate',
                    ref1name='$ref1name',ref1relationship='$ref1relationship',ref1phone='$ref1phone',
                    ref2name='$ref2name',ref2relationship='$ref2relationship',ref2phone='$ref2phone',
                    ref3name='$ref3name',ref3relationship='$ref3relationship',ref3phone='$ref3phone',
                    bgcheckpass='".$staff["bgcheckpass"]."',bgcheckpassdate='".$staff["bgcheckpassdate"]."'
                    WHERE staffid='$staffid' AND year='".date("Y")."' AND pageid='$pageid'";
                execute_db_sql($SQL);
            } else {
                $SQL = "INSERT INTO events_staff_archive
                            (staffid,userid,pageid,year,name,phone,dateofbirth,address,agerange,cocmember,congregation,priorwork,q1_1,q1_2,q1_3,q2_1,q2_2,q2_3,parentalconsent,parentalconsentsig,workerconsent,workerconsentsig,workerconsentdate,ref1name,ref1relationship,ref1phone,ref2name,ref2relationship,ref2phone,ref3name,ref3relationship,ref3phone,bgcheckpass,bgcheckpassdate)
                            VALUES('$staffid','$userid','$pageid','".date("Y")."','$name','$phone','$dateofbirth','$address','$agerange','$cocmember','$congregation','$priorwork','$q1_1','$q1_2','$q1_3','$q2_1','$q2_2','$q2_3','$parentalconsent','$parentalconsentsig','$workerconsent','$workerconsentsig','$workerconsentdate','$ref1name','$ref1relationship','$ref1phone','$ref2name','$ref2relationship','$ref2phone','$ref3name','$ref3relationship','$ref3phone','".$staff["bgcheckpass"]."',".$staff["bgcheckpassdate"].")";
                execute_db_sql($SQL);
            }

           	//Log
	          log_entry("event", $pageid, $subject);

            $emailnotice = new \stdClass;
            $emailnotice->email = $CFG->siteemail;
            $emailnotice->fname = $CFG->sitename;
            $emailnotice->lname = "";

            //Requesting email setup
            $name = stripslashes($name);
            $message = "<strong>$name has applied to work</strong>";

            //Send email to the requester letting them know we received the request
            send_email($emailnotice, $emailnotice, false, $subject, $message);

            $backgroundchecklink = '';
            $featureid = "*";
            if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        		    make_or_update_settings_array(default_settings("events", $pageid, $featureid));
        		    $settings = fetch_settings("events", $featureid, $pageid);
        	  }

            $linkurl = $settings->events->$featureid->bgcheck_url->setting;

            $status = empty($staff["bgcheckpass"]) ? false : (time()-$staff["bgcheckpassdate"] > ($settings->events->$featureid->bgcheck_years->setting * 365 * 24 * 60 * 60) ? false : true);

            $eighteen = 18 * 365 * 24 * 60 * 60; // 18 years in seconds
            $backgroundchecklink = ((time() - $dateofbirth) < $eighteen) || ($status || empty($linkurl)) ? '' : '
               <br /><br />
               If you have not already done so, please complete a background check.<br />
               <h2><a href="'.$linkurl.'">Submit a background check</a></h2>';

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
    $pageid = dbescape($MYVARS->GET["pageid"]);
	  if (!isset($FILELIB)) { include_once ($CFG->dirroot . '/lib/filelib.php'); }
    $fields = array("STATUS",
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
                    "Background Check Date");
    $CSV = '"' . implode('","', $fields). "\"\n";

    $SQL = "SELECT name,userid,phone,dateofbirth,address,agerange,cocmember,congregation,
                   priorwork,q1_1,q1_2,q1_3,q2_1,q2_2,q2_3,parentalconsent,
                   parentalconsentsig,workerconsent,workerconsentsig,workerconsentdate,
                   ref1name,ref1relationship,ref1phone,ref2name,ref2relationship,ref2phone,
                   ref3name,ref3relationship,ref3phone,bgcheckpass,bgcheckpassdate
                   FROM events_staff_archive WHERE pageid='$pageid' AND year='$year' ORDER BY name";
    if ($applications = get_db_result($SQL)) {
		    while ($app = fetch_row($applications)) {
            $status = staff_status($app);
            $status = empty($status) ? array("APPROVED") : $status;
            $email = get_db_field("email", "users", "userid='".$app["userid"]."'");
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
            $CSV .= '"'.implode(" | " , array_column($status, 'full')).
                    '","'.$app["name"].
                    '","'.$email.
                    '","'.date('m/d/Y',$app["dateofbirth"]).
                    '","'.$app["phone"].
                    '","'.$app["address"].
                    '","'.$app["agerange"].
                    '","'.$app["cocmember"].
                    '","'.$app["congregation"].
                    '","'.$app["priorwork"].
                    '","'.$app["q1_1"].
                    '","'.$app["q1_2"].
                    '","'.$app["q1_3"].
                    '","'.$app["q2_1"].
                    '","'.$app["q2_2"].
                    '","'.$app["q2_3"].
                    '","'.$app["parentalconsent"].
                    '","'.$app["parentalconsentsig"].
                    '","'.$app["workerconsent"].
                    '","'.$app["workerconsentsig"].
                    '","'.date('m/d/Y',$app["workerconsentdate"]).
                    '","'.$app["ref1name"].
                    '","'.$app["ref1phone"].
                    '","'.$app["ref1relationship"].
                    '","'.$app["ref2name"].
                    '","'.$app["ref2phone"].
                    '","'.$app["ref2relationship"].
                    '","'.$app["ref3name"].
                    '","'.$app["ref1phone"].
                    '","'.$app["ref3relationship"].
                    '","'.$app["bgcheckpass"].
                    '","'.(!empty($app["bgcheckpassdate"]) ? date('m/d/Y',$app["bgcheckpassdate"]) : '').
                    '"' . "\n";
          }
    }
	echo get_download_link("staffapps($year).csv",$CSV);
}
?>
