<?php
/***************************************************************************
* user.php - User thickbox page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 04/10/2023
* Revision: 0.4.6
***************************************************************************/
include('header.php');

callfunction();

echo template_use("tmp/page.template", [], "end_of_page_template");

function new_user() {
global $MYVARS, $CFG;
	if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$params = ["email_req" => get_error_message('valid_req_email'), "email_valid" => get_error_message('valid_email_invalid'),
	           "email_unique" => get_error_message('valid_email_unique'), "email_help" => get_help("input_email"),
			   "fname_req" => get_error_message('valid_req_fname'), "fname_help" => get_help("input_fname"),
			   "lname_req" => get_error_message('valid_req_lname'), "lname_help" => get_help("input_lname"),
			   "password_req" => get_error_message('valid_req_password'), "password_length" => get_error_message('valid_password_length'),
			   "password_help" => get_help("input_password"), "vpassword_req" => get_error_message('valid_req_vpassword'),
			   "vpassword_match" => get_error_message('valid_vpassword_match'), "vpassword_help" => get_help("input_vpassword")];

	echo create_validation_script("signup_form" , template_use("tmp/user.template", [], "new_user_validation"));
  	echo format_popup(template_use("tmp/user.template", $params, "new_user_template"), $CFG->sitename . ' Signup',"500px");
}

function reset_password() {
global $MYVARS, $PAGE, $CFG;
	//Not an ajax call so full start of new page is needed.  This is pretty rare.
	$PAGE->title = "Reset Password";
    include($CFG->dirroot . '/header.html');
	echo get_js_tags(["validate"]);

	$userid = $MYVARS->GET["userid"];
	$alternate = get_db_row("SELECT * FROM users WHERE userid='$userid' AND alternate='" . $MYVARS->GET["alternate"] . "'") ? true : false;
	$params = ["siteid" => $CFG->SITEID, "userid" => $userid, "wwwroot" => $CFG->wwwroot, "directory" => (empty($CFG->directory) ? '' : $CFG->directory . '/'), "alternate" => $alternate];

	if ($alternate) {
		if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

		$params["password_req"] = get_error_message('valid_req_password');
		$params["password_length"] = get_error_message('valid_password_length');
		$params["password_help"] = get_help("input_password");
		$params["vpassword_req"] = get_error_message('valid_req_password');
		$params["vpassword_match"] = get_error_message('valid_vpassword_match');
		$params["vpassword_help"] = get_help("input_vpassword");

		$password_validate_form = template_use("tmp/user.template", $params, "reset_password_validation_template");
		$validation_script = create_validation_script("password_reset_form" , $password_validate_form);
		$middle_contents = template_use("tmp/user.template", $params, "reset_password_template") . $validation_script;

		// Main Layout
		$params2 = ["mainmast" => page_masthead(true), "middlecontents" => $middle_contents];
		echo template_use("tmp/index.template", $params2, "mainlayout_template");

		// End Page
		include('../footer.html');

		// Log
		log_entry("user", $userid, "Password Reset Form Viewed");
	} else {
		echo template_use("tmp/user.template", ["alternate" => false], "reset_password_template");

		// Log
		log_entry("user", $userid, "Defunct Password Reset Link Clicked");
	}
}

function change_profile() {
global $CFG, $USER;
	$params = ["siteid" => $CFG->SITEID, "userid" => !empty($USER->userid), "user" => $USER];
	if (!empty($USER->userid)) {
		if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

		$params["fname_help"] = get_help("input_fname");
		$params["lname_help"] = get_help("input_lname");
		$params["email_req"] = get_error_message('valid_req_email');
		$params["email_valid"] = get_error_message('valid_email_invalid');
		$params["email_unique"] = get_error_message('valid_email_unique');
		$params["email_help"] = get_help("input_email");
		$params["password_length"] = get_error_message('valid_password_length');
		$params["password_help"] = get_help("input_password");
		$params["vpassword_match"] = get_error_message('valid_vpassword_match');
		$params["vpassword_help"] = get_help("input_vpassword");
		echo create_validation_script("profile_change_form", template_use("tmp/user.template", $params, "change_profile_validation_template"));
    	echo format_popup(template_use("tmp/user.template", $params, "change_profile_template"),'Edit Profile',"500px");
	} else {
		echo template_use("tmp/user.template", $params, "change_profile_template");
	}
}

function forgot_password() {
global $CFG;
	if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$params = [
		"email_req" => get_error_message('valid_req_email'),
		"email_valid" => get_error_message('valid_email_invalid'),
		"email_used" => get_error_message('valid_email_used'),
		"email_help" => get_help("input_email"),
	];

	echo create_validation_script("password_request_form", template_use("tmp/user.template", [], "forgot_password_validation_template"));
  	echo format_popup(template_use("tmp/user.template", $params, "forgot_password_form_template"), 'Forgot Password', "500px");
}

function user_alerts() {
global $MYVARS;
	echo template_use("tmp/user.template", ["alerts" => get_user_alerts($MYVARS->GET["userid"], false, true)], "user_alerts_template");
}
?>
