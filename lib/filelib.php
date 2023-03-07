<?php
/***************************************************************************
* filelib.php - File Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 06/03/2021
* Revision: 0.0.7
***************************************************************************/

if (!isset($LIBHEADER)) { include('header.php'); }
$FILELIB = true;

// Make Javascript loaded array.
if (!isset($LOADED)) {
  $LOADED = array();
}

function get_file_captions($path) {
	$caption_return = array();
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
	$dir    = $CFG->dirroot . $path;
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
  return file_exists($path) || mkdir($path, 0777, true);
}

function recursive_delete ( $folderPath ) {
    if ( is_dir ( $folderPath ) ) {
        foreach ( scandir ( $folderPath )  as $value ) {
            if ( $value != "." && $value != ".." ) {
                $value = $folderPath . "/" . $value;
                if ( is_dir ( $value ) ) {
                    FolderDelete ( $value );
                }elseif ( is_file ( $value ) ) {
                    @unlink ( $value );
                }
            }
        }
        return rmdir ( $folderPath );
    } else {
        return false;
    }
}

function copy_file($old,$new) {
    if (file_exists($old)) {
		copy($old, $new) or die("Unable to copy $old to $new.");
	}
}

function make_csv($filename,$contents) {
    $tempdir = sys_get_temp_dir() == "" ? "/tmp/" : sys_get_temp_dir();
    $tmpfname = tempnam($tempdir, $filename);
    if (file_exists($tmpfname)) {	unlink($tmpfname); }
    $handle = fopen($tmpfname, "w");
    foreach ($contents as $fields) {
        fputcsv($handle, $fields);
    }
    fclose($handle);
    rename($tmpfname,$tempdir."/".$filename);
    return addslashes($tempdir."/".$filename);
}

function create_file($filename,$contents,$makecsv=false) {
    if ($makecsv) {
        return make_csv($filename,$contents);
    } else {
        $tempdir = sys_get_temp_dir() == "" ? "/tmp/" : sys_get_temp_dir();
        $tmpfname = tempnam($tempdir, $filename);
        if (file_exists($tmpfname)) {	unlink($tmpfname); }
        $handle = fopen($tmpfname, "w");

        fwrite($handle, stripslashes($contents));
        fclose($handle);
        rename($tmpfname,$tempdir."/".$filename);
        return addslashes($tempdir."/".$filename);
    }
}

function get_download_link($filename,$contents,$makecsv=false) {
    global $CFG;
    return 'window.open("'.$CFG->wwwroot . '/scripts/download.php?file='.create_file($filename,$contents,$makecsv).'", "download","menubar=yes,toolbar=yes,scrollbars=1,resizable=1,width=600,height=400");';
}

function return_bytes ($size_str) {
    switch (substr ($size_str, -1)) {
        case 'M': case 'm': case 'mb': return (int)$size_str * 1048576;
        case 'K': case 'k': case 'kb': return (int)$size_str * 1024;
        case 'G': case 'g': case 'gb': return (int)$size_str * 1073741824;
        default: return $size_str;
    }
}

