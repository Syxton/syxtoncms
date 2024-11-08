<?php
/***************************************************************************
* filelib.php - File Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.7
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('FILELIB', true);

// Make Javascript loaded array.
if (!isset($LOADED)) {
    $LOADED = [];
}

function get_file_captions($path) {
    $caption_return = [];
    if (file_exists($path . '/captions.txt')) {
        $fhandle = fopen($path . '/captions.txt', 'r');
        while(!feof($fhandle)) { // until end of file
            $caption = explode("||", fgets($fhandle));
            $caption_return[trim($caption[0])] = trim($caption[1]);
        }
        fclose($fhandle);
    }
    return $caption_return;
}

function delete_old_files($path, $days = 1) {
global $CFG;
    $seconds = $days * (24*60*60);
    $dir = $CFG->dirroot . $path;
    $files = scandir($dir);
    foreach ($files as $num => $fname) {
        if (file_exists("{$dir}{$fname}") && ((time() - filemtime("{$dir}{$fname}")) > $seconds)) {
            $mod_time = filemtime("{$dir}{$fname}");
            if ($fname != "..") {
                if (unlink("{$dir}{$fname}")) {$del = $del + 1;}
            }
        }
    }
}

function delete_file($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }

    return true; // File doesn't exist.
}

function recursive_mkdir($path) {
    return file_exists($path) || mkdir($path, 0777, true);
}

function recursive_delete($folderPath) {
    if ( is_dir ( $folderPath ) ) {
        foreach ( scandir ( $folderPath )  as $value ) {
            if ( $value != "." && $value != ".." ) {
                $value = $folderPath . "/" . $value;
                if ( is_dir ( $value ) ) {
                    FolderDelete ( $value );
                } elseif ( is_file ( $value ) ) {
                    @unlink ( $value );
                }
            }
        }
        return rmdir ( $folderPath );
    } else {
        return false;
    }
}

function copy_file($old, $new) {
    if (file_exists($old)) {
        return copy($old, $new) or die("Unable to copy $old to $new.");
    }
    return false;
}

function make_csv($filename, $contents) {
    $tempdir = sys_get_temp_dir() == "" ? "/tmp/" : sys_get_temp_dir();
    $tmpfname = tempnam($tempdir, $filename);
    if (file_exists($tmpfname)) { unlink($tmpfname); }
    $handle = fopen($tmpfname, "w");
    foreach ($contents as $fields) {
        fputcsv($handle, $fields);
    }
    fclose($handle);
    rename($tmpfname, $tempdir. "/" . $filename);
    return addslashes($tempdir. "/" . $filename);
}

function create_file($filename, $contents, $makecsv = false) {
    if ($makecsv) {
        return make_csv($filename, $contents);
    } else {
        $tempdir = sys_get_temp_dir() == "" ? "/tmp/" : sys_get_temp_dir();
        $tmpfname = tempnam($tempdir, $filename);
        if (file_exists($tmpfname)) {	unlink($tmpfname); }
        $handle = fopen($tmpfname, "w");

        fwrite($handle, stripslashes($contents));
        fclose($handle);
        rename($tmpfname, $tempdir. "/" . $filename);
        return addslashes($tempdir . "/" . $filename);
    }
}

function get_download_link($filename, $contents, $makecsv = false, $iframename = "downloadframe") {
    global $CFG;
    return 'getRoot()[0].window.open("' . $CFG->wwwroot . '/scripts/download.php?file=' . create_file($filename, $contents, $makecsv) . '", "' . $iframename . '");';
}

function return_bytes ($size_str) {
    switch (substr ($size_str, -1)) {
        case 'M': case 'm': case 'mb': return (int)$size_str * 1048576;
        case 'K': case 'k': case 'kb': return (int)$size_str * 1024;
        case 'G': case 'g': case 'gb': return (int)$size_str * 1073741824;
        default: return $size_str;
    }
}

function get_protocol() {
global $CFG;
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https:" : "http:";
    $protocol = strstr($CFG->wwwroot, "http") ? '' : $protocol;
    return $protocol;
}

/**
 * Fetches a template file and returns its contents.
 *
 * @param string $file The name of the file to fetch.
 * @param mixed $subsection The subsection(s) of the file to fetch. Can be a string or an array.
 * @param bool|string $feature Optional. The name of the feature the file belongs to.
 * @param array $params Optional. An array of parameters to process qualifiers with.
 * @return string|array The contents of the fetched template. If $subsection is an array, an array of fetched templates is returned.
 * @throws Exception If the template or subsection is not found.
 */
