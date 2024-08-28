// As soon as possible, do these things.
// Set root directory
var WWW_ROOT = location.protocol + '//' + location.host;

// global storage of all custom javascript globals.
var myGlobals = {
    "myIntervals": {}, // Used to store all reoccuring javascript functions.
    "exitEvent": false, // This is a global that could be used during live events (jquery on(click), on(submit), etc) to exit the event.
};

var xmlHttp = createXMLHttpRequest(); // OLD XMLHTTP OBJECT THAT I'M TRYING NOT TO USE ANYMORE.

if (document.layers) {
    document.captureEvents(Event.MOUSEOVER | Event.MOUSEOUT)
} // Not sure why I need this.

function getGlobals() {
    if (typeof getRoot()[0] !== 'undefined') {
        return getRoot()[0].myGlobals;
    }
    if (typeof myGlobals !== 'undefined') {
        console.log("do we ever actually use this?");
        return myGlobals;
    }
}

function getRoot(element = false) {
    if (!element) {
        return $($(top)[0], $(top)[0].document)
    }
    return $(element, $(top)[0].document);
}

function getColorbox(gallery = "") {
    if (gallery.length > 0) {
        return getRoot(gallery).colorbox;
    }
    return getRoot().colorbox;
}

function getIntervals() {
    return getGlobals().myIntervals;
}

function makeInterval(identifier, action, interval) {
    var fn = Function(action);
    getIntervals()[identifier] = {
        "id": setInterval(fn, interval),
        "script": fn,
    };
    return getIntervals()[identifier].id;
}

function killInterval(identifier) {
    if (typeof(getIntervals()[identifier]) !== "undefined") {
        clearInterval(getIntervals()[identifier].id);
        delete getIntervals()[identifier];
    }
}

//AJAX API
/* Create a new XMLHttpRequest object to talk to the Web server */
function createXMLHttpRequest() {
    xmlHttp = null;
    if (typeof XMLHttpRequest != "undefined") {
        xmlHttp = new XMLHttpRequest();
    } else if (typeof window.ActiveXObject != "undefined") {
        try {
            xmlHttp = new ActiveXObject("Msxml2.XMLHTTP.4.0");
        } catch (e) {
            try {
                xmlHttp = new ActiveXObject("MSXML2.XMLHTTP");
            } catch (e) {
                try {
                    xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
                } catch (e) {
                    xmlHttp = null;
                }
            }
        }
    }
    return xmlHttp;
}

function ajaxapi_old(script, action, param, display, async) {
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
    setTimeout(function() {
        activatejs();
    }, 500);
}

function ajaxpost(url, parameters, async, display) {
    if (async == true) {
        xmlHttp.open('POST', url, true);
        xmlHttp.onreadystatechange = display;
        xmlHttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xmlHttp.send(parameters);
    } else {
        xmlHttp.open('POST', url, false);
        xmlHttp.onreadystatechange = function() {};
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
        setTimeout(function() {
            ajaxapi_old(script, action, param, display, async);
        }, 500); // if there is an ajax conflict.  Wait 1 second before trying again.
        return false;
    }
    return true;
}

String.prototype.entityify = function() {
    return this.replace(/&amp;/g, "&");
};

String.prototype.singleline = function() {
    return this.replace(/(\r\n|\n|\r)/gm, "").replace(/\s\s+/g, ' ');
};

function save_action(objecta, objectb) {
    if ($(objecta).click != undefined) {
        $(objectb).val($(objecta).attr("onclick").singleline());
    }
}

//Display or Return Functions
function simple_display(container) {
    if ($("#" + container).length) {
        // plant flag that container will soon be updated.
        plant_update_flag($("#" + container));
        $().waitTillExists($("#" + container), '#updating_' + container, function () {
            // update dom container.
            $("#" + container).html(xmlHttp.responseText);
            // make sure updating flag is gone, signifying dom is updated.
            $().waitTillGone($("#" + container), '#updating_' + container, function () {
                resize_modal();
            });
        });
    } else if (getRoot()[0].$("#" + container).length) { // might be in an iframe and wanting to populate a parent container.
        getRoot()[0].simple_display(container);
    }
}

function plant_update_flag(container) {
    container.append('<input type="hidden" id="updating_' + container.attr("id") + '" />');
}

