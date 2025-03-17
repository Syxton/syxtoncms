<?php
/***************************************************************************
* helplib.php - Help library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.2
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define("HELPLIB", true);

$HELP = new \stdClass;

//PAGE EDITING HINTS
$HELP->input_page_name = "This is the name of the page.  It will be displayed in links and in the page HTML header.  It is search indexed.";
$HELP->input_page_tags = "List of words that would best be used to find your page in a search.";
$HELP->input_page_summary = "This is a quick summary of the purpose of the page.  It is search indexed.";
$HELP->input_page_default_role = "New users will automatically be given this role.  Default: Guest";
$HELP->input_page_opendoor = "Allow people into your page without an invitation.  (logged in users only)";
$HELP->input_page_siteviewable = "Allow people into your page without an invitation.  (All website visitors)";
$HELP->input_page_menulink = "Link to the page in the sites main menu.";
$HELP->input_page_visitors_hide = "Link to the page in the sites main menu.";

//STANDARD INPUT FIELDS HINTS
$HELP->input_username = "Please enter your username/email.";
$HELP->input_email = "This is the email address that will be used to log into the website.";
$HELP->input_password = "Password must be <strong>at least 6 characters long</strong>";
$HELP->input_password2 = "Please enter your password.";
$HELP->input_vpassword = "This must match your previously entered password.</strong>";
$HELP->input_fname = "Please use your full first name, not just an initial.";
$HELP->input_lname = "Please use your full last name, not just an initial.";

// DEFAULT HINTS
$HELP->input_default_text = "Please enter a value.";
$HELP->input_default_email = "Please enter a valid email address.";
$HELP->input_default_date = "Please enter a valid date.";
$HELP->input_default_time = "Please enter a valid time.";
$HELP->input_default_phone = "Please enter a valid phone number.";
$HELP->input_default_url = "Please enter a valid url.";
$HELP->input_default_number = "Please enter a valid number.";
$HELP->input_default_int = "Please enter a valid number.";
$HELP->input_default_float = "Please enter a valid number.";
$HELP->input_default_string = "Please enter a valid string.";
$HELP->input_default_textarea = "Please enter a valid string.";
$HELP->input_default_select = "Please select an option.";
$HELP->input_default_checkbox = "Please select an option.";
$HELP->input_default_radio = "Please select an option.";
$HELP->input_default_password = "Please enter a password.";

//FORUM HINTS
$HELP->new_category = "Please type the new name for this category.";

function get_help($help) {
    global $CFG, $HELP;
    $helpParams = explode(":", $help);
    $helpString = $helpParams[0];
    if (count($helpParams) === 3) {
        $feature = $helpParams[1];
        $langFile = $helpParams[2];
        $langFilePath = $CFG->dirroot . "/features/$feature/$langFile/lang.php";
        if (file_exists($langFilePath)) {
            include($langFilePath);
            return $HELP->$helpString;
        }
    } elseif (count($helpParams) === 2) {
        $feature = $helpParams[1];
        $langFilePath = $CFG->dirroot . "/features/$feature/lang.php";
        if (file_exists($langFilePath)) {
            include($langFilePath);
            return $HELP->$helpString;
        }
    } elseif (isset($HELP->$help)) {
        return $HELP->$help;
    }
    return false;
}
?>