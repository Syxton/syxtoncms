<?php
/***************************************************************************
* adminpanel.php - Site Admin Panel Area
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.6
***************************************************************************/

if (empty($_POST["aslib"])) {
	if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

    $head = fill_template("tmp/page.template", "page_js_css", false, ["dirroot" => $CFG->directory]);
    echo fill_template("tmp/page.template", "start_of_page_template", false, ["head" => $head]);

    callfunction();

    echo fill_template("tmp/main.template", "header", "adminpanel");

    echo fill_template("tmp/page.template", "end_of_page_template");
}


//An area that links to all functions
//		<li>
//			<a href="#">Framework Checks</a>
//			<div class="acitem panel">
//			<p>This contains stuff</p>
//			<p>There can be <a href="//www.i-marco.nl/">links</a> too</p>
//			<ul>
//				<li>blerk</li>
//				<li>wonk</li>
//				<li><a href="#">meh</a></li>
//			</ul>
//			</div>
//		</li>

function site_administration() {
    site_admin_javascript();
    echo fill_template("tmp/main.template", "site_administration", "adminpanel");
}

function site_admin_javascript() {
    ajaxapi([
        "id" => "user_admin",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "user_admin",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "camper_list",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "camper_list",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "site_versions",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "site_versions",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "get_phpinfo",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "get_phpinfo",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "admin_email_tester",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "admin_email_tester",
        ],
        "display" => "display",
    ]);
}
?>