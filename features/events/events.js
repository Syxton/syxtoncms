var WWW_ROOT = location.protocol + '//' + location.host;
/* Create a new XMLHttpRequest object to talk to the Web server */
var xmlHttp = false;
/*@cc_on @*/
/*@if (@_jscript_version >= 5)
try {
  xmlHttp = new ActiveXObject("Msxml2.XMLHTTP");
} catch (e) {
  try {
     xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
  } catch (e2) {
     xmlHttp = false;
  }
}
@end @*/
if (!xmlHttp && typeof XMLHttpRequest != 'undefined') {
    xmlHttp = new XMLHttpRequest();
}

function movetonextbox(e) {
    var unicode = e.keyCode ? e.keyCode : e.charCode;
    if (unicode == 8 || unicode == 46) {
        return false;
    }
    if ($("#lasthint").val().match("_1") && $("#" + $("#lasthint").val()).val().length == 3) {
        document.getElementById($("#lasthint").val().replace("_1", "_2")).focus();
    }
    if ($("#lasthint").val().match("_2") && $("#" + $("#lasthint").val()).val().length == 3) {
        document.getElementById($("#lasthint").val().replace("_2", "_3")).focus();
    }
}

function submit_registration(eventid, formlist) {
    if (validate_fields()) {
        var d = new Date();
        var parameters = "action=pick_registration&eventid=" + eventid + "&currTime=" + d.toUTCString();
        var elements = formlist.split("*");
        var i = 0;
        while (elements[i]) {
            elparam = elements[i].split(":");
            if (elparam[0] == "text") {
                parameters += "&" + elparam[1] + "=";
                parameters += $("#" + elparam[1]).val();
            } else if (elparam[0] == "email") {
                parameters += "&" + elparam[1] + "=";
                parameters += $("#" + elparam[1]).val();
                parameters += "&email=" + $("#" + elparam[1]).val();
            } else if (elparam[0] == "contact") {
                parameters += "&" + elparam[1] + "=";
                parameters += $("#" + elparam[1]).val();
                parameters += "&email=" + $("#" + elparam[1]).val();
            } else if (elparam[0] == "phone") {
                parameters += "&" + elparam[1] + "=";
                parameters += $("#" + elparam[1] + "_1").val() + "-" + $("#" + elparam[1] + "_2").val() + "-" + $("#" + elparam[1] + "_3").val();
            } else if (elparam[0] == "payment") {
                if ($("#payment_amount").length) {
                    parameters += "&payment_amount=" + $("#payment_amount").val();
                    parameters += "&payment_method=" + $("#payment_method").val();
                    parameters += $("#total_owed") ? "&total_owed=" + $("#total_owed").val() : "&total_owed=0";
                    parameters += $("#items") ? "&items=" + $("#items").val() : "";
                }
            }
            i++;
        }
        // Build the URL to connect to
        var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
        // Open a connection to the server\
        ajaxpost(url, parameters);
        // Setup a function for the server to run when it's done
        simple_display("registration_div");
    }
}

function clear_limits() {
    $("#limit_form").html("");
    $("#custom_limits").html('<input type="hidden" id="hard_limits" value="" /><input type="hidden" id="soft_limits" value="" />');
}

function get_end_time(starttime) {
    if ($("#begin_time").val() != "") {
        var d = new Date();
        var endtime = $("#end_time").length && $("#end_time").val() != "" ? "&endtime=" + $("#end_time").val() : "";
        var limit = $("#multiday").val() == 1 ? "&limit=0" : "&limit=1";
        var parameters = "action=get_end_time&starttime=" + starttime + endtime + limit + "&currTime=" + d.toUTCString();
        // Build the URL to connect to
        var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
        // Open a connection to the server\
        ajaxpost(url, parameters);
        simple_display("end_time_span");
    }
}

function lookup_reg(code) {
    var d = new Date();
    var parameters = "action=lookup_reg&code=" + code + "&currTime=" + d.toUTCString();
    // Build the URL to connect to
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    // Open a connection to the server\
    ajaxpost(url, parameters);
    simple_display("payarea");
}

function get_limit_form(template_id) {
    var d = new Date();
    var parameters = "action=get_limit_form&template_id=" + template_id + "&currTime=" + d.toUTCString();
    // Build the URL to connect to
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    // Open a connection to the server\
    ajaxpost(url, parameters);
    simple_display("limit_form");
}

