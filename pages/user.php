<?php
/***************************************************************************
* user.php - User thickbox page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.4.6
***************************************************************************/
include('header.php');

callfunction();

echo fetch_template("tmp/page.template", "end_of_page_template");

function new_user() {
global $MYVARS, $CFG;
	if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$params = [
		"email_req" => error_string('valid_req_email'), "email_valid" => error_string('valid_email_invalid'),
		"email_unique" => error_string('valid_email_unique'), "email_help" => get_help("input_email"),
		"fname_req" => error_string('valid_req_fname'), "fname_help" => get_help("input_fname"),
		"lname_req" => error_string('valid_req_lname'), "lname_help" => get_help("input_lname"),
		"password_req" => error_string('valid_req_password'), "password_length" => error_string('valid_password_length'),
		"password_help" => get_help("input_password"), "vpassword_req" => error_string('valid_req_vpassword'),
		"vpassword_match" => error_string('valid_vpassword_match'), "vpassword_help" => get_help("input_vpassword"),
	];

	echo create_validation_script("signup_form", fetch_template("tmp/user.template", "new_user_validation"));
	echo format_popup(fill_template("tmp/user.template", "new_user_template", false, $params), $CFG->sitename . ' Signup');
}

function reset_password() {
global $PAGE, $CFG;
	//Not an ajax call so full start of new page is needed.  This is pretty rare.
	$PAGE->title = "Reset Password";
	include($CFG->dirroot . '/header.html');

	echo get_js_tags(["validate"]);
	$userid = clean_myvar_req("userid", "int");
	$password = clean_myvar_req("alternate", "string");

	$alternate = get_db_row(fetch_template("dbsql/db.sql", "authenticate_alt_userid"), ["userid" => $userid, "alternate" => $password]) ? true : false;
	if ($alternate) {
		if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }
		$params = [];
		$params["siteid"] = $CFG->SITEID;
		$params["userid"] = $userid;
		$params["wwwroot"] = $CFG->wwwroot;
		$params["directory"] = (empty($CFG->directory) ? '' : $CFG->directory . '/');
		$params["alternate"] = $alternate;
		$params["password_req"] = error_string('valid_req_password');
		$params["password_length"] = error_string('valid_password_length');
		$params["password_help"] = get_help("input_password");
		$params["vpassword_req"] = error_string('valid_req_password');
		$params["vpassword_match"] = error_string('valid_vpassword_match');
		$params["vpassword_help"] = get_help("input_vpassword");

		$password_validate_form = fill_template("tmp/user.template", "reset_password_validation_template", false, $params);
		$validation_script = create_validation_script("password_reset_form", $password_validate_form);
		$middle_contents = fill_template("tmp/user.template", "reset_password_template", false, $params) . $validation_script;

		// Main Layout
		echo fill_template("tmp/index.template", "mainlayout_template", false, ["mainmast" => page_masthead(true), "middlecontents" => $middle_contents]);

		// End Page
		include('../footer.html');

		log_entry("user", $userid, "Password Reset Form Viewed");
	} else {
		echo fill_template("tmp/user.template", "reset_password_template", ["alternate" => false]);
		log_entry("user", $userid, "Defunct Password Reset Link Clicked");
	}
}

function change_profile() {
global $CFG, $USER;
	$params = ["siteid" => $CFG->SITEID, "userid" => !empty($USER->userid), "user" => $USER];
	if (!empty($USER->userid)) {
		if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

		$params["fname_help"] = get_help("input_fname");
		$params["lname_help"] = get_help("input_lname");
		$params["email_req"] = error_string('valid_req_email');
		$params["email_valid"] = error_string('valid_email_invalid');
		$params["email_unique"] = error_string('valid_email_unique');
		$params["email_help"] = get_help("input_email");
		$params["password_length"] = error_string('valid_password_length');
		$params["password_help"] = get_help("input_password");
		$params["vpassword_match"] = error_string('valid_vpassword_match');
		$params["vpassword_help"] = get_help("input_vpassword");
		echo create_validation_script("profile_change_form", fill_template("tmp/user.template", "change_profile_validation_template", false, $params));
  		echo format_popup(fill_template("tmp/user.template", "change_profile_template", false, $params), 'Edit Profile', "500px");
	} else {
		echo fill_template("tmp/user.template", "change_profile_template", false, $params);
	}
}

function forgot_password_form() {
global $CFG;
	if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$params = [
		"email_req" => error_string('valid_req_email'),
		"email_valid" => error_string('valid_email_invalid'),
		"email_used" => error_string('valid_email_used'),
		"email_help" => get_help("input_email"),
	];

	echo create_validation_script("password_request_form", fetch_template("tmp/user.template", "forgot_password_validation_template"));
	echo format_popup(fill_template("tmp/user.template", "forgot_password_form_template", false, $params), 'Forgot Password', "500px");
}

function user_alerts() {
	$userid = clean_myvar_req("userid", "int");
	echo fill_template("tmp/user.template", "user_alerts_template", false, ["alerts" => get_user_alerts($userid, false)]);
}
?>