function get_coordinates(ip, display){	
	if((xmlHttp.readyState != 4 && xmlHttp.readyState != 0)){ return;}
	// Build the URL to connect to
  	var url = "//api.hostip.info/get_html.php";
  	var d = new Date();
	var parameters = "ip="+ip+"&position=true" + "&currTime=" + d.toUTCString();
	ajaxget(url,parameters,false,display);
}

function ajaxget(url, parameters, async, display){
    xmlHttp.onreadystatechange=function(){
        if (xmlHttp.readyState==4){
            if (xmlHttp.status==200 || window.location.href.indexOf("http")==-1){ display(); }
            else{ alert("An error has occured making the request"); }
        }
    }
    xmlHttp.open("GET", url+"?"+parameters, true)
    xmlHttp.send(null)
}

function display_map(divname){
    var returned = xmlHttp.responseText.split(':');
    var city = returned[2].split("\n");
    if(city[0].indexOf("Unknown") != -1){
        alert('Location could not be found.');
    }else{
        document.getElementById(divname).innerHTML = '<iframe style="height:100%;width:100%" src="//maps.google.com/maps?f=q&source=s_q&z=16&pw=2&hl=en&geocode=&q='+city[0]+'"></iframe>';   
    }  
}