function fetch_template($file, $subsection, $feature = false, $params = []) {
    global $CFG;
    try {
        // If $subsection is an array, fetch each subsection, store the results in $contents.
        if (is_array($subsection)) {
            $contents = [];
            foreach ($subsection as $sub) {
                $contents[] = fetch_template($file, $sub, $feature, $params);
            }
        } else {
            // If $feature is set, add the feature to the file path.
            if ($feature) {
                $file = "features/$feature/$file";
            }

            // Check if the file exists.
            $filePath = $CFG->dirroot . '/' . $file;
            if (!file_exists($filePath)) {
                throw new Exception("$filePath not found.");
            }

            // Fetch the contents of the file.
            $contents = file_get_contents($filePath);
            if ($contents === false) {
                throw new Exception("$filePath not able to be opened.");
            }

            // If $subsection is set, process the subsection.
            if (!empty($subsection)) {
                $contents = template_subsection($contents, $subsection);
                if ($contents === false) {
                    throw new Exception("Fetching template subsection $file:$subsection failed.");
                }
            }

            // If $params is set, process qualifiers.
            if (!empty($params)) {
                $contents = templates_process_qualifiers($contents, $params);
            }
        }

        // Return the contents of the template(s).
        return is_array($contents) ? $contents : trim($contents);
    } catch (\Exception $e) {
        debugging($e->getMessage());
        throw $e;
    }
    return false;
}

/**
 * Fetches a set of templates and returns their contents.  Only processes qualifiers if params are sent.
 *
 * @param array $templates An array of templates, each containing 'file', 'subsection' and 'feature' keys.
 * @return array The contents of the fetched templates.
 * @throws Exception If a template or subsection is not found.
 */
function fetch_template_set($templates) {
    global $CFG;

    // Initialize variables.
    $contents = [];

    if (isset($templates["file"])) { // Single array passed.  Make it a multiarray.
        $templates = [$templates];
    }

    // Loop through each template.
    foreach ($templates as $template) {
        $file = $template['file'] ?? false;
        $subsection = $template['subsection'] ?? false;
        $feature = $template['feature'] ?? false;

        // Check if the template has both a file and subsection.
        if ($file && $subsection) {
            // Fetch the template and check if it was found.
            if (!$temp = fetch_template($file, $subsection, $feature)) {
                trigger_error("Fetching template $file:$subsection failed.", E_USER_ERROR);
                throw new \Exception("Fetching template $file:$subsection failed.");
            }

            // If the subsection is an array, merge the template contents with the contents.
            // Otherwise, add the template contents to the contents.
            if (is_array($subsection)) {
                $contents = array_merge($contents, $temp);
            } else {
                $contents[] = $temp;
            }
        }
    }

    // Return the fetched templates.
    return $contents;
}

/**
 * Fill a template with variables.
 * @param string $file The name of the template file that the template is found in.
 * @param string|array $subsection The subsection of the template to fill. Can be a string or an array of strings.
 * @param string $feature The name of the feature template folders are in. Defaults to false for core templates.
 * @param array $params The parameters to use when filling the template. Defaults to an empty array.
 * @param bool $allowpartial Whether to allow a return of a partially filled template. Defaults to false. (Used mostly for dynamic sql building)
 * @return string|array The filled template. If the template is an array, it will be returned as an array. Otherwise, it will be returned as a string.
 * @throws Exception If the template or subsection is not found.
 */
