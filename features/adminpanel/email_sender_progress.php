<?php
/***************************************************************************
* email_sender_script.php - AJAX mass email progress bar script
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

global $CFG, $USER;

if (!is_siteadmin($USER->userid)) { trigger_error(getlang("generic_permissions"), E_USER_WARNING); return; }

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
    $emaillist = clean_myvar_req("emaillist", "string");

    $fromuser = (object) [
        "email" => $CFG->siteemail,
        "fname" => $CFG->sitename,
        "lname" => "",
    ];

    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
    }

    ini_set('output_buffering', 'Off');
    ini_set('implicit_flush', 'On');
    ini_set("zlib.output_compression", "Off");
    ob_implicit_flush(true);

    header("Content-Encoding: none"); // This is not a valid value, but it is the only way to keep from a gzip'ed response.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Sun, 01 Jan 2000 00:00:00 GMT');


    // Create progress bar.
    echo '
        <!DOCTYPE html>
        <html lang="en">
        <head></head>
        <body style="padding: 0;margin: 0;">
            <div>
                <div style="box-sizing: border-box;text-align:center;width:100%;position:absolute;background-color:white;border:1px solid black;border-radius:5px;overflow: hidden;">
                    <span style="position: relative;font-weight: bolder;">
                        0%
                    </span>
                </div>';

    $emaillist = preg_split('/[\n,\r]+/', $emaillist, -1, PREG_SPLIT_NO_EMPTY);
    $chunk = 0;
    $count = count($emaillist);
    foreach ($emaillist as $email) {
        if (strpos($email, '@') !== false) {
            // Every 10 emails wait 5 seconds.
            if ($chunk % 10 == 0) {
                sleep(5);
            }

            // Prepare recipient user object.
            $touser = (object) [
                "email" => trim($email),
            ];

            // Send email.
            if (send_email($touser, $fromuser, $subject, $message)) {
                $success++;
            } else {
                $failed++;
            }

            // Update progress bar chunk;
            $chunk++;

            // Update progress bar.
            echo '
                <div style="box-sizing: border-box;text-align:center;width:100%;position:absolute;background-color:white;border:1px solid black;border-radius:5px;overflow: hidden;">
                    <span style="width: ' . round(($chunk / $count) * 100) . '%; background-color: green;height:100%;position:absolute;left:0;"></span>
                    <span style="position: relative;font-weight: bolder;">
                        ' . round(($chunk / $count) * 100) . '%
                    </span>
                </div>';
        }
    }
    echo '
            </div>
            <br /><br />
            <div>
                <h3>
                    Final Status:
                </h3>
                <strong>
                    Success: ' . $success . '
                </strong>
                <br />
                <strong>
                    Failed: ' . $failed . '
                </strong>
            </div>
        </body>
    </html>';
} catch (\Throwable $e) {
    echo $e->getMessage();
}
