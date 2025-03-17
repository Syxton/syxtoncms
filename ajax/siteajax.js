// Font Awesome config.
FontAwesome.config.searchPseudoElements = true;

// As soon as possible, do these things.
// Set root directory
var WWW_ROOT = location.protocol + '//' + location.host;

// global storage of all custom javascript globals.
var myGlobals = {
    "myIntervals": {}, // Used to store all reoccuring javascript functions.
    "exitEvent": false, // This is a global that could be used during live events (jquery on(click), on(submit), etc) to exit the event.
};

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
    if ($("#" + container).not("script").length > 0) {
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
    if ($("#" + divname).length) {
        $("#" + divname).html("");
    }
}

function istrue(data = false) {
    return data.message == "false" ? false : true;
}

function do_nothing() {}

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
    }, 100);
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
                        case "password":
                        case "number":
                        case "email":
                        case "search":
                        case "url":
                        case "tel":
                        case "range":
                        case "date":
                        case "hidden":
                        case "color":
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

//Validation functions
function isValidEmail(emailaddress) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailRegex.test(emailaddress)) {
        return true;
    }

    return false;
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
    if (hexcolor.charAt(0) == '#') { hexcolor = hexcolor.substr(1); }
    if (hexcolor.search("rgb") !== -1) {
        const rgb2hex = (rgb) => `#${rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/).slice(1).map(n => parseInt(n, 10).toString(16).padStart(2, '0')).join('')}`;
        hexcolor = rgb2hex(hexcolor);
    }

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

function printArea(divId) {
    var printContents = $("#" + divId).html();
    var originalContents = $("body").html();

    $("body").html(printContents);

    window.print();

    $("body").html(originalContents);
}

function toggle_nav_menu() {
    var x = document.getElementById("myTopnav");
    if (x.className === "topnav") {
        x.className += " responsive";
    } else {
        x.className = "topnav";
    }
}

$(function() { // At the end of the document load.  do these things.
    activatejs(); // Check for inactive ajax javascript and attempt to activate it.
});