function fill_template($file, $subsection, $feature = false, $params = [], $allowpartial = false) {
    $v = $params;
    $contents = "";

    try {
        // If subsection is an array, fill each subsection separately.
        if (is_array($subsection)) {
            $contents = [];
            $i = 0;
            foreach($subsection as $sub) {
                $p = isMultiArray($params) ? array_slice($params, $i, 1)[0] : $params;
                $contents[] = fill_template($file, $sub, $feature, $p);
                $i++;
            }
        } else {
            // Fetch the template and check if it was found.
            $temp = fetch_template($file, $subsection, $feature, $params);
            if ($temp === false) {
                trigger_error("Fetching template $file:$subsection failed.", E_USER_ERROR);
                throw new \Exception("Fetching template $file:$subsection failed.");
            }
            $contents = $temp;

            // Look for template variables
            $pattern = '/\|\|((?s).*?)\|\|/i'; //Look for stuff between ||
            preg_match_all($pattern, $contents, $matches);

            // Loop through each instance where the template variable bars are found. ie || xxx ||
            foreach ($matches[1] as $match) {
                $optional = "";
                // Check for leading asterisks denoting optional variables.
                if (strpos($match, "*") === 0) {
                    // Remove leading asterisks.
                    $match = substr($match, 1);
                    $optional = "*";
                }

                // Check if the matched variable exists.
                if ($varisset = template_variable_exists($match, $params)) {
                    // Get the functionality of the variable.
                    $replacement = template_get_functionality($match);

                    $replacement = 'try { $t = error_reporting(); error_reporting(E_PARSE); ' . $replacement . ' } catch (\Throwable $e) { ob_get_clean(); error_reporting($t); throw new Exception($e->getMessage()); }';
                    ob_start(); // Capture output
                    eval($replacement);
                    $contents = str_replace("||$optional$match||", ob_get_clean(), $contents);
                } else {
                    // If the variable is not found, remove it from the template.
                    if (!$allowpartial) {
                        $contents = str_replace("||$optional$match||", "", $contents);
                    }

                    // If the variable is not optional and allowpartial is false, trigger a notice.
                    if (!$optional && !$allowpartial) {
                        trigger_error("Expected $subsection template variable $match not found in parameters array.", E_USER_NOTICE);
                    }
                }
            }
        }
        return is_array($contents) ? $contents : trim($contents);
    } catch (\Exception $e) {
        debugging($e->getMessage());
        throw new \Exception($e->getMessage());
    }
    return false;
}

/**
 * Fills a set of templates with given parameters and returns the contents.
 *
 * @param array $templates An array of templates, each containing 'file', 'subsection' and 'feature' keys.
 * @param array $params An optional array of parameters to fill the templates with.
 * @return array The contents of the filled templates.
 */
function fill_template_set($templates, $params = []) {
    // Initialize variables.
    $contents = [];

    // If templates is an array, fill each template separately.
    if (is_array($templates)) {
        $i = 0; // Counter for params array.
        foreach($templates as $template) {
            $file = $template['file'] ?? false; // Get the template file.
            $subsection = $template['subsection'] ?? false; // Get the template subsection.
            $feature = $template['feature'] ?? false; // Get the template feature.
            $sliceoff = 1; // Number of params to slice off.

            // If template file and subsection are specified, fill the template.
            if ($file && $subsection) {
                if (is_array($subsection)) {
                    $sliceoff = count($subsection); // If subsection is an array, slice off the count of subsections.
                    $p = isMultiArray($params) ? array_slice($params, $i, $sliceoff) : $params; // Get the params for the template.
                    $contents = array_merge($contents, fill_template($file, $subsection, $feature, $p)); // Fill the template and merge with contents.
                } else {
                    // If a single subsection, but multiarray, slicing 1 off, and only send a simple array. array()[0]
                    $p = isMultiArray($params) ? array_slice($params, $i, 1)[0] : $params; // Get the params for the template.

                    $contents[] = fill_template($file, $subsection, $feature, $p); // Fill the template and add to contents.
                }
            }
            $i += $sliceoff; // Increment the params counter.
        }
    }

    // Return the filled templates.
    return $contents;
}

