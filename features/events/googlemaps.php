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
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml"  xmlns:v="urn:schemas-microsoft-com:vml">
        <head>
            <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
            <title>Directions</title>
            <script>
                let map, infoWindow, geocoder;
                var current = { lat: 0, lng: 0 };

                function initMap() {
                    const directionsService = new google.maps.DirectionsService();
                    const directionsRenderer = new google.maps.DirectionsRenderer();
                    directionsRenderer.setPanel(document.getElementById("sidebar"));
                    map = new google.maps.Map(document.getElementById("map"), {
                        center: current,
                        zoom: 6,
                    });
                    geocoder = new google.maps.Geocoder();

                    directionsRenderer.setMap(map);
                    infoWindow = new google.maps.InfoWindow();

                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                const pos = {
                                    lat: position.coords.latitude,
                                    lng: position.coords.longitude,
                                };

                                current = pos;
                                map.setCenter(pos);
                            },
                            () => {
                                handleLocationError(true, infoWindow, map.getCenter());
                            },
                        );
                    } else {
                        // Browser does not support Geolocation
                        handleLocationError(false, infoWindow, map.getCenter());
                        map.setCenter(current);
                    }

                    calculateAndDisplayRoute(directionsService, directionsRenderer);
                }

                function myaddress() {
                    geocoder.geocode({ address: document.getElementById("myaddress").value, })
                        .then((result) => {
                            const { results } = result;
                            current = results[0].geometry.location;
                        })
                        .catch((e) => {
                            alert("Geocode was not successful for the following reason: " + e);
                        });
                    document.getElementById("sidebar").innerHTML = "";
                    initMap();
                }

                function calculateAndDisplayRoute(directionsService, directionsRenderer) {
                    geocoder.geocode({ address: "' . $address_1 . " " . $address_2 . '", })
                        .then((result) => {
                            const { results } = result;
                            destination = results[0].geometry.location;

                            directionsService.route({
                                origin: current,
                                destination: destination,
                                travelMode: google.maps.TravelMode.DRIVING,
                            }).then((response) => {
                                directionsRenderer.setDirections(response);
                            }).catch((e) => window.alert("Directions request failed due to " + status));
                        })
                        .catch((e) => {
                            alert("Geocode was not successful for the following reason: " + e);
                        });
                }

                function handleLocationError(browserHasGeolocation, infoWindow, pos) {
                    infoWindow.setPosition(pos);
                    infoWindow.setContent(
                        browserHasGeolocation
                        ? "Error: The Geolocation service failed."
                        : "Error: Your browser does not support geolocation.",
                    );
                    infoWindow.open(map);
                }

                window.initMap = initMap;
            </script>
        </head>
        <body>
            <script
            src="https://maps.googleapis.com/maps/api/js?key=' . $CFG->googleapikey . '&callback=initMap&v=weekly"
            defer
            ></script>
            <h2>Directions to Event</h2>
            <table class="directions" style="width: 100%">
                <tr>
                    <td colspan="2">
                        My Location: <input type="text" id="myaddress" value="My Location" />
                        <button onclick="myaddress()">
                            Get Directions
                        </button>
                    </td>
                </tr>
                <tr>
                    <th>Formatted Directions</th>
                    <th>Map</th>
                </tr>
                <tr>
                    <td valign="top" style="width: 50%">
                        <div id="sidebar"></div>
                    </td>
                    <td valign="top" style="width: 50%">
                        <div id="map" style="height: 800px"></div>
                    </td>
                </tr>
            </table>
        </body>
    </html>';
}

?>