function validate_limit() {
    var valid = true;
    if (!$("#custom_limit_value").val().length > 0) {
        $("#custom_limit_value_error").html("You must add a field value.");
        valid = false;
    } else if (!IsNumeric($("#custom_limit_value").val()) && ($("#operators").val() == "gt" || $("#operators").val() == "gteq" || $("#operators").val() == "lt" || $("#operators").val() == "lteq")) {
        $("#custom_limit_value_error").html("Value must be a number.");
        valid = false;
    } else {
        $("#custom_limit_value_error").html("");
    }

    if (!$("#custom_limit_num").val().length > 0) {
        $("#custom_limit_num_error").html("You must add a limit amount.");
        valid = false;
    } else if (!IsNumeric($("#custom_limit_num").val())) {
        $("#custom_limit_num_error").html("Value must be a number.");
        valid = false;
    } else {
        $("#custom_limit_num_error").html("");
    }
    return valid;
}

function add_custom_limit() {
    if (validate_limit()) {
        var d = new Date();
        if ($("#custom_limit_sorh").val() == 0) {
            var hard = $("#hard_limits").length && $("#hard_limits").val() == "" ? $("#custom_limit_fields").val() + ":" + $("#operators").val() + ":" + $("#custom_limit_value").val() + ":" + $("#custom_limit_num").val() : $("#hard_limits").val() + "*" + $("#custom_limit_fields").val() + ":" + $("#operators").val() + ":" + $("#custom_limit_value").val() + ":" + $("#custom_limit_num").val();
            var soft = $("#soft_limits").length ? $("#soft_limits").val() : "";
        } else {
            var soft = $("#soft_limits").length && $("#soft_limits").val() == "" ? $("#custom_limit_fields").val() + ":" + $("#operators").val() + ":" + $("#custom_limit_value").val() + ":" + $("#custom_limit_num").val() : $("#soft_limits").val() + "*" + $("#custom_limit_fields").val() + ":" + $("#operators").val() + ":" + $("#custom_limit_value").val() + ":" + $("#custom_limit_num").val();
            var hard = $("#hard_limits").length ? $("#hard_limits").val() : "";
        }
        var template_id = "&template_id=" + $("#template").val();
        var hard_limits = "&hard_limits=" + hard;
        var soft_limits = "&soft_limits=" + soft;
        var parameters = "action=add_custom_limit" + hard_limits + soft_limits + template_id + "&currTime=" + d.toUTCString();
        // Build the URL to connect to
        var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
        ajaxpost(url, parameters);
        simple_display("custom_limits");
        document.getElementById("limit_form").innerHTML = "";
    }
}

function delete_limit(limit_type, limit_num) {
    var hard_limits = "&hard_limits=" + $("#hard_limits").val();
    var soft_limits = "&soft_limits=" + $("#soft_limits").val();
    var template_id = "&template_id=" + $("#template").val();
    var limit_type = "&limit_type=" + limit_type;
    var limit_num = "&limit_num=" + limit_num;
    var d = new Date();
    var parameters = "action=delete_limit" + hard_limits + soft_limits + template_id + limit_type + limit_num + "&currTime=" + d.toUTCString();
    // Build the URL to connect to
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    // Open a connection to the server\
    ajaxpost(url, parameters);
    simple_display("custom_limits");
}

function copy_location(location, eventid) {
    if (location != "false") {
        var d = new Date();
        var parameters = "action=copy_location&location=" + location + "&eventid=" + eventid + "&currTime=" + d.toUTCString();
        // Build the URL to connect to
        var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
        // Open a connection to the server\
        ajaxpost(url, parameters);
        simple_display("select_location");
        $("#location_status").html("Location Added");
        hide_show_buttons("addtolist");
        hide_show_buttons("hide_menu");
        hide_show_buttons("new_button");
        hide_show_buttons("or");
        hide_show_buttons("location_menu");
        hide_show_buttons("add_location_div");
        setTimeout("clear_display(\'location_status\')", 2000);
    }
}

function get_location_details(location) {
    if (location != "false") {
        var d = new Date();
        var parameters = "action=get_location_details&location=" + location + "&currTime=" + d.toUTCString();
        // Build the URL to connect to
        var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
        // Open a connection to the server\
        ajaxpost(url, parameters);
        simple_display("location_details_div");
    } else {
        clear_display("location_details_div");
    }
}