/**
 * Replaces placeholders in content with actual values from an array.
 *
 * @param string $content The content to replace placeholders in.
 * @param array $params An associative array of placeholders and their values.
 * @return string The content with placeholders replaced.
 */
function fill_in_blanks($content, $params) {
    // Loop through the parameters and replace placeholders in content.
    foreach ($params as $key => $value) {
        // Replace "||$key||" with the value in the content.
        $content = str_replace("||$key||", $value, $content);
    }
    // Return the content with placeholders replaced.
    return $content;
}

/**
 * Extracts a subsection of a template.
 *
 * @param string $contents The contents of the template.
 * @param string $subsection The subsection to extract.
 * @return string|false The extracted subsection or false if not found.
 */
function template_subsection($content, $subsection) {
    // Construct the start and end delimiters
    $startDelimiter = $subsection . '||';
    $endDelimiter = '||' . $subsection;

    // Find the starting position of the content
    $startPos = false;

    // Check if the start delimiter is at the beginning of the content
    if (strpos($content, $startDelimiter) === 0) {
        $startPos = 0;
    } else {
        // Check if the start delimiter is preceded by a newline character
        $startPos = strpos($content, "\n" . $startDelimiter);
        if ($startPos !== false) {
            $startPos += 1; // Move to the position after the newline character
        }
    }

    if ($startPos === false) {
        return false; // Start delimiter not found
    }

    // Move the starting position to the end of the start delimiter
    $startPos += strlen($startDelimiter);

    // Find the ending position of the content
    $endPos = strpos($content, $endDelimiter, $startPos);
    if ($endPos === false) {
        return false; // End delimiter not found
    }

    // Extract the content between the delimiters
    $extractedContent = substr($content, $startPos, $endPos - $startPos);

    return $extractedContent;
}

/**
 * Process qualifiers in the content.
 *
 * @param string $contents The contents to process.
 * @param array $params The parameters to replace placeholders with.
 * @return string The processed content.
 */
function templates_process_qualifiers($contents, $params) {
    // Define the pattern to match the qualifiers.
    $pattern = '/(?<outer>\|\|(?<opt>\*?)(?<var>.*)\{\{(?<inner>(?s).*?)\}\}\k<var>\|\|)/i';

    // Find all the qualifiers in the content.
    preg_match_all($pattern, $contents, $qualifiers);

    // Check if qualifiers were found.
    if (!empty($qualifiers[0])) {
        $i = 0;

        // Loop through all the qualifiers found.
        while (!empty($qualifiers['outer'][$i])) {
            // Replace the qualifier with the processed version.
            $optional = empty($qualifiers['opt'][$i]) ? false : true;
            $contents = templates_replace_qualifiers($qualifiers['var'][$i], $qualifiers['outer'][$i], $qualifiers['inner'][$i], $params, $contents, $optional);
            $i++;
        }
    }

    // Return the processed content.
    return $contents;
}

/**
 * Replaces a qualifier in the content with the appropriate part based on the evaluation result.
 *
 * @param string $eval The evaluation to check.
 * @param string $fullcode The full code of the qualifier.
 * @param string $innercode The inner code of the qualifier.
 * @param array $params The parameters to replace placeholders with.
 * @param string $contents The contents to process.
 * @return string The processed content.
 */
