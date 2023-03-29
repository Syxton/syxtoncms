<?php
/***************************************************************************
 * preview.php - Events backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 02/13/2012
 * $Revision: 1.0.0
 ***************************************************************************/
 
if (!isset($CFG)) { include_once('../../config.php'); }
if (!isset($ERRORS)) { include_once($CFG->dirroot . '/lib/errors.php'); }
if (!isset($USERLIB)) { include_once($CFG->dirroot . '/lib/userlib.php'); }
if (!isset($DBLIB)) { include_once($CFG->dirroot . '/lib/dblib.php'); }
if (!isset($PAGELIB)) { include_once($CFG->dirroot . '/lib/pagelib.php'); }
if (!isset($EVENTSLIB)) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

callfunction();

function preview_template() {
global $CFG,$MYVARS;
    $form = "";
	echo js_code_wrap('var dirfromroot = "' . $CFG->directory . '";');
	echo get_js_tags(array("siteajax", "features/events/events.js"));
	echo get_css_tags(array("main"));
     
	$template_id = $MYVARS->GET['template_id'];
	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='".$template_id."'");
	
	$formlist = "";
	$returnme = '<input id="lasthint" type="hidden" /><div id="registration_div"><table class="registration"><tr><td>'.$template['intro'].'</td></tr></table>';
	
	if ($template['folder'] != "none") { //registration template refers to a file
		$preview = true;
		include($CFG->dirroot . '/features/events/templates/' . $template['folder'] . '/template.php');
	} else { //registration template refers to a database style template
		$form = '<table style="width:100%">';
		$templateform = get_db_result("SELECT * FROM events_templates_forms WHERE template_id='".$template['template_id']."' ORDER BY sort");
	
		while ($element = fetch_row($templateform)) {
			$opt = $element['optional'] ? '<font size="1.2em" color="blue">(optional)</font> ' : '';
			$formlist .= $formlist == "" ? $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'] : "*" . $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'];
			
			if ($element['type'] == 'select') {
				
			}elseif ($element['type'] == 'phone') {
				$form .= '<tr><td class="field_title">' . $opt . $element['display'] . ': </td><td class="field_input" style="width:70%">' . create_form_element($element['type'],$element['elementid'],$element['optional'],$element['length'],false) . '</td></tr>';
				$form .= '<tr><td></td><td class="field_input"><span id="'.$element['elementid'].'_error" class="error_text"></span></td></tr>';
			}elseif ($element['type'] == 'payment') {
				$form .= '
				<tr>
					<td class="field_title">Payment Amount:</td>
					<td class="field_input">'.make_fee_options($event['fee_min'],$event['fee_full'],'payment_amount','').'</td>
				</tr>
				<tr>
					<td class="field_title">Method of Payment:</td>
					<td class="field_input">
						<select id="payment_method" name="payment_method" size="1" >
							<option value="">Choose One</option>
							<option value="PayPal">Pay online using Credit Card/PayPal</option>
							<option value="Check/Money Order">Check or Money Order</option>
						</select>
					</td>
				</tr>
				<tr><td></td><td class="field_input"><span id="payment_method_error" class="error_text"></span></td></tr>
    				';
			} else {
				$form .= '<tr><td class="field_title">' . $opt . $element['display'] . ': </td><td class="field_input" style="width:70%">' . create_form_element($element['type'],$element['elementid'],$element['optional'],$element['length'],false) . '<span class="hint">'. $element['hint'] . '<span class="hint-pointer">&nbsp;</span></span></td></tr>';
				$form .= '<tr><td></td><td class="field_input"><span id="'.$element['elementid'].'_error" class="error_text"></span></td></tr>';
			}
		}
		$form .= '<tr><td></td><td><input type="button" value="Submit" disabled="disabled" onclick="submit_registration(\''.$eventid.'\',\''.$formlist.'\');" /></td></tr></table>';
	}
	
	$returnme .= $form . '</div>';
    echo $returnme;
}
?>