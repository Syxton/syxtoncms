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

function get_form_section($element, $section = false) {
    if (isset($element['section'])) {
        if ($section === false) { // No section started.
            return $element['section'];
        }

        // Get last element of array $section and compare.
        if ($section !== $element['section']) { // New section.
            return $element['section'];
        }
    }
    return $section;
}

function get_form_section_opening($lastsection, $section) {
    if ($section === false || $section === $lastsection) {
        return '';
    }

    if ($lastsection === false) {
        $output = fetch_template('tmp/forms.template', 'form_section_js');
        $output .= '<div class="formMenu"></div>';
        $output .= '<div class="formSection firstSection selectedSection">';
    }

    if ($lastsection !== false && $lastsection !== $section) {
        $output = get_form_section_closing($section);
        $output .= '<div class="formSection">';
    }

    $output .= get_form_navigation_buttons('topButtons');
    $output .= '<div class="formSectionTitle">Section: ' . $section . '</div>';

    return $output;
}

function get_form_navigation_buttons($extraclass = "") {
    return '
    <div class="formNavigation ' . $extraclass . '">
        <div class="formNavigationPrevious">
            <button type="button">
                Previous Section
            </button>
        </div>
        <div class="formNavigationNext">
            <button type="button">
                Next Section
            </button>
        </div>
    </div>';
}
function get_form_section_closing($section = false) {
    $output = "";
    if ($section !== false) {
        $output .= get_form_navigation_buttons('bottomButtons');
        $output .= '</div>';
    }
    return $output;
}