function templates_replace_qualifiers($var, $fullcode, $innercode, $params, $contents, $optional = false) {
    // Recursively process the inner code to check for nested qualifiers.
    $innercontent = templates_process_qualifiers($innercode, $params);

    // Split the inner content based on the //OR// delimiter.
    $replacewith = explode("//OR//", $innercontent);

    // If no //OR// is found, fill the missing array with an empty string.
    if (count($replacewith) < 2) {
        $replacewith[1] = "";
    }

    if (isset($params[$var])) {
        if (!$params[$var]) { // If $var variable evaluates to false, replace with the 2nd part of the OR or nothing.
            $contents = str_replace($fullcode, $replacewith[1], $contents);
        } elseif ($params[$var]) { // If eval variable evaluates to true, replace with the 1st part of the OR
            $contents = str_replace($fullcode, $replacewith[0], $contents);
        }
    } else {
        if (!$optional) {
            throw new Exception("Missing qualifier variable: " . $var);
        }
        $contents = str_replace($fullcode, "", $contents);
    }

    return $contents;
}

/**
 * Looks for functions or loops and arrange parts.
 *
 * @param string $match
 *   The function or loop call with variables.
 *
 * @return string
 *   The completed PHP code.
 */
function template_get_functionality($match) {
    // Determine if direct function or code provided.
    $type = strpos($match, "::") === false ? "simple" : "code";
    $varslist = ""; $code = ""; $func = "";  $variables = $match; $replacement = "";

    if ($type === "code") { // Code provided from the template.
        // Split function::variablename.
        $template = explode("::", $match);
        $func = $template[0]; // Get the function name.
        $variables = template_clean_complex_variables($template[1]); // Get the variables.
    }

    // Split the variables.
    $vars = explode("|", $variables);

    // Loop through each variable.
    foreach ($vars as $var) {
        $cp = template_get_complex_variables($var); // Get complex variable parts.
        $vr = template_clean_complex_variables($var); // Get root variable.
        $v = template_create_v($var, $vr, $cp); // Combine root and complex parts.

        if ($type === "code") { // If code is provided.
            $code = empty($code) ? $func : $code; // Get code on the first variable.
            $code = str_replace($var, $v, $code, $count); // Replace the placeholder in code.
            if ($var !== "none" && !$count) { // If there isn't a perfect match in the template code.
                echo "<br />Variable '$v' given for the template code section could not be found.<br /><br />";
            }
        } else { // Simple echo of single variables.
            $replacement .= 'echo ' . $v . ';' . PHP_EOL;
        }
    }

    if ($type === "code") { $replacement = $code; } // Completed code is set as the replacement text.

    return $replacement;
}

/**
 * Check to make sure that variables in the template are provided.
 *
 * @param string $match
 *   The variable name or function and variable name
 * @param array $params
 *   The parameters to check
 *
 * @return bool
 *   True if the variable exists, false otherwise
 */
function template_variable_exists($match, $params) {
    // Check for simple variable
    if (strpos($match, "::") === false) {
        if ($return = template_variable_tests($match, $params)) {
            return $return;
        }
    } else { // Advanced functionality
        // Split function::variablename
        $x = explode("::", $match);
        $vars = explode("|", template_clean_complex_variables($x[1]));

        // Loop through all variables
        foreach ($vars as $m) {
            if ($return = template_variable_tests($m, $params)) {
                return $return;
            }
        }
    }
    return false;
}

/**
 * Create tests to see if a variable is set in the params array.  Handles both
 * simple and complex variables.
 *
 * @param string $match
 *   The variable name to check
 * @param array $params
 *   The parameters to check
 *
 * @return bool
 *   True if the variable exists, false otherwise
 */
function template_variable_tests($match, $params) {
    // Cut off and store any complex variable parts. ie [] arrays or -> objects
    $cp = template_get_complex_variables($match);
    // Get root variable.
    $vr = template_clean_complex_variables($match);

    if (strpos($match, "[") !== false || strpos($match, "-") !== false) { // Complex variable.
        // PHP code to check for the variable
        $v = 'return isset($params["' . $vr . '"]' . $cp . ');';
    } else { // Simple variable.
        // PHP code to check for the variable
        $v = 'return array_key_exists("' . $vr . '", $params);';
    }

    if ($return = template_variable_isset($params, $vr, $v)) {
        return $return;
    }
    return false;
}

