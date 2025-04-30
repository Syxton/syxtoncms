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

/**
 * @function getGlobals
 * @description
 * Returns the global storage object of all custom javascript globals.
 *
 * @returns {object} - The global storage object.
 */
function getGlobals() {
    if (typeof getRoot()[0] !== 'undefined') {
        // If the root element has a myGlobals property, return it.
        return getRoot()[0].myGlobals;
    }

    if (typeof myGlobals !== 'undefined') {
        // This should only be called if we're not in the context of a document.
        console.log("do we ever actually use this?");
        return myGlobals;
    }
}

/**
 * @function getRoot
 * @description
 * Returns the root element of the document.
 *
 * @param {object} [element=false] - An optional element to get the root of.
 * @returns {object} - The root element of the document.
 */
function getRoot(element = false) {
    if (!element) {
        // If no element is provided, return the root of the document.
        return $($(top)[0], $(top)[0].document)
    }
    // If an element is provided, return the root of that element.
    return $(element, $(top)[0].document);
}

/**
 * @function getColorbox
 * @description
 * Returns the colorbox instance of the document or
 * the specified element.
 *
 * @param {string} [gallery=""] - The optional gallery element to get the colorbox from.
 * @returns {object} - The colorbox instance.
 */
function getColorbox(gallery = "") {
    if (gallery.length > 0) {
        // If a gallery element is provided, return its colorbox.
        return getRoot(gallery).colorbox;
    }
    // If no element is provided, return the document's colorbox.
    return getRoot().colorbox;
}

/**
 * @function getIntervals
 * @description
 * Returns the global storage object of all reoccuring javascript intervals.
 *
 * @returns {object} - The global storage object of all reoccuring javascript intervals.
 */
function getIntervals() {
    return getGlobals().myIntervals;
}

/**
 * @function makeInterval
 * @description
 * Creates a new reoccuring javascript interval.
 *
 * @param {string} identifier - A unique identifier for the interval.
 * @param {string} action - The action string to be executed.
 * @param {number} interval - The interval time in ms.
 * @returns {number} - The id of the interval.
 */
function makeInterval(identifier, action, interval) {
    // Create a function from the action string.
    var fn = Function(action);
    // Store the interval in the global storage object.
    getIntervals()[identifier] = {
        "id": setInterval(fn, interval),
        "script": fn,
    };
    // Return the id of the interval.
    return getIntervals()[identifier].id;
}

/**
 * @function killInterval
 * @description
 * Kills a reoccuring javascript interval.
 *
 * @param {string} identifier - A unique identifier for the interval.
 * @returns {void} - Nothing.
 */
function killInterval(identifier) {
    if (typeof(getIntervals()[identifier]) !== "undefined") {
        // Clear the interval.
        clearInterval(getIntervals()[identifier].id);
        // Delete the interval object.
        delete getIntervals()[identifier];
    }
}

/**
 * @function entityify
 * @description
 * Replaces all occurences of &amp; with &.
 *
 * @returns {string} - The entityified string.
 */
String.prototype.entityify = function() {
    // Replace all occurences of &amp; with &.
    return this.replace(/&amp;/g, "&");
};

/**
 * @function singleline
 * @description
 * Removes all linebreaks from a string and replaces multiple
 * whitespace characters with a single space.
 *
 * @returns {string} - The single line string.
 */
String.prototype.singleline = function() {
    // Remove all linebreaks from the string.
    var str = this.replace(/(\r\n|\n|\r)/gm, "");
    // Replace multiple whitespace characters with a single space.
    return str.replace(/\s\s+/g, ' ');
};

/**
 * @function save_action
 * @description
 * Saves the value of objecta's onclick attribute into objectb's value attribute.
 *
 * @param {object} objecta - The object containing the onclick attribute.
 * @param {object} objectb - The object containing the value attribute.
 * @returns {void} - Nothing.
 */
function save_action(objecta, objectb) {
    if ($(objecta).click != undefined) {
        $(objectb).val($(objecta).attr("onclick").singleline());
    }
}

/**
 * @function plant_update_flag
 * @description
 * Plants a flag in the DOM that a container is about to be updated.
 * This flag is used to prevent the container from being updated twice.
 *
 * @param {object} container - The container element that is about to be updated.
 * @returns {void} - Nothing.
 */
