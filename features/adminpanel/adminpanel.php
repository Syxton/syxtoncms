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
    echo '
        <div style="display: flex;height:100vh;">
            <div style="min-height:300px;display:inline-block;vertical-align:top;border:2px solid silver;background-color:DarkSlateGray;">
                <ul class="vertmenu">
                    <li>
                        <a href="#" class="active">Admin Features</a>
                        <ul class="acitem">
                            <li><a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/adminpanel_ajax.php\',\'user_admin\',\'\',function() { simple_display(\'display\');});">User Admin</a></li>
                            <li><a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/adminpanel_ajax.php\',\'camper_list\',\'\',function() { simple_display(\'display\');});"">Camper Lists</a></li>
                            <li><a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/adminpanel_ajax.php\',\'site_versions\',\'\',function() { simple_display(\'display\');});">Version Checker</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Framework Checks</a>
                        <ul class="acitem">
                            <li><a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/adminpanel_ajax.php\',\'get_phpinfo\',\'\',function() { simple_display(\'display\');});">PHPinfo()</a></li>
                            <li><a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/adminpanel_ajax.php\',\'admin_email_tester\',\'\',function() { simple_display(\'display\');});">Email Tester</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <div id="display" style="width: 100%;height: 100%;">
                <div style="font-size:.8em;width:98%;padding:10px;text-align:center;">
                    <h2>Site Administration Area</h2>
                    All administration features are displayed in this area.  Click the links to the left to display the various administration features.
                </div>
            </div>
        </div>
        <script type="text/javascript">
            $(window).on("load", function() {
                $(".vertmenu li .active").click();
            });
        </script>';
}
?>