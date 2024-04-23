function get_coordinates(ip, divname) {
    var url = "//extreme-ip-lookup.com/json/" + ip;
    $.ajax({
      jsonpCallback: 'jsonCallback',
      contentType: "application/json",
      dataType: 'jsonp',
      url: url,
      cache: false,
      done: function( json ) {
        if (!json.latitude) {
            alert('Location could not be found.');
        } else {
            ajaxapi('/features/adminpanel/adminpanel_ajax.php','ipmap','&json='+JSON.stringify(json),function(){if (xmlHttp.readyState == 4) { document.getElementById(divname).innerHTML = xmlHttp.responseText; } }, true);
        }
      }
    });
}

function loginas(userid) {
    ajaxapi('/features/adminpanel/adminpanel_ajax.php','loginas','&userid='+userid,function(){if (xmlHttp.readyState == 4) { window.parent.go_to_page(xmlHttp.responseText); } }, true);
}