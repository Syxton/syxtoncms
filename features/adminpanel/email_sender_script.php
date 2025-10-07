<?php
/***************************************************************************
* email_sender_script.php - AJAX mass email script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 10/07/2025
* Revision: 0.0.1
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

update_user_cookie();

if (!is_siteadmin($USER->userid)) { trigger_error(getlang("generic_permissions"), E_USER_WARNING); return; }

callfunction();

function admin_email_send() {
    global $CFG, $USER;

    // logged in
    $loggedin = is_logged_in() ? true : false;
    $userid = $loggedin ? $USER->userid : "";

    // is a site admin
    $admin = $loggedin && is_siteadmin($userid) ? true : false;

    if (!$admin) { trigger_error(getlang("generic_permissions"), E_USER_WARNING); return; }

    $error = "";
    $returnme = "";
    $success = 0;
    $failed = 0;

    try {
        $message = clean_myvar_req("message", "html");
        $subject = clean_myvar_req("subject", "html");
        $emaillist = clean_myvar_req("email", "string");

        $fromuser = (object) [
            "email" => $CFG->siteemail,
            "fname" => $CFG->sitename,
            "lname" => "",
        ];

        $emaillist = preg_split('/[\n,\r]+/', $emaillist, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($emaillist as $email) {
            if (strpos($email, '@') !== false) {
                $touser = (object) [
                    "email" => trim($email),
                ];
                if (send_email($touser, $fromuser, $subject, $message)) {
                    $success++;
                } else {
                    $failed++;
                }
                $randomwait = rand(1, 5);
                usleep($randomwait * 100000); // waits tenths of a second
            }
        }

        $returnme = '
            <div>
                <h3>
                    Send Status:
                </h3>
                <strong>
                    Success: ' . $success . '
                </strong>
                <br />
                <strong>
                    Failed: ' . $failed . '
                </strong>
            </div>
        ';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($returnme, $error);
}