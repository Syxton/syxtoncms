<?php
/***************************************************************************
* formlib.php - Form Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/14/2025
* Revision: 0.0.1
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('FORMLIB', true);

function make_form_elements($elements, $data = []) {
    $output = '';
    $tabindex = 1;
    foreach ($elements as $element) {
        $rules = get_form_element_data_rules($element, $data);
        $help = get_form_element_help($element);
        $req = $element['required'] ? ' * ' : '';
        $element['rules'] = $rules;
        $element['tabindex'] = $i;
        switch ($element['type']) {
        case 'text':
        case 'select':
        case 'textarea':
        case 'date':
            $make_form_element = 'make_form_' . $element['type'];
            $form_element = $make_form_element($element, $data);

            if (strstr($form_element, 'type="hidden"')) {
                $output .= $form_element;
            } else {
                $output .= '
                <div class="rowContainer">
                    <label class="rowTitle" for="' . $element['name'] . '">
                        ' . $element['title'] . $req . '
                    </label>
                    ' . $form_element . '
                    <div class="tooltipContainer info">
                        ' . $help . '
                    </div>
                    <div class="spacer" style="clear: both;"></div>
                </div>';
            }
            break;
        case 'hidden':
            $output .= make_form_hidden($element, $data);
            break;
        }
        $tabindex++;
    }
    return $output;
}

function get_form_element_help($element) {
    return isset($element['help']) ? get_help($element['help']) : get_help('input_default_' . $element['type']);
}

function get_form_element_data_rules($element, $data = []) {
    $rules = '';
    if (isset($element['required']) && $element['required'] == true) {
        $rules .= ' data-rule-required="true"';
        $msg = isset($element['required_msg']) ? $element['required_msg'] : 'valid_req';
        $rules .= ' data-msg-required="' . error_string($msg) . '"';
    }

    if (isset($element['readonly']) && $element['readonly'] == true) {
        $rules .= ' readonly';
    }

    if (isset($element['lettersonly']) && $element['lettersonly'] == true) {
        $rules .= ' data-rule-letters="true"';
    }

    if (isset($element['nonumbers']) && $element['nonumbers'] == true) {
        $rules .= ' data-rule-nonumbers="true"';
    }

    if (isset($element['number']) && $element['number'] == true) {
        $rules .= ' data-rule-number="true"';
    }

    if ($element['type'] == 'email') {
        $rules .= ' data-rule-email="true"';
        $msg = isset($element['email_msg']) ? $element['email_msg'] : 'valid_email_invalid';
        $rules .= ' data-msg-email="' . error_string($msg) . '"';
    }

    if ($element['type'] == 'tel') {
        $rules .= ' data-rule-phone="true"';
        $msg = isset($element['phone_msg']) ? $element['phone_msg'] : 'valid_phone_invalid';
        $rules .= ' data-msg-phone="' . error_string($msg) . '"';
    }

    if ($element['type'] == 'date') {
        $rules .= ' data-rule-date="true"';
        $msg = isset($element['date_msg']) ? $element['date_msg'] : 'valid_date_invalid';
        $rules .= ' data-msg-date="' . error_string($msg) . '"';
    }

    if ($element['type'] == 'url') {
        $rules .= ' data-rule-url="true"';
        $msg = isset($element['url_msg']) ? $element['url_msg'] : 'valid_url_invalid';
        $rules .= ' data-msg-url="' . error_string($msg) . '"';
    }

    if (isset($element['maxlength'])) {
        $rules .= ' maxlength="' . $element['maxlength'] . '" data-rule-maxlength="' . $element['maxlength'] . '"';
    }

    if (isset($element['minlength'])) {
        $rules .= ' data-rule-minlength="' . $element['minlength'] . '"';
    }

    if (isset($element['max'])) {
        $rules .= ' data-rule-max="' . $element['max'] . '"';
    }

    if (isset($element['min'])) {
        $rules .= ' data-rule-min="' . $element['min'] . '"';
    }

    if (isset($element['customrules'])) {
        foreach ($element['customrules'] as $rule => $path) {
            include_once($CFG->dataroot . $path);
            $rule_function = "customrule_" . $rule;
            $rules .= $rule_function($data);
        }
    }
}

function get_form_gender_options() {
    return [
        '' => 'Select One...',
        'Male' => 'Male',
        'Female' => 'Female',
    ];
}

function get_form_USSTATES_options() {
    return [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District Of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ];
}

// Functions to take in an array describing an HTML form type
// and output the form HTML.
// Example element:
// [
//     'name' => 'camper_name_middle',
//     'section' => 'Camper',
//     'title' => 'Middle Initial',
//     'type' => 'text',
//     'required' => false,
// ]
function make_form_text($element, $data = []) {
    $value = isset($element['value']) ? $element['value'] : "";
    $style = isset($element['style']) ? $element['style'] : "";
    $output = '<input type="text" tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . '
                style="' . $style . '" />';
    return $output;
}

function make_form_textarea($element, $data = []) {
    $value = isset($element['value']) ? $element['value'] : "";
    $output = '<textarea tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                ' . $element['rules'] . '>' . $value . '</textarea>';
    return $output;
}

function make_form_hidden($element, $data = []) {
    global $CFG;
    $value = isset($element['value']) ? $element['value'] : "checkdynamicvalue";

    if ($value === "checkdynamicvalue") {
        $value = "";
        if (isset($element['dynamicvalue'])) {
            foreach ($element['dynamicvalue'] as $func => $path) {
                include_once($CFG->dataroot . $path);
                $value_function = "customvalue_" . $func;
                $value = $value_function($data);
            }
        }
    }

    $output = '<input type="hidden"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . ' />';
    return $output;
}

function make_form_date($element, $data = []) {
    $value = isset($element['value']) ? date("Y-m-d", $element['value']) : date("Y-m-d");
    $output = '<input type="date" tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . ' />';
    return $output;
}

function make_form_tel($element, $data = []) {
    $value = isset($element['value']) ? $element['value'] : "";
    $output = '<input type="tel" tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . ' />';
    return $output;
}

function make_form_email($element, $data = []) {
    $value = isset($element['value']) ? $element['value'] : "";
    $output = '<input type="email" tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . ' />';
    return $output;
}

function make_form_select($element, $data = []) {
    global $CFG;

    // Get the options if they exist.
    $options = isset($element['options']) ? $element['options'] : 0;

    // If the options are not an array, check for dynamic options.
    if (!is_array($options)) {
        // If the dynamic options exist, retrieve them.
        if (isset($element['dynamicoptions'])) {
            foreach ($element['dynamicoptions'] as $func => $path) {
                include_once($CFG->dataroot . $path);
                $options_function = "customoptions_" . $func;
                $options = $options_function($data);
            }
        }

        // If the options are still not an array, convert to a hidden input.
        if (!is_array($options)) {
            $element['value'] = $options;
            return make_form_hidden($element, $data = []);
        }
    }

    // Array of options are available.
    $output = '<select tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                ' . $element['rules'] . '>';
    foreach ($options as $value => $option) {
        $output .= '<option value="' . $value . '"';

        // Check for selected option.
        if ($value == $element['selected']) {
            $output .= ' selected="selected"';
        }
        $output .= '>' . $option . '</option>';
    }
    $output .= '</select>';
    return $output;
}
?>