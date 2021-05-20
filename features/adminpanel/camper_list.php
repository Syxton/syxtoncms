<?php
/**
 * @author Matthew Davidson
 * @copyright 2012
 * Get camper labels
 */
if (!isset($CFG)) { include('../header.php'); } 

echo "<h3>Current Camper listing</h3>This download contains a list of names,ages,and addresses of all campers from previous seasons and for campers 13+ years old as of June 1st of the current year (or today if past June 1st).<br />";
$SQL = "SELECT * FROM events_registrations WHERE eventid IN (SELECT eventid FROM events WHERE template_id = 10 ) ORDER BY regid DESC"; //ONLY CAMP WEEK TEMPLATED EVENTS
if ($registrations = get_db_result($SQL)) {
    $allcamperlist[] = array("REGID","Name","Gender","Birthday","Current Age","Address1","Address2","City","State","Zip");
    $retreatcamperlist[] = array("REGID","Name","Gender","Birthday","Current Age","Address1","Address2","City","State","Zip");
    while ($reg = fetch_row($registrations)) {
        $SQL = "SELECT * FROM events_registrations_values WHERE regid='".$reg['regid']."' ORDER BY entryid";
        if ($entries = get_db_result($SQL)) {
            unset($temp); unset($bday); unset($age);
            while ($entry = fetch_row($entries)) {
                $temp[$entry["elementname"]] = $entry["value"];
            }
            if (strstr($temp["Camper_Birth_Date"],'-')) {
                $bday = date("m/d/Y",strtotime(str_replace("-","/",$temp["Camper_Birth_Date"])));
            }elseif (!strstr($temp["Camper_Birth_Date"],'/') && !strstr($temp["Camper_Birth_Date"],'-')) {
                if (strlen($temp["Camper_Birth_Date"])==6) {
                    $century = $temp["Camper_Birth_Date"][4] > 1 ? "19" : "20";
                    $bday = date("m/d/Y",strtotime($temp["Camper_Birth_Date"][0].$temp["Camper_Birth_Date"][1].'/'.$temp["Camper_Birth_Date"][2].$temp["Camper_Birth_Date"][3].'/'.$century.$temp["Camper_Birth_Date"][4].$temp["Camper_Birth_Date"][5]));
                }elseif (strlen($temp["Camper_Birth_Date"])==8) {
                    $bday = date("m/d/Y",strtotime($temp["Camper_Birth_Date"][0].$temp["Camper_Birth_Date"][1].'/'.$temp["Camper_Birth_Date"][2].$temp["Camper_Birth_Date"][3].'/'.$temp["Camper_Birth_Date"][4].$temp["Camper_Birth_Date"][5].$temp["Camper_Birth_Date"][6].$temp["Camper_Birth_Date"][7]));
                } else { //Most likely empty so try the age field
                    if (!empty($temp["Camper_Age"])) {
                        $regdate = $reg["date"];//datetime when they registered
                        $regage = $temp["Camper_Age"];//age when they registered
                        $age = round((time() - $regdate) / (60*60*24*365)) + $regage;                      
                    } else {
                        $age = "Unknown";
                    }
                    $bday = "Unknown";
                }
            } else { $bday = date("m/d/Y",strtotime($temp["Camper_Birth_Date"])); };
            
            $temp["Camper_Gender"] = $temp["Camper_Gender"] == "F" ? "Female" : $temp["Camper_Gender"];
            $temp["Camper_Gender"] = $temp["Camper_Gender"] == "M" ? "Male" : $temp["Camper_Gender"];
            
            // Assume June 1st unless past June 1st, then use current date.
            $today = time();
            $june = strtotime("June 1"); // Gets closest June 1st
            $cutoff = $june > $today ? $june : $today;

            $age = round(($cutoff - strtotime($bday)) / (60*60*24*365));
            if ($age != "Unknown" && $age > 7 && $age < 19.5) {
                $allcamperlist[] = array($reg['regid'],ucwords(strtolower(stripslashes($temp["Camper_Name"]))),ucwords(strtolower($temp["Camper_Gender"])),$bday,$age,ucwords(strtolower(stripslashes($temp["Parent_Address_Line1"]))),ucwords(strtolower(stripslashes($temp["Parent_Address_Line2"]))),ucwords(strtolower(stripslashes($temp["Parent_Address_City"]))),strtoupper($temp["Parent_Address_State"]),$temp["Parent_Address_Zipcode"]);
                if ($age >= 12.5) {
                    $retreatcamperlist[] = array($reg['regid'],ucwords(strtolower(stripslashes($temp["Camper_Name"]))),ucwords(strtolower($temp["Camper_Gender"])),$bday,$age,ucwords(strtolower(stripslashes($temp["Parent_Address_Line1"]))),ucwords(strtolower(stripslashes($temp["Parent_Address_Line2"]))),ucwords(strtolower(stripslashes($temp["Parent_Address_City"]))),strtoupper($temp["Parent_Address_State"]),$temp["Parent_Address_Zipcode"]);
                }   
            }
        }    
    }
  
    $allcamperlist = array_distinct_new($allcamperlist,array(1,3),"80"); // Removes duplicate Camper_Name's'
    echo "<br /><a href='javascript:".get_download_link("camperlist.csv",$allcamperlist,true)."'>Download All Campers List</a>";
    
    $retreatcamperlist = array_distinct_new($retreatcamperlist,array(1,3),"80"); // Removes duplicate Camper_Name's'
    echo "<br /><a href='javascript:".get_download_link("youthretreatlist.csv",$retreatcamperlist,true)."'>Download Retreat 13+ List</a>";
}