function make_form_elements($elements, $data = []) {
    global $CFG;
    $output = '';
    $tabindex = 1;
    $lastsection = false;
    foreach ($elements as $element) {
        // Skip fields that are only shown at checkout.
        if (isset($element['checkout']) && $element['checkout'] === true) {
            continue;
        }

        $rules = get_form_element_data_rules($element, $data);
        $help = get_form_element_help($element, $data);
        $req = isset($element['required']) && $element['required'] ? ' * ' : '';
        $element['rules'] = $rules;
        $element['tabindex'] = $tabindex;
        $elementHTML = "";
        switch ($element['type']) {
        case 'text':
        case 'select':
        case 'textarea':
        case 'date':
        case 'tel':
        case 'email':
        case 'password':
            $make_form_element = 'make_form_' . $element['type'];
            $form_element = $make_form_element($element, $data);

            if (strstr($form_element, 'type="hidden"')) {
                $elementHTML = $form_element;
            } else {
                $elementHTML = '
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
            $elementHTML = make_form_hidden($element, $data);
            break;
        case 'custom':
            if (isset($element['customtype'])) {
                $element["help"] = $help;
                $element["rules"] = $rules;
                foreach ($element['customtype'] as $func => $path) {
                    include_once($CFG->dirroot . $path);
                    $type_function = "customtype_" . $func;
                    $elementHTML .= $type_function($element, $data);
                }
            }
            break;
        case 'html':
            $elementHTML = file_get_contents($CFG->dirroot . $element['file']);
            break;
        }



        // If the element is not hidden, we might need to open a new section
        if ($element['type'] !== 'hidden' && !strstr($elementHTML, 'type="hidden"')) {
            // Get the section of the latest element to be added to the form.
            $section = get_form_section($element, $lastsection);

            // If the section has changed, open a new section.  Closing last opened section also.
            // If section does not need to change, do nothing.
            $output .= get_form_section_opening($lastsection, $section);

            // Update the opened section.
            $lastsection = $section;
        }

        // Add the element to the form.
        $output .= $elementHTML;
        $tabindex++;
    }

    // Close the last section.
    $output .= get_form_section_closing($section);
    return $output;
}

function get_form_element_help($element, $data = []) {
    global $CFG;
    if (!isset($element['help']) && !isset($element['dynamichelp'])) {
        return get_help('input_default_' . $element['type']);
    }

    if (isset($element['dynamichelp'])) {
        foreach ($element['dynamichelp'] as $func => $path) {
            include_once($CFG->dirroot . $path);
            $help_function = "customhelp_" . $func;
            return $help_function($data);
        }
    }

    if (isset($element['help'])) {
        return $element['help'];
    }

    return "";
}

function get_form_element_data_rules($element, $data = []) {
    global $CFG;
    $rules = '';
    if (isset($element['required']) && $element['required'] == true) {
        $rules .= ' data-rule-required="true"';
        $msg = isset($element['required_msg']) ? $element['required_msg'] : error_string('valid_req');
        $rules .= ' data-msg-required="' . $msg . '"';
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
        $msg = isset($element['email_msg']) ? $element['email_msg'] : error_string('valid_email_invalid');
        $rules .= ' data-msg-email="' . $msg . '"';
    }

    if ($element['type'] == 'tel') {
        $rules .= ' data-rule-phone="true"';
        $msg = isset($element['phone_msg']) ? $element['phone_msg'] : error_string('valid_phone_invalid');
        $rules .= ' data-msg-phone="' . $msg . '"';
    }

    if ($element['type'] == 'date') {
        $rules .= ' data-rule-date="true"';
        $msg = isset($element['date_msg']) ? $element['date_msg'] : error_string('valid_date_invalid');
        $rules .= ' data-msg-date="' . $msg . '"';
    }

    if ($element['type'] == 'url') {
        $rules .= ' data-rule-url="true"';
        $msg = isset($element['url_msg']) ? $element['url_msg'] : error_string('valid_url_invalid');
        $rules .= ' data-msg-url="' . $msg . '"';
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

    if (isset($element['autocapitalize'])) {
        $rules .= ' autocapitalize="' . $element['autocapitalize'] . '"';
    }

    if (isset($element['customrules'])) {
        foreach ($element['customrules'] as $rule => $path) {
            include_once($CFG->dirroot . $path);
            $rule_function = "customrule_" . $rule;
            $rules .= $rule_function($data);
        }
    }
    return $rules;
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
    $value = get_element_value($element, $data);
    $style = isset($element['style']) ? $element['style'] : "";
    $money = isset($element['money']) ? '<span class="formMoneySymbol">$</span>' : "";
    $readonly = isset($element['readonly']) ? " readonly " : "";

    $output = '<input ' . $readonly . ' type="text" tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . '
                style="' . $style . '" />';
    return $money . $output;
}

function make_form_textarea($element, $data = []) {
    $value = get_element_value($element, $data);
    $style = isset($element['style']) ? $element['style'] : "";

    $output = '
        <textarea tabindex="' . $element['tabindex'] . '"
            id="' . $element['name'] . '" name="' . $element['name'] . '"
            ' . $element['rules'] . ' style="' . $style . '">' . $value . '</textarea>';
    return $output;
}

function make_form_hidden($element, $data = []) {
    $value = get_element_value($element, $data);

    $output = '<input type="hidden"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . ' />';
    return $output;
}

function make_form_date($element, $data = []) {
    $value = get_element_value($element, $data);
    $value = !empty($value) ? $value : (isset($element['value']) ? date("Y-m-d", $element['value']) : date("Y-m-d"));
    $output = '<input type="date" tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . ' />';
    return $output;
}

function make_form_tel($element, $data = []) {
    $value = get_element_value($element, $data);
    $output = '<input type="tel" tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                value="' . $value . '" ' . $element['rules'] . ' />';
    return $output;
}

function make_form_email($element, $data = []) {
    $value = get_element_value($element, $data);
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
                include_once($CFG->dirroot . $path);
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

    // Check for selected option.
    $selected = get_element_value($element, $data);

    // Array of options are available.
    $output = '<select tabindex="' . $element['tabindex'] . '"
                id="' . $element['name'] . '" name="' . $element['name'] . '"
                ' . $element['rules'] . '>';
    foreach ($options as $value => $option) {
        $output .= '<option value="' . $value . '"';
        $selected = !empty($selected) ? $selected : (isset($element['selected']) ? $element['selected'] : '');
        if ($value == $selected) {
            $output .= ' selected="selected"';
        }
        $output .= '>' . $option . '</option>';
    }
    $output .= '</select>';
    return $output;
}

function get_element_value($element, $data = []) {
    global $CFG;

    // If a dynamic value exists, retrieve it.
    if (isset($element['dynamicvalue'])) {
        if (isset($element['dynamicvalue'])) {
            foreach ($element['dynamicvalue'] as $func => $path) {
                include_once($CFG->dirroot . $path);
                $value_function = "customvalue_" . $func;
                return $value_function($data);
            }
        }
    }

    // If an autofill value exists, return it.
    if (isset($data['autofill']) && isset($data['autofill'][$element['name']])) {
        if (isset($data['autofill'][$element['name']])) {
            return $data['autofill'][$element['name']];
        }

        // alternate element name remove all non alpha numeric values.
        // (ex latest template uses health_plan instead of HealthPlan)
        $alt = preg_replace('/[^A-Za-z0-9]/', '', $element['name']);
        if (isset($data['autofill'][$alt])) {
            return $data['autofill'][$alt];
        }
    }

    // If a static default value exists, return it.
    if (isset($element['value'])) {
        return $element['value'];
    }

    return "";
}
?>