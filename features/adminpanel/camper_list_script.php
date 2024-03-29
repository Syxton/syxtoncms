<?php
/***************************************************************************
* camper_list.php - Page relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 10/12/2022
* Revision: 2.0.1
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); }
update_user_cookie();

callfunction();

function all_campers_list($filename = "camperlist", $year = false, $removeduplicates = false, $minage = 0, $maxage = 100) {
    global $CFG,$MYVARS,$USER;

    $filename = isset($MYVARS->GET["filename"]) ? $MYVARS->GET["filename"] : "camperlist";
    $year = empty($MYVARS->GET["year"]) ? false : $MYVARS->GET["year"];
    $removeduplicates = empty($MYVARS->GET["removeduplicates"]) ? false : true;
    $minage = empty($MYVARS->GET["minage"]) ? 0 : $MYVARS->GET["minage"];
    $maxage = empty($MYVARS->GET["maxage"]) ? 100 : $MYVARS->GET["maxage"];

    $yearsql = $year ? " AND date > '" . mktime(0, 0, 0, 0, 0, $year) . "' AND date < '" . mktime(23, 59, 59, 12, 31, $year) . "'" : "";
    $SQL = "SELECT * 
              FROM events_registrations 
             WHERE eventid IN (SELECT eventid 
                                 FROM events 
                                WHERE template_id = 10 )
          $yearsql
          ORDER BY regid DESC";
    if ($registrations = get_db_result($SQL)) {
        $camperlist[] = array("REGID", "Event", "Name", "Gender", "Birthday", "Current Age", "Address1", "Address2", "City", "State", "Zip", "Email", "Payment Method", "Sponsor");
        while ($reg = fetch_row($registrations)) {
            $event = get_db_row("SELECT * FROM events WHERE eventid='" . $reg["eventid"] . "'");
            $SQL = "SELECT * FROM events_registrations_values WHERE regid='".$reg['regid']."' ORDER BY entryid";
            $temp = $age = $bday = false;
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
                $age = $bday != "Unknown" ? $age : "0";
                if ($bday != "Unknown" && $age > $minage && $age < $maxage) {
                    $camperlist[] = array($reg['regid'],
                                          $event["name"],
                                          ucwords(strtolower(stripslashes($temp["Camper_Name"]))),
                                          ucwords(strtolower($temp["Camper_Gender"])),
                                          $bday,
                                          $age,
                                          ucwords(strtolower(stripslashes($temp["Parent_Address_Line1"]))),
                                          ucwords(strtolower(stripslashes($temp["Parent_Address_Line2"]))),
                                          ucwords(strtolower(stripslashes($temp["Parent_Address_City"]))),
                                          strtoupper($temp["Parent_Address_State"]),
                                          $temp["Parent_Address_Zipcode"],
                                          $reg["email"],
                                          $temp["payment_method"],
                                          $temp["campership"]);
                }
            }    
        }

        if ($removeduplicates) {
            $camperlist = array_distinct($camperlist, array(1,3), "80"); // Removes duplicate Camper_Name's'
        }
        echo '<iframe src="' . $CFG->wwwroot . '/scripts/download.php?file=' . create_file("$filename.csv", $camperlist, true) . '"></iframe>';
    }
}

function array_distinct($array, $fieldnum, $matchpercent) {
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
?>
