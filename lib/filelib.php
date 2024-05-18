<?php
/***************************************************************************
* filelib.php - File Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.7
***************************************************************************/

if (!LIBHEADER) { include('header.php'); }
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
		unlink($filepath);
	}
}

function recursive_mkdir($path) {
	return file_exists($path) || mkdir($path, 0o777, true);
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
		copy($old, $new) or die("Unable to copy $old to $new.");
	}
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

function create_file($filename, $contents, $makecsv=false) {
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

function get_download_link($filename, $contents, $makecsv=false) {
	global $CFG;
	return 'window.open("' . $CFG->wwwroot . '/scripts/download.php?file=' . create_file($filename, $contents, $makecsv) . '", "download", "menubar=yes,toolbar=yes,scrollbars=1,resizable=1,width=600,height=400");';
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

function fetch_template($file, $subsection, $feature = false) {
global $CFG;
    if (is_array($subsection)) {
        $contents = [];
        foreach($subsection as $sub) {
            if (!$temp = fetch_template($file, $sub, $feature)) {
                trigger_error("Fetching template $file:$subsection failed.", E_USER_ERROR);
				throw new Exception("Fetching template $file:$subsection failed.");
			}
            $contents[] = $temp;
        }
    } else {
        if ($feature) {
            $file = "features/$feature/$file";
        }

        if (!file_exists($CFG->dirroot . '/' . $file)) { // Template file not found.
            trigger_error($CFG->dirroot . '/' . $file . " not found.", E_USER_ERROR);
            throw new Exception($CFG->dirroot . '/' . $file . " not found.");
        }

        if (!$contents = file_get_contents($CFG->dirroot . '/' . $file)) {
            trigger_error($CFG->dirroot . '/' . $file . " not able to be opened.", E_USER_ERROR);
            throw new Exception($CFG->dirroot . '/' . $file . " not able to be opened.");
        }

        if (!empty($subsection)) { // Templates with multiple sections.
            if (!$contents = template_subsection($contents, $subsection)) {
				trigger_error("Fetching template $file:$subsection failed.", E_USER_ERROR);
				throw new Exception("Fetching template $file:$subsection failed.");
            }
        }
        $contents = trim($contents);
    }
	return is_array($contents) ? $contents : trim($contents);
}

function fetch_template_set($templates) {
global $CFG;
    $contents = [];
    foreach ($templates as $template) {
		$temp = false;
        $file = $template['file'] ?? false;
        $subsection = $template['subsection'] ?? false;
        $feature = $template['feature'] ?? false;
        if ($file && $subsection) { // required.
            if (!$temp = fetch_template($file, $subsection, $feature)) {
				trigger_error("Fetching template $file:$subsection failed.", E_USER_ERROR);
                throw new \Exception("Fetching template $file:$subsection failed.");
			}
            if (is_array($subsection)) {
                $contents = array_merge($contents, $temp);
            } else {
                $contents[] = $temp;
            }
        }
    }
    return $contents;
}

// Main template function
function use_template($file, $params = [], $subsection = false, $feature = false) {
	$v = $params;
    $contents = "";

    if (is_array($subsection)) {
        $contents = [];
        $i = 0;
        foreach($subsection as $sub) {
            $p = ismultiarray($params) ? array_slice($params, $i, 1)[0] : $params;
            $contents[] = use_template($file, $p, $sub, $feature);
            $i++;
        }
    } else {
		if (!$temp = fetch_template($file, $subsection, $feature)) {
			trigger_error("Fetching template $file:$subsection failed.", E_USER_ERROR);
			throw new \Exception("Fetching template $file:$subsection failed.");
		}
        $contents = $temp;
        $contents = templates_process_qualifiers($contents, $params); // Look for qualifiers ||x{{ ~~ }}x||

        // Look for template variables
        $pattern = '/\|\|((?s).*?)\|\|/i'; //Look for stuff between ||
        preg_match_all($pattern, $contents, $matches);

        foreach ($matches[1] as $match) { // Loop through each instance where the template variable bars are found. ie || xxx ||
            $optional = "";
            // Check for leading asterisks denoting optional variables.
            if (strpos($match, "*") === 0) {
                // Remove leading asterisks.
                $match = substr($match, 1);
                $optional = "*";
            }

            $replacement = template_get_functionality($match);

            // Check to make sure that a matched variable exists.
            if ($varisset = template_variable_exists($match, $params)) {
                ob_start(); // Capture eval() output
                eval($replacement);
                $contents = str_replace("||$optional$match||", ob_get_clean(), $contents);
            } else {
                $contents = str_replace("||$optional$match||", "", $contents);

                if (!$optional) {
                    trigger_error("Expected $subsection template variable $match not found in parameters array.", E_USER_NOTICE);
                }
            }
        }
    }
	return is_array($contents) ? $contents : trim($contents);
}

function use_template_set($templates, $params = []) {
	$v = $params;
    $contents = [];

    if (is_array($templates)) {
        $i = 0;
        foreach($templates as $template) {
            $file = $template['file'] ?? false;
            $subsection = $template['subsection'] ?? false;
            $feature = $template['feature'] ?? false;
            $sliceoff = 1;
            if ($file && $subsection) { // required.
                if (is_array($subsection)) {
                    $sliceoff = count($subsection);
                    $p = ismultiarray($params) ? array_slice($params, $i, $sliceoff)[0] : $params;
                    //$contents += use_template($file, $p, $subsection, $feature);
					$contents = array_merge($contents, use_template($file, $p, $subsection, $feature));
                } else {
                    $p = ismultiarray($params) ? array_slice($params, $i, $sliceoff)[0] : $params;
                    $contents[] = use_template($file, $p, $subsection, $feature);
                }
            }

            $i += $sliceoff;
        }
    }

	return $contents;
}

// Grab only a subsection of the template
function template_subsection($contents, $subsection) {
	if (!empty($subsection)) { // Templates with multiple sections.
		// Check if subsection exists.
		if (strpos($contents, "$subsection||") === false) { return false; }
		if (strpos($contents, "||$subsection") === false) { return false; }

		$startsAt = strpos($contents, "$subsection||") + strlen("$subsection||");
		$endsAt = strpos($contents, "||$subsection", $startsAt);
		return trim(substr($contents, $startsAt, $endsAt - $startsAt));
	}
	return trim($contents);
}

function templates_process_qualifiers($contents, $params) {
	$pattern = '/\|\|(?<x>.*)\{\{((?s).*?)\}\}\k<x>\|\|/i'; //Look for stuff between ||x{{ ---- }}x||
	preg_match_all($pattern, $contents, $qualifiers); // Match all on the same level (does not see nested)
	if (!empty($qualifiers[0])) { // Qualifiers found
		$i = 0;
		while (!empty($qualifiers[0][$i])) { // Full code match found
			// Make replacements in the found qualifier.
			$contents = templates_replace_qualifiers($qualifiers['x'][$i], $qualifiers[0][$i], $qualifiers[2][$i], $params, $contents);
			$i++;
		}
	}
	return $contents;
}

function templates_replace_qualifiers($eval, $fullcode, $innercode, $params, $contents) {
	$innercontent = templates_process_qualifiers($innercode, $params); // Look for nested qualifiers.
	$replacewith = explode("//OR//", $innercontent);

	if (count($replacewith) < 2) { // If no //OR// is found, fill the missing array with an empty string.
		$replacewith[1] = "";
	}

	if ($params[$eval] === false) { // If eval variable is false, replace with the 2nd part of the OR
		$contents = str_replace($fullcode, $replacewith[1], $contents);
	} else { // If eval variable is true, replace with the 1st part of the OR
		$contents = str_replace($fullcode, $replacewith[0], $contents);
	}
	return $contents;
}

// Looks for functions or loops and arrange parts.
function template_get_functionality($match) {
	$type = strpos($match, "::") === false ? "simple" : "code";
	$varslist = ""; $code = ""; $func = "";  $variables = $match; $replacement = "";
	if ($type === "code") { // Either direct functon or code provided
		$template = explode("::", $match); // Split function::variablename
		$func = $template[0];
		$variables = template_clean_complex_variables($template[1]);
	}

	$vars = explode("|", $variables);
	foreach ($vars as $var) {
		$cp = template_get_complex_variables($var); // cut off and store any complex variable parts. ie [] arrays or -> objects
		$vr = template_clean_complex_variables($var); // cut off the complex variable parts and return the root variable.
		$v = template_create_v($var, $vr, $cp); // take the root and complex parts (if any) and recombine them to work in the template.

		if ($type === "code") { // Is code provided from the template?
			$code = empty($code) ? $func : $code; // Get code on the first variable.  Keep the old version for the next variables.
			$code = str_replace($var, $v, $code, $count); // Replace the placeholder in code with the PHP variable.
			if ($var != "none" && !$count) { // If there isn't a perfect match in the template code, it is either a mistake or an unneccesary variable given.
				echo "<br />Variable '$v' could not be matched in the supplied code.<br /><br />";
			}
		} else { // Simple echo of single variables;
			$replacement .= 'echo ' . $v . ';';
		}
	}

	if ($type === "code") { $replacement = $code; }  // Completed code is set as the replacement text.

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

// Strips off array brackets or object pointers to be reattached later.
function template_get_complex_variables($var) {
	$part = "";
  if (strpos($var, "[") !== false) {
	$bracket = strpos($var, "[");
	$part = substr($var, $bracket, strlen($var) - $bracket);
		$part = str_replace('"', '', $part);
	$part = str_replace('[', '["', $part);
	$part = str_replace(']', '"]', $part);
  } elseif (strpos($var, "-") !== false) {
	$part = substr($var, strpos($var, "-"), strlen($var) - strpos($var, "-"));
  }
  return $part;
}

// Gets the root variable.  For arrays this will be the key, for objects it will be blank.
function template_clean_complex_variables($var) {
  if (strpos($var, "[") !== false) { $var = substr($var, 0, strpos($var, "[")); } // Remove assoiative array brackets
  if (strpos($var, "-") !== false) { $var = substr($var, 0, strpos($var, "-")); } // Remove everything after object arrows
	if (strpos($var, "{{") !== false) { $var = substr($var, 0, strpos($var, "{{")); } // Remove everything after qualifier code.
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
		return $link; // for loadjs() so we don't know if it is actually every loaded.
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

function js_code_wrap($code, $loadtype = false, $jquery = false) {
	$loadtype = !$loadtype ? "" : $loadtype;
	$jq_open = $jquery ? 'defer(function () { $(function() {' : '';
	$jq_close = $jquery ? '}); });' : '';
	return <<<EOT
		<script type="text/javascript" $loadtype >
			$jq_open
			$code
			$jq_close
		</script>
		EOT;
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
		add_js_to_array("ajax", "siteajax.js", $javascript);
	}
	if (array_search("jquery", $params) !== false) { // jQuery.
		add_js_to_array("scripts", "jquery.min.js", $javascript);
		add_js_to_array("scripts", "jquery.extend.js", $javascript);
	}
	if (array_search("ui", $params) !== false) { // jQuery UI.
		add_js_to_array("scripts", "jquery.min.js", $javascript);
		add_js_to_array("scripts", "jquery.extend.js", $javascript);
		add_js_to_array("scripts", "jquery-ui.min.js", $javascript);
	}
	if (array_search("colorbox", $params) !== false) { // Modal popups.
		add_js_to_array("scripts", "jquery.min.js", $javascript);
		add_js_to_array("scripts", "jquery.extend.js", $javascript);
		add_js_to_array("scripts", "jquery.colorbox.js", $javascript);
		add_js_to_array("scripts", "jquery.colorbox.extend.js", $javascript);
	}
	if (array_search("tabs", $params) !== false) { // Tabs.
		add_js_to_array("scripts", "ajaxtabs.js", $javascript);
	}
	if (array_search("popupcal", $params) !== false) { // Calendar.
		add_js_to_array("scripts", "popupcalendar.js", $javascript);
	}
	if (array_search("validate", $params) !== false) { // jQuery validate.
		add_js_to_array("scripts", "jquery.min.js", $javascript);
		add_js_to_array("scripts", "jquery.extend.js", $javascript);
		add_js_to_array("scripts", "jqvalidate.js", $javascript);
		add_js_to_array("scripts", "jqvalidate_addon.js", $javascript);
	}
	if (array_search("picker", $params) !== false) { // Color picker.
		add_js_to_array("scripts/picker", "picker.js", $javascript);
	}
	if (array_search("flickity", $params) !== false) { // Image carolsel.
		add_js_to_array("scripts", "flickity.js", $javascript);
	}
	// Check for module level js.
	foreach ($params as $p) {
		$module = array_filter(explode("/", $p));
		if (count($module) > 1) {
			$file = end($module);
			array_pop($module);
			$folder = implode("/", $module);
			add_js_to_array($folder, $file, $javascript);
		}
	}
	return $javascript;
}

function get_js_set($setname, $loadtype = false) {
	$params = [];
	switch ($setname) {
		case "main":
			$params = ["siteajax", "jquery", "colorbox", "ui", "flickity"];
			break;
		case "basics":
			$params = ["siteajax", "jquery"];
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
	if (array_search("jtip", $params) !== false) {
		add_css_to_array("styles", "jtip.css", $css); // jTip
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