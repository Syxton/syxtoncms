function get_coordinates(ip, divname) {
    var url = "//freegeoip.net/json/" + ip;
    $.ajax({
      jsonpCallback: 'jsonCallback',
      contentType: "application/json",
      dataType: 'jsonp',
      url: url,
      cache: false,
      success: function( json ) {
        if (!json.latitute) {
            alert('Location could not be found.');
        } else {
            document.getElementById(divname).innerHTML = '<iframe style="height:100%;width:100%" src="//maps.google.com/maps?q='+json.latitute+','+json.longitude+'"></iframe>';   
        }
      }
    });
}

function loginas(userid) {
    ajaxapi('/features/adminpanel/adminpanel_ajax.php','loginas','&userid='+userid,function(){if (xmlHttp.readyState == 4) { window.parent.go_to_page(xmlHttp.responseText); } }, true);
}