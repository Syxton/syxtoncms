//Set root directory
var WWW_ROOT = location.protocol + '//' + location.host;

//AJAX API
/* Create a new XMLHttpRequest object to talk to the Web server */
function createXMLHttpRequest() {
    xmlHttp = null;
    if (typeof XMLHttpRequest != "undefined") {
        xmlHttp = new XMLHttpRequest();
    } else if (typeof window.ActiveXObject != "undefined") {
        try{
            xmlHttp = new ActiveXObject("Msxml2.XMLHTTP.4.0");
        } catch(e) {
            try{
                xmlHttp = new ActiveXObject("MSXML2.XMLHTTP");
            } catch(e) {
                try{
                    xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
                } catch(e) {
                    xmlHttp = null;
                }
            }
        }
    }
    return xmlHttp;
}

function ajaxapi(script, action, param, display, async) {
    if (!ajaxready(script, action, param, display, async)) {
        return false;
    };

    //Build the URL to connect to
	var myurl = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + script;
	var d = new Date();
	var parameters = "action=" + action + param.entityify() + "&currTime=" + d.toUTCString();

	if (async != true) {
		ajaxpost(myurl, parameters, false, false);
  		display();
    } else {
        ajaxpost(myurl, parameters, true, display);
	}
	setTimeout(function() { enableajaxjs(); }, 500);
}

function ajaxpost(url, parameters, async, display) {
    if (async == true) {
        xmlHttp.open('POST', url, true);
        xmlHttp.onreadystatechange = display;
        xmlHttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xmlHttp.send(parameters);
    } else {
		xmlHttp.open('POST', url, false);
		xmlHttp.onreadystatechange = function () {};
		xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		xmlHttp.send(parameters);
	}
}

function ajaxready(script, action, param, display, async) {
    if (xmlHttp.readyState != 0) {
        if (xmlHttp.readyState == 4) {
            xmlHttp = createXMLHttpRequest();
            return true;
        }
		setTimeout(function() { ajaxapi(script, action, param, display, async); }, 500); // if there is an ajax conflict.  Wait 1 second before trying again.
        return false;
    }
    return true;
}

String.prototype.entityify = function () {
    return this.replace(/&amp;/g,"&");
};

String.prototype.singleline = function () {
    return this.replace(/(\r\n|\n|\r)/gm, "").replace(/\s\s+/g, ' ');
};

function save_action(objecta, objectb) {
    if ($(objecta).attr("onclick") != undefined) {
        $(objectb).val($(objecta).attr("onclick").singleline());
    }
}

//Display or Return Functions
function simple_display(divname) {
    if (document.getElementById(divname)) {
        document.getElementById(divname).innerHTML = xmlHttp.responseText;
        setTimeout(function () { resize_modal(divname); }, 100);
    }
}

function jq_display(target, data) {
	if ($("#" + target).length > 0) {
        $("#" + target).html(data.message);
        setTimeout(function () { resize_modal(target); }, 100);
    }
}

function jq_eval(data) {
    eval(data.message);
}

function clear_display(divname) { if (document.getElementById(divname)) { document.getElementById(divname).innerHTML = ''; } }
function display_backup(divname,backupdiv) {	document.getElementById(divname).innerHTML = document.getElementById(backupdiv).innerHTML + xmlHttp.responseText; }
function run_this() { eval(xmlHttp.responseText); }
function istrue() { if (trim(xmlHttp.responseText) == "false") { return false; } else { return true;}}
function do_nothing() {}
function option_display(pageid,resultsdiv) {
	var returned = trim(xmlHttp.responseText).split("**");
	if (returned[0] == "true") {
		go_to_page(pageid);
    } else { document.getElementById(resultsdiv).innerHTML = returned[1]; }
}

var xmlHttp = createXMLHttpRequest();
var myInterval; var temptimer; var tempinterval;

if (document.layers) { document.captureEvents(Event.MOUSEOVER | Event.MOUSEOUT)}

function countdown(section,timer,dothis) { $('#'+section).html(timer); clearInterval(tempinterval); tempinterval = setInterval(function() {timer--; $('#'+section).html(timer); if (timer == 0 ) { dothis(); clearInterval(tempinterval); return false; }},1000); }
function hidestatus() { window.status=''; return true; } document.onmouseover=hidestatus; document.onmouseout=hidestatus;

