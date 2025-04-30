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

echo fill_template("tmp/page.template", "page_js_css", false, ["dirroot" => $CFG->directory]);

if (!isset($_GET['data'])) { exit(); }

$data = json_decode($_GET['data']);

if (!isset($data->purchase_units[0]->reference_id)) {
    exit();
}

$reference = json_decode($data->purchase_units[0]->reference_id);

$type = $reference->t;
$cart = $reference->c;

switch ($type) {
    case 'events':
        if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }
        events_approved_payment($cart, $data);
        break;
    case 'donate':
        if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/donate/donatelib.php'); }
        break;
}

?>