<?php
/***************************************************************************
* pics.php - Pics modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 2.5.9
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); } 
    if (!isset($PICSLIB)) { include_once ($CFG->dirroot . '/features/pics/picslib.php'); }
    
    callfunction();
    
	echo get_js_tags(array("features/pics/pics.js", "features/pics/uploads.js"));
    
    echo '</body></html>';
}

function pics_settings() {
global $CFG,$MYVARS,$USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "pics";

	//Default Settings	
	$default_settings = default_settings($feature,$pageid,$featureid);
	$setting_names = get_setting_names($default_settings);
    
	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature,$featureid,$pageid)) {
        echo make_settings_page($setting_names,$settings,$default_settings,$feature,$featureid,$pageid);
	} else { //No Settings found...setup default settings
		if (make_or_update_settings_array($default_settings)) { pics_settings(); }
	}
}	

function add_pics() {
global $CFG,$MYVARS,$USER;
	$featureid = $MYVARS->GET["featureid"];
	$pageid = $MYVARS->GET["pageid"];
	if (!user_has_ability_in_page($USER->userid,"addpics",$pageid)) { echo get_page_error_message("no_permission",array("addpics")); return; }
	echo '
    <form id="pics_form" method="post" action="'.$CFG->wwwroot.'/features/pics/pics_ajax.php" enctype="multipart/form-data">
    <input type="hidden" id="filenames" name="filenames" />
    <input type="hidden" name="action" value="pics_upload" />
    <input type="hidden" name="featureid" value="'.$featureid.'" />
    <input type="hidden" name="pageid" value="'.$pageid.'" />
    <input style="margin:10px;vertical-align:bottom; float:right;" type="button" name="upload_form" value="Upload Files" onclick="update_picslist();">
    Click the browse button to choose the images you would like to upload.  You can add as many as you would like.  The images will not be uploaded to the server until you click the Upload File button.
    <p>
    <table style="width:98%; border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
    	<tr>
    		<td>
    		<strong>Gallery</strong><br /><br />
    			<table style="width:100%;">
    				<tr>
    					<td class="field_title" style="width:100px;">
    						New Gallery:
    					</td>
    					<td class="field_input">
    						<select id="new_gallery" name="new_gallery" onchange="ajaxapi(\'/features/pics/pics_ajax.php\',\'new_gallery\',\'&param=\' + this.value + \'&pageid='.$pageid.'\',function() { simple_display(\'gallery_name_div\');});"><option value="1">Yes</option><option value="0">No</option></select>
    					</td>
    				</tr>
    			</table>
    			<table style="width:100%;">
    				<tr>
    					<td class="field_title" style="width:100px;">
    						Gallery Name:
    					</td>
    					<td class="field_input">
    						<span id="gallery_name_div">
    						<input name="gallery_name" id="gallery_name" type="text" size="32" onkeypress="return handleEnter(this, event)"/>
    						</span>
    					</td>
    				</tr>
    			</table>
    			<table style="width:100%;">
    					<tr>
    					<td class="field_title" style="width:100px;">
    						File Uploads:
    					</td>
    					<td class="field_input">
    						<input type="file" class="multi" accept="gif|jpg|png|bmp" id="multi_0_0" name="files[]" onkeypress="return handleEnter(this, event)"/><p>
    					</td>
    				</tr>
    			</table>
    		</td>
    	</tr>
    </table>
    </form>';
}

function manage_pics() {
global $CFG,$MYVARS,$USER;
	$featureid = $MYVARS->GET["featureid"];
	$pageid = $MYVARS->GET["pageid"];
    
    echo '<div id="pics_manager">';
    echo get_pics_manager($pageid,$featureid);
    echo '</div>';
}

function get_galleries($pageid,$featureid) {	
	if ($results = get_db_result("SELECT picsid, gallery_title FROM pics WHERE pageid='$pageid' AND featureid='$featureid' GROUP BY gallery_title ORDER BY dateadded DESC")) {
		$returnme = '<select id="galleries" width="205" style="width: 205">';
		while ($row = fetch_row($results)) {
			$returnme .= '<option value="'.$row['picsid'].'">'. $row['gallery_title'] .'</option>';
		}
        $returnme .= '</select>&nbsp;<input type="button" value="Select" onclick="ajaxapi(\'/features/pics/pics_ajax.php\',\'get_gallery_pics\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;galleryid=\'document.getElementById(\'galleries\').value\',function() { simple_display(\'pics_list\');});" />';
        return $returnme;
	}
    return false;
}
?>