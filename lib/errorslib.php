<?php
/***************************************************************************
* errorslib.php - Error library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.4
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define("ERRORSLIB", true);

$ERRORS = new \stdClass;

$CFG->debug = $CFG->debug ?? 1;
$reportlevel = 0;
$reportlevel = $CFG->debug == 1 ? E_USER_ERROR | E_ERROR : $reportlevel;
$reportlevel = $CFG->debug == 2 ? E_ERROR | E_USER_ERROR | E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_PARSE : $reportlevel;
$reportlevel = $CFG->debug == 3 ? E_ALL | E_STRICT : $reportlevel;

// Turn off error reporting
ini_set('display_errors', "off");
set_error_handler("myErrorHandler", $reportlevel);
set_exception_handler("myExceptionHandler");

// Login Errors *********************************************************
$ERRORS->no_login = "Username or password was incorrect.<br />Please try again.";

//Permission Errors *********************************************************
$ERRORS->no_html_permissions = "You do not have the correct permissions to view this HTML content.";
$ERRORS->no_buttons = "You have rights to 0 buttons";
$ERRORS->generic_permissions = "You do not have the correct permissions to do this.";
$ERRORS->no_permission = "You do not have the <strong>[0]</strong> permission.";
$ERRORS->generic_error = "Congratulations, you found a bug.  Please inform the site admin. " . $CFG->siteemail;
$ERRORS->generic_db_error = "Database Error. Please inform the site admin. " . $CFG->siteemail;

//User Creation *********************************************************
$ERRORS->user_not_added = "The user could not be created correctly.";
$ERRORS->user_not_emailed = "The user account has been created, but the confirmation email has failed to send.  Please inform the site admin. " . $CFG->siteemail;

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
            return fill_error_string_blanks($ERRORS->$string, $vars);
        }
        return $ERRORS->$string;
    } else {
        if ($vars) {
            return fill_error_string_blanks($ERRORS->$error, $vars);
        }
        return $ERRORS->$error;
    }
}

function fill_error_string_blanks($string, $vars) {
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

function myExceptionHandler(Throwable $e) {
    global $CFG;
    debugging($e->getMessage(), $CFG->debug);
}

function myErrorHandler($errno, $errstr, $errfile, $errline) {
global $CFG;
    if (error_reporting() === 0) {
        return;
    }
    if (!(error_reporting() && $errno)) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        return false;
    }

    if (isset($CFG->debugoverride)) {
        switch ($CFG->debug) {
            case 3:
                $errno = E_USER_ERROR;
                break;
            case 2:
                $errno = E_USER_WARNING;
                break;
            case 1:
                $errno = E_USER_NOTICE;
                break;
            default:
                $errno = "HIDEALL";
                break;
        }
    }

    switch ($errno) {
        case E_USER_ERROR:
            $message = "<strong>ERROR</strong> [$errno] $errstr\nFatal error on line $errline in file $errfile\n";
            debugging($message);
            break;
        case E_USER_WARNING:
            debugging("<strong>WARNING</strong> [$errno] $errstr\n");
            break;
        case E_USER_NOTICE:
            debugging("<strong>NOTICE</strong> [$errno] $errstr\n");
            break;
        case "HIDEALL":
            break;
        default:
            debugging("Unknown error type: [$errno] $errstr\n");
            break;
    }

    /* Don't execute PHP internal error handler */
    return true;
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

    // Set debug level based on forced parameter or default
    $level = $forced ? $level : $CFG->debug;

    // Return early if no debug level is set
    if (!$level) {
        return '';
    }

    // If a message is provided, process it
    if ($message) {
        // Initialize variables
        $from = '';
        $from_printable = '';

        // If level > 2, include backtrace information
        if ($level > 2) {
            $backtrace = debug_backtrace();
            $from = format_backtrace($backtrace, true);
            $from_printable = format_backtrace($backtrace, false);
        }

        // Log the debug message with optional backtrace
        error_log('Debugging (Level ' . $level . '): ' . $message . $from);

        // Prepare the display content
        $display = '<div class="debugging">' . $message . $from_printable . '</div>';

        // Prepare the final print content based on debug level
        $print = '';

        // If level >= 2, display backtrace and message in <pre> format
        if ($level >= 2) {
            $print .= '<pre>' . $display . '</pre>';
        }

        // If level == 1, display a generic error message
        if ($level == 1) {
            $print .= '
                <div class="error_text" style="font-size: 3vw;padding: 6%;">
                    <h3>Site Error</h3>
                    <br />
                    <p>' . error_string("generic_error") . '</p>
                </div>';
        }

        // If there's any content to display, return via ajax
        if (!empty($print)) {
            ajax_return("", $print);
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

function testCheck($description, $result, &$passCounter, &$totalCounter) {
    // Increment the total counter every time the test is run
    $totalCounter++;

    // If the test passed, increment the pass counter
    if ($result === "PASS") {
        $passCounter++;
    }

    // Return the test description with the result
    return "<p>$totalCounter.) $description: $result</p>";
}
?>