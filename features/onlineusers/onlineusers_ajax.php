<?php
/***************************************************************************
* onlineusers_ajax.php - Online Users ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.2
***************************************************************************/

if (!isset($CFG)) {
    $sub = '';
    while (!file_exists($sub . 'header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'header.php');
}

if (!defined('ONLINEUSERSLIB')) { include_once($CFG->dirroot . '/features/onlineusers/onlineuserslib.php'); }

callfunction();

function get_onlineusers_ajax() {
    $pageid = clean_myvar_req('pageid', 'int');
    $featureid = clean_myvar_req('featureid', 'int');
    ajax_return(get_onlineusers($pageid, $featureid));
}
?>