function plant_update_flag(container) {
    // Add a hidden input to the container that acts as a flag.
    // This flag is used to prevent the container from being updated twice.
    container.append('<input type="hidden" id="updating_' + container.attr("id") + '" />');
}

/**
 * @function ajaxerror
 * @description
 * Called when an AJAX error occurs. Puts the error message in the DOM.
 *
 * @param {object} data - The object containing the error message.
 * @returns {void} - Nothing.
 */
function ajaxerror(data) {
    if (data.ajaxerror != undefined && data.ajaxerror.length > 0) {
        var container = "ajax_error_display";
        var containerobj = getRoot("#" + container);

        // plant flag that container will soon be updated.
        plant_update_flag(containerobj);

        // wait until container is ready to be updated.
        $().waitTillExists(containerobj, '#updating_' + container, function () {
            // update dom container.
            containerobj.html(data.ajaxerror);

            // make sure updating flag is gone, signifying dom is updated.
            $().waitTillGone(containerobj, '#updating_' + container, function () {
                containerobj.slideDown(1000, function () {
                    $(this).toggleClass('visible');
                });
                // wait 5 seconds before hiding the container again.
                setTimeout(function () {
                    containerobj.slideUp(1000, function () {
                        $(this).toggleClass('visible');
                    });
                }, 5000);
            });
        });
    }
}

/**
 * @function jq_display
 * @description
 * Populates a DOM container with the data sent from the server.
 *
 * @param {string} container - The DOM container to populate.
 * @param {object} data - The object containing the data to populate with.
 * @returns {void} - Nothing.
 */
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

/**
 * @function clear_display
 * @description
 * Clears the content of a specified DOM element.
 *
 * @param {string} divname - The ID of the DOM element to clear.
 * @returns {void} - Nothing.
 */
function clear_display(divname) {
    // Check if the element with the specified ID exists
    if ($("#" + divname).length) {
        // Clear the content of the element
        $("#" + divname).html("");
    }
}

/**
 * @function istrue
 * @description
 * Determines if the 'message' property of the data object is not equal to "false".
 *
 * @param {object} [data=false] - The object containing the message property.
 * @returns {boolean} - Returns true if the message is not "false", otherwise false.
 */
function istrue(data = false) {
    // Check if the message is "false" and return false, otherwise return true
    return data.message == "false" ? false : true;
}

/**
 * @function do_nothing
 * @description
 * Does nothing.
 *
 * @returns {void} - Nothing.
 */
function do_nothing() {}

/**
 * @function countdown
 * @description
 * Initiates a countdown for a given section. Updates the section's content with the timer value every second.
 * Executes a specified action when the timer reaches zero.
 *
 * @param {string} section - The ID of the section where the countdown is displayed.
 * @param {number} timer - The initial timer value in seconds.
 * @param {string} dothis - The action to execute when the countdown reaches zero.
 * @returns {void} - Nothing.
 */
function countdown(section, timer, dothis) {
    // Set the initial timer value in the specified section.
    $('#' + section).html(timer);

    // Stop any existing interval associated with the section.
    killInterval(section);

    // Script to decrement the timer and execute an action when it reaches zero.
    let countdownScript = `
        // Decrement the timer value.
        let timer = parseInt($("#` + section + `").html()) - 1;
        // Update the section with the new timer value.
        $("#` + section + `").html(timer);

        // Check if the timer has reached zero.
        if (timer === 0) {
            // Create a function from the action string and execute it.
            var fn = Function("` + dothis + `");
            fn();

            // Stop the interval as the countdown has completed.
            killInterval("` + section + `");
            return false;
        }
    `;

    // Start a new interval to update the countdown every second.
    makeInterval(section, countdownScript, 1000);
}

/**
 * @function hidestatus
 * @description
 * Hides the status bar by setting the window.status property to an empty string.
 * This is useful for hiding the URL of a page from users.
 *
 * @returns {boolean} - Returns true.
 */
function hidestatus() {
    // Set the window.status property to an empty string.
    window.status = '';
    // Return true.
    return true;
}
document.onmouseover = hidestatus;
document.onmouseout = hidestatus;

/**
 * @function go_to_page
 * @description
 * Navigates to a specified page.
 *
 * @param {number} pageid - The ID of the page to navigate to.
 * @returns {void} - Nothing.
 */
