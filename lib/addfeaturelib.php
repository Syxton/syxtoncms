<?php
/***************************************************************************
* addfeaturelib.php - Add Feature function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/21/2014
* Revision: 0.9.3
***************************************************************************/
 
$ADDFEATURELIB = true;
include('header.php');
//ADDFEATURELIB Config

function display_addfeature($pageid,$area) {
	global $CFG, $USER, $ABILITIES;
	
	if (is_logged_in()) {
		if (user_has_ability_in_page($USER->userid, 'addfeature', $pageid)) {
			$multiples = $pageid == $CFG->SITEID ? ' OR f.site_multiples_allowed = 1' : ' OR f.multiples_allowed = 1';
			$title = "Add Features";
			$SQL = "SELECT * FROM features f WHERE f.allowed='1' AND ((f.feature NOT IN (SELECT pf.feature FROM pages_features pf WHERE pf.pageid='$pageid') AND f.feature != 'addfeature') $multiples) ORDER BY f.feature_title";
			if ($result = get_db_result($SQL)) {
				$content = '<table style="width:100%"><tr><td style="vertical-align:top; text-align:right; width:90%;"><select id="addfeaturelist" style="width:100%"><option value="">Add Feature...</option>';
				while ($row = fetch_row($result)) {
					$content .= '<option value="' . $row['feature'] . '">' . $row['feature_title'] . '</option>';
				}
				$content .= '</select></td><td style="vertical-align:top; text-align:left;"><input type="button" value="Add" onclick="if ($(\'#addfeaturelist\').val() != \'\') { 
				                                                                                                                        ajaxapi(\'/ajax/site_ajax.php\',
                                                                                                                                                \'addfeature\',
                                                                                                                                                \'&amp;feature=\' + $(\'#addfeaturelist\').val() + \'&amp;pageid='.$pageid.'\',
                                                                                                                                                function() { 
                                                                                                                                                    if (xmlHttp.readyState == 4) {
                                                                                                                                                        go_to_page('.$pageid.');
                                                                                                                                                    }
                                                                                                                                                },
                                                                                                                                                true);
                                                                                                                                        }" /></td></tr></table>';
			}
			return get_css_box($title,$content,NULL,NULL,"addfeature");
		}
	}
}
?>