/**
 * Check to see if a variable is set in the params array and return true/false.
 *
 * @param array $params
 *   The parameters to check
 * @param string $var
 *   The variable name to check
 * @param string $test
 *   The PHP code to check for the variable
 *
 * @return bool
 *   True if the variable exists, false otherwise
 */
function template_variable_isset($params, $var, $test) {
    // If the variable is none, return true.
    if ($var == "none") {
        return true;
    }

    // Run the variable test and store the results.
    $eval = eval($test);

    // If the variable is set, return true, otherwise false.
    if ($eval !== NULL) {
        return $eval;
    }

    // If the variable was not found, notify the user.
    echo "<br /><br />Template variable '$var' not found.<br /><br />";
    return false;
}

/**
 * Creates the template variable string ie $v["name"] or $v->name
 *
 * @param string $match
 *   The string from the template that the variable is in.
 * @param string $var
 *   The variable name to check
 * @param string $complex_parts
 *   The PHP code to check for the variable
 *
 * @return string
 *   The PHP variable string to use in the template
 */
function template_create_v($match, $var, $complex_parts) {
    if (strpos($match, "[") !== false) { // Complex variable in associative array form
        $v = '$v["' . $var . '"]' . $complex_parts;
    } elseif (strpos($match, "-") !== false) { // Complex variable in object form
        if (empty($var)) {
            $v = '$v' . $complex_parts;
        } else {
            $v = '$v["' . $var . '"]' . $complex_parts;
        }
    } else { // Simple variable.
        $v = '$v["' . $var . '"]';
    }
    return $v;
}

/**
 * Strips off array brackets or object pointers to be reattached later.
 *
 * @param string $var
 *   The variable to extract complex parts from.
 *
 * @return string
 *   The complex parts of the variable.
 */
function template_get_complex_variables($var) {
    // Initialize the variable to store the complex parts.
    $part = "";

    // If the variable contains array brackets...
    if (strpos($var, "[") !== false) {
        // Get the starting position of the brackets.
        $bracket = strpos($var, "[");
        // Get the complex parts.
        $part = substr($var, $bracket, strlen($var) - $bracket);
        // Remove any double quotes.
        $part = str_replace('"', '', $part);
        // Change the brackets to be in the format used in PHP.
        $part = str_replace('[', '["', $part);
        $part = str_replace(']', '"]', $part);
    } elseif (strpos($var, "-") !== false) { // If the variable contains object pointers...
        // Get the complex parts.
        $part = substr($var, strpos($var, "-"), strlen($var) - strpos($var, "-"));
    }

    // Return the complex parts of the variable.
    return $part;
}

/**
 * Gets the root variable.
 * For arrays this will be the key, for objects it will be blank.
 *
 * @param string $var
 *   The variable to extract the root from.
 *
 * @return string
 *   The root part of the variable.
 */
function template_clean_complex_variables($var) {
    // Remove assoiative array brackets.
    if (strpos($var, "[") !== false) { $var = substr($var, 0, strpos($var, "[")); }
    // Remove everything after object arrows.
    if (strpos($var, "-") !== false) { $var = substr($var, 0, strpos($var, "-")); }
    // Remove everything after qualifier code.
    if (strpos($var, "{{") !== false) { $var = substr($var, 0, strpos($var, "{{")); }

    return $var;
}