$year = date("Y",strtotime("-1 year"));
$SQL = "SELECT * FROM events_registrations WHERE eventid IN (SELECT eventid FROM events WHERE template_id = 10 ) AND date > '".mktime(0,0,0,0,0,$year)."' AND date < '".mktime(23,59,59,12,31,$year)."' ORDER BY regid DESC"; //ONLY LAST YEAR CAMP WEEK TEMPLATED EVENTS
if ($registrations = get_db_result($SQL)) {
    $lastyearlist[] = array("REGID","Name","Gender","Birthday","Current Age","Address1","Address2","City","State","Zip");
    while ($reg = fetch_row($registrations)) {
        $SQL = "SELECT * FROM events_registrations_values WHERE regid='".$reg['regid']."' ORDER BY entryid";
        if ($entries = get_db_result($SQL)) {
            unset($temp); unset($bday); unset($age);
            while ($entry = fetch_row($entries)) {
                $temp[$entry["elementname"]] = $entry["value"];
            }
            if (strstr($temp["Camper_Birth_Date"],'-')) {
                $bday = date("m/d/Y",strtotime(str_replace("-","/",$temp["Camper_Birth_Date"])));
            } elseif (!strstr($temp["Camper_Birth_Date"],'/') && !strstr($temp["Camper_Birth_Date"],'-')) {
                if (strlen($temp["Camper_Birth_Date"])==6) {
                    $century = $temp["Camper_Birth_Date"][4] > 1 ? "19" : "20";
                    $bday = date("m/d/Y",strtotime($temp["Camper_Birth_Date"][0].$temp["Camper_Birth_Date"][1].'/'.$temp["Camper_Birth_Date"][2].$temp["Camper_Birth_Date"][3].'/'.$century.$temp["Camper_Birth_Date"][4].$temp["Camper_Birth_Date"][5]));
                } elseif (strlen($temp["Camper_Birth_Date"])==8) {
                    $bday = date("m/d/Y",strtotime($temp["Camper_Birth_Date"][0].$temp["Camper_Birth_Date"][1].'/'.$temp["Camper_Birth_Date"][2].$temp["Camper_Birth_Date"][3].'/'.$temp["Camper_Birth_Date"][4].$temp["Camper_Birth_Date"][5].$temp["Camper_Birth_Date"][6].$temp["Camper_Birth_Date"][7]));
                } else { //Most likely empty so try the age field
                    if (!empty($temp["Camper_Age"])) {
                        $regdate = $reg["date"];//datetime when they registered
                        $regage = $temp["Camper_Age"];//age when they registered
                        $age = round((time() - $regdate) / (60*60*24*365)) + $regage;                      
                    } else {
                        $age = "Unknown";
                    }
                    $bday = "Unknown";
                }
            } else { $bday = date("m/d/Y",strtotime($temp["Camper_Birth_Date"])); };
            
            $temp["Camper_Gender"] = $temp["Camper_Gender"] == "F" ? "Female" : $temp["Camper_Gender"];
            $temp["Camper_Gender"] = $temp["Camper_Gender"] == "M" ? "Male" : $temp["Camper_Gender"];

            $today = time();
            if ($age != "Unknown") {
                $age = round(($today - strtotime($bday)) / (60*60*24*365));
            }
            $lastyearlist[] = array($reg['regid'],ucwords(strtolower(stripslashes($temp["Camper_Name"]))),ucwords(strtolower($temp["Camper_Gender"])),$bday,$age,ucwords(strtolower(stripslashes($temp["Parent_Address_Line1"]))),ucwords(strtolower(stripslashes($temp["Parent_Address_Line2"]))),ucwords(strtolower(stripslashes($temp["Parent_Address_City"]))),strtoupper($temp["Parent_Address_State"]),$temp["Parent_Address_Zipcode"]);
        }    
    }

    $lastyearlist = array_distinct_new($lastyearlist,array(1,3),"80"); // Removes duplicate Camper_Name's'
    echo "<br /><a href='javascript:".get_download_link("lastyearlist.csv",$lastyearlist,true)."'>Download " .$year. " List</a>";
}

