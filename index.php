<?php
/***************************************************************************
* index.php
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/8/2012
* Revision: 1.0.1
***************************************************************************/
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

if(!isset($CFG)){ include_once ('config.php'); }

if(isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip,','.$_SERVER['REMOTE_ADDR'].',')){
	include($CFG->dirroot.$CFG->alternatepage);
}else{
	include_once ($CFG->dirroot . '/lib/header.php');

	//Check for upgrades or uninstalled components
	upgrade_check();

	//Cache roles
	$ROLES = load_roles();

	//Get page info
    $PAGE = new stdClass();
	$PAGE->id = isset($_GET['pageid']) ? $_GET['pageid'] : $CFG->SITEID;
	$PAGE->title = $CFG->sitename; //Title of page
	$PAGE->themeid = getpagetheme($PAGE->id);

	//Get User info
	load_user_cookie();
	update_user_cookie();

    //Start Page
    include ('header.html');

	if(is_logged_in()){
        echo '<script type="text/javascript">if(typeof(window.myInterval) == "undefined"){ var myInterval = setInterval(function(){update_login_contents(false,"check");}, (5 * 30000));}</script>';
		$ABILITIES = get_user_abilities($USER->userid,$PAGE->id);
		if(empty($ABILITIES->viewpages->allow)){
			if(get_db_field("opendoorpolicy", "pages", "pageid=" . $PAGE->id) == "0"){ $PAGE->id = $CFG->SITEID; }
		}
	}else{
		$ABILITIES = get_role_abilities($ROLES->visitor,$PAGE->id);
		if(!(get_db_field("siteviewable", "pages", "pageid=" . $PAGE->id) && !empty($ABILITIES->viewpages->allow))){
			if(get_db_field("opendoorpolicy", "pages", "pageid=" . $PAGE->id) == "0"){ $PAGE->id = $CFG->SITEID; }
		}
	}

	//Main Layout
	echo '	<div class="colmask rightmenu">
			<input type="hidden" id="currentpage" value="' . $PAGE->id . '" />
				<div class="colleft">
					<div class="col1 pagesort1 connectedSortable">
					<span id="column_width" style="width:100%;"></span>
					'.get_page_contents($PAGE->id, 'middle'). '
					</div>
					<div class="col2 pagesort2 connectedSortable">
					'.get_page_contents($PAGE->id, 'side').'
					</div>
				</div>
			</div>';

    //End Page
    include ('footer.html');

	//Log
	log_entry("page", null, "Page View");
}
?>