// Smarter Javascript gathering.
function get_js_tags($params, $linkonly = false, $loadtype = false) {
    global $CFG, $LOADED;
    $javascript = build_from_js_library($params);

    $filelist = [];
    $dir = empty($CFG->directory) ? '' : $CFG->directory . '/';
    foreach ($javascript as $path => $files) {
        foreach ($files as $file) {
            array_push($filelist, $dir . $path . "/" . $file);
        }
    }

    if (count($filelist)) {
        $link = $CFG->wwwroot . '/min/?f=' . implode(",", $filelist);
        if ($linkonly) {
            return $link; // for loadjs() so we don't know if it is actually ever loaded.
        } else {
            $LOADED = array_merge_recursive($LOADED, $javascript); // set global to loaded javascript.
            return js_script_wrap($link, $loadtype);
        }
    }
    return;
}

function js_script_wrap($link, $loadtype = false) {
    $loadtype = !$loadtype ? "" : $loadtype;
    return '<script type="text/javascript" src="' . $link . '" ' . $loadtype . '></script>';
}

/**
 * Wrap JavaScript code in a <script> tag.
 *
 * @param string $code The JavaScript code to wrap.
 * @param string $loadtype The type of script loading. Default is empty.
 * @param bool $waitforjquery Whether or not to wait for jQuery to load. Default is false.
 * @param string $id The ID of the script tag. Default is an empty string.
 * @param string $class The class of the script tag. Default is an empty string.
 * @return string The JavaScript code wrapped in a <script> tag.
 */
function js_code_wrap($code, $loadtype = '', $waitforjquery = false, $id = '', $class = '') {
    // Set the loadtype to an empty string if it is not set.
    $loadtype = !$loadtype ? '' : $loadtype;

    // Set the defer_open and defer_close variables based on the waitforjquery parameter.
    $defer_open = $waitforjquery ? 'defer(function () { $(function() {' : '';
    $defer_close = $waitforjquery ? '}); });' : '';

    // Return the JavaScript code wrapped in a <script> tag.
    return '<script id="' . $id . '" class="' . $class . '" type="text/javascript" ' . $loadtype . '>' . $defer_open . $code . $defer_close . '</script>';
}

function add_js_to_array($path, $script, &$javascript = []) {
    if (!js_already_loaded($path, $script)) {
        if (array_key_exists($path, $javascript) === false) { // path doesn't exist yet.
            $javascript[$path] = [];
        }
        array_push($javascript[$path], $script);
        $javascript[$path] = array_unique($javascript[$path]);
    }
    return $javascript;
}

function js_already_loaded($path, $script) {
global $LOADED;
    $key = array_key_exists($path, $LOADED);
    if ($key !== false) { // path exists.
        $key = array_search($script, $LOADED[$path]);
        if ($key !== false) { // script loaded.
            return true;
        }
    }
    return false;
}

function build_from_js_library($params) {
    $javascript = [];
    if (array_search("siteajax", $params) !== false) { // Site javascript.
        add_js_to_array("scripts", "jquery.min.js", $javascript);
        add_js_to_array("scripts", "jquery.extend.js", $javascript);
        add_js_to_array("scripts/fontawesome", "fontawesome.min.js", $javascript);
        add_js_to_array("scripts/fontawesome", "solid.min.js", $javascript);
        //add_js_to_array("scripts/fontawesome", "regular.min.js", $javascript);
        //add_js_to_array("scripts/fontawesome", "brands.min.js", $javascript);
        add_js_to_array("ajax", "siteajax.js", $javascript);
    }
    if (array_search("jquery", $params) !== false) { // jQuery.
        add_js_to_array("scripts", "jquery.min.js", $javascript);
        add_js_to_array("scripts", "jquery.extend.js", $javascript);
    }
    if (array_search("ui", $params) !== false) { // jQuery UI.
        add_js_to_array("scripts", "jquery.extend.js", $javascript);
        add_js_to_array("scripts", "jquery-ui.min.js", $javascript);
    }
    if (array_search("colorbox", $params) !== false) { // Modal popups.
        add_js_to_array("scripts", "jquery.min.js", $javascript);
        add_js_to_array("scripts", "jquery.extend.js", $javascript);
        add_js_to_array("scripts", "jquery.colorbox.js", $javascript);
        add_js_to_array("scripts", "jquery.colorbox.extend.js", $javascript);
        add_js_to_array("scripts", "frame_resize.js", $javascript);
    }
    if (array_search("tabs", $params) !== false) { // Tabs.
        add_js_to_array("scripts", "ajaxtabs.js", $javascript);
    }
    if (array_search("validate", $params) !== false) { // jQuery validate.
        add_js_to_array("scripts", "jqvalidate.js", $javascript);
        add_js_to_array("scripts", "jqvalidate_addon.js", $javascript);
    }
    if (array_search("flickity", $params) !== false) { // Image carolsel.
        add_js_to_array("scripts", "flickity.js", $javascript);
    }
    // Check for module level js.
    foreach ($params as $p) {
        // Split the path into folder and file name.
        $module = array_filter(explode("/", $p));
        // If the path has more than one part, add the folder and file name to the javascript array.
        if (count($module) > 1) {
            $file = end($module); // Get the last part of the path.
            array_pop($module); // Remove the last part of the path.
            $folder = implode("/", $module); // Join the remaining parts of the path with forward slashes.
            add_js_to_array($folder, $file, $javascript); // Add the folder and file name to the javascript array.
        }
    }
    return $javascript;
}

