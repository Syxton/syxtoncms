<?php
/***************************************************************************
* paypal.php - Paypal PDT page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.2.0
***************************************************************************/

if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}

if (!isset($_GET['data'])) { exit(); }

$data = json_decode($_GET['data']);

if (!isset($data->purchase_units[0]->reference_id)) {
    exit();
}

$reference = json_decode($data->purchase_units[0]->reference_id);

$type = $reference->t;
$cart = $reference->c;

$successfunction = $type . "_print_confirmation";

// Include feature specific functions.
include_once($CFG->dirroot . "/features/$type/$type" . "lib.php");

$display = "";
if (function_exists($successfunction)) {
    $display = $successfunction($cart, $data);
} else {
    throw new Exception("Failed to find payment confirmation functions.");
}

// Output confirmation page.
$PAGE->title        = "Payment Confirmation Page"; // Title of page
$PAGE->name         = $PAGE->title; // Title of page
$PAGE->description  = $PAGE->title; // Description of page
$PAGE->themeid =    get_page_themeid($PAGE->id);

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0");

// Start Page
include($CFG->dirroot . '/header.html');

echo fill_template("tmp/index.template", "simplelayout_template", false, [
    "mainmast" => page_masthead(true, true),
    "middlecontents" => $display,
]);

// End Page
include($CFG->dirroot . '/footer.html');

?>