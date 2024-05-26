<?php
/***************************************************************************
* pics.php - Pics modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.5.9
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

    if (!defined('PICSLIB')) { include_once ($CFG->dirroot . '/features/pics/picslib.php'); }
    
    callfunction();
    
	echo get_js_tags(["features/pics/pics.js", "features/pics/uploads.js"]);
    
    echo '</body></html>';
}

function pics_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = "pics";

	//Default Settings	
	$default_settings = default_settings($feature, $pageid, $featureid);
    
	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { pics_settings(); }
	}
}	

function add_pics() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	if (!user_is_able($USER->userid, "addpics", $pageid)) { trigger_error(error_string("no_permission", ["addpics"]), E_USER_WARNING); return; }

    $existing_galleries = get_db_result(fetch_template("dbsql/pics.sql", "get_page_galleries", "pics"), ["pageid" => $pageid]);

    $hide_select = $existing_galleries ? '' : 'display:none;';

    echo '
    <form id="pics_form" method="post" action="' . $CFG->wwwroot . '/features/pics/pics_ajax.php" enctype="multipart/form-data">
    <input type="hidden" id="filenames" name="filenames" />
    <input type="hidden" name="action" value="pics_upload" />
    <input type="hidden" name="featureid" value="' . $featureid . '" />
    <input type="hidden" name="pageid" value="' . $pageid . '" />
    <p>
        Click the browse button to choose the images you would like to upload.  You can add as many as you would like.  The images will not be uploaded to the server until you click the Upload File button.
    </p>
    <table class="dotted" style="width:100%;>
  		<tr>
  			<td>
  			    <strong>Gallery</strong><br /><br />
  				<table style="width:100%;">
  					<tr style="' . $hide_select . '">
  						<td class="field_title">
  							New Gallery:
  						</td>
  						<td class="field_input">
  							<select id="new_gallery" name="new_gallery" onchange="ajaxapi(\'/features/pics/pics_ajax.php\',\'new_gallery\',\'&param=\' + this.value + \'&pageid=' . $pageid . '\',function() { simple_display(\'gallery_name_div\');});">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
  						</td>
  					</tr>
  					<tr>
  						<td class="field_title">
  							Gallery Name:
  						</td>
  						<td class="field_input">
  							<span id="gallery_name_div">
  							<input name="gallery_name" id="gallery_name" type="text" size="32" onkeypress="return handleEnter(this, event)"/>
  							</span>
  						</td>
  					</tr>
  					<tr>
  						<td class="field_title">
  							File Uploads:
  						</td>
  						<td class="field_input">
  							<input type="file" class="multi" multiple="multiple" accept="gif|jpg|jpeg|png|bmp" id="multi_0_0" name="files[]" onkeypress="return handleEnter(this, event)"/>
  						</td>
  					</tr>
  				</table>
  			</td>
  		</tr>
    </table>
    <input style="position: absolute;margin: 10px;bottom: 0px;right: 0px;" type="button" name="upload_form" value="Submit Gallery" onclick="update_picslist();">
    </form>';
}

function manage_pics() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
    
    echo '<div id="pics_manager">';
    echo get_pics_manager($pageid, $featureid);
    echo '</div>';
}

function get_galleries($pageid, $featureid) {	
	if ($results = get_db_result("SELECT picsid, gallery_title FROM pics WHERE pageid='$pageid' AND featureid='$featureid' GROUP BY gallery_title ORDER BY dateadded DESC")) {
		$returnme = '<select id="galleries" width="205" style="width: 205">';
		while ($row = fetch_row($results)) {
			$returnme .= '<option value="' . $row['picsid'] . '">' . $row['gallery_title'] . '</option>';
		}
        $returnme .= '</select>&nbsp;<input type="button" value="Select" onclick="ajaxapi(\'/features/pics/pics_ajax.php\',\'get_gallery_pics\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;galleryid=\'document.getElementById(\'galleries\').value\',function() { simple_display(\'pics_list\');});" />';
        return $returnme;
	}
    return false;
}
?>