// Main template function
function template_use($file, $params = array(), $subsection = "", $feature = false) {
	global $CFG;
    $v = $params;

    if ($feature) {
      $file = "features/$feature/$file";
    }
		
    if (!file_exists($CFG->dirroot . '/' . $file)) { // template file not found.
      echo $CFG->dirroot . '/' . $file . " not found."; 
      return; 
    }

    $contents = file_get_contents($CFG->dirroot . '/' . $file);

    if (!empty($subsection)) { // Templates with multiple sections.
      if (!$contents = template_subsection($contents, $subsection)) {
				echo "Subsection $subsection not found.";
        return;
      }
    }

		$contents = templates_process_qualifiers($contents, $params); // Look for qualifiers ||x{{ ~~ }}x||

  	$pattern = '/\|\|((?s).*?)\|\|/i'; //Look for stuff between ||
		preg_match_all($pattern, $contents, $matches);

    foreach ($matches[1] as $match) { // Loop through each instance where the template variable bars are found. ie || xxx ||
        $replacement = template_get_functionality($match);
        // send paramaters into a smaller scope so that they don't conflict with other template variables of the same name. Commented out because complex HTML can break it.
        //$replacement = '$func = function() { $v = unserialize(\''.htmlentities(serialize($params)).'\'); ' . $replacement .' }; $func(); unset($func);';

        // Check to make sure that a matched variable exists.
				if (template_variable_exists($match, $params)) {
					ob_start(); // Capture eval() output
			    eval($replacement);
          $contents = str_replace("||$match||", ob_get_clean(), $contents);
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
    return substr($contents, $startsAt, $endsAt - $startsAt);
  }
  return $contents;
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

// Check to make sure that variables in the template are provided.
function template_variable_exists($match, $params) {
  if (strpos($match, "::") === false) {
    $complex_parts = template_get_complex_variables($match);
    $var = template_clean_complex_variables($match);
    if (strpos($match,"[") !== false) { // Complex variable in associative array form
      $v = 'return isset($params["' . $var . '"]' . $complex_parts . ');';
    } elseif (strpos($match,"-") !== false) { // Complex variable in object form
      $v = 'return isset($params' . $complex_parts . ');';
    } else { // Simple variable.
      $v = 'return isset($params["' . $var . '"]);';
    }

    if ($var == "none" || eval($v) !== NULL) {
      return true;
    } else {
      echo "<br /><br />Template variable '$var' not found.<br /><br />";
    }
  } else { //Advanced functionality.
    $x = explode("::", $match); // Split function::variablename
    $vclean =  template_clean_complex_variables($x[1]);
    $vars = explode("|", $vclean);

    foreach ($vars as $param) {
        $cp = template_get_complex_variables($param);
        $vr = template_clean_complex_variables($param);
        if (strpos($param,"[") !== false) { // Complex variable in associative array form
          $v = 'return $params["' . $vr . '"]' . $cp . ';';
        } elseif (strpos($param,"-") !== false) { // Complex variable in object form
          $v = 'return $params' . $cp . ';';
        } else { // Simple variable.
          $v = 'return $params["' . $vr . '"];';
        }

        if ($vr == "none" || !empty(eval($v))) {
          return true;
        } else {
          echo "<br /><br />Template variable '$vr' not found.<br /><br />";
        }
    }
  }
 return false;
}

// Creates the template variable string ie $v["name"] or $v->name
function template_create_v($match, $var, $complex_parts) {
  if (strpos($match,"[") !== false) { // Complex variable in associative array form
    $v = '$v["' . $var . '"]' . $complex_parts;
  } elseif (strpos($match,"-") !== false) { // Complex variable in object form
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
  if (strpos($var,"[") !== false) {
    $bracket = strpos($var,"[");
    $part = substr($var, $bracket, strlen($var) - $bracket);
		$part = str_replace('"', '', $part);
    $part = str_replace('[','["',$part);
    $part = str_replace(']','"]',$part);
  } elseif (strpos($var,"-") !== false) {
    $part = substr($var, strpos($var,"-"), strlen($var) - strpos($var,"-"));
  }
  return $part;
}

// Gets the root variable.  For arrays this will be the key, for objects it will be blank.
function template_clean_complex_variables($var) {
  if (strpos($var,"[") !== false) { $var = substr($var, 0, strpos($var,"[")); } // Remove assoiative array brackets
  if (strpos($var,"-") !== false) { $var = substr($var, 0, strpos($var,"-")); } // Remove everything after object arrows
	if (strpos($var,"{{") !== false) { $var = substr($var, 0, strpos($var,"{{")); } // Remove everything after qualifier code.
  return $var;
}

// Smarter Javascript gathering.
function get_js_tags($params, $linkonly = false) {
  global $CFG, $LOADED;
  $javascript = build_from_js_library($params);

  $filelist = array();
  $dir = empty($CFG->directory) ? '' : $CFG->directory . '/';
  foreach ($javascript as $path => $files) {
    foreach($files as $file){
      array_push($filelist, $dir . $path . "/" . $file);
    }
  }

  if(count($filelist)) {
    $link = $CFG->wwwroot . '/min/?f=' . implode(",", $filelist);
    if($linkonly){
      return $link; // for loadjs() so we don't know if it is actually every loaded.
    } else {
      $LOADED = array_merge_recursive($LOADED, $javascript); // set global to loaded javascript.
      return js_script_wrap($link);
    }
  }
  return;
}

function js_script_wrap($link) {
  return '<script type="text/javascript" src="' . $link . '"></script>';
}

function add_js_to_array($path, $script, &$javascript = array()) {
  if (!js_already_loaded($path, $script)) {
    if (array_key_exists($path, $javascript) === false) { // path doesn't exist yet.
      $javascript[$path] = array();
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
  $javascript = array();
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
  if (array_search("flickity", $params) !== false) { // Image carolsel.
    add_js_to_array("scripts", "flickity.js", $javascript);
  }
  if (array_search("tabs", $params) !== false) { // Tabs.
    add_js_to_array("scripts", "ajaxtabs.js", $javascript);
  }
  if (array_search("popupcal", $params) !== false) { // Tabs.
    add_js_to_array("scripts", "popupcalendar.js", $javascript);
  }
  if (array_search("validate", $params) !== false) { // jQuery validate.
    add_js_to_array("scripts", "jquery.min.js", $javascript);
    add_js_to_array("scripts", "jquery.extend.js", $javascript);
    add_js_to_array("scripts", "jqvalidate.js", $javascript);
    add_js_to_array("scripts", "jqvalidate_addon.js", $javascript);
  }
  if (array_search("picker", $params) !== false) { // Tabs.
    add_js_to_array("scripts/picker", "picker.js", $javascript);
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

function get_js_set($setname) {
  $params = array();
  switch ($setname) {
    case "main":
        $params = array("siteajax", "jquery", "colorbox", "ui", "flickity");
        break;
    case "basics":
        $params = array("siteajax", "jquery");
        break;
  }
  return get_js_tags($params);
}

// Smarter CSS gathering.
function get_css_tags($params) {
  global $CFG;
  $css = build_from_css_library($params);

  $filelist = array();
  $dir = empty($CFG->directory) ? '' : $CFG->directory . '/';
  foreach ($css as $path => $files) {
    foreach($files as $file){
      array_push($filelist, $dir . $path . "/" . $file);
    }
  }

  if(count($filelist)) {
    $link = $CFG->wwwroot . '/min/?f=' . implode(",", $filelist);
    return css_script_wrap($link);
  }
  return;
}

function css_script_wrap($link) {
  return '<link type="text/css" rel="stylesheet" href="' . $link . '" />';
}

function add_css_to_array($path, $script, &$css = array()) {
  if (array_key_exists($path, $css) === false) { // path doesn't exist yet.
    $css[$path] = array();
  }
  array_push($css[$path], $script);
  $css[$path] = array_unique($css[$path]);
  return $css;
}

function build_from_css_library($params) {
  $css = array();
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
  $params = array();
  switch ($setname) {
    case "main":
        $params = array("main", "colorbox", "flickity");
        break;
  }
  return get_css_tags($params);
}
?>

