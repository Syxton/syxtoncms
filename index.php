<?php
/***************************************************************************
* index.php
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 1.0.2
***************************************************************************/
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

if(!isset($CFG)){ include_once ('config.php'); }

if(isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip,','.$_SERVER['REMOTE_ADDR'].',')){
	include($CFG->dirroot.$CFG->alternatepage);
}else{
	include_once ($CFG->dirroot . '/lib/header.php');

	//Get User info
	load_user_cookie();
	update_user_cookie();

    $directory = $CFG->directory == '' ? 'root' : $CFG->directory;
    setcookie('directory', $directory, get_timestamp() + $CFG->cookietimeout, '/');
    $_SESSION['directory'] = $directory;

	//Get page info
    $PAGE = new stdClass();
	$PAGE->id = isset($_GET['pageid']) ? $_GET['pageid'] : $CFG->SITEID;
    if(!is_numeric($PAGE->id)) { // Somebody could be playing with this variable.
        $PAGE->id = $CFG->SITEID;
    }
    setcookie('pageid', $PAGE->id, get_timestamp() + $CFG->cookietimeout, '/');
	$_SESSION['pageid'] = $PAGE->id;
    $PAGE->title = $CFG->sitename; //Title of page
	$PAGE->themeid = getpagetheme($PAGE->id);

    //Use this page only to keep session and cookies refreshed (during forms)
    if(!empty($_GET['keepalive'])) { header("Refresh:30"); echo rand(); die(); }

	//Check for upgrades or uninstalled components
	upgrade_check();

	//Cache roles
	$ROLES = load_roles();
    
    //Start Page
    include ('header.html');

	if(is_logged_in()){
	    // Approximate every 15 seconds
        echo '<script type="text/javascript">if(typeof(window.myInterval) == "undefined"){ var myInterval = setInterval(function(){update_login_contents(false,"check");}, 14599);}</script>';
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
				<div class="colleft">
                    <div class="logo_nav">'.page_masthead(true).'</div>
                    <div class="col2 pagesort2 connectedSortable">
                        '.page_masthead(false).'
    					'.get_page_contents($PAGE->id, 'side').'
					</div>
					<div class="col1 pagesort1 connectedSortable">
    					<span id="column_width" style="width:100%;"></span>
    					'.get_page_contents($PAGE->id, 'middle'). '
					</div>
				</div>
			</div>';

    //End Page
    include ('footer.html');

	//Log
	log_entry("page", null, "Page View");
}
?>