<?php
/***************************************************************************
 * preview.php - Events backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 02/13/2012
 * $Revision: 1.0.0
 ***************************************************************************/

if (!isset($CFG)) {
    $sub = '';
    while (!file_exists($sub . 'config.php')) {
        $sub .= '../';
    }
    include_once($sub . 'config.php');
}

$libs = ['ERRORSLIB', 'DBLIB','FILELIB', 'PAGELIB', 'USERLIB', 'ROLESLIB'];
foreach ($libs as $lib) {
    if (!defined($lib)) {
        include_once($CFG->dirroot . '/lib/' . strtolower($lib) . '.php');
    }
}

if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

callfunction();

function preview_template() {
global $CFG, $MYVARS;
    $form = "";
    echo js_code_wrap('var dirfromroot = "' . $CFG->directory . '";');
    echo js_code_wrap(fetch_template("tmp/pagelib.template", "defer_script"));
    echo get_js_tags(["siteajax", "features/events/events.js"]);
    echo get_css_tags(["main"]);

    $template_id = clean_myvar_req("template_id", "int");
    $template = get_event_template($template_id);

    $formlist = "";
    if ($template['folder'] != "none") { //registration template refers to a file
        $preview = true;
        include($CFG->dirroot . '/features/events/templates/' . $template['folder'] . '/template.php');
    } else { //registration template refers to a database style template
        $templateform = get_db_result("SELECT * FROM events_templates_forms WHERE template_id='" . $template['template_id'] . "' ORDER BY sort");
        while ($element = fetch_row($templateform)) {
            $opt = $element['optional'] ? '<font size="1.2em" color="blue">(optional)</font> ' : '';
            $formlist .= $formlist == "" ? $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'] : "*" . $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'];

            if ($element['type'] == 'phone') {
                $form .= '
                    <tr>
                        <td class="field_title">
                            ' . $opt . $element['display'] . ':
                        </td>
                        <td class="field_input" style="width:70%">
                            ' . create_form_element($element['type'], $element['elementid'], $element['optional'], $element['length']) . '
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="field_input">
                            <span id="' . $element['elementid'] . '_error" class="error_text"></span>
                        </td>
                    </tr>';
            } elseif ($element['type'] == 'payment') {
                $form .= '
                <tr>
                    <td class="field_title">
                        Payment Amount:
                    </td>
                    <td class="field_input">
                        ' . make_fee_options(0, 100, 'payment_amount', '') . '
                    </td>
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
                <tr>
                    <td colspan="2" class="field_input">
                        <span id="payment_method_error" class="error_text"></span>
                    </td>
                </tr>';
            } else {
                $form .= '
                    <tr>
                        <td class="field_title">
                            ' . $opt . $element['display'] . ':
                        </td>
                        <td class="field_input" style="width:70%">
                            ' . create_form_element($element['type'], $element['elementid'], $element['optional'], $element['length']) . '
                            <span class="hint">
                                ' . $element['hint'] . '
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="field_input">
                            <span id="' . $element['elementid'] . '_error" class="error_text">
                            </span>
                        </td>
                    </tr>';
            }
        }

        $form = '
            <table style="width:100%">
                ' . $form . '
                <tr>
                    <td colspan="2">
                        <input type="button" value="Submit" disabled="disabled" />
                    </td>
                </tr>
            </table>';
    }

    $returnme = '
        <input id="lasthint" type="hidden" />
            <div id="registration_div" style="padding: 30px;">
                <table class="registration">
                    <tr>
                        <td>
                            ' . $template['intro'] . '
                        </td>
                    </tr>
                </table>
                ' . $form . '
            </div>';

    echo $returnme;
}
?>