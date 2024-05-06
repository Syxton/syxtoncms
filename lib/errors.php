<?php
/***************************************************************************
* errors.php - Error library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 1/29/2016
* Revision: 0.1.4
***************************************************************************/
 
unset($ERRORS);
if (!isset($LIBHEADER)) { include('header.php'); }

$ERRORS = new \stdClass;

ini_set('display_errors', '0');
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
}, E_ERROR | E_PARSE);

//Login Errors *********************************************************
	$ERRORS->no_login = "Username or password was incorrect.<br />Please try again.";

//Permission Errors *********************************************************
	$ERRORS->no_html_permissions = "You do not have the correct permissions to view this HTML content.";
	$ERRORS->no_buttons = "You have rights to 0 buttons";
    $ERRORS->generic_permissions = "You do not have the correct permissions to do this.";
    $ERRORS->no_permission = "You do not have the <strong>[0]</strong> permission.";
    $ERRORS->generic_error = "Congratulations, you found a bug.  Please inform " .$CFG->siteemail;
    $ERRORS->generic_db_error = "Congratulations, you found a database bug.  Please inform " .$CFG->siteemail;

//User Creation *********************************************************
	$ERRORS->user_not_added = "The user could not be created correctly.";

//Page Errors *********************************************************
	$ERRORS->could_not_subscribe = "You did NOT add this page successfully.";
	$ERRORS->page_not_created = "Your page was NOT created successfully.";
    $ERRORS->no_function = "The function: <strong>[0]</strong> could not be found.";
	$ERRORS->no_data = "The expected data of [0] could not be found.";
	$ERRORS->pagenotfound = "Page not found.";

//Polls *********************************************************
	$ERRORS->no_poll_permissions = "You do not have the correct permissions to view this poll.";

//Search Errors *********************************************************
	$ERRORS->search_nosearchwords = "No search words given.";

// Validation Errors *********************************************************
	//generic
	$ERRORS->valid_req = "This field is required.";
	
	//username
	$ERRORS->valid_req_username = "Please enter your username.";
	
	//first name
	$ERRORS->valid_req_fname = "Please enter your first name.";
	
	//last name
	$ERRORS->valid_req_lname = "Please enter your last name.";
	
	//email
	$ERRORS->valid_req_email = "Please enter your email address.";
	$ERRORS->valid_email_invalid = "Please enter a valid email address.";
	$ERRORS->valid_email_unique = "This email address is already in use.";
	$ERRORS->valid_email_used = "Could not find a user with that email.";
	
	//password
	$ERRORS->valid_req_password = "Please enter a password.";
	$ERRORS->valid_password_length = "Must be at least 6 characters long.";
	
	//verify password
	$ERRORS->valid_req_vpassword = "Please verify your password.";
	$ERRORS->valid_vpassword_match = "Must match the password field.";

function error_string($error, $vars = false) {
global $CFG, $ERRORS;
    $lang = explode(":", $error);
    $string = $lang[0];
    if (isset($lang[2])) {
        include($CFG->dirroot . '/features/' . $lang[1] . "/" . $lang[2] . "/lang.php");
        return $ERRORS->$string;        
    } elseif (isset($lang[1])) {
        include($CFG->dirroot . '/features/' . $lang[1] . "/lang.php");
        if ($vars) {
			return fill_template($ERRORS->$string, $vars);
		}
        return $ERRORS->$string;
    } else { 
		if ($vars) { 
			return fill_template($ERRORS->$error, $vars);
		} 
		return $ERRORS->$error;
	}
}

function fill_template($string, $vars) {
	$vars = is_array($vars) ? $vars : [$vars];
    $i = 0;
    foreach ($vars as $var) {
		// Check if $var is array.
		if (is_array($var)) {
			$allvars = "";
			foreach ($var as $v) {
				$allvars .= $v . " ";
			}
			$string = str_replace("[$i]", $allvars, $string);
		} else {
			$string = str_replace("[$i]", $var, $string);
		}
        $i++;
    }
    return $string;
}

/**
 * debugging
 *
 * This function is used to debug code. It can log the message and
 * optionally display it on the page (level 2 or above). It will not
 * display the message unless the debug setting in config.php is set
 * to a level greater than 0.
 *
 * @param string $message The message to log and possibly display
 * @param int $level The level of debugging. 0: No debugging, 1: Log only, 2: Display on page, 3: Display with backtrace
 * @param bool $forced If true, use the level value regardless of config.php
 *
 * @return string HTML to display on the page if level is 2 or above
 */
function debugging($message = '', $level = 1, $forced = false) {
    global $CFG, $USER;

    $display = "";
    $default = $CFG->debug ?? $level;
    $default = $level > $default ? $level : $default;
    $level = $forced ? $level : $default;

    if (!$level) {
        return $display;
    }

    if ($message) {
        $from = ""; $from_printable = "";

        if ($level > 2) {
            // Include backtrace if level is 2 or above or $backtrace is true
            $backtrace = debug_backtrace();
            $from = " in " . format_backtrace($backtrace, true);
            $from_printable = " in " . format_backtrace($backtrace, false);
        }

        // Log any level above 0
        error_log('Debugging: ' . $message . $from);

        $display = '<div style="background:red;padding:20px;text-align:center;">
                    ' . $message . $from_printable . '
                    </div>';

        if ($level >= 2) {
            echo $display;
            die();
        }
    }
    return $display;
}

function format_backtrace($callers, $plaintext = false) {
    // do not use $CFG->dirroot because it might not be available in destructors
    $dirroot = dirname(__DIR__);

    if (empty($callers)) {
        return '';
    }

    $from = $plaintext ? '' : '<ul style="text-align: left" data-rel="backtrace">';
    foreach ($callers as $caller) {
        if (!isset($caller['line'])) {
            $caller['line'] = '?'; // probably call_user_func()
        }
        if (!isset($caller['file'])) {
            $caller['file'] = 'unknownfile'; // probably call_user_func()
        }
        $line = $plaintext ? '* ' : '<li>';
        $line .= 'line ' . $caller['line'] . ' of ' . str_replace($dirroot, '', $caller['file']);
        if (isset($caller['function'])) {
            $line .= ': call to ';
            if (isset($caller['class'])) {
                $line .= $caller['class'] . $caller['type'];
            }
            $line .= $caller['function'] . '()';
        } else if (isset($caller['exception'])) {
            $line .= ': '.$caller['exception'].' thrown';
        }

        // Remove any non printable chars.
        $line = preg_replace('/[[:^print:]]/', '', $line);

        $line .= $plaintext ? "\n" : '</li>';
        $from .= $line;
    }
    $from .= $plaintext ? '' : '</ul>';

    return $from;
}
?>