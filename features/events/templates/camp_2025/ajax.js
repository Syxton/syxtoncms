var folder = "camp_2025";

function show_form_again(eventid, regid, autofill){
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events.php?action=show_registration&i=!&v=!&total_owed="+$("#total_owed").val()+"&items="+$("#items").val()+"&eventid=" + eventid + "&regid=" + regid + "&show_again=1&autofill=" + autofill;
    window.location=url;
}

function updateMessage(){
    var message = "Please select a payment method. <br />If you have a campership code, enter it and click 'Apply'.";
    const paymentMethod = $("select[name='payment_method']").val(); // Get the selected value using jQuery

    if (paymentMethod == 'PayPal') {
        $(".costinfo").show();
        message = `
            <p>We use PayPal to process payments for our online registration.</p>
            <p>Your credit card or bank account information is not transmitted to us.<br />
            You do not need a PayPal account to make payment using most major credit cards, <br />
            however we do encourage you to become a verified member.</p>
            <p>A PayPal payment button will be displayed after you Submit your application.<br />
            PayPal timeout issues can be avoided by clearing your temporary internet files and cookies
             and adding <a href="https://www.paypal.com/" target="_blank">https://www.paypal.com/</a> to your list of trusted sites.</p>
        `;
    }

    if (paymentMethod == 'Pay Later') {
        $(".costinfo").not(".paywithapp").show();
        $(".paywithapp").hide();
        message = `
            <p>You have chosen to make a payment at a later date. You can make payments online at
            any time using the payment link in your registration confirmation email,
            or you can pay at the time of the event. (Additional fees may apply)</p>
            <p>You may also pay by check or money order.</p>
            <p>Please write the name of the camper in the memo field and send checks to:</p>
            <p><strong>Camp Wabashi</strong><br>3525 East Harlan Drive<br>Terre Haute, IN 47802</p>
        `;
    }

    if (paymentMethod == 'Campership') {
        $(".costinfo").hide();
        message = `<p>Your registration will be paid by the applied campership.</p>`;
    }

    $("#payment_note").html(message);

    return true;
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

function updateTotal(){
    var today = parseFloat($("#payment_amount").val());
    var total = parseFloat($("#payment_amount option:last-child").val());

    if ($("#camper_picture")) {
        today += parseFloat($("#camper_picture").val());
        total += parseFloat($("#camper_picture").val());
    }

    if ($("#camper_shirt_size")) {
        if ($("#camper_shirt_size").val() !== "0") {
            today += parseFloat($("#camper_shirt_price").val());
            total += parseFloat($("#camper_shirt_price").val());
        }
    }

    $("#full_payment_amount").html(total);
    $("#owed").val(today);
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
    $("#payment_method").on("change", function () { updateMessage(); });
    $("#payment_method").on("click", function () { updateMessage(); });
    $("#payment_amount, #camper_picture, #camper_shirt_size").on("change", function () { updateTotal(); });
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

function calculate_payment_amount() {
    var amount = 0;

    $(".payment_amounts").each(function () {
        amount += parseFloat($(this).val());
    });
    console.log(amount);
    $("#payment_amount").val(amount.toFixed(2));
}