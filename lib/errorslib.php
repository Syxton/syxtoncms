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
                <div class="error_text" style="font-size: calc(10px + .5vw);padding: 6%;">
                    <h3>Site Error</h3>
                    <br />
                    <p>' . getlang("generic_error") . '</p>
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