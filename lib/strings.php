<?php
/***************************************************************************
* global strings.php
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/08/2025
* Revision: 0.0.1
***************************************************************************/

return (object) [
	// COMMON STRINGS
	"yes" => "Yes",
	"no" => "No",
	"firstlastemail" => "{fname} {lname} ({email})",
	"firstlast" => "{fname} {lname}",

	// PAGE HINTS
	"input_page_name" => "This is the name of the page.  It will be displayed in links and in the page HTML header.  It is search indexed.",
	"input_page_tags" => "List of words that would best be used to find your page in a search.",
	"input_page_summary" => "This is a quick summary of the purpose of the page.  It is search indexed.",
	"input_page_default_role" => "New users will automatically be given this role.  Default: Guest",
	"input_page_opendoor" => "Allow people into your page without an invitation.  (logged in users only)",
	"input_page_siteviewable" => "Allow people into your page without an invitation.  (All website visitors)",
	"input_page_menulink" => "Link to the page in the sites main menu.",
	"input_page_visitors_hide" => "Link to the page in the sites main menu.",

	// DEFAULT HINTS BY CONTENT
	"input_username" => "Please enter username or email.",
	"input_full_name" => "Please enter full name.",
	"input_email" => "Please enter an email address.",
	"input_login" => "This is the email address that will be used to log into the website.",
	"input_password" => "Password must be <strong>at least 6 characters long</strong>",
	"input_password2" => "Please enter a password.",
	"input_vpassword" => "Please enter the password again.",
	"input_fname" => "Please enter a full first name, not just an initial.",
	"input_lname" => "Please enter a full last name, not just an initial.",
	"input_middlename" => "Please enter a full middle name, not just an initial.",
	"input_middle_initial" => "Please enter a middle initial.",
	"input_address" => "Please enter a full address: <strong>(ex. 55 Oak Street, Marshall, IL 62441)</strong>",
	"input_address1" => "Please enter a street address: <strong>(ex. 55 Oak Street)</strong>",
	"input_address2" => "Please enter additional address info",
	"input_city" => "Please enter a city.",
	"input_state" => "Please enter a state.",
	"select_state_" => "Please select a state.",
	"input_zip" => "Please enter a zip code.",
	"select_gender" => "Please select a gender.",
	"select_yesno" => "Please select Yes or No.",

	// DEFAULT HINTS BY TYPE
	"input_default_text" => "Please enter a value.",
	"input_default_email" => "Please enter an email address.",
	"input_default_date" => "Please enter a date.",
	"input_default_date_slashes" => "Please enter a valid date format <strong>(mm/dd/yyyy)</strong>.",
	"input_default_date_dashes" => "Please enter a valid date format <strong>(mm-dd-yyyy)</strong>.",
	"input_default_time" => "Please enter a time.",
	"input_default_phone" => "Please enter a phone number. <strong>(xxx xxx xxxx)</strong>",
	"input_default_url" => "Please enter a url.",
	"input_default_number" => "Please enter a number.",
	"input_default_int" => "Please enter a number.",
	"input_default_float" => "Please enter a valid number.",
	"input_default_string" => "Please enter a simple text.",
	"input_default_textarea" => "Please enter text.",
	"input_default_select" => "Please select an option.",
	"input_default_checkbox" => "Please select an option.",
	"input_default_radio" => "Please select an option.",
	"input_default_password" => "Please enter a password.",

	// Login Errors
	"invalid_login" => "Username or password was incorrect.<br />Please try again.",

	// Permission Errors
	"generic_permissions" => "You do not have the correct permissions to do this.",
	"no_permission" => "You do not have the <strong>{0}</strong> permission.",
	"generic_error" => "Congratulations, you found a bug.  Please inform the site admin. " . $CFG->siteemail,
	"generic_db_error" => "Database Error. Please inform the site admin. " . $CFG->siteemail,

	// User Creation
	"user_not_added" => "The user could not be created correctly.",
	"user_not_emailed" => "The user account has been created, but the confirmation email has failed to send.  Please inform the site admin. " . $CFG->siteemail,

	// Page Errors
	"no_function" => "The function: <strong>{0}</strong> could not be found.",
	"no_data" => "The expected data of {0} could not be found.",
	"pagenotfound" => "Page not found.",

	// Polls <--- Needs moved to polls strings.
	"no_poll_permissions" => "You do not have the correct permissions to view this poll.",

	// Validation Errors
	"input_required" => "This field is required.",
	"invalid_email" => "Please enter a valid email address.",
	"invalid_email_unique" => "This email address is already in use.",
	"invalid_email_notfound" => "Could not find a user with that email.",
	"invalid_phone" => "Please enter a valid phone number.",
	"invalid_date" => "Please enter a valid date.",
	"invalid_time" => "Please enter a valid time.",
	"invalid_url" => "Please enter a valid url.",
	"invalid_password" => "Please enter a valid password.",
	"invalid_password_match" => "Must match the password field.",
	"invalid_length" => "Must be at least {0} characters long.",
	"invalid_number" => "Please enter a valid number.",
];
?>