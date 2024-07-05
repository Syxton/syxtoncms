<?php
/**
 * This file is part of the Syxton CMS.
 *
 *  New Google style members search page - features/adminpanel/members.php
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 8/19/2010
 * Revision: 0.0.2
 ***************************************************************************/

global $CFG;

ajaxapi([
    "id" => "members_search",
    "url" => "/features/adminpanel/members_script.php",
    "paramlist" => "search = '', pagenum = 0",
    "data" => [
        "action" => "members_search",
        "pagenum" => "js||pagenum||js",
        "search" => "js||encodeURIComponent(search)||js",
    ],
    "display" => "mem_resultsdiv",
    "loading" => "loading_overlay",
    "event" => "none",
]);

ajaxapi([
    "id" => "export_search",
    "url" => "/features/adminpanel/members_script.php",
    "paramlist" => "search = '', csv = 0, mailman = 0",
    "data" => [
        "action" => "members_search",
        "csv" => "js||csv||js",
        "mailman" => "js||mailman||js",
        "search" => "js||encodeURIComponent(search)||js",
    ],
    "display" => "mem_exportsdiv",
    "loading" => "loading_overlay",
    "event" => "none",
]);

$allfields = "";
// Get list of all fields that can be searched on.
if ($result = get_db_result("SHOW COLUMNS FROM users")) {
    while ($field = fetch_row($result)) {
        $allfields .= '<option val="' . $field["Field"] . '">' . $field["Field"] . '</option>';
    }
}

$params = [
    "allfields" => $allfields,
];

// Load the template
ajax_return(fill_template("tmp/main.template", "members_search_page", "adminpanel", $params));

?>