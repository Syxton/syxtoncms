<?php
/***************************************************************************
* adminpanel.php - Site Admin Panel Area
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 0.0.6
***************************************************************************/

if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); } 
    
    callfunction();

    echo template_use("tmp/main.template", array(), "header", "adminpanel");
    
    echo '</body></html>';
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
    echo '<table style="height:98%;width:100%"><tr><td style="width:1%;font-size:.75em;border:2px solid silver;background-color:DarkSlateGray;">
    <div style="height:100%;">
    <ul class="vertmenu">
		<li>
			<a href="#">Admin Features</a>
			<ul class="acitem">
				<li><a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'user_admin\',\'\',function() { simple_display(\'display\');});">User Admin</a></li>
				<li><a href="#">Page Admin (Not Yet Available)</a></li>
                <li><a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'camper_list\',\'\',function() { simple_display(\'display\');});"">Camper Lists</a></li>
				<li><a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'site_versions\',\'\',function() { simple_display(\'display\');});">Version Checker</a></li>
			</ul>
		</li>
		<li>
			<a href="#">Framework Checks</a>
			<ul class="acitem">
				<li><a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'get_phpinfo\',\'\',function() { simple_display(\'display\');});">PHPinfo()</a></li>
				<li><a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'admin_email_tester\',\'\',function() { simple_display(\'display\');});">Email Tester</a></li>
			</ul>
		</li>
    </ul>
    </div></td><td style="">
    <div id="display" style="height:100%;width:100%;"><div style="font-size:.8em;width:98%;padding:10px;text-align:center;">
    <h2>Site Administration Area</h2>
    All administration features are displayed in this area.  Click the links to the left to display the various administration features.
    </div></div>
    </td></tr></table>';
}
?>