function go_to_page(pageid) {
    if (pageid == 1) {
        location.href = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot);
    } else {
        location.href = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/index.php?pageid=" + pageid;
    }
}


/**
 * @function hide_section
 * @description
 * Hides a specified section by setting its display to 'none'.
 *
 * @param {string} section_name - The ID of the section to hide.
 * @returns {void} - Nothing.
 */
function hide_section(section_name) {
    // Use jQuery to hide the section by ID
    $("#" + section_name).hide();
}

/**
 * @function show_section
 * @description
 * Shows a specified section by setting its display to the specified value.
 *
 * @param {string} section_name - The ID of the section to show.
 * @param {boolean} block - Whether to set the display to 'block' or 'inline'.
 * @returns {void} - Nothing.
 */
function show_section(section_name, block) {
    var display = block == true ? "block" : "inline";
    $("#" + section_name).css("display", display);
}

/**
 * @function hide_show_buttons
 * @description
 * Toggles the display of a specified section between 'none' and the specified display value ('block' or 'inline').
 *
 * @param {string} section_name - The ID of the section to toggle.
 * @param {boolean} block - When true, sets the display to 'block', otherwise 'inline'.
 * @returns {void} - Nothing.
 */
function hide_show_buttons(section_name, block) {
    // Get the current display style of the section
    let current = $("#" + section_name).css("display");

    // If the section is hidden or has no display style, show it
    if (current == 'none' || current.length == 0) {
        let display = block == true ? "block" : "inline";
        $("#" + section_name).css("display", display);
    } else {
        // Otherwise, hide the section
        $("#" + section_name).css("display", "none");
    }
}

/**
 * @function trim
 * @description
 * Trims whitespace from the beginning and end of a given string.
 *
 * @param {string} stringToTrim - The string to trim.
 * @returns {string} - The trimmed string.
 */
function trim(stringToTrim) {
    return stringToTrim.replace(/^\s+|\s+$/g, "");
}

/**
 * @function ltrim
 * @description
 * Trims whitespace from the beginning of a given string.
 *
 * @param {string} stringToTrim - The string to trim.
 * @returns {string} - The trimmed string.
 */
function ltrim(stringToTrim) {
    return stringToTrim.replace(/^\s+/, "");
}

/**
 * @function rtrim
 * @description
 * Trims whitespace from the end of a given string.
 *
 * @param {string} stringToTrim - The string to trim.
 * @returns {string} - The trimmed string.
 */
function rtrim(stringToTrim) {
    return stringToTrim.replace(/\s+$/, "");
}

/**
 * @function stripslashes
 * @description
 * Removes backslashes from a string.
 *
 * @param {string} str - The string to strip slashes from.
 * @returns {string} - The modified string.
 */
