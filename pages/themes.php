<?php
/***************************************************************************
* themes.php - Themes and Styles
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 0.1.5
***************************************************************************/

include('header.php');

echo '
	 <script type="text/javascript">var dirfromroot = "'.$CFG->directory.'";</script>
	 <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'ajax/siteajax.js"></script>
     <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts&amp;f=jquery.min.js"></script>
	 <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts/picker/picker.js"></script>
     <link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'styles/styles_main.css" />
	 <input id="lasthint" type="hidden" />
';

callfunction();

echo '</body></html>';

function change_theme(){
global $CFG, $MYVARS, $USER, $PAGE;
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$feature = dbescape($MYVARS->GET['feature']);
	$PAGE = new stdClass();
	$PAGE->id = $pageid;
	$PAGE->themeid = getpagetheme($PAGE->id);
	echo '<div id="themes_page">';
	//Allow the Theme Selector
	if($feature == "page"){
		//Page has theme selected show themes
		if($PAGE->themeid > 0){ //Theme selected
			echo theme_selector($pageid,$PAGE->themeid,$feature);
		}else{ //Custom Theme
			echo theme_selector($pageid,$PAGE->themeid,$feature);
		}
	}else{
		include_once($CFG->dirroot . '/features/'.$feature.'/'.$feature.'lib.php');
		$function = "display_$feature";
		$featureid = dbescape($MYVARS->GET['featureid']);
		echo make_panes(get_feature_styles($pageid,$feature,$featureid), $function($pageid,"side",$featureid));
		
	}
	echo '</div>';
}
?>