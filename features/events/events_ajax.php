<?php
/***************************************************************************
* events_ajax.php - Events backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/02/2025
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
    $startdate = clean_myvar_req("startdate", "string");
    $enddate = clean_myvar_opt("enddate", "string", false);

    if ($featureid) {
        $pageid = get_feature_pageid("events", $featureid);

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
    $startdate = clean_myvar_req("startdate", "string");
    $enddate = clean_myvar_opt("enddate", "string", $startdate);
    $participants = clean_myvar_req("participants", "int");
    $description = clean_myvar_req("description", "html");

    $return = $error = "";
    try {
        $startdate = strtotime($startdate);
        $enddate = strtotime($enddate);
        $protocol = get_protocol();

        $SQL = "INSERT INTO events_requests
                        (featureid,contact_name,contact_email,contact_phone,event_name,startdate,enddate,participants,description,votes_for,votes_against)
                        VALUES('$featureid','$contact_name','$contact_email','$contact_phone','$event_name','$startdate','$enddate','$participants','$description', 0, 0)";
        //Save the request
        if (!$reqid = execute_db_sql($SQL)) {
            throw new Exception("Could not save request");
        }

        $from = (object)[
            "email" => $CFG->siteemail,
            "fname" => $CFG->sitename,
            "lname" => "",
        ];

        //Requesting email setup
        $contact = (object)[
            "email" => $contact_email,
            "fname" => count(explode(" ", $contact_name)) > 1 ? explode(" ", $contact_name)[0] : $contact_name,
            "lname" => count(explode(" ", $contact_name)) > 1 ? explode(" ", $contact_name)[1] : "",
        ];

        $request_info = get_request_info($reqid);
        $subject = $CFG->sitename . " Event Request Received";
        $event_name = stripslashes($event_name);
        $message = '
            <strong>Thank you for submitting a request for us to host your event: ' . $event_name . '.</strong>
            <br /><br />
            We will look over the details that you provided and respond shortly.
            <br />
            Through the approval process you may receive additional questions about your event.
            <br /><br />
            ' . $request_info;

        //Send email to the requester letting them know we received the request
        send_email($contact, $from, $subject, $message);

        if (isset($featureid)) {
            //Get feature request settings
            $pageid = get_feature_pageid("events", $featureid);
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
                $message = '
                    <p>
                        A new event request has been submitted. Your input is required to approve the event. This email contains specific links designed for you to be able to submit and view questions as well as vote for this event\'s approval.
                    </p>
                    <p>
                        Do <strong>not</strong> delete this email. <strong>Please review the following event request.</strong>
                    </p>
                    <br />
                    <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_question&reqid=' . $reqid . '&voteid=' . $voteid . '">
                        View / Ask Questions
                    </a>
                    <br />
                    <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_vote&reqid=' . $reqid . '&approve=1&voteid=' . $voteid . '">
                        Approve
                    </a>
                    <br />
                    <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_vote&reqid=' . $reqid . '&approve=0&voteid=' . $voteid . '">
                        Deny
                    </a>
                    <br /><br />
                    ' . $request_info;

                $thisuser = (object)[
                    "email" => $emailuser,
                    "fname" => "",
                    "lname" => "",
                ];
                send_email($thisuser, $from, $subject, $message);
            }
        }
        $return = '
            <div style="width:100%;text-align:center;">
                <strong>Your request has been sent.</strong>
            </div>
            <div>
                <br />
                You should receive an email shortly informing you of the event approval process.<br />
                <br />
                Thank you.
            </div>';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
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

// Request question form
function request_question() {
global $CFG, $PAGE;
    $reqid = clean_myvar_req("reqid", "int");
    $voteid = clean_myvar_req("voteid", "string");

    // Make sure request exists and get the featureid
    if (!$featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
        throw new Exception(getlang("old_request", "/features/events"));
    }

    if (!$pageid = get_feature_pageid("events", $featureid)) {
        throw new Exception(getlang("old_request", "/features/events"));
    }

    if (!valid_voter($pageid, $featureid, $voteid)) {
        throw new Exception(getlang("generic_permissions"));
    }

    $PAGE->title = $CFG->sitename . " - Event Request"; // Title of page
    $PAGE->name = $PAGE->title; // Title of page
    $PAGE->description = "Event Request form for event request discussions and approval/denial"; // Description of page
    $PAGE->themeid = get_page_themeid($pageid);

    // Start Page
    require($CFG->dirroot . '/header.html');

    echo request_questions_form($reqid, $voteid, $featureid, $pageid);

    // End Page
    require($CFG->dirroot . '/footer.html');
}

// Save question
function request_question_send() {
global $CFG, $MYVARS;
    $reqid = clean_myvar_req("reqid", "int");
    $voteid = clean_myvar_req("voteid", "string");
    $question = clean_myvar_req("editor1", "string");
    $question = strip_tags(trim($question, " \n\r\t"),'<a><em><u><img><br>');

    // Make sure request exists and get the featureid
    if (!$featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
        throw new Exception(getlang("old_request", "/features/events"));
    }

    // Get feature request settings
    $pageid = get_feature_pageid("events", $featureid);
    if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        save_batch_settings(default_settings("events", $pageid, $featureid));
        $settings = fetch_settings("events", $featureid, $pageid);
    }
    $locationid = $settings->events->$featureid->allowrequests->setting;
    $emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);

    if (!valid_voter($pageid, $featureid, $voteid)) {
        throw new Exception(getlang("generic_permissions"));
    }

    // Allowed to ask questions
    $SQL = fetch_template("dbsql/events.sql", "insert_events_requests_questions", "events");
    if (!$qid = execute_db_sql($SQL, ["reqid" => $reqid, "question" => $question, "qtime" => get_timestamp()])) {
        throw new Exception(getlang("generic_error"));
    }

    if (!$request = get_db_row("SELECT * FROM events_requests WHERE reqid = ||reqid||", ["reqid" => $reqid])) {
        throw new Exception(getlang("old_request", "/features/events"));
    }

    $protocol = get_protocol();

    $from = (object) [
        "email" => $CFG->siteemail,
        "fname" => $CFG->sitename,
        "lname" => "",
    ];

    $request_info = get_request_info($reqid);

    // Question is saved.  Now send it to everyone.
    $subject = $CFG->sitename . " Event Request Question";
    foreach ($emaillist as $emailuser) {
        //Each message must has an md5'd email address so I know if a person has voted or not
        $voteid = md5($emailuser);
        $message = '
            <strong>A question has been asked about the event (' . $request["event_name"] . ').</strong>
            <br /><br />
            ' . $question . '
            <br /><br />
            <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_question&reqid=' . $reqid . '&voteid=' . $voteid . '">
                View / Ask Questions
            </a>
            <br />
            <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_vote&reqid=' . $reqid . '&approve=1&voteid=' . $voteid . '">
                Approve
            </a>
            <br />
            <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_vote&reqid=' . $reqid . '&approve=0&voteid=' . $voteid . '">
                Deny
            </a>
            <br /><br />
            ' . $request_info;

        $thisuser = (object)[
            "email" => $emailuser,
            "fname" => "",
            "lname" => "",
        ];
        send_email($thisuser, $from, $subject, $message);
    }

    $subject = $CFG->sitename . " Event Request Answers Needed";
    $message .= '
        <br /><br />
        <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_answer&qid=' . $qid . '&reqid=' . $reqid . '">
            Answer Question
        </a>';

    // Requesting email setup
    $contact_email = clean_param_req($request, "contact_email", "string");
    $contact_name = clean_param_req($request, "contact_name", "string");
    $contact = (object)[
        "email" => $contact_email,
        "fname" => count(explode(" ", $contact_name)) > 1 ? explode(" ", $contact_name)[0] : $contact_name,
        "lname" => count(explode(" ", $contact_name)) > 1 ? explode(" ", $contact_name)[1] : "",
    ];

    //Send email to the requester letting them know a question has been raised
    send_email($contact, $from, $subject, $message);

    request_question();
}

// Request question form
function request_answer($qid = false) {
global $CFG, $PAGE;
    $reqid = clean_myvar_req("reqid", "int");
    $qid = $qid === false ? clean_myvar_opt("qid", "int", 0) : $qid;

    // Make sure request exists and get the featureid
    if (!$featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
        throw new Exception(getlang("old_request", "/features/events"));
    }

    if (!$pageid = get_feature_pageid("events", $featureid)) {
        throw new Exception(getlang("old_request", "/features/events"));
    }

    $PAGE->title = $CFG->sitename . " - Event Request"; // Title of page
    $PAGE->name = $PAGE->title; // Title of page
    $PAGE->description = "Event Request form for event request discussions and approval/denial"; // Description of page
    $PAGE->themeid = get_page_themeid($pageid);

    // Start Page
    require($CFG->dirroot . '/header.html');

    echo request_answers_form($reqid, $qid, $featureid, $pageid);

    // End Page
    require($CFG->dirroot . '/footer.html');
}

//Save question
function request_answer_send() {
global $CFG;
    $reqid = clean_myvar_req("reqid", "int");
    $qid = clean_myvar_req("qid", "int");
    $answer = clean_myvar_req("editor1", "string");
    $answer = strip_tags(trim($answer, " \n\r\t"),'<a><em><u><img><br>');

    // Make sure request exists and get the featureid
    if (!$featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
        throw new Exception(getlang("old_request", "/features/events"));
    }

    if (!$pageid = get_feature_pageid("events", $featureid)) {
        throw new Exception(getlang("old_request", "/features/events"));
    }

    if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        save_batch_settings(default_settings("events", $pageid, $featureid));
        $settings = fetch_settings("events", $featureid, $pageid);
    }
    $request = get_db_row("SELECT * FROM events_requests WHERE reqid = ||reqid||", ["reqid" => $reqid]);

    $SQL = "UPDATE events_requests_questions set answer = ||answer||, answer_time = ||answer_time|| WHERE id = ||id||";
    if (!execute_db_sql($SQL, ["answer" => $answer, "answer_time" => get_timestamp(), "id" => $qid])) {
        throw new Exception(getlang("generic_error"));
    }

    $protocol = get_protocol();

    $from = (object) [
        "email" => $CFG->siteemail,
        "fname" => $CFG->sitename,
        "lname" => "",
    ];

    // Question is saved.  Now send it to everyone.
    $subject = $CFG->sitename . " Event Request Question Answered";
    $question = get_db_field("question", "events_requests_questions", "id = ||id||", ["id" => $qid]);
    $answer = get_db_field("answer", "events_requests_questions", "id = ||id||", ["id" => $qid]);

    $emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);

    foreach ($emaillist as $emailuser) {
        //Each message must has an md5'd email address so I know if a person has voted or not
        $voteid = md5($emailuser);
        $message = '
            <h2>Event Request Question Answered</h2>
            <strong>Requested Event:</strong> ' . $request["event_name"] . '
            <br /><br />
            <strong>An answer to the following question has been received.</strong>
            <br /><br />
            <strong>Question:</strong> ' . $question . '
            <br /><br />
            <strong>Answer:</strong> ' . $answer . '
            <br /><br />
            <strong>Next Step:</strong>
            <br />
            <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_question&reqid=' . $reqid . '&voteid=' . $voteid . '">
                View / Ask Questions
            </a>
            <br />
            <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_vote&reqid=' . $reqid . '&approve=1&voteid=' . $voteid . '">
                Approve
            </a>
            <br />
            <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_vote&reqid=' . $reqid . '&approve=0&voteid=' . $voteid . '">
                Deny
            </a>';

        $thisuser = (object)[
            "email" => $emailuser,
            "fname" => "",
            "lname" => "",
        ];
        send_email($thisuser, $from, $subject, $message);
    }

    request_answer(0);
}

function confirm_event_relay() {
    $eventid = clean_myvar_req("eventid", "int");
    $confirm = clean_myvar_req("confirm", "int");
    $error = "";
    if (!confirm_or_deny_event($eventid, $confirm)) {
        $error = getlang("failed_confirm", "/features/events");
    }
    ajax_return("", $error);
}

function delete_events_relay() {
    $eventid = clean_myvar_req("eventid", "int");
    $error = "";
    try {
        if (!delete_event($eventid)) {
            throw new Exception("Could not delete event");
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    ajax_return("", $error);
}

// Function is called on email votes
function request_vote() {
global $CFG, $PAGE, $MYVARS;
    $reqid = clean_myvar_req("reqid", "int");
    $newvote = clean_myvar_opt("approve", "int", 0);
    $voteid = clean_myvar_req("voteid", "string");

    try {
        start_db_transaction();
        $stance = $newvote ? "approve" : "deny";

        // Make sure request exists and get the featureid
        if (!$featureid = get_db_field("featureid", "events_requests", "reqid = ||reqid||", ["reqid" => $reqid])) {
            throw new Exception(getlang("old_request", "/features/events"));
        }

        if (!$pageid = get_feature_pageid("events", $featureid)) {
            throw new Exception(getlang("old_request", "/features/events"));
        }

        $PAGE->title = $CFG->sitename . " - Event Request has been voted on"; // Title of page
        $PAGE->name = $PAGE->title; // Title of page
        $PAGE->description = "Event Request form for event request discussions and approval/denial"; // Description of page
        $PAGE->themeid = get_page_themeid($pageid);

        // Start Page
        require($CFG->dirroot . '/header.html');

        if (!$settings = fetch_settings("events", $featureid, $pageid)) {
            save_batch_settings(default_settings("events", $pageid, $featureid));
            $settings = fetch_settings("events", $featureid, $pageid);
        }
        $locationid = $settings->events->$featureid->allowrequests->setting;
        $approveneeded = $settings->events->$featureid->requestapprovalvotes->setting;
        $denyneeded = $settings->events->$featureid->requestdenyvotes->setting;
        $emaillist = prepare_email_list($settings->events->$featureid->emaillistconfirm->setting);

        if (!valid_voter($pageid, $featureid, $voteid)) {
            throw new Exception(getlang("generic_permissions"));
        }

        // See if the person has already voted
        if ($record = request_has_already_voted($reqid, $voteid)) { // Person has already voted
            $voted = explode("::", $record["voted"]);
            foreach ($voted as $vote) {
                $vote = trim($vote, ":");
                $entry = explode(";", $vote);
                if ($entry[0] == $voteid) { //This is the vote that needs removed
                    if ($entry[1] == $newvote) { //Same vote, nothing else needs done
                        $response = "You have already voted to $stance this event";
                    } else { //They have changed their vote.
                        $oldvote = $entry[1];
                        // Remove old vote
                        $p = ["reqid" => $reqid, "voteid" => $voteid, "newvote" => ":$voteid;$newvote:", "oldvote" => ":$voteid;$oldvote:"];

                        // Update vote record.
                        execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_change_vote", "events"), $p);

                        if ($newvote) { // Remove 1 from against and add 1 to for
                            execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_recalculate", "events", ["approve" => true]), ["reqid" => $reqid]);
                        } else { // Remove 1 from for and add 1 to against
                            execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_recalculate", "events", ["approve" => false]), ["reqid" => $reqid]);
                        }
                        $response = "You have changed your vote to $stance";
                    }
                }
            }
        } else { // New vote
            // Update vote record.
            $p = ["reqid" => $reqid, "voteid" => $voteid, "newvote" => ":$voteid;$newvote:"];
            execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_new_vote", "events"), $p);

            if ($newvote == "1") { // Add 1 to the for column
                execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_calculate", "events", ["approve" => true]), ["reqid" => $reqid]);
            } else { // Add 1 to the against column
                execute_db_sql(fetch_template("dbsql/events.sql", "events_requests_calculate", "events", ["approve" => false]), ["reqid" => $reqid]);
            }
            $response = "You have voted to $stance this event";
        }

        // See if the voting on this request is finished
        if (!$request = get_db_row(fetch_template("dbsql/events.sql", "get_events_requests", "events"), ["reqid" => $reqid])) {
            throw new Exception(getlang("old_request", "/features/events"));
        }

        $votes_for = clean_param_opt($request, "votes_for", "int", 0);
        $votes_against = clean_param_opt($request, "votes_against", "int", 0);

        $from = (object) [
            "email" => $CFG->siteemail,
            "fname" => $CFG->sitename,
            "lname" => "",
        ];

        //Requesting email setup
        $contact_email = clean_param_req($request, "contact_email", "string");
        $contact_name = clean_param_req($request, "contact_name", "string");
        $contact = (object)[
            "email" => $contact_email,
            "fname" => count(explode(" ", $contact_name)) > 1 ? explode(" ", $contact_name)[0] : $contact_name,
            "lname" => count(explode(" ", $contact_name)) > 1 ? explode(" ", $contact_name)[1] : "",
        ];

        $request_info = get_request_info($reqid);
        $subject = $CFG->sitename . " Event Request Received";
        $message = '
            <strong>Thank you for submitting a request for us to host your event: ' . $request["event_name"] . '</strong>
            <br /><br />
            We will look over the details that you provided and respond shortly.
            <br />
            Through the approval process you may receive additional questions about your event.
            <br /><br />
            ' . $request_info;

        if ($votes_for == $approveneeded && $votes_against < $denyneeded) {
            // Make event
            if (!$eventid = convert_to_event($request, $locationid, $pageid, $featureid)) {
                throw new Exception(getlang("invalid_event_conversion", "/features/events"));
            }

            // Confirm event
            $siteviewable = $pageid == $CFG->SITEID ? true : false; //if feature is on site, then yes...otherwise no.
            if (!confirm_or_deny_event($eventid, $siteviewable)) {
                throw new Exception(getlang("failed_confirm", "/features/events"));
            }

            // Delete event request
            execute_db_sql(fetch_template("dbsql/events.sql", "delete_events_requests", "events"), ["reqid" => $reqid]);
            execute_db_sql(fetch_template("dbsql/events.sql", "delete_events_requests_questions", "events"), ["reqid" => $reqid]);

            // Event Approved
            $subject = $CFG->sitename . " Event Request Approved";
            $message = '
                <strong>The event ' . stripslashes($request["event_name"]) . ' has been approved by a vote of ' . $votes_for . ' to ' . $votes_against . '.</strong>
                <br /><br />
                ' . $request_info;

            //Send email to the requester letting them know we denied the request
            send_email($contact, $from, $subject, $message);

            foreach ($emaillist as $emailuser) {
                //Let everyone know the event has been denied
                $thisuser = (object) [
                    "email" => $emailuser,
                    "fname" => "",
                    "lname" => "",
                ];
                send_email($thisuser, $from, $subject, $message);
            }
        } elseif ($votes_for < $approveneeded && $votes_against == $denyneeded) {
            // Delete event request
            execute_db_sql(fetch_template("dbsql/events.sql", "delete_events_requests", "events"), ["reqid" => $reqid]);
            execute_db_sql(fetch_template("dbsql/events.sql", "delete_events_requests_questions", "events"), ["reqid" => $reqid]);

            // Event Denied
            $subject = $CFG->sitename . " Event Request Denied";
            $message = '
                <strong>The event: ' . stripslashes($request["event_name"]) . ' has been denied by a vote of ' . $votes_for . ' to ' . $votes_against . '.</strong>
                <br /><br />
                ' . $request_info;

            //Send email to the requester letting them know we denied the request
            send_email($contact, $from, $subject, $message);

            foreach ($emaillist as $emailuser) {
                //Let everyone know the event has been denied
                $thisuser = (object) [
                    "email" => $emailuser,
                    "fname" => "",
                    "lname" => "",
                ];
                send_email($thisuser, $from, $subject, $message);
            }
        } elseif ($request["votes_for"] > $approveneeded) {
            $response = "Event has already been approved";
        }

        commit_db_transaction();

        $middlecontents = '
            <div id="question_form" style="padding: 20px;text-align: center;">
                <h3>' . $response . '</h3>
                <br />
                <strong>Thank you for your feedback.</strong>
            </div>';

        $return = fill_template("tmp/index.template", "simplelayout_template", false, ["mainmast" => page_masthead(true, true), "middlecontents" => $middlecontents]);
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $return = $e->getMessage();
    }

    echo $return;

    // End Page
    require($CFG->dirroot . '/footer.html');
}

function convert_to_event($request, $location, $pageid, $featureid) {
global $CFG, $MYVARS;
    $featureid = clean_var_req($featureid, "int");
    $pageid = clean_var_opt($pageid, "int", get_pageid());

    $MYVARS->GET["pageid"] = $pageid;
    $MYVARS->GET["featureid"] = $featureid;

    $MYVARS->GET["event_name"] = $request["event_name"];
    $MYVARS->GET["byline"] = "w/ " . $request["contact_name"];
    $MYVARS->GET["contact"] = $request["contact_name"];
    $MYVARS->GET["email"] = $request["contact_email"];
    $MYVARS->GET["phone"] = $request["contact_phone"];
    $MYVARS->GET["location"] = $location;

    $MYVARS->GET["category"] = "1"; //Placed in general category...can be changed later
    $MYVARS->GET["siteviewable"] = $pageid == $CFG->SITEID ? "1" : "0"; // if feature is on site, then yes...otherwise no.

    $MYVARS->GET["description"] = $request["description"]; //event description

    $MYVARS->GET["multiday"] = $request["startdate"] == $request["enddate"] ? "0" : "1";

    $MYVARS->GET["event_begin_date"] = date(DATE_RFC822, $request["startdate"]);
    $MYVARS->GET["event_end_date"] = $MYVARS->GET["multiday"] == "1" ? date(DATE_RFC822, $request["enddate"]) : $MYVARS->GET["event_begin_date"];
    $MYVARS->GET["allday"] = "1";
    $MYVARS->GET["reg"] = "0";
    $MYVARS->GET["fee"] = "0";

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
global $CFG;
    $regid = clean_myvar_opt("regid", "int", false);
    $online_only = clean_myvar_opt("online_only", "bool", false);
    $eventid = clean_myvar_req("eventid", "int");
    $event = get_event($eventid);
    $template_id = $event["template_id"];
    $eventname = $event["name"];

    $printarea = '<link rel="stylesheet" type="text/css" media="print" href="' . $CFG->wwwroot . '/styles/print.css">';
    $returnme = '';
    $returnme .= get_back_to_registrations_link($eventid);

    if ($regid) { //Print form for 1 registration
        $printarea .= printable_registration($regid, $eventid, $template_id);
    } else { //Batch print all registrations
        if ($registrations = get_db_result(get_registration_sort_sql($eventid, $online_only))) {
            while ($registration = fetch_row($registrations)) {
                $printarea .= '<div class="pagestart print">';
                $printarea .= printable_registration($registration["regid"], $eventid, $template_id);
                $printarea .= '</div>';
            }
        }
    }

    $returnme .= '
        <form>
            <button class="dontprint" onclick="window.print();return false;">
                Print
            </button>
            ' . $printarea . '
        </form>';
    ajax_return($returnme);
}

function get_registration_sort_sql($eventid, $online_only=false) {
    if ($online_only) { $online_only = "AND e.manual = 0"; }
    $sortSQL = "SELECT e.*"; $orderby = "";

    $SQL = fetch_template("dbsql/events.sql", "get_events_with_same_template", "events");
    $sort_info = get_db_row($SQL, ["eventid" => $eventid]);

    if ($sort_info["folder"] == "none") { //form template
        $sort_elements=explode(",", $sort_info["orderbyfield"]);$i=0;
        while (isset($sort_elements[$i])) {
            $sortSQL .= ",(SELECT value FROM events_registrations_values
                            WHERE elementid='$sort_elements[$i]' AND regid=v.regid) as val$i";
            $orderby .= $orderby == "" ? "val$i" : ",val$i";
            $i++;
        }
        $sortSQL .= " FROM events_registrations as e
                    JOIN events_registrations_values as v ON (e.regid=v.regid)
                    WHERE e.eventid='$eventid' $online_only
                    GROUP BY regid
                    ORDER BY val0 LIKE '%Reserved%' DESC, $orderby";
    } else { //custom template
        $sort_elements=explode(",", $sort_info["orderbyfield"]);$i=0;
        while (isset($sort_elements[$i])) {
            $sortSQL .= ",(SELECT value FROM events_registrations_values
                            WHERE elementname='$sort_elements[$i]' AND regid=v.regid) as val$i";
            $orderby .= $orderby == "" ? "val$i" : ",val$i";
            $i++;
        }
        $sortSQL .= " FROM events_registrations as e
                    JOIN events_registrations_values as v ON (e.regid=v.regid)
                    WHERE e.eventid='$eventid' $online_only
                    GROUP BY regid
                    ORDER BY val0 LIKE '%Reserved%' DESC, $orderby";
    }

return $sortSQL;
}

function printable_registration($regid, $eventid, $template_id) {
    global $CFG;
    $returnme = '<div>';

    if (!defined('FORMLIB')) { include_once($CFG->dirroot . '/lib/formlib.php'); }
    $template = get_event_template($template_id);
    $template_forms = get_template_formlist($template_id);

    if ($template["folder"] == "none") {
        if ($template_forms) {
            while ($form_element = fetch_row($template_forms)) {
                $SQL = fetch_template("dbsql/events.sql", "get_registration_value_by_id", "events");
                if ($form_element["type"] == "payment") {
                    $elements = get_db_result($SQL, ["regid" => $regid, "elementid" => $form_element["elementid"]]);
                    if ($elements) {
                        $i = 0; $values_display = explode(",", $form_element["display"]);
                        while ($element = fetch_row($elements)) {
                            $returnme .= get_printable_registration_row($values_display[$i], $element["value"]);
                            $i++;
                        }
                    }
                } else {
                    $element = get_db_row($SQL, ["regid" => $regid, "elementid" => $form_element["elementid"]]);
                    $returnme .= get_printable_registration_row($form_element["display"], $element["value"]);
                }
            }
        }
    } else {
        $i=0;
        foreach($template_forms as $form) {
            $field = get_template_formlist_element_array($template, $form);
            if (isset($field["displayonly"])) {
                continue;
            }

            $SQL = fetch_template("dbsql/events.sql", "get_registration_value", "events");
            $value = get_db_row($SQL, ["regid" => $regid, "elementname" => strtolower($field["name"])]);
            $returnme .= get_printable_registration_row($field["title"], $value["value"]);
        }
    }

    // Payment info
    $SQL = fetch_template("dbsql/events.sql", "get_registration_value", "events");
    if ($values = get_db_result($SQL, ["regid" => $regid, "elementname" => 'tx'])) {
        while ($value = fetch_row($values)) {
            $params = unserialize($value["value"]);
            $returnme .= get_printable_registration_row("Paypal TX", "$" . $params["amount"] . " on " . date("m/d/Y", $params["date"]) . " - " . $params["txid"]);
            $i++;
        }
    }

    $returnme .= "</div>";

    return $returnme;
}

function get_printable_registration_row($title, $value) {
    return '
        <br />
        <div style="line-height: 1.5em;display:inline-block;width:200px;margin-left: 50px;vertical-align: top;">
            <strong>' . $title . '</strong>
        </div>
        <div style="display:inline-block;padding-left:5px;line-height: 1.5em;">
            ' . stripslashes($value) . '
        </div>';
}

function save_reg_changes() {
global $CFG, $MYVARS, $USER;
    $eventid = clean_myvar_opt("eventid", "int", false);
    $regid = clean_myvar_opt("regid", "int", false);
    $reg_eventid = clean_myvar_opt("reg_eventid", "int", false);

    $sendemail = false;

    $error = "";
    try {
        start_db_transaction();

        // Changing core registration values
        if ($reg_eventid && get_event($reg_eventid)) {
            $reg_email = clean_myvar_opt("reg_email", "string", "");
            $reg_code = clean_myvar_opt("reg_code", "string", "");

            execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_event_info", "events"), ["regid" => $regid, "eventid" => $reg_eventid, "email" => $reg_email, "code" => $reg_code]);
            execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_event", "events"), ["regid" => $regid, "eventid" => $reg_eventid]);
        }

        // Create temp table
        if (execute_db_sql(fetch_template("dbsql/events.sql", "reg_copy_create_temptable", "events"))) {
            if ($entries = get_db_result(fetch_template("dbsql/events.sql", "get_registration_values", "events"), ["regid" => $regid])) {
                $SQL = ''; $params = [];
                while ($entry = fetch_row($entries)) {
                    if (clean_myvar_opt($entry["entryid"], "string", false)) {
                        $params["entryid_" . $entry["entryid"]] =  $entry["entryid"];
                        $params["entryvalue_" . $entry["entryid"]] =  clean_myvar_opt($entry["entryid"], "string", "");
                        $SQL .= $SQL == "" ? "" : ",";
                        $SQL .= "(||entryid_" . $entry["entryid"] . "||, ||entryvalue_" . $entry["entryid"] . "||)";
                    }
                }
                $SQL = "INSERT INTO temp_updates (entryid, newvalue) VALUES" . $SQL;
                if (execute_db_sql($SQL, $params)) {
                    if (execute_db_sql(fetch_template("dbsql/events.sql", "reg_update_from_temptable", "events"), ["regid" => $regid])) {
                        // check paid status
                        $paid = (float) get_reg_paid($regid);
                        $payment_method = get_reg_paymethod($regid);

                        $minimum = get_db_field("fee_min", "events", "eventid = ||eventid||", ["eventid" => $eventid]);
                        $verified = get_db_field("verified", "events_registrations", "regid = ||regid||", ["regid" => $regid]);
                        if ($paid >= $minimum) {
                            if (empty($verified)) { // Not already verified.
                                // If payment is made, it is no longer in queue.
                                execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_status", "events"), ["regid" => $regid, "verified" => 1]);

                                $touser = (object) [
                                    "fname" => get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname='Camper_Name_First'", ["regid" => $regid]),
                                    "lname" => get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname='Camper_Name_Last'", ["regid" => $regid]),
                                    "email" => get_db_field("email", "events_registrations", "regid = ||regid||", ["regid" => $regid]),
                                ];

                                $fromuser = (object) [
                                    "email" => $CFG->siteemail,
                                    "fname" => $CFG->sitename,
                                    "lname" => "",
                                ];

                                $message = registration_email($regid, $touser);
                                $sendemail = true;
                            }
                        } else {
                            if ($payment_method = "Campership") {
                                execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_status", "events"), ["regid" => $regid, "verified" => 1]);
                            } else {
                                execute_db_sql(fetch_template("dbsql/events.sql", "update_reg_status", "events"), ["regid" => $regid, "verified" => 0]);
                            }
                        }
                    }
                }
            }
        }
        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    if ($sendemail && isset($touser) && isset($fromuser) && isset($message)) {
        if (@send_email($touser, $fromuser, $CFG->sitename . " Registration", $message)) {
            @send_email($fromuser, $fromuser, $CFG->sitename . " Registration", $message);
        }
    }

    ajax_return("", $error);
}

function delete_registration_info() {
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
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        trigger_error($e->getMessage(), E_USER_WARNING);
    }
    ajax_return();
}

function resend_registration_email() {
global $CFG;
    $regid = clean_myvar_opt("regid", "int", false);
    $eventid = clean_myvar_opt("eventid", "int", false);

    $emailstatus = "email could not be sent. <br />";
    if (!empty($regid) && !empty($eventid)) {
        $event = get_event($eventid);

        $touser = (object)[
            'email' => get_db_field("email", "events_registrations", "regid=||regid||", ["regid" => $regid]),
            'fname' => get_registrant_name($regid),
            'lname' => "",
        ];

        $fromuser = (object)[
            'email' => $CFG->siteemail,
            'fname' => $CFG->sitename,
            'lname' => "",
        ];

        try {
            $message = registration_email($regid, $touser);
            if (send_email($touser, $fromuser, $event["name"] . " Registration", $message)) {
                $emailstatus = 'email was sent';
            }
        } catch (\Exception $e) {
            $emailstatus .= $e->getMessage() . "<br /><br />";
        }
    }

    $returnme = get_back_to_registrations_link($eventid) .
                '<br />
                <div style="text-align:center">
                    <strong>The following ' . $emailstatus . '</strong>
                    <br />
                    ' . $message . '
                </div>';
    ajax_return($returnme);
}

function get_registration_info() {
    global $CFG, $MYVARS, $USER;
    $eventid = clean_myvar_req("eventid", "int");
    $regid = clean_myvar_opt("regid", "int", false);

    $event = get_event($eventid);
    $template_id = $event["template_id"];
    $eventname = $event["name"];
    $pageid = $event["pageid"];

    $return = $error = "";
    try {
        $return = get_back_to_registrations_link($eventid);

        // Get all events beginning 3 months in the past, to a year in the future.
        $events = get_db_result(
            fetch_template("dbsql/events.sql", "get_current_events_with_same_template", "events"),
            [
                "template_id" => $template_id,
                "today" => get_timestamp(),
            ]
        );

        // If similar events exist, display the copy registration form.
        if (!$events) {
            ajaxapi([
                "id" => "copy_registration",
                "url" => "/features/events/events_ajax.php",
                "if" => "$('#copy_reg_to').val() > 0",
                "data" => [
                    "action" => "copy_registration",
                    "regid" => $regid,
                ],
                "reqstring" => "copy_form",
                "ondone" => "show_registrations($('#copy_reg_to').val());",
                "loading" => "loading_overlay",
                "event" => "submit",
            ]);

            $selectproperties = [
                "properties" => [
                    "name" => "copy_reg_to",
                    "id" => "copy_reg_to",
                    "style" => "width:100%",
                ],
                "values" => $events,
                "valuename" => "eventid",
                "displayname" => "name",
                "firstoption" => "",
                "exclude" => $eventid,
            ];
            $return .= fill_template("tmp/events.template", "copy_registration_info_form", "events", ["eventselect" => make_select($selectproperties)]);
        }

        ajaxapi([
            "id" => "save_reg_changes",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "save_reg_changes",
                "regid" => $regid,
                "eventid" => $eventid,
            ],
            "reqstring" => "reg_form",
            "ondone" => "show_registrations(" . $eventid . ", " . $regid . ");",
            "loading" => "loading_overlay",
            "event" => "submit",
        ]);

        $reg_status = get_db_field("verified", "events_registrations", "regid='$regid'");
        $reg_status = !$reg_status ? "Pending" : "Completed";
        $return .= '
            <tr>
                <td>
                    Status
                </td>
                <td>
                    ' . $reg_status . '
                </td>
            </tr>';

        // Make select or show selected.
        if ($events) {
            db_goto_row($events); // reset pointer to use it again.
            $p = [
                "properties" => [
                    "name" => "reg_eventid",
                    "id" => "reg_eventid",
                    "style" => "width:100%;",
                ],
                "values" => $events,
                "valuename" => "eventid",
                "displayname" => "name",
                "selected" => $eventid,
            ];
            $selected = make_select($p);
        } else {
            $selected = '<input id="reg_eventid" name="reg_eventid" type="hidden" value="' . $eventid . '" />' . $event["name"];
        }

        $event_reg = get_db_row("SELECT * FROM events_registrations WHERE regid='$regid'");

        $rows = "";
        if (!defined('FORMLIB')) { include_once($CFG->dirroot . '/lib/formlib.php'); }
        $template = get_event_template($template_id);
        $template_forms = get_template_formlist($template_id);

        if ($template["folder"] == "none") {
            if ($template_forms) {
                while ($form_element = fetch_row($template_forms)) {
                    if ($form_element["type"] == "payment") {
                        if ($values = get_db_result("SELECT * FROM events_registrations_values
                                                        WHERE regid='$regid'
                                                            AND elementid='" . $form_element["elementid"] . "'
                                                        ORDER BY entryid")) {
                            $i = 0; $values_display = explode(",", $form_element["display"]);
                            while ($value = fetch_row($values)) {
                                $rows .= '
                                    <tr>
                                        <td class="registration_info_title">
                                            ' . $values_display[$i] . '
                                        </td>
                                        <td>
                                            <input id="' . $value["entryid"] . '" name="' . $value["entryid"] . '" type="text" style="width:100%;" value="' . stripslashes($value["value"]) . '" />
                                        </td>
                                    </tr>';
                                $i++;
                            }
                        }
                    } else {
                        $value = get_db_row("SELECT * FROM events_registrations_values
                                            WHERE regid='$regid'
                                                AND elementid='" . $form_element["elementid"] . "'");
                        $rows .= '
                            <tr>
                                <td class="registration_info_title">
                                    ' . $form_element["display"] . '
                                </td>
                                <td>
                                    <input id="' . $value["entryid"] . '" name="' . $value["entryid"] . '" type="text" style="width:100%;" value="' . stripslashes($value["value"]) . '" />
                                </td>
                            </tr>';
                    }
                }
            }
        } else {
            $template_forms = get_template_formlist($template_id);
            foreach ($template_forms as $form) {
                $field = get_template_formlist_element_array($template, $form);

                if (isset($field["displayonly"])) {
                    continue;
                }

                $SQL = fetch_template("dbsql/events.sql", "get_registration_value", "events");
                $value = get_db_row($SQL, ["regid" => $regid, "elementname" => strtolower($field["name"])]);

                $val = $entryid = "";
                if (!$value) { // No value so we create a blank.
                    $SQL = "INSERT INTO events_registrations_values
                                        (regid, value, eventid, elementname)
                                 VALUES ('$regid', '', '$eventid', '" . $field["name"] . "')";
                    $entryid = execute_db_sql($SQL);
                  } else {
                    $val = stripslashes($value["value"]);
                    $entryid = $value["entryid"];
                }

                $formfield = '<input id="' . $entryid . '" name="' . $entryid . '" type="text" style="width: 100%" value="' . $val . '" />';

                $rows .= '
                    <tr>
                        <td class="registration_info_title">' .
                            $field["title"] . '
                        </td>
                        <td>
                            ' . $formfield . '
                        </td>
                    </tr>';
            }
        }

        $params =  [
            "selected" => $selected,
            "rows" => $rows,
            "email" => stripslashes($event_reg["email"]),
            "code" => stripslashes($event_reg["code"]),
        ];
        $return .= fill_template("tmp/events.template", "registration_info_form", "events", $params);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return('<div style="padding:15px 5px">' . $return . '</div>', $error);
}

function add_blank_registration_ajax() {
    $eventid = clean_myvar_req("eventid", "int");
    $reserveamount = clean_myvar_opt("reserveamount", "int", 1);
    $returnme = "";
    $return = add_blank_registration($eventid, $reserveamount);
    foreach ($return as $key => $val) {
        $returnme .= "Reserved spot #". ($key + 1);
        $returnme .= $val ? ": Success <br />" : " Failed <br />";
    }
    ajax_return($returnme);
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
    $selected = clean_myvar_opt("sel", "int", false);

    $event = get_event($eventid);
    $template_id = $event["template_id"];
    $eventname = $event["name"];
    $pageid = $event["pageid"];

    $display = $selected ? "" : "display:none;";

    $registrationlist = "";
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

        $menuParams = [
            "properties" => [
                "name" => "registrants",
                "id" => "registrants",
                "style" => "width: 100%",
                "onchange" => 'if ($(this).val().length > 0) { $(\'#event_menu_button\').show(); } else { $(\'#event_menu_button\').hide(); }',
            ],
            "values" => $values,
            "valuename" => "regid",
            "displayname" => "name",
            "selected" => $selected,
            "firstoption" => "Select Registration from List",
        ];

        $templateParams = [
            "wwwroot" => $CFG->wwwroot,
            "eventid" => $eventid,
            "display" => $display,
            "menu" => make_select($menuParams),
        ];
        $registrationlist = fill_template("tmp/events.template", "show_registrations_menu_tools", "events", $templateParams);

        // Load javascript needed for the registration menu and tools.
        load_registrations_menu_javascript("show_registrations($eventid)");
    }

    // Load javascript needed for the print and quick reserve areas.
    load_show_registrations_javascript($eventid);

    //Print all registration button and Print online registrations only button
    $templateParams = [
        "eventname" => $eventname,
        "registrationlist" => $registrationlist,
    ];

    ajax_return(fill_template("tmp/events.template", "show_registrations_page", "events", $templateParams));
}

function load_show_registrations_javascript($eventid) {
    ajaxapi([
        "id" => "print_registrations",
        "if" => "$('#print_registrations').val() != ''",
        "url" => "/features/events/events_ajax.php",
        "data" => ["action" => "print_registration", "eventid" => $eventid, "online_only" => "js|| $('#print_registrations').val() ||js"],
        "display" => "searchcontainer",
        "loading" => "loading_overlay",
        "event" => "change",
    ]);

    ajaxapi([ // Add Blank Registration.
        "id" => "add_blank_registration",
        "url" => "/features/events/events_ajax.php",
        "data" => ["action" => "add_blank_registration_ajax", "reserveamount" => "js|| $('#reserveamount').val() ||js", "eventid" => $eventid],
        "ondone" => "show_registrations($eventid);",
        "loading" => "loading_overlay",
    ]);
}

function load_registrations_menu_javascript($callback) {
    ajaxapi([ // Delete Registration.  Sends to showregistrationpage callback.
        "id" => "delete_registration",
        "paramlist" => "eventid, regid",
        "if" => "regid != '' && confirm('Do you want to delete this registration?')",
        "url" => "/features/events/events_ajax.php",
        "data" => ["action" => "delete_registration_info", "regid" => "js|| regid ||js"],
        "ondone" => "$callback;",
        "loading" => "loading_overlay",
        "event" => "none",
    ]);

    ajaxapi([ // Go to Edit Registration Page.
        "if" => "regid != ''",
        "id" => "edit_registration",
        "paramlist" => "eventid, regid",
        "url" => "/features/events/events_ajax.php",
        "data" => ["action" => "get_registration_info", "regid" => "js|| regid ||js", "eventid" => "js|| eventid ||js"],
        "display" => "searchcontainer",
        "loading" => "loading_overlay",
        "event" => "none",
    ]);

    ajaxapi([ // Resend Registration Email.
        "if" => "regid != ''",
        "id" => "email_registration",
        "paramlist" => "eventid, regid",
        "url" => "/features/events/events_ajax.php",
        "data" => ["action" => "resend_registration_email", "regid" => "js|| regid ||js", "eventid" => "js|| eventid ||js"],
        "display" => "searchcontainer",
        "loading" => "loading_overlay",
        "event" => "none",
    ]);
}

function registrationsearch() {
global $CFG, $USER;
    if (!defined('SEARCH_PERPAGE')) { define('SEARCH_PERPAGE', 8); }

    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
    $searchtype = clean_myvar_opt("searchtype", "string", "events");
    $pagenum = clean_myvar_opt("pagenum", "int", 0);

    $pageparams = [];
    $pageparams["pagenum"] = $pagenum;
    $pageparams["perpage"] = SEARCH_PERPAGE;
    $pageparams["firstonpage"] = $pageparams["pagenum"] * $pageparams["perpage"];

    $return = $error = "";
    try {
        // No search words given.
        $dbsearchwords = empty($searchwords) ? "%" : $searchwords;

        // Is a site admin.
        $admin = is_siteadmin($USER->userid);

        $i = 0; $searchstring = "";
        $searchparams = ["pageid" => $pageid];
        $words = explode(" ", $dbsearchwords);

        if ($searchtype == "events") {
            while (isset($words[$i])) {
                $searchparams["words$i"] = "%" . $words[$i] . "%";
                $searchpart = "(name LIKE ||words$i||)";
                $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
                $i++;
            }

            $sqlparams = [
                "issite" => ($pageid == $CFG->SITEID),
                "searchstring" => $searchstring,
            ];
            $SQL = fill_template("dbsql/events.sql", "events_search", "events", $sqlparams, true);
        } else {
            while (isset($words[$i])) {
                $searchparams["words$i"] = "%" . $words[$i] . "%";
                $searchpart = "(LOWER(elementname) LIKE '%name%' AND value LIKE ||words$i||)";
                $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
                $i++;
            }

            $sqlparams = [
                "issite" => ($pageid == $CFG->SITEID),
                "searchstring" => $searchstring,
            ];
            $SQL = fill_template("dbsql/events.sql", "registration_search", "events", $sqlparams, true);
        }

        // Get the total for all pages returned.
        $pageparams["total"] = get_db_count($SQL, $searchparams);

        // Get the amount returned...is it a full page of results?
        $pageparams["count"] = get_page_count($pageparams);

        if ($pageparams["count"] > 0) {
            // Limit to this page.
            $SQL .= " LIMIT " . $pageparams["firstonpage"] . "," . $pageparams["perpage"];

            // Count was > 0, so this shouldn't be empty.
            if (!$results = get_db_result($SQL, $searchparams)) {
                throw new \Exception(getlang("generic_error"));
            }


            $navparams = get_nav_params($pageparams);
            $navparams["prev_action"] = "perform_registrationsearch(" . ($pagenum - 1) . ", '$searchwords');";
            $navparams["next_action"] = "perform_registrationsearch(" . ($pagenum + 1) . ", '$searchwords');";

            $rows = "";
            if ($searchtype == "events") {
                ajaxapi([
                    "id" => "export_event_registrations" ,
                    "paramlist" => "eventid, pageid",
                    "url" => "/features/events/events_ajax.php",
                    "data" => [
                        "action" => "export_registrations",
                        "pagieid" => "js||pageid||js",
                        "eventid" => "js||eventid||js",
                    ],
                    "display" => "downloadframe",
                    "event" => "none",
                ]);

                while ($event = fetch_row($results)) {
                    $export = "";
                    $limit = "&#8734;"; // infinity symbol
                    if ($event["start_reg"] > 0) {
                        $regcount = get_db_count("SELECT * FROM events_registrations WHERE eventid='" . $event['eventid'] . "'");
                        $limit = $event['max_users'] == "0" ? $limit : $event['max_users'];

                        // GET EXPORT CSV BUTTON
                        $canexport = false;
                        if (user_is_able($USER->userid, "exportcsv", $event["pageid"])) {
                            $canexport = true;
                        }
                    }

                    $rowparams = [
                        "event" => $event,
                        "begindate" => date("m/d/Y", $event["event_begin_date"]),
                        "canexport" => $canexport,
                        "regcount" => $regcount,
                        "limit" => $limit,
                    ];
                    $rows .= fill_template("tmp/events.template", "eventsearchrows", "events", $rowparams);
                }

                $params = [
                    "searchnav" => fill_template("tmp/events.template", "searchnav", "events", $navparams),
                    "rows" => $rows,
                ];
                $return = fill_template("tmp/events.template", "eventsearchresults", "events", $params);
            } else {

                load_registrations_menu_javascript("perform_registrationsearch()");

                while ($reg = fetch_row($results)) {
                    $rowparams = [
                        "reg" => $reg,
                        "regdate" => date("m/d/Y", $reg["date"]),
                        "actions" => get_reg_actions($reg),
                    ];
                    $rows .= fill_template("tmp/events.template", "regsearchrows", "events", $rowparams);
                }

                $params = [
                    "searchnav" => fill_template("tmp/events.template", "searchnav", "events", $navparams),
                    "rows" => $rows,
                ];
                $return = fill_template("tmp/events.template", "regsearchresults", "events", $params);
            }


        } else {
            $return = '
                <div class="error_text" class="centered_span">
                    No matches found.
                </div>';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    $return .= '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';
    ajax_return($return, $error);
}

function get_reg_actions($reg) {
    global $CFG;
    $actions = "";

    // Go to pay link
    $actions .= '
        <div class="dropdowncreator">
            <button class="dropbtn">' . icon("gear") . '</button>
            <div class="dropcontent">
                <a
                    href="javascript:void(0)"
                    title="Edit Registration"
                    onclick="edit_registration('. $reg["eventid"] . ',' . $reg["regid"] . ')">
                    ' . icon("pencil") . ' Edit Registration
                </a>
                <a
                    href="javascript:void(0)"
                    title="Resend Registration Email"
                    onclick="email_registration('. $reg["eventid"] . ',' . $reg["regid"] . ')">
                    ' . icon("envelope") . ' Resend Registration Email
                </a>
                <a
                    title="Pay"
                    target="_blank"
                    href="' . $CFG->wwwroot . '/features/events/events.php?action=pay&i=!&regcode=' . $reg["code"] . '">
                    ' . icon("credit-card") . ' Make Payment
                </a>
                <a
                    href="javascript:void(0)"
                    title="Delete Registration"
                    onclick="delete_registration('. $reg["eventid"] . ',' . $reg["regid"] . ')">
                    ' . icon("trash") . ' Delete Registration
                </a>
            </div>
        </div>
    ';
    return $actions;
}

function lookup_reg() {
global $CFG, $MYVARS, $USER;
    $code = clean_myvar_opt("code", "string", "");

    $return = $error = "";
    try {
        $SQL = "SELECT * FROM events_registrations WHERE code = ||code||";
        if (strlen($code) <= 5 || !$registration = get_db_row($SQL, ["code" => $code])) {
            $return = '
                <div style="text-align:center;padding: 20px">
                    <strong>No registration found.</strong>
                </div>';
            goto instant_end;
        }

        $SQL = "SELECT * FROM events WHERE eventid = ||eventid|| AND fee_full != 0";
        if (!$event = get_db_row($SQL, ["eventid" => $registration["eventid"]])) {
            $return = '
                <div style="text-align:center;padding: 20px">
                    <strong>We are unable to provide payment options for this registration id.</strong>
                </div>';
            goto instant_end;
        }

        $total_owed = (float) get_reg_owed($registration["regid"]);
        if (empty($total_owed)) {
            $total_owed = $registration["date"] < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
        }

        $paid = (float) get_reg_paid($registration["regid"]);
        $paid = empty($paid) ? "0.00" : $paid;
        $remaining = $total_owed - $paid;
        $registrant_name = get_registrant_name($registration["regid"]);

        $payarea = "";
        if ($remaining > 0) {
            ajaxapi([
                "id" => "makepayment",
                "if" => "$('#payment_amount').val() > 0",
                "url" => "/features/events/events_ajax.php",
                "data" => [
                    "action" => "makepayment",
                    "regid" => $registration["regid"],
                    "amount" => "js||$('#payment_amount').val()||js",
                ],
                "display" => "paymentarea",
            ]);

            $payarea = '
                <br /><br />
                    <h3>Make Payment</h3>
                    <br />
                    ' . make_fee_options(
                        0,
                        $remaining,
                        "payment_amount",
                        'class="payment_amounts" style="width:100px"',
                        $event['sale_end'],
                        $event['sale_fee']
                    ) . '
                    <button id="makepayment">
                        Pay Now
                    </button>
                </div>
                <div id="paymentarea"></div>';
        }

        $return = '
            <div class="centered">
                <h3>Registration Found</h3>
                <strong>Event: ' . $event["name"] . ' - ' . $registrant_name . '\'s Registration</strong>
                <br /><br />
                <span style="display: inline-block;text-align: right;">
                    Total Owed:  $' . number_format($total_owed, 2) . '
                    <br />
                    Amount Paid:  $' . number_format($paid, 2) . '
                    <br />
                    <strong>
                    Remaining Balance:  $' . number_format($remaining, 2) . '
                    </strong>
                </span>
                ' . $payarea;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    instant_end:
    ajax_return($return, $error);

}

function makepayment() {
    $return = "";
    $error = "";
    try {
        $regid = clean_myvar_req("regid", "int");
        $payment_amount = clean_myvar_req("amount", "float");

        $event = get_event_from_regid($regid);
        $registrant_name = get_registrant_name($regid);

        $return .= "HERE1 . $payment_amount";
        if ($payment_amount > 0) {
            $return .= "HERE2";
            $item[0] = new \stdClass;
            $item[0]->description = "Event: " . $event["name"] . " - $registrant_name's Registration - Remaining Balance Payment";
            $item[0]->cost = $payment_amount;
            $item[0]->regid = $regid;

            // Create payment cart session.
            make_payment_cart_session($item);

            // Get payment form.
            $return = get_payment_form();
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    ajax_return($return, $error);
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

    $return = $error = "";
    try {
        start_db_transaction();

        // Get event info.
        $event = get_event($eventid);

        // Prepare fee and payment info.
        if ($event['fee_full'] !== 0 && $payment_amount) {
            $cart_total = !empty($total_owed) ? $total_owed + $payment_amount : $payment_amount;
            $reginfo["cart_total"] = $cart_total;
            $reginfo["total_owed"] = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
        }

        if (!$regid = enter_registration($event['eventid'], $reginfo, $email)) {
            log_entry("events", $eventid, "Failed Event Registration"); // Log
            $return = '
                <center>
                    <div style="width:60%">
                        <span class="error_text">
                            Your registration for ' . $event['name'] . ' has failed.
                        </span>
                        <br />
                        ' . $error . '
                    </div>
                </center>';
            throw new \Exception($error);
        }

        $allowedinpage = "";
        if ($event['allowinpage'] != 0) {
            if (is_logged_in() && $event['pageid'] != $CFG->SITEID) {
                change_page_subscription($event['pageid'], $USER->userid);
                $allowedinpage .= 'You have been automatically allowed into this events web page.  This page contain specific information about this event . ';
            }
        }

        $paymentinfo = "";
        if ($event['fee_full'] != 0) {
            $registrant_name = get_registrant_name($regid);
            $items = $items ? "$items**$regid::Event: " . $event["name"] . " - $registrant_name's Registration::$payment_amount" : $regid . "::Event: " . $event["name"] . " - $registrant_name's Registration::$payment_amount";
            $paymentinfo .= '
                <div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="' . $cart_total . '" />
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
                $paymentinfo .= '
                    <br />
                    If you would like to pay the <span style="color:blue;font-size:1.25em;">$' . $cart_total . '</span> fee now, click the Paypal button below.
                    <center>
                    ' . make_paypal_button($cart_items, $event['paypal']) . '
                    </center>
                    <br /><br />
                    Your registration will be complete upon payment.';
            } else {
                $paymentinfo .= '
                    <br />
                    If you are done with the registration process, please make out your <br />
                    check or money order in the amount of <span style="color:blue;font-size:1.25em;">$' . $cart_total . '</span> payable to <strong>' . $event["payableto"] . '</strong> and send it to <br /><br />
                    <center>
                    ' . $event['checksaddress'] . '.
                    </center>
                    <br /><br />
                    Thank you for registering for this event.';
            }
        }

        $return .= '
            <center>
                <div style="width:70%">
                    You have successfully registered for ' . $event['name'] . '
                    <br />
                    ' . $allowedinpage . '
                    ' . $paymentinfo . '
                </div>
            </center>';

        commit_db_transaction();

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
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    ajax_return($return, $error);
}

function delete_limit() {
    $template_id = clean_myvar_opt("template_id", "int", false);
    $limit_type = clean_myvar_opt("limit_type", "string", false);
    $limit_num = clean_myvar_opt("limit_num", "int", 0);
    $hard_limits = clean_myvar_opt("hard_limits", "string", false);
    $soft_limits = clean_myvar_opt("soft_limits", "string", false);
    $hidden_variable1 = $hidden_variable2 = "";

    $return = $error = "";
    try {
        if (!$template_id) {
            throw new Exception("No Template ID");
        }

        $template = get_event_template($template_id);

        $hard = "";
        if ($hard_limits) { // There are some hard limits
            $limits_array = explode("*", $hard_limits);
            $i = 0;
            $hard .= "<br /><strong>Hard Limits</strong> <br />";
            $alter = 0;
            while (isset($limits_array[$i])) {
                if (!($limit_type == "hard_limits" && $limit_num == $i)) {
                    $limit = explode(":", $limits_array[$i]);
                    $displayname = get_template_field_displayname($template["template_id"], $limit[0]);
                    $hard .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false)  . delete_limit_button("hard_limits", $i - $alter) . '<br />';
                    $hidden_variable1 .= $hidden_variable1 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
                } else { $alter++; }
                $i++;
            }
        }
        if ($hidden_variable1 == "") { $hard = ""; }

        $soft = "";
        if ($soft_limits) { // There are some soft limits
            $limits_array = explode("*", $soft_limits);
            $i = 0;
            $soft .= "<br /><strong>Soft Limits</strong> <br />";
            $alter = 0;
            while (isset($limits_array[$i])) {
                if (!($limit_type == "soft_limits" && $limit_num == $i)) {
                    $limit = explode(":", $limits_array[$i]);
                    $displayname = get_template_field_displayname($template["template_id"], $limit[0]);
                    $soft .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . delete_limit_button("soft_limits", $i - $alter) . '<br />';
                    $hidden_variable2 .= $hidden_variable2 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
                } else { $alter++; }
                $i++;
            }
        }

        if ($hidden_variable2 == "") { $soft = ""; }

        $return = $hard . $soft . '<input type="hidden" id="hard_limits" name="hard_limits" value="' . $hidden_variable1 . '" /><input type="hidden" id="soft_limits" name="soft_limits" value="' . $hidden_variable2 . '" />';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function add_custom_limit() {
    $template_id = clean_myvar_opt("template_id", "int", false);
    $hard_limits = clean_myvar_opt("hard_limits", "string", false);
    $soft_limits = clean_myvar_opt("soft_limits", "string", false);
    $hidden_variable1 = $hidden_variable2 = "";

    $return = "";
    if (!$template_id) { emptyreturn(); }

    $template = get_event_template($template_id);

    if ($hard_limits) { // There are some hard limits
        $limits_array = explode("*", $hard_limits);
        $i = 0;
        $return .= "<br /><strong>Hard Limits</strong> <br />";
        while (isset($limits_array[$i])) {
            $limit = explode(":", $limits_array[$i]);
            if (isset($limit[3])) {
                if ($template["folder"] == "none") {
                    $displayname = get_db_field("display", "events_templates_forms", "elementid=" . $limit[0]);
                } else {
                    $displayname = $limit[0];
                }

                $return .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . delete_limit_button("hard_limits", $i) . '<br />';
                $hidden_variable1 .= $hidden_variable1 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
            }
            $i++;
        }
    }

    if ($soft_limits) { // There are some soft limits
        $limits_array = explode("*", $soft_limits);
        $i = 0;
        $return .= "<br /><strong>Soft Limits</strong> <br />";
        while (isset($limits_array[$i])) {
            $limit = explode(":", $limits_array[$i]);

            if ($template["folder"] == "none") {
                $displayname = get_db_field("display", "events_templates_forms", "elementid=" . $limit[0]);
            } else {
                $displayname = $limit[0];
            }

            $return .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . delete_limit_button("soft_limits", $i) . '<br />';
            $hidden_variable2 .= $hidden_variable2 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
            $i++;
        }
    }

    $return .= '<input type="hidden" id="hard_limits" name="hard_limits" value="' . $hidden_variable1 . '" /><input type="hidden" id="soft_limits" name="soft_limits" value="' . $hidden_variable2 . '" />';

    ajax_return($return);
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
            $formlist = get_template_formlist($template_id);
            foreach ($formlist as $f) {
                $el = get_template_formlist_element_array($template, $f);
                if (!isset($el["displayonly"]) || !$el["displayonly"]) {
                    $values[] = [
                        "elementid" => $el["name"],
                        "display" => $el["title"],
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

    $return = '
    <br />
    <table>
        <tr>
            <td class="sub_field_title" style="padding: 5px;width: 75px;text-align:center">
                Soft Limit:
            </td>
            <td class="field_input">
                <select id="custom_limit_sorh">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
                ' . get_hint_box("input_event_custom_limit_explanation") . '
            </td>
        </tr>
        <tr>
            <td class="field_input" colspan="2" style="padding: 5px;">
                <input id="custom_limit_num" type="text" size="3" style="margin: 0px" />
                ' . get_hint_box("input_event_custom_limit_num") . '
                <strong> registrations allowed</strong>
            </td>
        </tr>
        <tr>
            <td class="field_input" colspan="2" style="padding: 5px;">
                <strong>where </strong>
                ' . $fields . '
                ' . get_hint_box("input_event_custom_limit_fields") . '
            </td>
        </tr>
        <tr>
            <td class="field_input" colspan="2" style="padding: 5px;">
                <strong>is </strong>
                ' . $operators . ' <input id="custom_limit_value" type="text" />
                ' . get_hint_box("input_event_custom_limiter") . '
            </td>
        </tr>
    </table>
    <div style="text-align: center;width: 360px;white-space: normal;margin: auto;">
        <span style="display:inline-block;" id="custom_limit_fields_error" class="error_text"></span>
        <span style="display:inline-block;" id="custom_limit_value_error" class="error_text"></span>
        <span style="display:inline-block;" id="custom_limit_num_error" class="error_text"></span>
        <span style="display:inline-block;" id="custom_limit_sorh_error" class="error_text"></span><br />
        <button id="add_custom_limit" type="button">
            Add Custom Limit
        </button>
    </div>
    ' . js_code_wrap('prepareInputsForHints();');

    ajax_return($return);
}

function add_new_location() {
global $USER;
    $eventid = clean_myvar_opt("eventid", "int", false);
    $name = clean_myvar_req("name", "string");
    $add1 = clean_myvar_req("add1", "string");
    $add2 = clean_myvar_req("add2", "string");
    $zip = clean_myvar_req("zip", "string");
    $phone1 = clean_myvar_opt("phone1", "string", "");
    $phone2 = clean_myvar_opt("phone2", "string", "");
    $phone3 = clean_myvar_opt("phone3", "string", "");
    $phone = $phone1 . "-" . $phone2 . "-" . $phone3;
    $phone = str_replace("---", "", $phone);

    $shared = clean_myvar_opt("shared", "int", 0);

    $SQL = "INSERT INTO events_locations (location, address_1, address_2, zip, phone, userid, shared)
    VALUES(||location||, ||address_1||, ||address_2||, ||zip||, ||phone||, ||userid||, ||shared||)";

    $params = [
        "location" => $name,
        "address_1" => $add1,
        "address_2" => $add2,
        "zip" => $zip,
        "phone" => $phone,
        "userid" => "," . $USER->userid . ",",
        "shared" => $shared,
    ];
    $id = execute_db_sql($SQL, $params);

    log_entry("events", $name, "Added Location");
    $return = get_my_locations($USER->userid, $id, $eventid);
    ajax_return($return);
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

    $phone1 = clean_myvar_req("phone_1", "int");
    $phone2 = clean_myvar_req("phone_2", "int");
    $phone3 = clean_myvar_req("phone_3", "int");
    $phone = $phone1 . "-" . $phone2 . "-" . $phone3;

    $location = clean_myvar_req("location", "string");
    $category = clean_myvar_req("category", "int");
    $siteviewable = clean_myvar_opt("siteviewable", "int", 0);
    $byline = clean_myvar_opt("byline", "string", "");
    $description = clean_myvar_opt("editor1", "html", "");
    $multiday = clean_myvar_opt("multiday", "bool", false);
    $workers = clean_myvar_opt("workers", "int", 0);

    //strtotime php5 fixes
    $event_begin_date = clean_myvar_req("event_begin_date", "string");
    if ($event_begin_date) {
        $event_begin_date = strtotime($event_begin_date);
    }

    $event_end_date = clean_myvar_opt("event_end_date", "string", $event_begin_date);
    if ($event_end_date) {
        $event_end_date = $multiday == "1" ? strtotime($event_end_date) : $event_begin_date;
    }

    $allday = clean_myvar_opt("allday", "int", 1);
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

    $return = $error = "";
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
                if (!$request) { $return = "Event Added"; }
            } else {
                if (!$request) {
                    // Log event error
                    log_entry("events", 0, "Event could NOT be added");

                    throw new \Exception("Event could NOT be added");
                }
            }

            if ($pageid == $CFG->SITEID && $eventid) {
                if (!confirm_or_deny_event($eventid, true)) {
                    throw new Exception(getlang("failed_confirm", "/features/events"));
                }
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

                $SQL = fetch_template("dbsql/events.sql", "delete_event_settings", "events");

                // Delete old template settings info just in case things have changed
                execute_db_sql($SQL, ["type" => "events_template", "extra" => (string) $eventid]);

                save_template_settings($template_id, $MYVARS->GET);

                log_entry("events", $eventid, "Event Edited");
                if (!$request) { $return = "Event Edited"; }
            } else {
                if (!$request) {
                    log_entry("events", $eventid, "Event could NOT be edited");
                    $return = "Event could NOT be Edited";
                }
            }

            if ($pageid == $CFG->SITEID && $eventid) {
                if (!confirm_or_deny_event($eventid, true)) {
                    throw new Exception(getlang("failed_confirm", "/features/events"));
                }
            }
            commit_db_transaction();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            rollback_db_transaction($error);
        }
    }
    ajax_return($return, $error);
}

function get_end_time() {
    $starttime = clean_myvar_opt("starttime", "string", false);
    $endtime = clean_myvar_opt("endtime", "string", false);
    $limit = clean_myvar_opt("limit", "int", 0);


    // event is not a multi day event and endtime is already set
    if ($limit === 1 && $endtime) {
        $return = get_possible_times("end_time", $endtime, $starttime);
    } elseif ($limit === 1) {
        $return = get_possible_times("end_time", false, $starttime);
    } elseif ($limit === 0 && $endtime) {
        $return = get_possible_times("end_time", $endtime);
    } elseif ($limit === 0) {
        $return = get_possible_times("end_time");
    }
    ajax_return($return);
}

function unique() {
    $eventid = clean_myvar_req("eventid", "int");
    $elementid = clean_myvar_req("elementid", "int");
    $value = clean_myvar_req("value", "string");

    $return = "true";
    if (is_unique("events_registrations_values", "elementid='$elementid' AND eventid='$eventid' AND value='$value'")) {
        $return = "false";
    }

    ajax_return($return);
}

function unique_relay() {
    $table = clean_myvar_req("table", "string");
    $key = clean_myvar_req("key", "string");
    $value = clean_myvar_req("value", "string");

    $return = "true";
    if (is_unique($table, "$key='$value'")) {
        $return = "false";
    }

    ajax_return($return);
}

function add_location_form() {
    $eventid = clean_myvar_opt("eventid", "int", false);
    $formtype = clean_myvar_req("formtype", "string");

    switch($formtype) {
        case "new":
            ajaxapi([
                "id" => "new_event_location_submit",
                "if" => "valid_new_location()",
                "reqstring" => "new_event_location_form",
                "url" => "/features/events/events_ajax.php",
                "data" => [
                    "action" => "add_new_location",
                    "eventid" => "js||$('#eventid').length ? $('#eventid').val() : 0||js",
                ],
                "display" => "select_location",
                "ondone" => "$('#location_status').html('Location Added'); reset_location_menu(); setTimeout('clear_display(\'location_status\')', 5000);",
                "event" => "click",
            ]);

            ajaxapi([
                "id" => "is_unique_location_name",
                "url" => "/features/events/events_ajax.php",
                "async" => "false",
                "data" => [
                    "action" => "unique_relay",
                    "table" => "events_locations",
                    "key" => "location",
                    "value" => "js||$('#location_name').val()||js",
                ],
                "event" => "none",
            ]);

            $return = new_location_form($eventid);
            break;
        case "existing":
            $return = location_list_form($eventid);
            break;
    }

    ajax_return($return);
}

function copy_location() {
global $USER;
    $eventid = clean_myvar_opt("eventid", "int", false);
    $location = clean_myvar_req("location", "int");
    execute_db_sql("UPDATE events_locations
                    SET userid = CONCAT(userid,||userid||)
                    WHERE id = ||id||", ["id" => $location, "userid" => $USER->userid . ","]);
    $return = get_my_locations($USER->userid, $location, $eventid);

    ajax_return($return);
}

function get_location_details() {
    $location = clean_myvar_req("location", "int");
    $row = get_db_row("SELECT * FROM events_locations WHERE id = ||id||", ["id" => $location]);

    $return = '
        <strong>' . $row['location'] . '</strong>
        <br />
        ' . $row['address_1'] . '<br />
        ' . $row['address_2'] . '  ' . $row['zip'] . '<br />
        ' . $row['phone'];
    ajax_return($return);
}

function export_registrations() {
global $CFG, $USER;
    if (!defined('FILELIB')) { include_once ($CFG->dirroot . '/lib/filelib.php'); }
    date_default_timezone_set(date_default_timezone_get());
    $CSV = "Registration Date,Contact Email";
    $eventid = clean_myvar_req("eventid", "int");
    $event = get_event($eventid);
    $template = get_event_template($event['template_id']);

    if ($template['folder'] != "none") {
        $fields = get_template_formlist($event['template_id']);
        foreach ($fields as $f) {
            $field = get_template_formlist_element_array($template, $f);
            if (isset($field["displayonly"])) {
                continue;
            }
            $CSV .= "," . $field["title"];
        }
        $sortby = "elementname";
    } else {
        $formlist = get_db_result("SELECT * FROM events_templates_forms
                                        WHERE template_id='" . $event['template_id'] . "'
                                        ORDER BY sort");
        while ($form = fetch_row($formlist)) {
            $CSV .= "," . $form["display"];
        }
        $sortby = "elementid";
    }
    $CSV .= "\n";

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
                $fields = get_template_formlist($event['template_id']);
                foreach ($fields as $f) {
                    $field = get_template_formlist_element_array($template, $f);
                    if (isset($field["displayonly"])) {
                        continue;
                    }
                    $row .= ',"' . $reorder[$field["name"]]["value"] . '"';
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
                $fields = get_template_formlist($event['template_id']);
                foreach ($fields as $f) {
                    $field = get_template_formlist_element_array($template, $f);
                    if (isset($field["displayonly"])) {
                        continue;
                    }
                    $row .= ',"' . $reorder[$field["name"]]["value"] . '"';
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
                $fields = get_template_formlist($event['template_id']);
                foreach ($fields as $f) {
                    $field = get_template_formlist_element_array($template, $f);
                    if (isset($field["displayonly"])) {
                        continue;
                    }
                    $row .= ',"' . $reorder[$field["name"]]["value"] . '"';
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
    ajax_return('<iframe src="' . $CFG->wwwroot . '/scripts/download.php?file=' . create_file("export.csv", $CSV) . '"></iframe>');
}

function show_template_settings() {
    $templateid = clean_myvar_req("templateid", "int");
    $eventid = clean_myvar_opt("eventid", "int", false);

    $return = $error = "";
    try {
        $return = get_template_settings_form($templateid, $eventid);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function global_template_settings() {
	$templateid = clean_myvar_req("templateid", "int");
	$feature = "events_template_global";

	//Default Settings
	$default_settings = get_template_settings($templateid, true);

	$template_name = get_db_field("name", "events_templates", "template_id = '$templateid'");

	//Check if any settings exist for this feature
    if ($settings = fetch_settings($feature, $templateid)) {
        $settings_page = make_settings_page($settings, $default_settings, "Template ($template_name) Settings");
        ajax_return($settings_page);
    } else { // No Settings found...setup default settings
        if (save_batch_settings($default_settings)) {
            global_template_settings();
		}
    }
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
global $CFG, $USER;
    if (!defined('SEARCH_PERPAGE')) { define('SEARCH_PERPAGE', 8); }

    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
    $pagenum = clean_myvar_opt("pagenum", "int", 0);

    $pageparams = [];
    $pageparams["pagenum"] = $pagenum;
    $pageparams["perpage"] = SEARCH_PERPAGE;
    $pageparams["firstonpage"] = $pageparams["pagenum"] * $pageparams["perpage"];

    $return = $error = "";
    try {
        // No search words given.
        $dbsearchwords = empty($searchwords) ? "%" : $searchwords;

        // Is a site admin.
        $admin = is_siteadmin($USER->userid);

        $i = 0; $searchstring = "";
        $searchparams = [];
        $words = explode(" ", $dbsearchwords);
        while (isset($words[$i])) {
            $searchparams["words$i"] = "%" . $words[$i] . "%";
            $searchpart = "(name LIKE ||words$i||)";
            $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
            $i++;
        }

        $SQL = fill_template("dbsql/events.sql", "templates_search", "events", ["searchstring" => $searchstring], true);

        // Get the total for all pages returned.
        $pageparams["total"] = get_db_count($SQL, $searchparams);

        // Get the amount returned...is it a full page of results?
        $pageparams["count"] = get_page_count($pageparams);

        if ($pageparams["count"] > 0) {
            // Limit to this page.
            $SQL .= " LIMIT " . $pageparams["firstonpage"] . "," . $pageparams["perpage"];

            // Count was > 0, so this shouldn't be empty.
            if (!$results = get_db_result($SQL, $searchparams)) {
                throw new \Exception(getlang("generic_error"));
            }

            // Change template status.
            ajaxapi([
                'id' => "change_template_status",
                'paramlist' => "template_id, status = 0",
                'url' => '/features/events/events_ajax.php',
                'data' => [
                    'action' => 'change_template_status',
                    'status' => "js||status||js",
                    'template_id' => "js||template_id||js",
                ],
                "loading" => "loading_overlay",
                "event" => "none",
                "ondone" => "perform_templatesearch($pagenum);",
            ]);

            $rows = "";
            while ($template = fetch_row($results)) {
                $issettings = false;
                $global_settings = fetch_settings("events_template_global", $template["template_id"]);
                if (!empty((array) $global_settings)) {
                    $issettings = true;
                    ajaxapi([
                        'id' => "global_settings_" . $template["template_id"],
                        'url' => '/features/events/events_ajax.php',
                        'data' => [
                            'action' => 'global_template_settings',
                            'templateid' => $template["template_id"],
                        ],
                        'display' => 'searchcontainer',
                        "loading" => "loading_overlay",
                    ]);
                }

                $version = empty($template["folder"]) ? "N/A" : get_db_field("setting", "settings", "setting_name='version' AND type='events_template' AND extra = ||folder||", ["folder" => $template["folder"]]);
                $rowparams = [
                    "name" => $template["name"],
                    "type" => $template["folder"] == "none" ? icon("database") : icon("folder"),
                    "version" => $version,
                    "issettings" => $issettings,
                    "isactive" => !empty($template["activated"]),
                    "template_id" => $template["template_id"],
                ];
                $rows .= fill_template("tmp/events.template", "templatesearchrow", "events", $rowparams);
            }

            $navparams = get_nav_params($pageparams);
            $navparams["prev_action"] = "perform_templatesearch(" . ($pagenum - 1) . ", '$searchwords');";
            $navparams["next_action"] = "perform_templatesearch(" . ($pagenum + 1) . ", '$searchwords');";

            $params = [
                "searchnav" => fill_template("tmp/events.template", "searchnav", "events", $navparams),
                "rows" => $rows,
            ];
            $return = fill_template("tmp/events.template", "templatesearchresults", "events", $params);
        } else {
            $return = '
                <div class="error_text" class="centered_span">
                    No matches found.
                </div>';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    $return .= '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';
    ajax_return($return, $error);
}

function change_template_status() {
    $template_id = clean_myvar_req("template_id", "int");
    $status = clean_myvar_opt("status", "int", 0);

    $error = "";
    try {
        if (is_numeric($template_id)) {
            $SQL = fetch_template("dbsql/events.sql", "update_template_status", "events");
            execute_db_sql($SQL, ["template_id" => $template_id, "activated" => $status]);
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return("", $error);
}

function change_bgcheck_status() {
    $pageid = get_pageid();
    $staffid = clean_myvar_opt("staffid", "int", false);
    $date = clean_myvar_opt("bgcdate", "string", "");

    if ($pageid && $staffid && $date) {
        execute_db_sql("UPDATE events_staff SET bgcheckpassdate = ||bgcheckpassdate||, bgcheckpass = '1' WHERE staffid = ||staffid|| AND pageid = ||pageid||", ["staffid" => $staffid, "pageid" => $pageid, "bgcheckpassdate" => strtotime($date)]);
        execute_db_sql("UPDATE events_staff_archive SET bgcheckpassdate = ||bgcheckpassdate||, bgcheckpass = '1' WHERE staffid = ||staffid|| AND year = '" . date('Y') . "' AND pageid = ||pageid||", ["staffid" => $staffid, "pageid" => $pageid, "bgcheckpassdate" => strtotime($date)]);
    } elseif ($pageid && $staffid && !$date) {
        execute_db_sql("UPDATE events_staff SET bgcheckpassdate = 0, bgcheckpass = '' WHERE staffid = ||staffid|| AND pageid = ||pageid||", ["staffid" => $staffid, "pageid" => $pageid]);
        execute_db_sql("UPDATE events_staff_archive SET bgcheckpassdate = 0, bgcheckpass = '' WHERE staffid = ||staffid|| AND year = '" . date('Y') . "' AND pageid = ||pageid||", ["staffid" => $staffid, "pageid" => $pageid]);
    }
    appsearch();
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

        $emailnotice = (object)[
            "email" => $CFG->siteemail,
            "fname" => $CFG->sitename,
            "lname" => "",
        ];

        foreach ($stafflist as $email) {
            $name = "";
            $email = trim($email);
            if (strpos($email, '@') !== false) { // It is an email address, so let's get an email ready.
                $user = get_db_row("SELECT * FROM users WHERE LOWER(email) LIKE LOWER(||email||)", ["email" => "%$email%"]);

                $contact = (object)[
                    "email" => $email,
                    "fname" => $user ? $user["fname"] : "",
                    "lname" => $user ? $user["lname"] : "",
                ];

                if ($user) {
                    $name = $user["fname"] . " " . $user["lname"];
                    $m1 = "Hello " . trim($name) . "!,";
                    $archive = get_db_row("SELECT * FROM events_staff WHERE userid = ||userid|| LIMIT 1", ["userid" => $user["userid"]]);
                    $status = staff_status($archive);
                    $action1 = "login.";
                    $action2 = "<li>If you have previously applied, the information from your previous application should already be filled in. Please update any information as needed.</li>";
                    $action3 = "<li>If you have completed a background check previously you can also send an email to " . $CFG->siteemail . " giving us permission to renew your background check.</li>";
                } else {
                    $m1 = "Hello future staff member!,";
                    $status = staff_status(false, false);
                    $action1 = "sign up for an account.&nbsp; It's easy and free.&nbsp; Once you have created an account, please login.";
                    $action2 = $action3 = "";
                }

                if (!empty($sendemails)) {
                    if (!empty($status) && !(count($status) == 1 && $status[0]["tag"] == "Flagged")) {
                        $m2 = "<br />I hope this email finds you well.<br />
                        <p><strong>If you are receiving this, it is because you have been selected to be on staff for an event this year.</strong>&nbsp; <strong>Please do the following ASAP.&nbsp;&nbsp; You must complete this staff application to be a " . date("Y") . " staff member. </strong></p>
                        <ul>
                        <li>Go to <a href='" . $protocol.$CFG->wwwroot . "'>$CFG->sitename</a> and $action1 &nbsp;<strong> <br />Only log in with your own account to fill out the application. </strong></li>
                        <li>Once you are logged into the site, you will find a button labeled <strong>Staff Apply</strong>.&nbsp; Fill out the staff application and submit.</li>
                        $action2
                        <li>If you are 18 years of age or older, once you complete your staff application you will be given an opportunity to follow a link to complete the Background Authorization Form. This background check will be valid for the next 5 years and will not need to be done every year.</li>
                        $action3
                        </ul><br />";
                        $m3 = "<strong>Current Status:</strong><br />" . print_status($status);
                        send_email($contact, $emailnotice, $subject, $m1.$m2.$m3);
                        $staffcomstatus[] = "$name($email) contacted.";
                    } else {
                        $staffcomstatus[] = "$name($email) is APPROVED / was NOT contacted.";
                    }
                } else {
                    if (empty($status) || (count($status) == 1 && $status[0]["tag"] == "Flagged")) {
                        if (!empty($status)) {
                            $staffapproved[] = "$name($email) is <strong> APPROVED (FLAGGED)</strong>";
                        } else {
                            $staffapproved[] = "$name($email) is <strong> APPROVED</strong>";
                        }
                    } else {
                        $staffcomstatus[] = "$name($email) <strong> Requires: " . implode(", ", array_column($status, 'tag')) . "</strong>";
                    }
                }
            } else {
                if (strlen($email) > 4) {
                    $staffcomstatus[] = "$email is not a valid email address.";
                }
            }
        }
    }

    ajax_return(implode('<br />', $staffcomstatus) . "<br /><br />" . implode('<br />', $staffapproved));
}

function appsearch() {
global $CFG, $USER;
    if (!defined('SEARCH_PERPAGE')) { define('SEARCH_PERPAGE', 8); }

    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
    $pagenum = clean_myvar_opt("pagenum", "int", 0);

    $pageparams = [];
    $pageparams["pagenum"] = $pagenum;
    $pageparams["perpage"] = SEARCH_PERPAGE;
    $pageparams["firstonpage"] = $pageparams["pagenum"] * $pageparams["perpage"];

    $return = $error = "";
    try {
        // No search words given.
        $dbsearchwords = empty($searchwords) ? "%" : $searchwords;

        // Is a site admin.
        $admin = is_siteadmin($USER->userid);

        $i = 0; $searchstring = "";
        $searchparams = ["pageid" => $pageid];
        $words = explode(" ", $dbsearchwords);
        while (isset($words[$i])) {
            $searchparams["words$i"] = "%" . $words[$i] . "%";
            $searchpart = "(s.name LIKE ||words$i||)";
            $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
            $i++;
        }

        $SQL = fill_template("dbsql/events.sql", "search_staff_app", "events", ["searchstring" => $searchstring], true);

        // Get the total for all pages returned.
        $pageparams["total"] = get_db_count($SQL, $searchparams);

        // Get the amount returned...is it a full page of results?
        $pageparams["count"] = get_page_count($pageparams);

        if ($pageparams["count"] > 0) {
            // Limit to this page.
            $SQL .= " LIMIT " . $pageparams["firstonpage"] . "," . $pageparams["perpage"];

            // Count was > 0, so this shouldn't be empty.
            if (!$results = get_db_result($SQL, $searchparams)) {
                throw new \Exception(getlang("generic_error"));
            }

            // save bgcheck date
            ajaxapi([
                "id" => "save_staff_bg",
                "paramlist" => "staffid",
                "if" => "!$('#bgcheckdate_' + staffid).prop('disabled')",
                "else" => "$('#bgcheckdate_' + staffid).prop('disabled', false);",
                "url" => "/features/events/events_ajax.php",
                "data" => [
                    "action" => "change_bgcheck_status",
                    "staffid" => "js||staffid||js",
                    "bgcdate" => "js||$('#bgcheckdate_' + staffid).val()||js",
                    "pagenum" => $pagenum,
                    "searchwords" => "js||encodeURIComponent('$searchwords')||js"],
                "display" => "searchcontainer",
                "loading" => "loading_overlay",
                "event" => "none",
            ]);

            // show staff app.
            ajaxapi([
                "id" => "show_staff_app",
                "paramlist" => "staffid",
                "url" => "/features/events/events_ajax.php",
                "data" => [
                    "action" => "show_staff_app",
                    "staffid" => "js||staffid||js",
                    "pagenum" => $pagenum,
                    "searchwords" => "js||encodeURIComponent('$searchwords')||js"],
                "display" => "searchcontainer",
                "loading" => "loading_overlay",
                "event" => "none",
            ]);

            $rows = "";
            while ($staff = fetch_row($results)) {
                $rowparams = [
                    "staff" => $staff,
                    "status" => print_status(staff_status($staff)),
                    "bgcheckdate" => empty($staff["bgcheckpassdate"]) ? '' : date('m/d/Y', $staff["bgcheckpassdate"]),
                ];
                $rows .= fill_template("tmp/events.template", "staffsearchrow", "events", $rowparams);
            }

            $navparams = get_nav_params($pageparams);
            $navparams["prev_action"] = "perform_appsearch(" . ($pagenum - 1) . ", '$searchwords');";
            $navparams["next_action"] = "perform_appsearch(" . ($pagenum + 1) . ", '$searchwords');";

            $params = [
                "searchnav" => fill_template("tmp/events.template", "searchnav", "events", $navparams),
                "rows" => $rows,
            ];
            $return = fill_template("tmp/events.template", "staffsearchresults", "events", $params);
        } else {
            $return = '
                <div class="error_text" class="centered_span">
                    No matches found.
                </div>';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    $return .= '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';
    ajax_return($return, $error);
}

function show_staff_app() {
global $CFG, $USER;
    $staffid = clean_myvar_opt("staffid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
    $pagenum = clean_myvar_opt("pagenum", "int", 0);
    $year = clean_myvar_opt("year", "int", false);

    ajaxapi([
        "id" => "perform_appsearch",
        "paramlist" => "pagenum = 0",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "appsearch",
            "pagenum" => "js||pagenum||js",
            "searchwords" => "js||encodeURIComponent('$searchwords')||js"],
        "display" => "searchcontainer",
        "ondone" => "init_event_menu();",
        "loading" => "loading_overlay",
        "event" => "none",
    ]);

    $returnme = '
        <link rel="stylesheet" media="print" type="text/css" href="' . $CFG->wwwroot . '/styles/print.css">
        <button onclick="perform_appsearch(' . $pagenum . ');" class="alike dontprint" title="Return to Staff Applications">
            Return to Staff Applications
        </button>';

    if ($archive = get_db_result("SELECT * FROM events_staff_archive WHERE staffid = ||staffid|| ORDER BY year", ["staffid" => $staffid])) {
        $i = -1;
        $values = new \stdClass;
        while ($vals = fetch_row($archive)) {
            $i++;
            $values->$i = new \stdClass;
            $values->$i->year = $vals["year"];
        }
        $year = $year ? $year : $values->$i->year; // default to most recent.

        $params = [
            "properties" => [
                "name" => "year",
                "id" => "year_$staffid",
            ],
            "values" => $values,
            "valuename" => "year",
            "selected" => $year,
        ];
        $returnme .= "<br />" . make_select($params) . "<br />";

        ajaxapi([
            "if" => "$('#year_$staffid').val() > 0",
            "id" => "year_$staffid",
            "url" => "/features/events/events_ajax.php",
            "data" => ["action" => "show_staff_app", "staffid" => $staffid, "pagenum" => $pagenum, "year" => "js|| $('#year_$staffid').val() ||js", "searchwords" => "js||encodeURIComponent('$searchwords')||js"],
            "display" => "searchcontainer",
            "loading" => "loading_overlay",
            "event" => "change",
        ]);
    }

    if ($row = get_db_row("SELECT * FROM events_staff_archive WHERE staffid='$staffid' AND year='$year'")) {
        $returnme .= '<input style="float:right;" class="dontprint" type="button" value="Print" onclick="window.print(); return false;" />
                        <p style="font-size:.95em;" class="print">
                        ' . staff_application_form($row, true) . '
                        </p>';
    } else {
        $returnme .= "<h3>No Application on Record</h3>";
    }
    ajax_return($returnme);
}

function event_save_staffapp() {
global $CFG, $USER;
    $userid = $USER->userid;
    $staffid = clean_myvar_opt("staffid", "int", false);

    // Get pageid.
    $pageid = get_pageid();

    if ($pageid) {
        $return = $error = "";
        try {
            start_db_transaction();
            $params = [
                "userid" => $USER->userid,
                "pageid" => $pageid,
                "name" => nameize(clean_myvar_req("name", "string")),
                "phone" => preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", clean_myvar_opt("phone", "string", ""))), 2),
                "dateofbirth" => strtotime(clean_myvar_opt("dateofbirth", "string", "")),
                "address" => clean_myvar_req("address", "string"),
                "agerange" => clean_myvar_req("agerange", "int"),
                "cocmember" => clean_myvar_opt("cocmember", "int", 0),
                "congregation" => clean_myvar_req("congregation", "string"),
                "priorwork" => clean_myvar_opt("priorwork", "int", 0),
                "q1_1" => clean_myvar_req("q1_1", "int"), "q1_2" => clean_myvar_req("q1_2", "int"), "q1_3" => clean_myvar_req("q1_3", "int"),
                "q2_1" => clean_myvar_req("q2_1", "int"), "q2_2" => clean_myvar_req("q2_2", "int"), "q2_3" => clean_myvar_opt("q2_3", "string", ""),
                "parentalconsent" => clean_myvar_opt("parentalconsent", "string", ""),
                "parentalconsentsig" => clean_myvar_opt("parentalconsentsig", "string", ""),
                "workerconsent" => clean_myvar_opt("workerconsent", "string", ""),
                "workerconsentsig" => clean_myvar_opt("workerconsentsig", "string", ""),
                "workerconsentdate" => strtotime(clean_myvar_opt("workerconsentdate", "string", "")),
                "ref1name" => nameize(clean_myvar_req("ref1name", "string")),
                "ref1relationship" => clean_myvar_req("ref1relationship", "string"),
                "ref1phone" => preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", clean_myvar_req("ref1phone", "string"))), 2),
                "ref2name" => nameize(clean_myvar_req("ref2name", "string")),
                "ref2relationship" => clean_myvar_req("ref2relationship", "string"),
                "ref2phone" => preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", clean_myvar_req("ref2phone", "string"))), 2),
                "ref3name" => nameize(clean_myvar_req("ref3name", "string")),
                "ref3relationship" => clean_myvar_req("ref2relationship", "string"),
                "ref3phone" => preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", clean_myvar_req("ref3phone", "string"))), 2),
            ];

            $newid = false;
            if ($staffid) { // Update / Edit staff app
                $params["staffid"] = $staffid;
                execute_db_sql(fetch_template("dbsql/events.sql", "update_staff_app", "events"), $params);
                $subject = "Application Updated";
            } else { // New staff app
                $newid = execute_db_sql(fetch_template("dbsql/events.sql", "insert_staff_app", "events"), $params);
                $subject = "Application Complete";
            }

            $staffid = $newid ? $newid : ($staffid ? $staffid : false);
            // Update the staff archives.
            if ($staffid) {
                $staff = get_db_row(fetch_template("dbsql/events.sql", "get_staff_app", "events"), ["staffid" => $staffid]);
                $params["bgcheckpass"] = $staff["bgcheckpass"];
                $params["bgcheckpassdate"] = $staff["bgcheckpassdate"];
                $params["year"] = date("Y");
                $params["staffid"] = $staffid; // Make sure this is set.

                if (get_db_row(fetch_template("dbsql/events.sql", "get_staff_by_year", "events"), ["staffid" => $staffid, "pageid" => $pageid, "year" => $params["year"]])) {
                    $SQL = fetch_template("dbsql/events.sql", "update_staff_app_archive", "events");
                } else {
                    $SQL = fetch_template("dbsql/events.sql", "insert_staff_app_archive", "events");
                }

                execute_db_sql($SQL, $params);

                commit_db_transaction();

                log_entry("event", $pageid, $subject);

                $emailnotice = (object)[
                    "email" => $CFG->siteemail,
                    "fname" => $CFG->sitename,
                    "lname" => "",
                ];

                //Requesting email setup
                $name = stripslashes($params["name"]);
                $message = "<strong>$name has applied to work</strong>";

                //Send email to the requester letting them know we received the request
                //@send_email($emailnotice, $emailnotice, $subject, $message);

                $backgroundchecklink = '';
                $featureid = "*";
                if (!$settings = fetch_settings("events", $featureid, $pageid)) {
                    save_batch_settings(default_settings("events", $pageid, $featureid));
                    $settings = fetch_settings("events", $featureid, $pageid);
                }

                $linkurl = $settings->events->$featureid->bgcheck_url->setting;

                $status = empty($staff["bgcheckpass"]) ? false : (time() - $staff["bgcheckpassdate"] > ($settings->events->$featureid->bgcheck_years->setting * 365 * 24 * 60 * 60) ? false : true);

                $eighteen = 18 * 365 * 24 * 60 * 60; // 18 years in seconds
                $backgroundchecklink = ((time() - $params["dateofbirth"]) < $eighteen) || ($status || empty($linkurl)) ? '' : '
                    <br /><br />
                    If you have not already done so, please complete a background check.<br />
                    <h2><a href="' . $linkurl . '">Submit a background check</a></h2>';

                $return = '
                    <div style="text-align:center;">
                        <h1>' . $subject . '</h1>
                        ' . $backgroundchecklink . '
                    </div>';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            rollback_db_transaction($error);
            $return = '
                <div style="text-align:center;">
                    <h1>Failed to save application.</h1>
                </div>';
        }
    } else {
        $return = '
            <div style="text-align:center;">
                <h1>The page has timed out and the form could not be save for security reasons.  Please try again.</h1>
            </div>';
    }

    ajax_return($return, $error);
}

function export_staffapp() {
global $MYVARS, $CFG, $USER;
    $year = clean_myvar_opt("year", "int", date("Y"));
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

    if ($applications = get_db_result(fetch_template("dbsql/events.sql", "get_all_staff_by_year", "events"), ["pageid" => $pageid, "year" => $year])) {
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

    ajax_return('<iframe src="' . $CFG->wwwroot . '/scripts/download.php?file=' . create_file("staffapps($year).csv", $CSV) . '"></iframe>');
}

function get_promocode_manager_ajax() {
    global $PAGE;
    $setid = clean_myvar_opt("setid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", false);

    if (!$pageid) {
        if ($setid) {
            $set = get_promocode_set($setid);
            $pageid = $set["pageid"];
        } else {
            $pageid = $PAGE->id;
        }
    }

    ajax_return(get_promocode_manager($pageid));
}

function edit_promocode_set() {
    $setid = clean_myvar_req("setid", "int");

    $display_codes = ""; $setname = "";
    if (!empty($codes = get_promocode_set_array($setid))) {
        $set = get_promocode_set($setid);
        $setname = $set["setname"];
        ajaxapi([
            "id" => "save_promocode",
            "paramlist" => "codeid",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "save_promocode",
                "codeid" => "js||codeid||js",
                "setid" => $setid,
                "pageid" => $set["pageid"],
            ],
            "reqstring" => "codejs||codeid||js",
            "display" => "promocodemanager",
            "loading" => "loading_overlay",
            "event" => "none",
        ]);

        ajaxapi([
            "id" => "delete_promocode",
            "if" => "confirm('Are you sure you want to delete this code?')",
            "paramlist" => "codeid",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "delete_promocode",
                "codeid" => "js||codeid||js",
                "setid" => $setid,
                "pageid" => $set["pageid"],
            ],
            "display" => "promocodemanager",
            "loading" => "loading_overlay",
            "event" => "none",
        ]);

        foreach ($codes as $code) {
            $code["dollartype"] = strstr($code["reduction"], "%") ? "" : "selected";
            $code["percenttype"] = strstr($code["reduction"], "%") ? "selected" : "";
            $code["reduction"] = str_replace("%", "", $code["reduction"]);
            $display_codes .= fill_template("tmp/events.template", "promocode_code", "events", ["code" => $code]);
        }
    }

    ajaxapi([
        "id" => "get_promocode_manager_ajax",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "get_promocode_manager_ajax",
            "setid" => $setid,
        ],
        "display" => "promocodemanager",
        "loading" => "loading_overlay",
    ]);

    ajaxapi([
        "id" => "add_promocode_form",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "add_promocode_form",
            "setid" => $setid,
        ],
        "display" => "promocodemanager",
        "loading" => "loading_overlay",
    ]);

    $manager = fill_template("tmp/events.template", "promocodes_manager_code_editor", "events", ["setname" => $setname, "setid" => $setid, "codes" => $display_codes]);
    ajax_return($manager);
}

function add_promoset_form() {
    $pageid = clean_myvar_req("pageid", "int");

    // Back button.
    ajaxapi([
        "id" => "back_to_promo_sets",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "get_promocode_manager_ajax",
            "pageid" => $pageid,
        ],
        "display" => "promocodemanager",
        "loading" => "loading_overlay",
    ]);

    // Save button.
    ajaxapi([
        "id" => "add_promoset",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "add_promoset",
            "pageid" => $pageid,
        ],
        "reqstring" => "new_promo_set",
        "display" => "promocodemanager",
        "loading" => "loading_overlay",
    ]);

    $return = fill_template("tmp/events.template", "add_promoset_form", "events");
    ajax_return($return);
}

function edit_promoset_form() {
    $pageid = clean_myvar_req("pageid", "int");
    $setid = clean_myvar_req("setid", "int");
    $set = get_promocode_set($setid);
    $setname = $set["setname"];

    // Back button.
    ajaxapi([
        "id" => "back_to_promo_sets",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "get_promocode_manager_ajax",
            "pageid" => $pageid,
        ],
        "display" => "promocodemanager",
        "loading" => "loading_overlay",
    ]);

    // Save button.
    ajaxapi([
        "id" => "edit_promoset",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "edit_promoset",
            "setid" => $setid,
        ],
        "reqstring" => "edit_promo_set",
        "display" => "promocodemanager",
        "loading" => "loading_overlay",
    ]);

    $return = fill_template("tmp/events.template", "edit_promoset_form", "events", ["setname" => $setname]);
    ajax_return($return);
}

function edit_promoset() {
    $name = clean_myvar_req("setname", "string");
    $setid = clean_myvar_req("setid", "int");
    $set = get_promocode_set($setid);

    try {
        start_db_transaction();

        $SQL = fetch_template("dbsql/events.sql", "update_promocode_set", "events");
        execute_db_sql($SQL, [
            "setid" => $setid,
            "setname" => $name,
        ]);

        log_entry("events", $set["pageid"], "Promocode Set edited");
        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    ajax_return(get_promocode_manager($set["pageid"]));
}

function delete_promoset() {
    $setid = clean_myvar_req("setid", "int");
    $set = get_promocode_set($setid);
    $pageid = $set["pageid"];

    try {
        start_db_transaction();

        $templates = [
            [
                "file" => "dbsql/events.sql",
                "feature" => "events",
                "subsection" => [
                    "delete_promocode_set",
                    "delete_promocode_set_codes",
                ],
            ]
        ];
		execute_db_sqls(fetch_template_set($templates), ["setid" => $setid]);

        log_entry("events", $pageid, "Promocode Set deleted");
        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    ajax_return(get_promocode_manager($pageid));
}

function add_promoset() {
    $pageid = clean_myvar_req("pageid", "int");
    $name = clean_myvar_req("setname", "string");

    try {
        start_db_transaction();

        $SQL = fetch_template("dbsql/events.sql", "insert_promocode_set", "events");
        execute_db_sql($SQL, [
            "pageid" => $pageid,
            "setname" => $name,
            "created" => get_timestamp(),
        ]);

        log_entry("events", $pageid, "Promocode Set created");
        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    ajax_return(get_promocode_manager($pageid));
}

function add_promocode_form() {
    $setid = clean_myvar_req("setid", "int");
    $set = get_promocode_set($setid);

    // Back button.
    ajaxapi([
        "id" => "back_to_promo_set",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "edit_promocode_set",
            "setid" => $setid,
        ],
        "display" => "promocodemanager",
        "loading" => "loading_overlay",
    ]);

    // Save button.
    ajaxapi([
        "id" => "add_promocode",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "add_promocode",
            "setid" => $setid,
            "pageid" => $set["pageid"],
        ],
        "reqstring" => "new_promo_code",
        "display" => "promocodemanager",
        "loading" => "loading_overlay",
    ]);

    $return = fill_template("tmp/events.template", "add_promocode_form", "events");
    ajax_return($return);
}

function add_promocode() {
    $setid = clean_myvar_req("setid", "int");
    $set = get_promocode_set($setid);

    $name = clean_myvar_req("promoname", "string");
    $code = clean_myvar_req("promocode", "string");
    $reduction = clean_myvar_req("promoreduction", "float");
    $type = clean_myvar_req("promotype", "string");

    if ($type === "percent") {
        $reduction = $reduction . "%";
    }

    try {
        start_db_transaction();

        $SQL = fetch_template("dbsql/events.sql", "insert_promocode", "events");
        execute_db_sql($SQL, [
            "setid" => $setid,
            "codename" => $name,
            "code" => preg_replace('/\s+/', '', strtolower($code)),
            "reduction" => $reduction,
            "created" => get_timestamp(),
        ]);

        log_entry("events", $set["pageid"], "Promocode created");
        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    edit_promocode_set();
}

function delete_promocode() {
    global $PAGE;
    $codeid = clean_myvar_req("codeid", "int");
    $setid = clean_myvar_req("setid", "int");
    $pageid = clean_myvar_opt("pageid", "int", $PAGE->id);

    // delete promocode
    try {
        start_db_transaction();

        $SQL = fetch_template("dbsql/events.sql", "delete_promocode", "events");
        execute_db_sql($SQL, [
            "codeid" => $codeid,
        ]);
        log_entry("events", $pageid, "Promocode deleted");
        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    edit_promocode_set();
}

function save_promocode() {
    global $PAGE;
    $codeid = clean_myvar_req("codeid", "int");
    $setid = clean_myvar_req("setid", "int");
    $pageid = clean_myvar_opt("pageid", "int", $PAGE->id);

    // save promocode
    $name = clean_myvar_req("promoname", "string");
    $code = clean_myvar_req("promocode", "string");
    $reduction = clean_myvar_req("promoreduction", "float");
    $type = clean_myvar_req("promotype", "string");

    if ($type === "percent") {
        $reduction = $reduction . "%";
    }

    try {
        start_db_transaction();

        $SQL = fetch_template("dbsql/events.sql", "update_promocode", "events");
        execute_db_sql($SQL, [
            "codeid" => $codeid,
            "code" => preg_replace('/\s+/', '', strtolower($code)),
            "codename" => $name,
            "reduction" => $reduction,
        ]);
        log_entry("events", $pageid, "Promocode added");
        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    edit_promocode_set();
}

?>