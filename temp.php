<?php
/***************************************************************************
 * temp.php: Templating system tests.
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/

header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

if (!isset($CFG)) {
    include_once('config.php');
}

if (isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip, ',' . $_SERVER['REMOTE_ADDR'] . ',')) {
    include($CFG->dirroot . $CFG->alternatepage);
} else {
    include_once($CFG->dirroot . '/lib/header.php');

    echo get_css_set("main");
    echo get_js_set("main");
    echo get_js_set("main");

    $params = ["variable" => "TEST"];
    echo template_use("tmp/test.template", $params, "simple");

    echo template_use("tmp/test.template", $params, "code1");

    $params = ["variable" => [5, 4, 3, 2, 1]];
    echo template_use("tmp/test.template", $params, "simple2");
    echo template_use("tmp/test.template", $params, "code2");

    $params = ["variables" => [1, 2, 3, 4, 5]];
    echo template_use("tmp/test.template", $params, "code3");

    $variables = new \stdClass();
    $variables->one = 1;
    $variables->two = 2;
    $variables->three = 3;
    $params = ["variables" => $variables];
    echo template_use("tmp/test.template", $params, "simple3");
    echo template_use("tmp/test.template", $params, "code4");

    $params = ["variable1" => "variable1", "variable3" => "variable3"];
    echo template_use("tmp/test.template", $params, "optional");
}
?>
