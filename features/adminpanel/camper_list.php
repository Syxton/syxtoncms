<?php
/***************************************************************************
* camper_list.php - Page relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.0.1
***************************************************************************/

include ('../header.php');

echo use_template("tmp/page.template", ["dirroot" => $CFG->directory], "page_js_css");
?>

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
                <option value="<?php echo date("Y", strtotime("-2 year")) ?>"><?php echo date("Y", strtotime("-2 year")) ?></option>
                <option value="<?php echo date("Y", strtotime("-1 year")) ?>"><?php echo date("Y", strtotime("-1 year")) ?></option>
                <option value="<?php echo date("Y") ?>"><?php echo date("Y") ?></option>
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
            <a href="javascript: void(0);" onclick="ajaxapi('/features/adminpanel/camper_list_script.php','all_campers_list','&removeduplicates=' + $('#remdup').val() + '&year=' + $('#year').val(),function() { simple_display('downloadfile'); } );">
                All Ages
            </a>
            <br />
            <a href="javascript: void(0);" onclick="ajaxapi('/features/adminpanel/camper_list_script.php','all_campers_list','&removeduplicates=' + $('#remdup').val() + '&year=' + $('#year').val() + '&minage=19',function() { simple_display('downloadfile'); } );">
                Age 19+
            </a>
            <br />
            <a href="javascript: void(0);" onclick="ajaxapi('/features/adminpanel/camper_list_script.php','all_campers_list','&removeduplicates=' + $('#remdup').val() + '&year=' + $('#year').val() + '&maxage=19.5',function() { simple_display('downloadfile'); } );">
                Age 19 and under
            </a>
            <br />
            <a href="javascript: void(0);" onclick="ajaxapi('/features/adminpanel/camper_list_script.php','all_campers_list','&removeduplicates=' + $('#remdup').val() + '&year=' + $('#year').val() + '&minage=13&maxage=19.5',function() { simple_display('downloadfile'); } );">
                Ages 13-19
            </a>
        </td> 
    </tr>
</table>

<div id="downloadfile" style="display: none"></div>

<?php
    echo use_template("tmp/page.template", [], "end_of_page_template");
?>