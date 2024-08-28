var WWW_ROOT = location.protocol + '//' + location.host;

function movetonextbox(e) {
    var unicode = e.keyCode ? e.keyCode : e.charCode;
    if (unicode == 8 || unicode == 46) {
        return false;
    }

    if ($(e.srcElement)[0].id.match("_1") && $(e.srcElement).val().length == 3) {
        $("#" + $(e.srcElement)[0].id.replace("_1", "_2")).focus();
    }
    if ($(e.srcElement)[0].id.match("_2") && $(e.srcElement).val().length == 3) {
        $("#" + $(e.srcElement)[0].id.replace("_2", "_3")).focus();
    }
}

function submit_registration(eventid, formlist) {
    if (validate_fields()) {
        // Build the URL to connect to
        var d = new Date();
        var parameters = "action=pick_registration&eventid=" + eventid + "&ajaxapi=true&currTime=" + d.toUTCString();
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

        $.ajax({
            async: false,
            type: 'post',
            url: WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php",
            dataType: 'json',
            data: parameters,
        }).done(function (data) {
            jq_display("registration_div", data);
        });
    }
}

function clear_limits() {
    $("#limit_form").html("");
    $("#custom_limits").html('<input type="hidden" id="hard_limits" name="hard_limits" value="" /><input type="hidden" id="soft_limits" name="soft_limits" value="" />');
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
        let d = new Date();
        if (!$("#custom_limit_sorh").val()) {
            let hard = $("#hard_limits").length && $("#hard_limits").val() == "" ? $("#custom_limit_fields").val() + ":" + $("#operators").val() + ":" + $("#custom_limit_value").val() + ":" + $("#custom_limit_num").val() : $("#hard_limits").val() + "*" + $("#custom_limit_fields").val() + ":" + $("#operators").val() + ":" + $("#custom_limit_value").val() + ":" + $("#custom_limit_num").val();
            let soft = $("#soft_limits").length ? $("#soft_limits").val() : "";
        } else {
            let soft = $("#soft_limits").length && $("#soft_limits").val() == "" ? $("#custom_limit_fields").val() + ":" + $("#operators").val() + ":" + $("#custom_limit_value").val() + ":" + $("#custom_limit_num").val() : $("#soft_limits").val() + "*" + $("#custom_limit_fields").val() + ":" + $("#operators").val() + ":" + $("#custom_limit_value").val() + ":" + $("#custom_limit_num").val();
            let hard = $("#hard_limits").length ? $("#hard_limits").val() : "";
        }
        let template_id = "&template_id=" + $("#template").val();
        let hard_limits = "&hard_limits=" + hard;
        let soft_limits = "&soft_limits=" + soft;

        $.ajax({
            async: false,
            type: 'post',
            url: WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php",
            dataType: 'json',
            data: "action=add_custom_limit" + hard_limits + soft_limits + template_id + "&ajaxapi=true&currTime=" + d.toUTCString(),
        }).done(function (data) {
            jq_display("custom_limits", data);
        });

        $("#limit_form").html("");
    }
}

function reset_location_menu() {
    $('#addtolist').removeClass('hidden');
    $('#location_menu,#add_location_div, #hide_menu').addClass('hidden');
    $('#new_button, #or, #browse_button').removeClass('invisible');
}

function valid_new_location() {
    var valid = true;
    if (!$('#location_name').val().length > 0) {
        $("#location_name_error").html("This is a required field.");
        valid = false;
    } else {
        $('#location_name_error').html('');
        let data = is_unique_location_name();
        if (!istrue(data)) {
            $('#location_name_error').html('This value already exists in our database.');
            valid = false;
        }
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
        if (isValidEmail($("#email").val())) {
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
        if ($("#paypal").val() != "" && !isValidEmail($("#paypal").val())) {
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