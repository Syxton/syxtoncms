<?php
/***************************************************************************
* user.php - User thickbox page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/06/2021
* Revision: 0.4.6
***************************************************************************/

include('header.php');

$params = array("dirroot" => $CFG->directory, "directory" => (empty($CFG->directory) ? '' : $CFG->directory . '/'), "wwwroot" => $CFG->wwwroot);
echo template_use("tmp/page.template", $params, "page_js_css");

callfunction();

echo template_use("tmp/page.template", array(), "end_of_page_template");

function new_user() {
global $MYVARS, $CFG;
	if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$params = array("email_req" => get_error_message('valid_req_email'), "email_valid" => get_error_message('valid_email_invalid'), "email_unique" => get_error_message('valid_email_unique'), "email_help" => get_help("input_email"),
									"fname_req" => get_error_message('valid_req_fname'), "fname_help" => get_help("input_fname"),
									"lname_req" => get_error_message('valid_req_lname'), "lname_help" => get_help("input_lname"),
									"password_req" => get_error_message('valid_req_password'), "password_length" => get_error_message('valid_password_length'), "password_help" => get_help("input_password"),
									"vpassword_req" => get_error_message('valid_req_vpassword'), "vpassword_match" => get_error_message('valid_vpassword_match'), "vpassword_help" => get_help("input_vpassword"));

	echo create_validation_script("signup_form" , template_use("tmp/user.template", array(), "new_user_validation"));
  echo format_popup(template_use("tmp/user.template", $params, "new_user_template"), $CFG->sitename.' Signup',"500px");
}

function reset_password() {
global $MYVARS, $PAGE, $CFG;
	$userid = $MYVARS->GET["userid"];
	$alternate = get_db_row("SELECT * FROM users WHERE userid='$userid' AND alternate='".$MYVARS->GET["alternate"]."'") ? true : false;
	$params = array("siteid" => $CFG->SITEID, "userid" => $userid, "wwwroot" => $CFG->wwwroot, "directory" => (empty($CFG->directory) ? '' : $CFG->directory . '/'), "alternate" => $alternate);

	if ($alternate) {
		if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

		$params["password_req"] = get_error_message('valid_req_password');
		$params["password_length"] = get_error_message('valid_password_length');
		$params["password_help"] = get_help("input_password");
		$params["vpassword_req"] = get_error_message('valid_req_password');
		$params["vpassword_match"] = get_error_message('valid_vpassword_match');
		$params["vpassword_help"] = get_help("input_vpassword");

		// Main Layout
		$params2 = array("mainmast" => page_masthead(true),
									 	 "middlecontents" => template_use("tmp/user.template", $params, "reset_password_template") .
													 							 create_validation_script("password_request_form" , template_use("tmp/user.template", $params, "reset_password_validation_template")));

		echo template_use("tmp/index.template", $params2, "mainlayout_template");
  } else {
		echo template_use("tmp/user.template", array("alternate" => false), "reset_password_template");
	}
}

function change_profile() {
global $MYVARS, $CFG, $USER, $PAGE;
	$params = array("siteid" => $CFG->SITEID, "userid" => !empty($USER->userid), "user" => $USER);
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
global $MYVARS, $CFG;
	if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$params["email_req"] = get_error_message('valid_req_email');
	$params["email_valid"] = get_error_message('valid_email_invalid');
	$params["email_used"] = get_error_message('valid_email_used');
	$params["email_help"] = get_help("input_email");

	echo create_validation_script("password_request_form", template_use("tmp/user.template", array(), "forgot_password_validation_template"));
  echo format_popup(template_use("tmp/user.template", $params, "forgot_password_template"),'Forgot Password',"500px");
}

function user_alerts() {
global $MYVARS, $CFG, $USER;
	echo template_use("tmp/user.template", array("alerts" => get_user_alerts($MYVARS->GET["userid"],false, true)), "user_alerts_template");
}
?>
