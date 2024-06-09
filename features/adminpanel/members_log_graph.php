<!DOCTYPE HTML>
<html style="overflow: hidden;caret-color: transparent;">
    <head>
        <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    </head>
    <body>
    <?php
        if (!isset($CFG)) { include('../header.php'); }

        collect_vars();

        $userid = $MYVARS->GET["userid"];
        $year = $MYVARS->GET["year"];
        $month = $MYVARS->GET["month"];

        if ($userid && $year && $month) {
            echo '
            <script type="text/javascript">
                google.charts.load("current", {"packages":["corechart"]});
                google.charts.setOnLoadCallback(drawChart);
                function drawChart() {
                    var data = google.visualization.arrayToDataTable([["Date", "Hits"]' . get_user_data($userid, $year, $month) . ']);

                    var options = {
                        chartArea: { top: 55, width: "90%", height: "70%" },
                        title: "' . get_user_name($userid) . "'s " . date("F Y", mktime(0, 0, 0, $month, 1, $year)) . ' Log",
                        legend: { position: "in" },
                        hAxis: { title: "Date",
                                 titleTextStyle: { color: "#333" },
                                 textStyle: { fontSize: 10 },
                                 slantedText: true,
                                 slantedTextAngle: 90,
                        },
                        vAxis: { minValue: 0, },
                    };

                    var chart = new google.visualization.ColumnChart(document.getElementById("chart_div"));
                    chart.draw(data, options);
                }
            </script>
            <div id="chart_div" style="width: 100%; height: 400px;"></div>';
        } else {
            trigger_error(error_string("no_data", [["Userid: $userid", "Year: $year", "Month: $month"]]), E_USER_WARNING);
        }
    ?>
    </body>
</html>
<?php

/**
 * Gets the log data for a given user, month and year
 * @param int $userid - The user id
 * @param int $year - The year
 * @param int $month - The month
 * @return string - The data string
 */
function get_user_data($userid, $year, $month) {
    global $CFG, $MYVARS, $USER;

    $datastring = ''; // The data to return

    $SQL = "SELECT COUNT(*) as hits,
                   YEAR(FROM_UNIXTIME(timeline)) as myyear,
                   MONTH(FROM_UNIXTIME(timeline)) as mymonth,
                   DAYOFMONTH(FROM_UNIXTIME(timeline)) as myday
              FROM logfile
             WHERE userid = '$userid'
               AND YEAR(FROM_UNIXTIME(timeline)) = $year
               AND MONTH(FROM_UNIXTIME(timeline)) = $month
          GROUP BY myyear, mymonth, myday
          ORDER BY myday";

    $date = mktime(0, 0, 0, $month, 1, $year); // Gets the first of the given month
    $day = 1; // first day of given month
    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            while ($day < $row["myday"]) {
                $datastring .= ',["' . date('M jS', mktime(0, 0, 0, $month, $day, $year)) . '", 0]'; // Create a string of data with 0 hits
                $day++;
            }
            $datastring .= ',["' . date('M jS', mktime(0, 0, 0, $month, $day, $year)) . '", ' . $row["hits"] . ']'; // Create a string of data with hits
            $day++;
        }
        $day--; // Undo the last $day++ at the end of the loop
        while ($day <= date('t', $date)) {
            $datastring .= ',["' . date('M jS', mktime(0, 0, 0, $month, $day, $year)) . '", 0]'; // Create a string of data with 0 hits
            $day++;
        }
    } else { // No data found so fill with zeros.
        while ($day <= date('t', $date)) {
            $datastring .= ',["' . date('M jS', mktime(0, 0, 0, $month, $day, $year)) . '", 0]'; // Create a string of data with 0 hits
            $day++;
        }
    }

    return $datastring;
}
?>