function array_distinct_new($array,$fieldnum,$matchpercent) {
    if (!is_array ($fieldnum)) { $fieldnum = array ($fieldnum);  }
    $output = array ();
    foreach ($array as $key => $sub_array) {
        $add = true;
        foreach ($output as $sub_array2) {
            $alreadyadded = $notadded = "";
            foreach ($fieldnum as $field) {
                $alreadyadded .= $sub_array2[$field];
                $notadded .= $sub_array[$field];
            }
            
            similar_text($alreadyadded,$notadded,$percent);

            if ($percent >= $matchpercent) {
                $add = false;
                //echo "<br />MATCH: ".$percent . "<br />";
                //print_r($sub_array);
            }
        }
        
        if ($add) {
            $output[$key] = $sub_array;    
        }   
    }
    return $output;
}

/*
$array - nothing to say
$group_keys - columns which have to be grouped - can be STRING or ARRAY (STRING, STRING[, ...])
$sum_keys - columns which have to be summed - can be STRING or ARRAY (STRING, STRING[, ...])
$count_key - must be STRING - count the grouped keys
*/
function array_distinct ($array, $group_keys, $sum_keys = NULL, $count_key = NULL) {
  if (!is_array ($group_keys)) $group_keys = array ($group_keys);
  if (!is_array ($sum_keys)) $sum_keys = array ($sum_keys);

  $existing_sub_keys = array ();
  $output = array ();

  foreach ($array as $key => $sub_array) {
    $puffer = NULL;
    #group keys
    foreach ($group_keys as $group_key) {
      $puffer .= $sub_array[$group_key];
    }
    $puffer = serialize ($puffer);
    if (!in_array ($puffer, $existing_sub_keys)) {
      $existing_sub_keys[$key] = $puffer;
      $output[$key] = $sub_array;
    } else {
      $puffer = array_search ($puffer, $existing_sub_keys);
      #sum keys
      foreach ($sum_keys as $sum_key) {
        if (is_string ($sum_key)) $output[$puffer][$sum_key] += $sub_array[$sum_key];
      }
      #count grouped keys
      //if (!array_key_exists ($count_key, $output[$puffer])) $output[$puffer][$count_key] = 1;
      if (is_string ($count_key)) $output[$puffer][$count_key]++;
    }
  }
  return $output;
}
?>