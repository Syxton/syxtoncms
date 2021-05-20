<?php
/***************************************************************************
* filelib.php - File Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/06/2021
* Revision: 0.0.6
***************************************************************************/

if (!isset($LIBHEADER)) { include('header.php'); }
$FILELIB = true;

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

function recursive_mkdir( $folder ) {
    $folder = preg_split( "/[\\\\\/]/" , $folder );
    $mkfolder = '';
    for (  $i=0 ; isset( $folder[$i] ) ; $i++ ) {
        if (!strlen(trim($folder[$i])))continue;
        $mkfolder .= $folder[$i];
        if ( !is_dir( $mkfolder ) ) {
          mkdir( "$mkfolder" ,  0777);
          chmod("$mkfolder", 0777);
        }
        $mkfolder .= DIRECTORY_SEPARATOR;
    }
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
    }else{
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
    }else{
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
function template_use($file, $params = array(), $subsection = "") {
	global $CFG;
    $v = $params;

    if (!file_exists($CFG->dirroot . '/' . $file)) { echo $CFG->dirroot . '/' . $file . " not found."; return; } // template file not found.

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
        if (!$count) { // If there isn't a perfect match in the template code, it is either a mistake or an unneccesary variable given.
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

    if (eval($v) !== NULL) {
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

        if (!empty(eval($v))) {
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
?>