function valid_new_location() {
    var valid = true;
    if (!$('#location_name').val().length > 0) {
        $("#location_name_error").html("This is a required field.");
        valid = false;
    } else {
        var returnme = { val: false }; // Objects pass by reference to synchronous ajax function.
        is_unique_location_name(returnme);
        valid = returnme.val;
    }

    if (!$('#location_address_1').val().length > 0) {
        $("#location_address_1_error").html("This is a required field.");
        valid = false;
    } else {
        $("#location_address_1_error").html("");
    }

    if (!$('#location_address_2').val().length > 0) {
        $("#location_address_2_error").html("This is a required field.");
        valid = false;
    } else {
        $("#location_address_2_error").html("");
    }

    if (!$("#zip").val().length > 0) {
        $("#zip_error").html("This is a required field.");
        valid = false;
    } else if ($("#zip").val().length < 5) {
        $("#zip_error").html("This is an invalid zipcode.");
        valid = false;
    } else if (!IsNumeric($("#zip").val())) {
        $("#zip_error").html("This is an invalid zipcode.");
        valid = false;
    } else {
        $("#zip_error").html("");
    }

    if ($("#opt_location_phone").val() == 0 || ($("#opt_location_phone").val() != 0 && ($('#location_phone_1').val().length > 0 || $('#location_phone_2').val().length > 0 || $('#location_phone_3').val().length > 0))) {
        //Phone # validity test
        if ($('#location_phone_1').val().length == 3 && $('#location_phone_2').val().length == 3 && $('#location_phone_3').val().length == 4) {
            if (!(IsNumeric($('#location_phone_1').val()) && IsNumeric($('#location_phone_2').val()) && IsNumeric($('#location_phone_3').val()))) {
                $("#location_phone_error").html("Not a valid phone #");
                valid = false;
            } else {
                $("#location_phone_error").html("");
            }
        } else {
            $("#location_phone_error").html("Phone # is not complete.");
            valid = false;
        }
    }
    return valid;
}

