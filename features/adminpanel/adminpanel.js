function loginas(userid) {
    ajaxapi_old('/features/adminpanel/adminpanel_ajax.php','loginas','&userid='+userid,function(){if (xmlHttp.readyState == 4) { window.parent.go_to_page(xmlHttp.responseText); } }, true);
}