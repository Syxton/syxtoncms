<!DOCTYPE HTML>
<html style="overflow: hidden;caret-color: transparent;">
    <head>
        <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
        <style>
            svg > g > g:last-child { pointer-events:none; }
        </style>
    </head>
    <body>
    <?php
    if (!isset($CFG) || !defined('LIBHEADER')) {
        $sub = '';
        while (!file_exists($sub . 'lib/header.php')) {
            $sub = $sub == '' ? '../' : $sub . '../';
        }
        include($sub . 'lib/header.php');
    }

    if (!defined('POLLSLIB')) { include_once ($CFG->dirroot . '/features/polls/pollslib.php'); }
    collect_vars();

    $pollid = clean_myvar_req("pollid", "int");
    $area = clean_myvar_opt("area", "string", get_db_field("area", "pages_features", 'feature = "polls" AND featureid = ||pollid||', ["pollid" => $pollid]));

    if ($pollid && $area) {
        $height = $area == "middle" ? "calc(95vh - 40px)" : "auto";
        $titlesize = $area == "middle" ? "25" : "15";
        $poll = get_db_row("SELECT * FROM polls WHERE pollid = ||pollid||", ["pollid" => $pollid]);
        echo '
        <script type="text/javascript">
            google.charts.load("current", {"packages":["corechart"]});
            google.charts.setOnLoadCallback(drawChart);
            function drawChart() {
                var data = google.visualization.arrayToDataTable([["Answer", "Votes", { role: \'style\' }]' . get_poll_data($pollid) . ']);

					var options = {
							chartArea: {left:0, top:0, width:"100%", height:"100%" },
							height: "' . $height . '",
							width: "100%",
							is3D: true,
							legend: "none",
							pieSliceTextStyle: {color: "black", fontSize: ' . $titlesize . ', bold: true},
							pieSliceText: "label",
							title: "' . $poll["question"] . '",
							pieStartAngle: 100,
                };
                var chart = new google.visualization.PieChart(document.getElementById("chart_div"));
                chart.draw(data, options);
            }
        </script>
        <div style="font-size:' . $titlesize . 'px;font-weight:bold;text-align:center;padding: 10px;">' . $poll["question"] . '</div>
        <div id="chart_div" style="display: flex;justify-content: center;width: 100%;height:' . $height . '"></div>';
    } else {
        trigger_error(getlang("no_data", false, [["Userid: $userid", "Year: $year", "Month: $month"]]), E_USER_WARNING);
    }
    ?>
    </body>
</html>
