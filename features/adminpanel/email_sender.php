<?php
/**
 * This file is part of the Syxton CMS.
 *
 *  New mass email sender - features/adminpanel/email_sender.php
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 109/07/2025
 * Revision: 0.0.1
 ***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

global $CFG, $USER;

// logged in
$loggedin = is_logged_in() ? true : false;
$userid = $loggedin ? $USER->userid : "";

// is a site admin
$admin = $loggedin && is_siteadmin($userid) ? true : false;

if (!$admin) { trigger_error(getlang("generic_permissions"), E_USER_WARNING); return; }

// send email form.
ajaxapi([
    "id" => "emailsender",
    "if" => "$('#emails').val().length > 0",
    "url" => "/features/adminpanel/email_sender_script.php",
    "data" => [
        "action" => "admin_email_send",
        "email" => "js||$('#emails').val()||js",
        "subject" => "js||encodeURIComponent($('#subject').val())||js",
        "message" => 'js||encodeURIComponent(' . get_editor_value_javascript("mass_email") . ')||js',
    ],
    "display" => "display",
]);

echo fill_template("tmp/page.template", "start_of_page_template", false, [
    "head" => fill_template("tmp/page.template", "page_js_css", false, ["dirroot" => $CFG->directory]),
]);

echo '
    <h1 class="centered">Send Mass Email</h1>
    <br /><br />
    <div id="display" style="width: 100%;padding: 10px;box-sizing: border-box;">
        <div class="centered">
            <fieldset class="formContainer" style="text-align: left;">
                <div class="rowContainer">
                    <label for="email">Send to Addresses</label>
                    <textarea id="emails" style="width: 100%"></textarea>
                    <br />
                </div>
                <div class="rowContainer">
                    <label for="subject">Subject</label><br />
                    <input type="text" id="subject" style="width: 100%" />
                    <br />
                </div>
                ' . get_editor_box(["name" => "mass_email"]) . '
                <br />
                <input id="emailsender" type="button" value="Send Email" />
            </fieldset>
        </div>
    </div>';

echo fill_template("tmp/page.template", "end_of_page_template");
?>