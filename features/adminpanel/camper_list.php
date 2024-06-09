<?php
/***************************************************************************
* camper_list.php - Page relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.0.1
***************************************************************************/

include ('../header.php');

$head = fill_template("tmp/page.template", "page_js_css", false, ["dirroot" => $CFG->directory]);

echo fill_template("tmp/page.template", "start_of_page_template", false, ["head" => $head]);
$template = '
   <h3>Camper Lists</h3>
    <br />
    This download contains a list of names, ages, and addresses of all campers from previous seasons and for
    campers 13+ years old as of June 1st of the current year (or today if past June 1st).
    <br />
    <table style="width:100%">
        <tr>
            <td style="width: 175px;text-align:right">
                Year
            </td>
            <td>
                <select id="year">
                    <option value="0">All</option>
                    <option value="' . date("Y", strtotime("-2 year")) . '">' . date("Y", strtotime("-2 year")) . '</option>
                    <option value="' . date("Y", strtotime("-1 year")) . '">' . date("Y", strtotime("-1 year")) . '</option>
                    <option value="' . date("Y") . '">' . date("Y") . '</option>
                </select>
            </td>
        </tr>
        <tr>
            <td style="text-align:right">
                Remove Duplicates
            </td>
            <td>
                <select id="remdup">
                    <option value="0">No</option>
                    <option value="true">Yes</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>
            </td>
            <td>
                <br />
                <button id="all_campers_list" class="alike">
                    All Ages
                </button>
                <br />
                <button id="all_over_19_list" class="alike">
                    Age 19+
                </button>
                <br />
                <button id="all_under_19_list" class="alike">
                    Age 19 and under
                </button>
                <br />
                <button id="all_13_19_list" class="alike">
                    Ages 13-19
                </button>
            </td>
        </tr>
    </table>

    <div id="downloadfile" style="display: none"></div>
';

    ajaxapi([
        "id" => "all_campers_list",
        "url" => "/features/adminpanel/camper_list_script.php",
        "data" => ["action" => "all_campers_list", "removeduplicates" => "js||$('#remdup').val()||js", "year" => "js||$('#year').val()||js"],
        "display" => "downloadfile",
    ]);

    ajaxapi([
        "id" => "all_over_19_list",
        "url" => "/features/adminpanel/camper_list_script.php",
        "data" => ["action" => "all_campers_list", "minage" => "19", "removeduplicates" => "js||$('#remdup').val()||js", "year" => "js||$('#year').val()||js"],
        "display" => "downloadfile",
    ]);

    ajaxapi([
        "id" => "all_under_19_list",
        "url" => "/features/adminpanel/camper_list_script.php",
        "data" => ["action" => "all_campers_list", "maxage" => "19.5","removeduplicates" => "js||$('#remdup').val()||js", "year" => "js||$('#year').val()||js"],
        "display" => "downloadfile",
    ]);

    ajaxapi([
        "id" => "all_13_19_list",
        "url" => "/features/adminpanel/camper_list_script.php",
        "data" => ["action" => "all_campers_list", "minage" => "13", "maxage" => "19.5", "removeduplicates" => "js||$('#remdup').val()||js", "year" => "js||$('#year').val()||js"],
        "display" => "downloadfile",
    ]);

    echo $template;
    echo fill_template("tmp/page.template", "end_of_page_template");
?>