function stripslashes(str) {
    // Replace backslashes before single quotes
    str = str.replace(/\\'/g, '\'');
    // Replace backslashes before double quotes
    str = str.replace(/\\"/g, '"');
    // Replace backslashes before null characters
    str = str.replace(/\\0/g, '\0');
    // Replace double backslashes with single backslashes
    str = str.replace(/\\\\/g, '\\');
    return str;
}

/**
 * @function close_colorbox
 * @description Closes the Colorbox modal.
 */
function close_colorbox() {
    getColorbox().close();
}

/**
 * @function close_modal
 * @description Closes the modal by calling the close_colorbox function on the root element.
 */
function close_modal() {
    getRoot()[0].close_colorbox();
}

/**
 * @function resize_modal
 * @description Resizes the modal by calling the resize_colorbox function on the root element.
 */
function resize_modal() {
    getRoot()[0].resize_colorbox();
}

/**
 * @function resize_colorbox
 * @description Initializes Colorbox iframes and triggers a resize event after resizing all elements.
 * @param {object} container - The container to resize.
 */
function resize_colorbox(container) {
    getRoot()[0].initialize_colorbox_iframes();
    setTimeout(function () {
        getRoot()[0].resizeAll();
        getRoot().trigger("resize");
    }, 100);
}

/**
 * @function initialize_colorbox_iframes
 * @description Initializes iframes used in Colorbox by assigning IDs and setting up load event handlers.
 */
function initialize_colorbox_iframes() {
    if (getRoot("iframe.cboxIframe").length && !getRoot("iframe.cboxIframe").attr("id")) {
        getRoot("iframe.cboxIframe").attr("id", "colorboxiframe");
        getRoot("iframe.cboxIframe").on('load', function () {
            getRoot()[0].resizeCaller("colorboxiframe");
        });
        getRoot("iframe.cboxIframe").trigger('load');
    }
}

/**
 * @function print_r
 * @description Prints an object recursively.
 * @param {object} theObj - The object to print.
 */
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

/**
 * @function loadjs
 * @description Load a JavaScript file and execute a callback when done.
 * @param {string} scriptName - The JavaScript file to load.
 * @param {function} callback - [optional] A callback function to execute when the script is loaded.
 */
function loadjs(scriptName, callback) {
    if (typeof callback == "undefined") {
        // If a callback was not specified, do nothing.
        callback = function() {};
    }
    // Use jQuery's getScript to load the specified script.
    $.getScript(scriptName, callback).fail(function(jqxhr, settings, exception) {
        // If the script fails to load, alert the user.
        alert("Some javascript files failed to load (" + scriptName + "), the page may not work as intended.");
    });
    return;
}

/**
 * Loads a dynamic JavaScript file by inserting it into the page head.
 *
 * @param {string} scriptname - The name of the script to load.
 */
function loaddynamicjs(scriptname) {
    var js = $('#' + scriptname).html();
    // Wait for 2 seconds before continuing.
    setTimeout(function() {
        return false;
    }, 2000);
    var head = document.getElementsByTagName("head")[0];
    var script = document.createElement('script');
    script.id = "dynamicscript";
    script.type = 'text/javascript';
    script.text = js;
    head.appendChild(script);
    // Wait for 100ms before continuing.
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

/**
 * Merges two JSON objects and returns the merged JSON object as a string.
 *
 * @param {string} data1 - The first JSON object to merge.
 * @param {string} data2 - The second JSON object to merge.
 * @returns {string} The merged JSON object as a string.
 */
function mergeJSON(data1, data2) {
    var json1 = JSON.parse(data1);
    var json2 = JSON.parse(data2);
    // Extend the first JSON object with the second JSON object.
    // This will overwrite any duplicate keys with the values from the second object.
    return JSON.stringify($.extend({}, json1, json2));
}

/**
 * Creates a string that represents the form data in the given container.
 * The string is in the format of a query string.
 * @param {string} container - The id or name of the container element.
 * @returns {string} The query string.
 */
function create_request_string(container) {
    var reqStr = "";

    if ($('[name="' + container + '"]').length > 0) { //container has a name
        var $container_id = $('[name="' + container + '"]');
    } else if ($('[id="' + container + '"]').length > 0) {
        var $container_id = $('[id="' + container + '"]');
    } else {
        return "";
    }

    //Loop through all the form elements in the container
    $container_id.find('input,textarea,select').each(
        function() {
            //access to form element via $(this)
            switch (this.tagName) {
                case "INPUT":
                    //Check the type of input field
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
                            //Add the name and value of the text field to the query string
                            reqStr += "&" + this.name + "=" + encodeURIComponent($(this).val());
                            break;
                        case "checkbox":
                            //Check if the checkbox is checked and add the name and value accordingly
                            if (this.checked) {
                                reqStr += "&" + this.name + "=" + $(this).val();
                            } else {
                                reqStr += "&" + this.name + "=";
                            }
                            break;
                        case "radio":
                            //Check if the radio button is checked and add the name and value accordingly
                            if (this.checked) {
                                reqStr += "&" + this.name + "=" + $(this).val();
                            }
                    }
                    break;
                case "TEXTAREA":
                    //Add the name and value of the textarea to the query string
                    reqStr += "&" + this.name + "=" + encodeURIComponent($(this).val());
                    break;
                case "SELECT":
                    //Add the name and value of the select to the query string
                    reqStr += "&" + this.name + "=" + $(this).val();
                    break;
            }
        }
    );
    return reqStr;
}

/**
 * Creates a JSON object that represents the form data in the given container.
 * @param {string} container - The id or name of the container element.
 * @returns {string} The JSON object as a string.
 */
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
    let datetype = new Date(document.getElementById(element).value);
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