function ajaxerror(data) {
    if (data.ajaxerror != undefined && data.ajaxerror.length > 0) {
        var container = "ajax_error_display";
        var containerobj = getRoot("#" + container);

        plant_update_flag(containerobj);

        $().waitTillExists(containerobj, '#updating_' + container, function () {
            // update dom container.
            containerobj.html(data.ajaxerror);

            // make sure updating flag is gone, signifying dom is updated.
            $().waitTillGone(containerobj, '#updating_' + container, function () {
                containerobj.slideDown(1000, function () {
                    $(this).toggleClass('visible');
                });
                setTimeout(function () {
                    containerobj.slideUp(1000, function () {
                        $(this).toggleClass('visible');
                    });
                }, 5000);
            });
        });
    }
}

function jq_display(container, data) {
    if ($("#" + container).length > 0) {
        // plant flag that container will soon be updated.
        plant_update_flag($("#" + container));
        $().waitTillExists($("#" + container), '#updating_' + container, function () {
            // update dom container.
            $("#" + container).html(data.message);
            // make sure updating flag is gone, signifying dom is updated.
            $().waitTillGone($("#" + container), '#updating_' + container, function () {
                resize_modal();
            });
        });
    } else if (getRoot()[0].$("#" + container).length) { // might be in an iframe and wanting to populate a parent container.
        getRoot()[0].jq_display(container, data);
    }
}

function clear_display(divname) {
    if (document.getElementById(divname)) {
        document.getElementById(divname).innerHTML = '';
    }
}

function display_backup(divname, backupdiv) {
    document.getElementById(divname).innerHTML = document.getElementById(backupdiv).innerHTML + xmlHttp.responseText;
}

function istrue_old() {
    if (trim(xmlHttp.responseText) == "false") {
        return false;
    } else {
        return true;
    }
}

function istrue(data = false) {
    if (data === false) {
        return istrue_old();
    }
    return data.message == "false" ? false : true;
}

function do_nothing() {}

function option_display(pageid, resultsdiv) {
    var returned = trim(xmlHttp.responseText).split("**");
    if (returned[0] == "true") {
        go_to_page(pageid);
    } else {
        document.getElementById(resultsdiv).innerHTML = returned[1];
    }
}

function countdown(section, timer, dothis) {
    $('#' + section).html(timer);
    killInterval(section);
    let countdownScript = `
        let timer = parseInt($("#` + section + `").html()) - 1;
        $("#` + section + `").html(timer);
        if (timer === 0) {
            var fn = Function("` + dothis + `");
            fn();
            killInterval("` + section + `");
            return false;
        }
    `;
    makeInterval(section, countdownScript, 1000);
}

function hidestatus() {
    window.status = '';
    return true;
}
document.onmouseover = hidestatus;
document.onmouseout = hidestatus;

//Small Common-use functions
function go_to_page(pageid) {
    if (pageid == 1) {
        location.href = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot);
    } else {
        location.href = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/index.php?pageid=" + pageid;
    }
}

function hide_section(section_name) {
    $("#" + section_name).hide();
}

function show_section(section_name, block) {
    var display = block == true ? "block" : "inline";
    document.getElementById(section_name).style.display = display;
}

function hide_show_buttons(section_name, block) {
    var display = block == true ? "block" : "inline";
    if (document.getElementById(section_name).style.display == 'none' || document.getElementById(section_name).style.display.length == 0) {
        document.getElementById(section_name).style.display = display;
    } else {
        document.getElementById(section_name).style.display = "none";
    }
}

function trim(stringToTrim) {
    return stringToTrim.replace(/^\s+|\s+$/g, "");
}

function ltrim(stringToTrim) {
    return stringToTrim.replace(/^\s+/, "");
}

function rtrim(stringToTrim) {
    return stringToTrim.replace(/\s+$/, "");
}

//Modal Functions
function close_colorbox() {
    getColorbox().close();
}

function close_modal() {
    getRoot()[0].close_colorbox();
}

function resize_modal() {
    getRoot()[0].resize_colorbox();
}

function resize_colorbox(container) {
    getRoot()[0].initialize_colorbox_iframes();
    setTimeout(function () {
        getRoot()[0].resizeAll();
        getRoot().trigger("resize");
    }, 50);
}