function valid_new_event() {
    var valid = true;
    var Today = new Date();
    Today.setHours(0, 0, 0, 0);

    //event name
    if (!$('#event_name').val().length > 0) {
        $("#event_name_error").html("This is a required field.");
        valid = false;
    } else {
        $("#event_name_error").html("");
    }
    //contact name
    if (!$("#contact").val().length > 0) {
        $("#contact_error").html("This is a required field.");
        valid = false;
    } else {
        $("#contact_error").html("");
    }
    //contact email
    if ($("#email").val().length > 0) {
        if (echeck($("#email").val())) {
            $("#email_error").html("");
        } else {
            $("#email_error").html("Email address is not valid.");
            valid = false;
        }
    } else {
        $("#email_error").html("Email address is required.");
        valid = false;
    }
    //contact phone #
    if ($("#phone_1").val().length == 3 && $("#phone_2").val().length == 3 && $("#phone_3").val().length == 4) {
        if (!(IsNumeric($("#phone_1").val()) && IsNumeric($("#phone_2").val()) && IsNumeric($("#phone_3").val()))) {
            $("#phone_error").html("Not a valid phone #");
            valid = false;
        } else {
            $("#phone_error").html("");
        }
    } else {
        $("#phone_error").html("Phone # is not complete.");
        valid = false;
    }
    if ($("#fee").val() == "1") { //Fee = YES
        //min fee
        if (!(IsNumeric($("#min_fee").val()))) {
            $("#event_min_fee_error").html("Must be a numeric value.");
            valid = false;
        } else if (parseInt($("#min_fee").val()) > parseInt($("#full_fee").val())) {
            $("#event_min_fee_error").html("Cannot be greater than full fee.");
            valid = false;
        } else {
            $("#event_min_fee_error").html("");
        }
        //full fee
        if (!(IsNumeric($("#full_fee").val()))) {
            $("#event_full_fee_error").html("Must be a numeric value.");
            valid = false;
        } else if ($("#full_fee").val() == "0") {
            $("#event_full_fee_error").html("Must be greater than 0.");
            valid = false;
        } else {
            $("#event_full_fee_error").html("");
        }
        //sale fee
        if (parseInt($("#sale_fee").val()) != 0) {
            if (!(IsNumeric($("#sale_fee").val()))) {
                $("#event_sale_fee_error").html("Must be a numeric value.");
                valid = false;
            } else {
                $("#event_sale_fee_error").html("");
            }
            let Compare = datetype("start_reg");
            if (Compare < Today) {
                $("#sale_end_error").html('Cannot select a date in the past.');
                valid = false;
            } else {
                $("#sale_end_error").html("");
            }
        }
        //payable to
        if ($("#payableto").val() == "") {
            $("#event_payableto_error").html("This is a required field.");
            valid = false;
        } else {
            $("#event_payableto_error").html("");
        }
        //checksaddress to
        if ($("#checksaddress").val() == "") {
            $("#event_checksaddress_error").html("This is a required field.");
            valid = false;
        } else {
            $("#event_checksaddress_error").html("");
        }
        //paypal
        if ($("#paypal").val() != "" && !echeck($("#paypal").val())) {
            $("#event_paypal_error").html("This is not a valid email address.");
            valid = false;
        } else {
            $("#event_paypal_error").html("");
        }
    }
    //event location
    if ($("#location").length) {
        $("#location_error").html("");
    } else {
        $("#location_error").html("Add a location for your event.");
        valid = false;
    }
    //multiday event
    //event_begin_date
    let Compare = datetype("event_begin_date");
    if (Compare < Today) {
        $("#event_begin_date_error").html('Cannot select a date in the past.');
        valid = false;
    } else {
        $("#event_begin_date_error").html("");
    }
    if ($("#multiday").val() == "1") { //Multi day = YES
        //event_end_date
        let Compare1 = datetype("event_end_date");
        let Compare2 = datetype("event_begin_date");
        if (Compare1 <= Compare2) {
            $("#event_end_date_error").html('Must select a date after the event start date');
            valid = false;
        } else if (Compare1 < Today) {
            $("#event_end_date_error").html('Cannot select a date in the past.');
            valid = false;
        } else {
            $("#event_end_date_error").html("");
        }
    }
    if ($("#allday").val() == "0") { //All day = NO
        //begin time
        if ($("#begin_time").val() == "") {
            $("#time_error").html("You must select a start time.");
            valid = false;
        } else if ($("#end_time").val() == "") {
            $("#time_error").html("You must select an end time.");
            valid = false;
        } else {
            $("#time_error").html("");
        }
    }
    if ($("#reg").val() == "1") { //Registration = YES
        if ($("#template").val() < 1) {
            $("#template_error").html("Template must be selected.");
            valid = false;
        }
        if ($("#max").val() == "") {
            $("#max").val("0");
        }
        if ($("#limits").val() == "1") {
            //max reg
            if (!IsNumeric($("#max").val())) {
                $("#max_error").html("Must be a numeric value.");
                valid = false;
            } else {
                $("#max_error").html("");
            }
        }
        //registration dates
        //start_reg
        let Compare1 = datetype("start_reg");
        let Compare2 = datetype("event_begin_date");
        let Compare3 = datetype("stop_reg");
        if (Compare1 < Today && !$("#eventid").length) {
            $("#start_reg_error").html('Cannot select a date in the past.');
            valid = false;
        } else if (Compare1 >= Compare2) {
            $("#start_reg_error").html('Registration must start and end before event starts.');
            valid = false
        } else {
            $("#start_reg_error").html("");
        }
        //stop_reg
        if (Compare3 < Today && !$("#eventid").length) {
            $("#stop_reg_error").html('Cannot select a date in the past.');
            valid = false;
        } else if (Compare3 <= Compare1) {
            $("#stop_reg_error").html('Must be a date after registration start.');
            valid = false
        } else if (Compare3 > Compare2) {
            $("#stop_reg_error").html('Registration must start and end before event starts.');
            valid = false
        } else {
            $("#stop_reg_error").html("");
        }
    }
    return valid;
}