//Small Common-use functions
function go_to_page(pageid) { if (pageid == 1) { location.href = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot); } else {location.href = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/index.php?pageid=" + pageid; }}
function hide_section(section_name) { document.getElementById(section_name).style.display = 'none'; }
function show_section(section_name,block) { var display = block == true ? "block" : "inline"; document.getElementById(section_name).style.display = display; }
function hide_show_buttons(section_name, block) {
	var display = block == true ? "block" : "inline";
	if (document.getElementById(section_name).style.display == 'none' || document.getElementById(section_name).style.display.length == 0) {
		document.getElementById(section_name).style.display = display;
	} else {
		document.getElementById(section_name).style.display = "none";
	}
}
function trim(stringToTrim) { return stringToTrim.replace(/^\s+|\s+$/g,""); }
function ltrim(stringToTrim) { return stringToTrim.replace(/^\s+/,""); }
function rtrim(stringToTrim) { return stringToTrim.replace(/\s+$/,""); }

//Modal Functions
function close_modal() {
    if (typeof self.parent.$ != "undefined") {
        self.parent.$.colorbox.close();
    } else if (typeof $ != "undefined") {
        $.colorbox.close();
    }
}

function resize_modal(container) {
    if (typeof self.parent.$.colorbox != "undefined") {
        self.parent.$("#colorbox").resize();
    } else if (typeof $.colorbox != "undefined") {
        $("#colorbox").resize();
    }
}


function refresh_page() { window.location.reload( true ); }

function stripslashes(str) {
    str=str.replace(/\\'/g,'\'');
    str=str.replace(/\\"/g,'"');
    str=str.replace(/\\0/g,'\0');
    str=str.replace(/\\\\/g,'\\');
    return str;
}

//Debug
function print_r(theObj) {
	var printme;
	if (theObj.constructor == Array || theObj.constructor == Object) {
		for(var p in theObj) {
			if (theObj[p] && (theObj[p].constructor == Array || theObj[p].constructor == Object)) {
				printme += "["+p+"] => "+typeof(theObj);
				print_r(theObj[p]);
			} else {
				printme += "["+p+"] => "+theObj[p];
			}
		}
	}
	alert(printme);
}

function loadjs(scriptName, callback) {
    if (typeof callback == "undefined") {
        callback = function() {};
    }
    $.getScript(scriptName, callback).fail(function(jqxhr, settings, exception) { alert( "Some javascript files failed to load ("+scriptName+"), the page may not work as intended." );});
    return;
}

function loaddynamicjs(scriptname) {
    var js = $('#'+scriptname).html();
    setTimeout(function() { return false; }, 2000);
	var head = document.getElementsByTagName("head")[0];
    script = document.createElement('script');
    script.id = "dynamicscript";
    script.type = 'text/javascript';
    script.text = js;
    head.appendChild(script);
    setTimeout(function() { return false; }, 100);
}

function loadajaxjs(data) {
	$.each(data.loadjs, function (key, value) {
		$("#script_" + key).remove();
		$("body").append($('<script class="ajaxapi active" id="script_' + key + '">' + value.singleline().trim() + '</script>'));
	});
}

function enableajaxjs() {
	$.each($('.ajaxapi.inactive'), function () {
		let key = $(this).attr('id');
		let value = $(this).html();
		$(this).remove(); // Remove the inactive script
		$("#script_" + key).remove(); // Remove any previously loaded script with the same id.
		$("body").append($('<script class="ajaxapi active" id="script_' + key + '">' + value.singleline().trim() + '</script>'));
	});
}

$(function () { // At the end of the document, check for inactive ajax javascript and attempt to activate it.
	enableajaxjs();
});

//2.0 jquery version
function create_request_string(container) {
    var reqStr = "";

    if ($('[name="'+container+'"]').length>0) { //container has a name
        var $container_id = $('[name="'+container+'"]');
    } else if ($('[id="'+container+'"]').length>0) {
        var $container_id = $('[id="'+container+'"]');
    } else {
        return "";
    }

    $container_id.find('input,textarea,select').each(
        function() {
            //access to form element via $(this)
  			switch (this.tagName) {
  				case "INPUT":
  					switch (this.type) {
  						case "text":
  						case "hidden":
  							reqStr += "&" + this.name + "=" + encodeURIComponent(this.value);
  							break;
  						case "checkbox":
  							if (this.checked) {
                                reqStr += "&" + this.name + "=" + this.value;
  							} else {
                                reqStr += "&" + this.name + "=";
  							}
  							break;
  						case "radio":
  							if (this.checked) {
								      reqStr += "&" + this.name + "=" + this.value;
  							}
  					}
  					break;
  				case "TEXTAREA":
  					reqStr += "&" + this.name + "=" + encodeURIComponent(this.value);
  					break;
  				case "SELECT":
  					reqStr += "&" + this.name + "=" + this.options[this.selectedIndex].value;
  					break;
  			}
        }
    );
    return reqStr;
}

function get_values_from_multiselect(label) {
	var returnme = "";	var i = 0;
	while (document.getElementById(label+i)) {
		if (document.getElementById(label+i).checked) {
			returnme += returnme === "" ? document.getElementById(label+i).value : "," + document.getElementById(label+i).value;
		}
		i++;
	}
	return returnme;
}

function getRadioValue(idOrName) {
    var value = null; var element = document.getElementById(idOrName); var radioGroupName = null;
    if (element == null) { radioGroupName = idOrName; // if null, then the id must be the radio group name
    } else { radioGroupName = element.name;}
    if (radioGroupName == null) { return null;}
    var radios = document.getElementsByTagName('input');
    for(var i=0; i<radios.length; i++) {
        var input = radios[i];
        if (input.type == 'radio' && input.name == radioGroupName && input.checked) {
            value = input.value;
            break;
        }
    }
    return value;
}

function change_selection(selectid, value) {
	eval('SelectObject = document.getElementById("' + selectid + '");');
	for(index = 0; index < SelectObject.length; index++) {
		if (SelectObject[index].value == value) { SelectObject.selectedIndex = index; }
	}
}

//Validation functions
function echeck(str) {
	var at="@"; var dot="."; var lat=str.indexOf(at);	var lstr=str.length;
	var ldot=str.indexOf(dot);
	if (str.indexOf(at)==-1) return false;
	if (str.indexOf(at)==-1 || str.indexOf(at)==0 || str.indexOf(at)==lstr) return false;
	if (str.indexOf(dot)==-1 || str.indexOf(dot)==0 || str.indexOf(dot)==lstr) return false;
	if (str.indexOf(at,(lat+1))!=-1) return false;
	if (str.substring(lat-1,lat)==dot || str.substring(lat+1,lat+2)==dot) return false;
	if (str.indexOf(dot,(lat+2))==-1) return false;
	if (str.indexOf(" ")!=-1) return false;
	return true
}

function IsNumeric(sText) {
	var ValidChars = "0123456789."; var IsNumber=true; var Char;
	for (i = 0; i < sText.length && IsNumber == true; i++) {
		Char = sText.charAt(i);
		if (ValidChars.indexOf(Char) == -1) IsNumber = false;
	}
	return IsNumber;
}

function datetype(element) {
	let datetype = new Date(document.getElementById(element).value + " ");
	datetype.setHours(0, 0, 0, 0);
	return datetype;
}

function checkPassword(x, y, f, alertsoff) {
    if (x.value != y.value) {
        if (!alertsoff) { 
			alert("The entered password fields must match");
		}
        x.value = "";
        y.value = "";
        f.focus();
        if (alertsoff) {
			return false;
        }
		return;
    }
    if (x.value.length  < 5) {
        if (!alertsoff)alert("The entered password fields must be at least 5 characters long");
        x.value = "";
        y.value = "";
        f.focus();
        if (alertsoff) { return false;
        } else { return; }
    }
    if (alerts) { return true; }
}

//Minor usage functions
function update_alerts(change) {
	var alerts = $("#alerts").val();
	if (change == 1) { //add an alert
		alerts++;
		$("#alerts_span").html(alerts + " Alerts");
	} else { //subtract an alert
		alerts--;
		$("#alerts_span").html(alerts == 0 ? "" : alerts + " Alerts");
        if (alerts == 0) {
            //remove entire link
            $("#alerts_link").remove();
        }
	}
	$("#alerts").val(alerts);
}

function adjustStyle(width) {
	width = parseInt(width);
	if (width < 701) {
		$(".rightmenu .col1").css("width","100%");
		$(".rightmenu .col2").css("width","100%");
		$(".rightmenu .colleft").css("right", "24.5%");
		$(".rightmenu .col1").css("left","25%");
		$(".rightmenu .col2").css("left","25%");
		$("#headerlogo").css("width","100%");
		$("#headerquotebox").css("width","100%");
	} else if ((width >= 701)) {
		$(".rightmenu .col1").css("width","76.3%");
		$(".rightmenu .col2").css("width","22.5%");
		$(".rightmenu .colleft").css("right", "25.5%");
		$("#headerlogo").css("width","70%");
		$("#headerquotebox").css("width","29%");
	}
}

function findPosX(obj) {
    var curleft = 0;
    if (obj.offsetParent) {
        while (1) {
            curleft+=obj.offsetLeft;
            if (!obj.offsetParent) {
                break;
            }
            obj=obj.offsetParent;
        }
    } else if (obj.x) {
        curleft+=obj.x;
    }
    return curleft;
}

function findPosY(obj) {
    var curtop = 0;
    if (obj.offsetParent) {
        while (1) {
            curtop+=obj.offsetTop;
            if (!obj.offsetParent) {
                break;
            }
            obj=obj.offsetParent;
        }
    } else if (obj.y) {
        curtop+=obj.y;
    }
    return curtop;
}

function getScrollY() {
	var scrOfY = 0;
	if ( typeof( window.pageYOffset ) == 'number' ) {
		      scrOfY = window.pageYOffset; //Netscape compliant
	} else if ( document.body && ( document.body.scrollLeft || document.body.scrollTop ) ) {
		      scrOfY = document.body.scrollTop; //DOM compliant
	} else if ( document.documentElement && ( document.documentElement.scrollLeft || document.documentElement.scrollTop ) ) {
		      scrOfY = document.documentElement.scrollTop; //IE6 standards compliant mode
	}
	return scrOfY;
}

function scrollto(target_id,speed) {
	if (target_id == '') { return;}
	if (!document.getElementById(target_id)) { return;}
	var currentypos = getScrollY();
	targetdiv = document.getElementById(target_id);
	var desty = targetdiv.offsetTop; var thisNode = targetdiv;
	while (thisNode.offsetParent && (thisNode.offsetParent != document.body)) {
		thisNode = thisNode.offsetParent;
		desty += thisNode.offsetTop;
	}

	desty -= 12; // bring you to just above
	if (desty < currentypos) {
		for (I=currentypos;I > desty; I-=speed) {
			      parent.scroll(1,I);
		}
	} else {
		for (I=currentypos; I < desty; I+=speed) {
			parent.scroll(1,I);
		}
	}
}

function getQueryVariable(variable) {
  var query = window.location.search.substring(1);
  var vars = query.split("&");
  for (var i=0;i<vars.length;i++) {
    var pair = vars[i].split("=");
    if (pair[0] == variable) {
      return pair[1];
    }
  }
 return false;
}

function login_display(reroute) {
	var returned = trim(xmlHttp.responseText).split("**");
	if (returned[0] == "false") { //login failed
        document.getElementById("login_box_error").innerHTML = returned[1]; return false;
    } else if (returned[1] != '') { //login with a reroute
		 document.getElementById("login_box_error").innerHTML = returned[1];
		 reroute.value = true;
		 return true;
	} else if (returned[0] == 'true') { return true; } //regular login success
}

function login(username, password) {
	var reroute = new Object();
	reroute.value = document.getElementById("reroute") ? true : false;
	ajaxapi('/ajax/site_ajax.php','login',"&username=" + encodeURIComponent(username) + "&password=" + password,function() {
		if (login_display(reroute)) {
			if (!reroute.value) {
                pageid = getCookie("pageid");
                if (pageid && pageid.isNumeric) {
                    go_to_page(pageid);
				} else {  go_to_page(1); }
			} else {
				window.location=WWW_ROOT + document.getElementById("reroute").value;
			}
		}
	});
}

//print function
function update_login_display(pageid) {
	var returned = trim(xmlHttp.responseText).split("**");
	if (returned[0] == "true") { if (returned[1] !== "check") {	go_to_page(pageid); }
    } else { if (document.getElementById("loggedin")) { go_to_page(1); } }
}

//checks to see if user should still be logged in
function update_login_contents(pageid, check) {
	if (check == "check") {
		ajaxapi('/ajax/site_ajax.php','update_login_contents','&pageid=0&check=1',function() {if (xmlHttp.readyState == 4) { update_login_display(pageid); }},true);
	} else {
		ajaxapi('/ajax/site_ajax.php','update_login_contents','&pageid='+pageid,function() {if (xmlHttp.readyState == 4) { update_login_display(pageid); }},true);
	}
}

//OLD FUNCTIONS THAT MIGHT NOT BE IN USE ANYMORE
function clear_window(pageid) { go_to_page(pageid); } //Might be useless now\

function page_display() {
	var sections = trim(xmlHttp.responseText).split("%%");	var content; var i=0;
 	while (sections[i]) {
	 	content = sections[i].split("**");
		var filldiv = content[0];
		var divname = content[1];
		$("#" + divname).html(filldiv);
	 	i++;
 	}
}

//print function
function create_page_display() {
	var text = JSON.parse(xmlHttp.responseText);
  $("#create_page_div").html(text[2]);
	if (text[0] == "true") {
    self.parent.go_to_page(text[1]);
	}
}

function prepareInputsForHints() {
	$("input, select, textarea, checkbox, radio").on("focus", function() {
		$(this).siblings(".hint").show();
	}).on("blur", function() {
		$(".hint").hide();
	});
}

var editor;
function createEditor(name,contents) {
	if ( editor ) return;
	// Create a new editor inside the <div id="editor">, setting its value to html
	var config = {};
	editor = CKEDITOR.appendTo(name, config, contents );
}

function removeEditor(name) {
	if ( !editor ) return;
	// Destroy the editor.
	editor.destroy();
	editor = null;
}

function preloadImg(image) {
	var img = new Image();
	img.src = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/" + image;
}

function getSession(cname) {
    ajaxapi('/ajax/site_ajax.php','get_cookie','&cname='+cname,function() {if (xmlHttp.readyState == 4) { return xmlHttp.responseText; } }, true);
}

function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i=0; i<ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1);
        if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
    }
    return "";
}
