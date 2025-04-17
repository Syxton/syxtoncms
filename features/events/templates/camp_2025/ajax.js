var folder = "camp_2025";

function show_form_again(eventid, regid, autofill){
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events.php?action=show_registration&i=!&v=!&eventid=" + eventid + "&regid=" + regid + "&show_again=1&autofill=" + autofill;
    window.location=url;
}

function updateAge() {
    var bday = datetype("camper_birth_date");
    var event = datetype("event_begin_date");
    difference = event.getFullYear() - bday.getFullYear() - 1;

    if (event > bday){
        difference++;
    }

    difference = difference > 0 ? difference : 0;
    difference = difference > 110 ? "Yikes!" : difference;

    $("#camper_age").val(difference);
}

function resetRegistration() {
    if (confirm('Are you sure you want to reset the application?')) {
        if ($(".formSection").length !== 0) {
            $("input:not([type=hidden]).error, textarea.error, select.error", ".formSection").first().parents(".formSection").addClass("selectedSection").siblings().removeClass("selectedSection");
            let sectioncount = $(".formSection").length;
            let firstSection = $(".formSection").first().index();
            let currentSection = $(".selectedSection").index() - firstSection + 1;

            $(".formMenu").html("Section " + currentSection + " of " + sectioncount);
            $(".displayOnFinalSection").hide();
            if (currentSection === sectioncount) {
                $(".displayOnFinalSection").show();
            }
            $("input:not([type=hidden]).error, textarea.error, select.error", ".formSection").first().focus();
        }
        return true;
    }

    return false;
}

function calculate_payment_amount() {
    var amount = 0;

    $(".payment_amounts").each(function () {
        amount += parseFloat($(this).val());
    });
    console.log(amount);
    $("#payment_amount").val(amount.toFixed(2));
}

function final_form_prep() {
    $("#camper_birth_date").on("change", function () { updateAge(); });

    $("input:not([type=hidden]).error, textarea.error, select.error", ".formSection").bind("focus", function () {
        $(this).closest(".rowContainer").css("background-color", "whitesmoke");
    });
    $("input:not([type=hidden]).error, textarea.error, select.error", ".formSection").bind("blur", function () {
        $(this).closest(".rowContainer").css("background-color", "white");
    });
}

$(function () {
    $(".registration_cart_menu").on("click", function () {
        if ($('#count_in_cart').val() > 0) {
            $(".registration_cart").toggle();
            return;
        }
        $(".registration_cart").hide();
     });

    final_form_prep();
});

async function sha256(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return hashHex;
}