function initialize_colorbox_iframes() {
    if (getRoot("iframe.cboxIframe").length && !getRoot("iframe.cboxIframe").attr("id")) {
        getRoot("iframe.cboxIframe").attr("id", "colorboxiframe");
        getRoot("iframe.cboxIframe").on('load', function () {
            getRoot()[0].resizeCaller("colorboxiframe");
        });
        getRoot("iframe.cboxIframe").trigger('load');
    }
}

function stripslashes(str) {
    str = str.replace(/\\'/g, '\'');
    str = str.replace(/\\"/g, '"');
    str = str.replace(/\\0/g, '\0');
    str = str.replace(/\\\\/g, '\\');
    return str;
}

//Debug
function print_r(theObj) {
    var printme;
    if (typeof theObj !== 'undefined') {
        if (theObj.constructor == Array || theObj.constructor == Object) {
            for (var p in theObj) {
                if (theObj[p] && (theObj[p].constructor == Array || theObj[p].constructor == Object)) {
                    printme += "[" + p + "] => " + typeof(theObj);
                    print_r(theObj[p]);
                } else {
                    printme += "[" + p + "] => " + theObj[p];
                }
            }
        }
        alert(printme);
    }
}

function loadjs(scriptName, callback) {
    if (typeof callback == "undefined") {
        callback = function() {};
    }
    $.getScript(scriptName, callback).fail(function(jqxhr, settings, exception) {
        alert("Some javascript files failed to load (" + scriptName + "), the page may not work as intended.");
    });
    return;
}

function loaddynamicjs(scriptname) {
    var js = $('#' + scriptname).html();
    setTimeout(function() {
        return false;
    }, 2000);
    var head = document.getElementsByTagName("head")[0];
    script = document.createElement('script');
    script.id = "dynamicscript";
    script.type = 'text/javascript';
    script.text = js;
    head.appendChild(script);
    setTimeout(function() {
        return false;
    }, 100);
}

/**
 * Loads AJAX scripts dynamically into the page.
 *
 * @param {Object} data - An object containing the AJAX scripts to load.
 *                       The keys are the script identifiers and the values
 *                       are the script contents.
 */
function loadajaxjs(data) {
    // Iterate over the AJAX scripts to load.
    $.each(data.loadajax, function (key, value) {
        // Append the script to the page as an active script.
        appendjs(key, value);
    });
}

/**
 * Enables all inactive AJAX scripts by removing the inactive class and
 * appending the script to the body of the page.
 */
function activatejs() {
    // Iterate over all inactive AJAX scripts.
    $.each($('.ajaxapi.inactive'), function() {
        // Get the id of the script.
        let key = $(this).attr('id');
        // Get the script contents.
        let value = $(this).html();
        // Unbind element from events just in case the script wasn't fully inactive.
        $('#' + key).unbind();
        $('#' + key).off();
        // Remove the inactive script.
        $(this).remove();

        // Append the script to the page as an active script.
        appendjs(key, value);
    });
}

/**
 * Activates a previously loaded AJAX script by appending it to the DOM.
 *
 * @param {string} id - The identifier of the script to activate.
 * @param {string} script - The script contents to append to the DOM.
 */
function appendjs(id, script) {
    // Create the container div if it doesn't exist.
    if ($("#jscontainer").length == 0) {
        $("body").append('<div id="jscontainer"></div>');
    }

    // Remove any previously loaded script with the same id.
    $("#script_" + id).remove();

    // Append the new script to the container div.
    $("#jscontainer").append(
        $('<script class="ajaxapi active" id="script_' + id + '">' + script.singleline().trim() + '</script>')
    );
}

function mergeJSON(data1, data2) {
    var json1 = JSON.parse(data1);
    var json2 = JSON.parse(data2);
    return JSON.stringify($.extend({}, json1, json2));
}

function create_request_json(container) {
    let queryString = create_request_string(container);
    let obj = {}
    if(queryString) {
      queryString.slice(1).split('&').map((item) => {
        const [ k, v ] = item.split('=')
        v ? obj[k] = v : null
      })
    }
    return JSON.stringify(obj);
}

//2.0 jquery version
function create_request_string(container) {
    var reqStr = "";

    if ($('[name="' + container + '"]').length > 0) { //container has a name
        var $container_id = $('[name="' + container + '"]');
    } else if ($('[id="' + container + '"]').length > 0) {
        var $container_id = $('[id="' + container + '"]');
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
                        case "date":
                        case "hidden":
                            reqStr += "&" + this.name + "=" + encodeURIComponent($(this).val());
                            break;
                        case "checkbox":
                            if (this.checked) {
                                reqStr += "&" + this.name + "=" + $(this).val();
                            } else {
                                reqStr += "&" + this.name + "=";
                            }
                            break;
                        case "radio":
                            if (this.checked) {
                                reqStr += "&" + this.name + "=" + $(this).val();
                            }
                    }
                    break;
                case "TEXTAREA":
                    reqStr += "&" + this.name + "=" + encodeURIComponent($(this).val());
                    break;
                case "SELECT":
                    reqStr += "&" + this.name + "=" + $(this).val();
                    break;
            }
        }
    );
    return reqStr;
}

