<?php
/***************************************************************************
 * googlemaps.php - Google Maps page
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 2/18/08
 * $Revision: .12
 ***************************************************************************/
 
 if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

callfunction("google_maps");

function google_maps() {
global $CFG, $MYVARS;
    $address_1 = clean_myvar_opt("from", "string", clean_myvar_opt("address_1", "string", ""));
    $address_2 = clean_myvar_opt("to", "string", clean_myvar_opt("address_2", "string", ""));
    echo '
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml"  xmlns:v="urn:schemas-microsoft-com:vml">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
        <title>Directions</title>
        <script src="//maps.googleapis.com/maps/api/js?v=3&key=' . $CFG->googleapikey . '" type="text/javascript"></script>
        <style type="text/css">
        body {
            font-family: Verdana, Arial, sans serif;
            font-size: 11px;
            margin: 2px;
        }
        table.directions th {
        background-color:#EEEEEE;
        }
            
        img {
            color: #000000;
        }
        </style>
        <script type="text/javascript">
        function initialize() {
            var directionsService = new google.maps.DirectionsService();
            var directionsRenderer = new google.maps.DirectionsRenderer();
            var chicago = new google.maps.LatLng(41.850033, -87.6500523);
            var mapOptions = {
              zoom:7,
              center: chicago
            }
            var map = new google.maps.Map(document.getElementById(\'map_canvas\'), mapOptions);
            directionsRenderer.setMap(map);
            directionsRenderer.setPanel(document.getElementById(\'directions\'));
          }
          
          function calcRoute() {
            var start = document.getElementById(\'toAddress\').value;
            var end = document.getElementById(\'fromAddress\').value;
            var request = {
              origin:start,
              destination:end,
              travelMode: \'DRIVING\'
            };
            directionsService.route(request, function(response, status) {
              if (status == \'OK\') {
                directionsRenderer.setDirections(response);
              }
            });
          }
        </script>
    </head>
    <body onload="initialize()" onunload="GUnload()">
    
    <h2>Directions to Location</h2>
    <form action="#" onsubmit="initialize(); return false">

    <table>
    <tr><th align="right">From:&nbsp;</th>

    <td><input type="text" size="25" id="fromAddress" name="from"
        value="' . $CFG->defaultaddress . '"/></td>
    <th align="right">&nbsp;&nbsp;To:&nbsp;</th>
    <td align="right"><input type="text" size="25" id="toAddress" name="to"
        value="' . $address_1 . ' ' . $address_2 . '" /></td></tr>

    <tr><th></th>
    <td colspan="3"><select id="locale" name="locale" style="display:none;">

        <option value="en" selected>English</option>

        <option value="fr">French</option>

        <option value="de">German</option>
        <option value="ja">Japanese</option>
        <option value="es">Spanish</option>
        </select>

        <input name="submit" type="submit" value="Get Directions!" />&nbsp;<input name="print" type="button" value="Print Directions" onclick="window.print();" />

    </td></tr>
    </table>

        
    </form>

        <br/>
        <table class="directions">
        <tr><th>Formatted Directions</th><th>Map</th></tr>

        <tr>
        <td valign="top"><div id="directions" style="width: 275px"></div></td>
        <td valign="top"><div id="map_canvas" style="width: 310px; height: 400px"></div></td>

        </tr>
        </table> 
    </body>
    </html>

    ';
}

?>