function get_js_set($setname, $loadtype = false) {
    $params = [];
    switch ($setname) {
        case "main":
            $params = ["siteajax", "colorbox", "ui", "flickity"];
            break;
        case "basics":
            $params = ["siteajax"];
            break;
    }
    return get_js_tags($params, false, $loadtype);
}

// Smarter CSS gathering.
function get_css_tags($params) {
global $CFG;
    $css = build_from_css_library($params);

    $filelist = [];
    $dir = empty($CFG->directory) ? '' : $CFG->directory . '/';
    foreach ($css as $path => $files) {
        foreach ($files as $file) {
            array_push($filelist, $dir . $path . "/" . $file);
        }
    }

    if (count($filelist)) {
        $link = $CFG->wwwroot . '/min/?f=' . implode(",", $filelist);
        return css_script_wrap($link);
    }
    return;
}

function css_script_wrap($link) {
    return '<link rel="stylesheet" href="' . $link . '" media="print" onload="this.onload=null;this.removeAttribute(\'media\');"/>';
}

function add_css_to_array($path, $script, &$css = []) {
    if (array_key_exists($path, $css) === false) { // path doesn't exist yet.
        $css[$path] = [];
    }
    array_push($css[$path], $script);
    $css[$path] = array_unique($css[$path]);
    return $css;
}

function build_from_css_library($params) {
    $css = [];
    if (array_search("main", $params) !== false) { // Site javascript.
        add_css_to_array("styles", "styles_main.css", $css);
    }
    if (array_search("colorbox", $params) !== false) { // Modal popups.
        add_css_to_array("styles", "colorbox.css", $css);
    }
    if (array_search("flickity", $params) !== false) { // Image carolsel.
        add_css_to_array("styles", "flickity.css", $css);
    }
    if (array_search("ui", $params) !== false) {
        add_css_to_array("styles/jqueryui", "jquery-ui.css", $css); // jQueryUI
    }
    if (array_search("print", $params) !== false) {
        add_css_to_array("styles", "print.css", $css); // jTip
    }
    if (array_search("menu", $params) !== false) {
        add_css_to_array("styles", "styles_menu.css", $css); // jTip
    }
    // Check for module level css.
    foreach ($params as $p) {
        $module = array_filter(explode("/", $p));
        if (count($module) > 1) {
            $file = end($module);
            array_pop($module);
            $folder = implode("/", $module);
            add_css_to_array($folder, $file, $css);
        }
    }
    return $css;
}

function get_css_set($setname) {
    $params = [];
    switch ($setname) {
        case "main":
            $params = ["main", "colorbox", "flickity"];
            break;
    }
    return get_css_tags($params);
}
?>