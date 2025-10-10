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

        $vars = "?message=" . urlencode($message) . "&subject=" . urlencode($subject) . "&emaillist=" . urlencode($emaillist);
        $returnme = '
            <iframe src="/features/adminpanel/email_sender_progress.php' . $vars . '"
                    style="width:100%;height:250px;border:none;"
                    frameborder="0">
            </iframe>';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($returnme, $error);
}