function new_event_submit(pageid) {
    if (valid_new_event()) {
        var d = new Date();
        var eventid = $('#eventid').val() ? '&eventid=' + $('#eventid').val() : ''; //Event id if update event
        var event_name = "&event_name=" + encodeURIComponent($('#event_name').val()); //Event name
        var contact = "&contact=" + encodeURIComponent($('#contact').val()); //Contacts name
        var email = "&email=" + encodeURIComponent($('#email').val()); //Contacts email
        var phone = "&phone=" + $('#phone_1').val() + "-" + $('#phone_2').val() + "-" + $('#phone_3').val(); //Event name
        var byline = "&byline=" + encodeURIComponent($('#byline').val()); //Event byline
        var description = "&description=" + encodeURIComponent($('#editor1').val()); //Event byline
        var siteviewable = "&siteviewable=" + $('#siteviewable').val(); //If event is viewable on front page
        var location = "&location=" + $('#location').val(); //Where event is located
        var category = "&category=" + $('#category').val(); //Event category (birthday, aniversary..)
        var multiday = "&multiday=" + $("#multiday").val(); //If the event is more than 1 day
        var allday = "&allday=" + $("#allday").val(); //all day event?
        var workers = "&workers=" + $("#workers").val(); //all day event?
        var event_begin_date = "&event_begin_date=" + $("#event_begin_date").val(); //when event begins
        var event_end_date = $("#multiday").val() == "1" ? "&event_end_date=" + $("#event_end_date").val() : ""; //when event ends
        var begin_time = $("#allday").val() == "1" ? "" : "&begin_time=" + $("#begin_time").val(); //If not an all day event, when does it begin
        var end_time = $("#allday").val() == "1" ? "" : "&end_time=" + $("#end_time").val(); //If not an all day event, when does it end
        let hard_limits = soft_limits = max = fee = min_fee = full_fee = sale_fee = sale_end = checksaddress = payableto = paypal = allowinpage = template = template_settings = start_reg = stop_reg = "";
        if ($("#reg").val() == "1") {
            allowinpage = "&allowinpage=" + $("#allowinpage").val(); //If a logged in user registers...allow them into the page that this event was created in.
            template = "&template=" + $("#template").val(); //registration template
            template_settings = create_request_string('template_settings_form');
            start_reg = "&start_reg=" + $("#start_reg").val(); //Registration open date
            stop_reg = "&stop_reg=" + $("#stop_reg").val(); //Registration ending date
            fee = "&fee=" + $("#fee").val(); //Are there fees associated with this reg page?
            min_fee = "&min_fee=" + $("#min_fee").val(); //minimum amount needed to pay to register
            full_fee = "&full_fee=" + $("#full_fee").val(); //full payment for registration
            sale_fee = "&sale_fee=" + $("#sale_fee").val(); //temporary sale payment
            sale_end = $("#sale_fee").val() != "" ? "&sale_end=" + $("#sale_end").val() : ""; //when temporary sale price ends
            checksaddress = "&checksaddress=" + encodeURIComponent($("#checksaddress").val()); //Address to send checks to
            payableto = "&payableto=" + encodeURIComponent($("#payableto").val()); //Make checks payable to
            paypal = "&paypal=" + $("#paypal").val(); //Paypal account

            if ($("#limits").val() == "1") {
                max = "&max=" + $("#max").val(); //Maximum registrations HARD
                hard_limits = $("#hard_limits").length && $("#hard_limits").val() != "" ? "&hard_limits=" + $("#hard_limits").val() : ""; //custom limits that keep people from registering
                soft_limits = $("#soft_limits").length && $("#soft_limits").val() != "" ? "&soft_limits=" + $("#soft_limits").val() : ""; //custom limits that place people in queue
            }
        }
        var reg = "&reg=" + $("#reg").val(); // Event has a registration page

        var parameters = "action=submit_new_event&pageid=" + pageid + workers + email + contact + phone + fee + min_fee + full_fee + sale_fee + sale_end + hard_limits + soft_limits + checksaddress + payableto + paypal + eventid + event_name + category + byline + description + siteviewable + location + multiday + template + event_begin_date + event_end_date + allday + begin_time + end_time + reg + allowinpage + max + start_reg + stop_reg + template_settings + "&currTime=" + d.toUTCString();
        // Build the URL to connect to
        var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
        // Open a connection to the server\
        ajaxpost(url, parameters);
        // Setup a function for the server to run when it's done
        simple_display("add_event_div");
        close_modal();
    }
    return false;
}

function clear_display(divname) {
    if ($("#" + divname).length) {
        $('#' + divname).html("");
    }
}

function init_event_menu() {
    $('#event_menu_button').click(function(event) {
        event.stopPropagation();
        $('#event_menu').toggle();
    });

    $(document).click(function() {
        $('#event_menu').hide();
    });
}