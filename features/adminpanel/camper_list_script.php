<?php
/***************************************************************************
* camper_list.php - Page relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.0.1
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); }
update_user_cookie();

callfunction();

/**
 * all_campers_list()
 *
 * This function creates a CSV file containing the name, gender, birthday, current age, address, email, payment method and sponsor for all campers that have registered for an event based on the filter parameters.
 *
 * @param string $filename         The filename to use when saving the CSV
 * @param int    $year             The year to filter the results by
 * @param bool   $removeduplicates If true, removes duplicate camper names from the list
 * @param int    $minage           The minimum age to include in the list
 * @param int    $maxage           The maximum age to include in the list
 *
 * @return string The HTML code to download the generated CSV file
 */
function all_campers_list($filename = "camperlist", $year = false, $removeduplicates = false, $minage = 0, $maxage = 100) {
    global $CFG, $MYVARS, $USER;
    $return = $error = "";
    try {
        // Clean the input parameters
        $filename = clean_myvar_opt("filename", "string", "camperlist");
        $year = clean_myvar_opt("year", "int", false);
        $year = $year === 0 ? false : $year;

        $removeduplicates = clean_myvar_opt("removeduplicates", "bool", false);
        $minage = clean_myvar_opt("minage", "int", 0);
        $maxage = clean_myvar_opt("maxage", "int", 100);

        // Get the events that use the same template as the currently selected event
        $params = [
            "templateid" => 10,
            "fromdate" => $year ? mktime(0, 0, 0, 0, 0, $year) : "",
            "todate" => $year ? mktime(23, 59, 59, 12, 31, $year) : "",
        ];
        $SQL = fetch_template("dbsql/events.sql", "get_events_having_same_template", "events", ["year" => $year]);

        if ($registrations = get_db_result($SQL, $params)) {
            // Initialize the array to store the camper list
            $camperlist[] = ["REGID", "Event", "Name", "Gender", "Birthday", "Current Age", "Address1", "Address2", "City", "State", "Zip", "Email", "Payment Method", "Sponsor",];

            // Loop through the registrations and fetch the camper list
            while ($reg = fetch_row($registrations)) {
                $event = get_db_row(fetch_template("dbsql/events.sql", "get_event", "events"), ["eventid" => $reg["eventid"]]);

                // Get the registration values for the current registration
                $SQL = fetch_template("dbsql/events.sql", "get_registration_values", "events");
                $temp = $age = $bday = false;
                if ($entries = get_db_result($SQL, ["regid" => $reg["regid"]])) {
                    // Initialize the temporary array to store the registration values
                    unset($temp); unset($bday); unset($age);
                    while ($entry = fetch_row($entries)) {
                        $temp[$entry["elementname"]] = $entry["value"];
                    }

                    // Determine the camper's birthday
                    if (strstr($temp["Camper_Birth_Date"], '-')) {
                        $bday = date("m/d/Y",strtotime(str_replace("-", "/", $temp["Camper_Birth_Date"])));
                    } elseif (!strstr($temp["Camper_Birth_Date"], '/') && !strstr($temp["Camper_Birth_Date"], '-')) {
                        if (strlen($temp["Camper_Birth_Date"])==6) {
                            $century = $temp["Camper_Birth_Date"][4] > 1 ? "19" : "20";
                            $bday = date("m/d/Y", strtotime($temp["Camper_Birth_Date"][0].$temp["Camper_Birth_Date"][1] . '/' . $temp["Camper_Birth_Date"][2].$temp["Camper_Birth_Date"][3] . '/' . $century.$temp["Camper_Birth_Date"][4].$temp["Camper_Birth_Date"][5]));
                        } elseif (strlen($temp["Camper_Birth_Date"])==8) {
                            $bday = date("m/d/Y", strtotime($temp["Camper_Birth_Date"][0].$temp["Camper_Birth_Date"][1] . '/' . $temp["Camper_Birth_Date"][2].$temp["Camper_Birth_Date"][3] . '/' . $temp["Camper_Birth_Date"][4].$temp["Camper_Birth_Date"][5].$temp["Camper_Birth_Date"][6].$temp["Camper_Birth_Date"][7]));
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
                    } else { $bday = date("m/d/Y", strtotime($temp["Camper_Birth_Date"])); };

                    // Clean the camper's gender
                    $temp["Camper_Gender"] = $temp["Camper_Gender"] == "F" ? "Female" : $temp["Camper_Gender"];
                    $temp["Camper_Gender"] = $temp["Camper_Gender"] == "M" ? "Male" : $temp["Camper_Gender"];

                    // Calculate the camper's age
                    $today = time();
                    $june = strtotime("June 1"); // Gets closest June 1st
                    $cutoff = $june > $today ? $june : $today;

                    $age = round(($cutoff - strtotime($bday)) / (60*60*24*365));
                    $age = $bday != "Unknown" ? $age : "0";
                    if ($bday != "Unknown" && $age > $minage && $age < $maxage) {
                        // Add the camper to the list
                        $camperlist[] = [
                            $reg['regid'],
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
                            $temp["campership"],
                        ];
                    }
                }
            }

            // If requested, remove duplicate camper names from the list
            if ($removeduplicates) {
                // Removes duplicate based on name, birthdate at 80% tolerance..
                $camperlist = array_distinct($camperlist, [2, 4], "80");
            }

            // Create the CSV file and return the HTML to download it
            $return = '<iframe src="' . $CFG->wwwroot . '/scripts/download.php?file=' . create_file("$filename.csv", $camperlist, true) . '"></iframe>';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

/**
 * Returns an array without duplicate entries based on the given fields
 *
 * @param array $array        Array to search for duplicates
 * @param array $fieldnum     Field(s) to check for duplicates
 * @param int   $matchpercent Percentage of similarity to consider a duplicate
 *
 * @return array Array without duplicates
 */
function array_distinct($array, $fieldnum, $matchpercent) {
    /* Make sure fieldnum is an array */
    if (!is_array($fieldnum)) {
        $fieldnum = [$fieldnum];
    }

    /* Initialize output array */
    $output = [];

    /* Iterate through input array */
    foreach ($array as $key => $sub_array) {

        /* Check if sub_array is already in output */
        $add = true;
        foreach ($output as $sub_array2) {
            /* Initialize variables to be compared */
            $alreadyadded = $notadded = "";

            /* Concat values of fields to be checked */
            foreach ($fieldnum as $field) {
                $alreadyadded .= $sub_array2[$field];
                $notadded     .= $sub_array[$field];
            }

            /* Calculate similarity */
            similar_text($alreadyadded, $notadded, $percent);

            /* Check if similarity is above threshold */
            if ($percent >= $matchpercent) {
                $add = false;
            }
        }

        /* If sub_array is not already in output, add it */
        if ($add) {
            $output[$key] = $sub_array;
        }
    }

    /* Return output */
    return $output;
}
?>