function get_values_from_multiselect(label) {
    var returnme = "";
    var i = 0;
    while (document.getElementById(label + i)) {
        if (document.getElementById(label + i).checked) {
            returnme += returnme === "" ? document.getElementById(label + i).value : "," + document.getElementById(label + i).value;
        }
        i++;
    }
    return returnme;
}

function getRadioValue(idOrName) {
    var value = null;
    var element = document.getElementById(idOrName);
    var radioGroupName = null;
    if (element == null) {
        radioGroupName = idOrName; // if null, then the id must be the radio group name
    } else {
        radioGroupName = element.name;
    }
    if (radioGroupName == null) {
        return null;
    }
    var radios = document.getElementsByTagName('input');
    for (var i = 0; i < radios.length; i++) {
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
    for (index = 0; index < SelectObject.length; index++) {
        if (SelectObject[index].value == value) {
            SelectObject.selectedIndex = index;
        }
    }
}

//Validation functions
function echeck(str) {
    var at = "@";
    var dot = ".";
    var lat = str.indexOf(at);
    var lstr = str.length;
    var ldot = str.indexOf(dot);
    if (str.indexOf(at) == -1) return false;
    if (str.indexOf(at) == -1 || str.indexOf(at) == 0 || str.indexOf(at) == lstr) return false;
    if (str.indexOf(dot) == -1 || str.indexOf(dot) == 0 || str.indexOf(dot) == lstr) return false;
    if (str.indexOf(at, (lat + 1)) != -1) return false;
    if (str.substring(lat - 1, lat) == dot || str.substring(lat + 1, lat + 2) == dot) return false;
    if (str.indexOf(dot, (lat + 2)) == -1) return false;
    if (str.indexOf(" ") != -1) return false;
    return true
}

function IsNumeric(sText) {
    var ValidChars = "0123456789.";
    var IsNumber = true;
    var Char;
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
    if (x.value.length < 5) {
        if (!alertsoff) alert("The entered password fields must be at least 5 characters long");
        x.value = "";
        y.value = "";
        f.focus();
        if (alertsoff) {
            return false;
        } else {
            return;
        }
    }
    if (alerts) {
        return true;
    }
}

// Minor usage functions
function update_alerts(addalert = 0) {
    var alerts = $("#alerts").val();
    if (addalert === 1) { // add an alert
        alerts++;
        $("#alerts_span").html(alerts + " Alerts");
    } else { // subtract an alert
        alerts--;
        $("#alerts_span").html(alerts == 0 ? "" : alerts + " Alerts");
        if (alerts === 0) {
            $("#alerts_link").remove();
        }
    }
    $("#alerts").val(alerts);
}

function get_contrast_color(hexcolor){
    var r = parseInt(hexcolor.substring(1,3),16);
    var g = parseInt(hexcolor.substring(3,5),16);
    var b = parseInt(hexcolor.substring(5,7),16);
    var yiq = ((r*299)+(g*587)+(b*114))/1000;
    return (yiq >= 128) ? 'black' : 'white';
}

function adjustStyle(width) {
    width = parseInt(width);
    if (width < 1100) {
        $(".rightmenu .col1").css("width", "100%");
        $(".rightmenu .col2").css("width", "100%");
        $(".rightmenu .colleft").css("right", "24.5%");
        $(".rightmenu .col1").css("left", "25%");
        $(".rightmenu .col2").css("left", "25%");
        $("#headerlogo").css("width", "100%");
        $("#headerquotebox").css("width", "100%");
    } else if ((width >= 720)) {
        $(".rightmenu .col1").css("width", "76.3%");
        $(".rightmenu .col2").css("width", "22.5%");
        $(".rightmenu .colleft").css("right", "25.5%");
        $("#headerlogo").css("width", "70%");
        $("#headerquotebox").css("width", "29%");
    }
}

function findPosX(obj) {
    var curleft = 0;
    if (obj.offsetParent) {
        while (1) {
            curleft += obj.offsetLeft;
            if (!obj.offsetParent) {
                break;
            }
            obj = obj.offsetParent;
        }
    } else if (obj.x) {
        curleft += obj.x;
    }
    return curleft;
}

function findPosY(obj) {
    var curtop = 0;
    if (obj.offsetParent) {
        while (1) {
            curtop += obj.offsetTop;
            if (!obj.offsetParent) {
                break;
            }
            obj = obj.offsetParent;
        }
    } else if (obj.y) {
        curtop += obj.y;
    }
    return curtop;
}

function getScrollY() {
    var scrOfY = 0;
    if (typeof(window.pageYOffset) == 'number') {
        scrOfY = window.pageYOffset; //Netscape compliant
    } else if (document.body && (document.body.scrollLeft || document.body.scrollTop)) {
        scrOfY = document.body.scrollTop; //DOM compliant
    } else if (document.documentElement && (document.documentElement.scrollLeft || document.documentElement.scrollTop)) {
        scrOfY = document.documentElement.scrollTop; //IE6 standards compliant mode
    }
    return scrOfY;
}

function scrollto(target_id, speed) {
    if (target_id == '') {
        return;
    }
    if (!document.getElementById(target_id)) {
        return;
    }
    var currentypos = getScrollY();
    targetdiv = document.getElementById(target_id);
    var desty = targetdiv.offsetTop;
    var thisNode = targetdiv;
    while (thisNode.offsetParent && (thisNode.offsetParent != document.body)) {
        thisNode = thisNode.offsetParent;
        desty += thisNode.offsetTop;
    }

    desty -= 12; // bring you to just above
    if (desty < currentypos) {
        for (I = currentypos; I > desty; I -= speed) {
            parent.scroll(1, I);
        }
    } else {
        for (I = currentypos; I < desty; I += speed) {
            parent.scroll(1, I);
        }
    }
}

function getQueryVariable(variable) {
    var query = window.location.search.substring(1);
    var vars = query.split("&");
    for (var i = 0; i < vars.length; i++) {
        var pair = vars[i].split("=");
        if (pair[0] == variable) {
            return pair[1];
        }
    }
    return false;
}

function verify_login(data) {
    let message = JSON.parse(data.message);
    let status = message.status;
    if (status === "failed") {
        $("#login_box_error").html(message.content);
        return false;
    }

    if (status === "reroute") {
        $("#login_box_error").html(message.content);
        window.location = WWW_ROOT + $("#reroute").val();
        return;
    }

    if (status === "success") {
        let pageid = getCookie("pageid");
        if (pageid && pageid.isNumeric) {
            go_to_page(pageid);
        } else {
            go_to_page(1);
        }
    }
}

function login_check_response(data) {
    let message = JSON.parse(data.message);
    if (message.status !== "active" && $("#loggedin").length) {
        go_to_page(message.pageid);
    }
}

//OLD FUNCTIONS THAT MIGHT NOT BE IN USE ANYMORE
function clear_window(pageid) {
    go_to_page(pageid);
} //Might be useless now\

function page_display() {
    var sections = trim(xmlHttp.responseText).split("%%");
    var content;
    var i = 0;
    while (sections[i]) {
        content = sections[i].split("**");
        var filldiv = content[0];
        var divname = content[1];
        $("#" + divname).html(filldiv);
        i++;
    }
}

//print function
function create_page_display(data) {
    var pageid = parseInt(data.message) || 0;
    if (pageid !== 0) {
        self.parent.go_to_page(pageid);
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

function createEditor(name, contents) {
    if (editor) return;
    // Create a new editor inside the <div id="editor">, setting its value to html
    var config = {};
    editor = CKEDITOR.appendTo(name, config, contents);
}

function removeEditor(name) {
    if (!editor) return;
    // Destroy the editor.
    editor.destroy();
    editor = null;
}

function preloadImg(image) {
    var img = new Image();
    img.src = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/" + image;
}

function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1);
        if (c.indexOf(name) == 0) return c.substring(name.length, c.length);
    }
    return "";
}

$(function() { // At the end of the document load.  do these things.
    activatejs(); // Check for inactive ajax javascript and attempt to activate it.
});