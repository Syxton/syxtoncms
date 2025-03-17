var folder = "camp_new";

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
            <p>A PayPal payment button will be displayed after you click on the Send Application button.<br />
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
    var bday = datetype("Camper_Birth_Date");
    var event = datetype("event_begin_date");
    difference = event.getFullYear() - bday.getFullYear() - 1;

    if (event > bday){
        difference++;
    }

    difference = difference > 0 ? difference : 0;
    difference = difference > 110 ? "Yikes!" : difference;

    $("#Camper_Age").val(difference);
}

function updateTotal(){
    var today = parseFloat($("#payment_amount").val());
    var total = parseFloat($("#payment_amount option:last-child").val());
    if ($("#Camper_Picture")) {
        today += parseFloat($("#Camper_Picture").val());
        total += parseFloat($("#Camper_Picture").val());
    }

    if ($("#Camper_Shirt_Size")) {
        if ($("#Camper_Shirt_Size").val() !== "0") {
            today += parseFloat($("#Camper_Shirt_Price").val());
            total += parseFloat($("#Camper_Shirt_Price").val());
        }
    }

    $("#full_payment_amount").html(total);
    $("#owed").val(today);
}

function final_form_prep(){
    $("select[id^='Camper_Birth_Date']").change(function(){ updateAge(); });
    $("select[id^='Camper_Birth_Date']").attr("onKeyUp","updateAge()");
    $("input[id^='Camper_Birth_Date']").change(function(){ updateAge(); });
    $("#Camper_Birth_Date").change(function(){ updateAge(); });
    $("#Camper_Birth_Date").attr("onChange","updateAge()");
    $("input,select,textarea").bind("focus",function(){ $(this).closest(".rowContainer").css("background-color","whitesmoke"); });
    $("input,select,textarea").bind("blur",function(){ $(this).closest(".rowContainer").css("background-color","initial"); });
}

$(function () {
    $("#applycampership").on("click", async function (e) {
        e.preventDefault();

        // Create an array of valid codes.
        var camperships = [
            {
                name: "David Grubb Campership", // myfirstcamp
                code: "c84cfb574f577b90aaa17db7f07e46dbe1ff8aedf5aa2bd00e5fc06f649c3950"
            },
            {
                name: "Eastside Church of Christ Campership", // east25side
                code: "2d4c5f274270d10e755c68721548101342e8d1ffae3f1f16b698a181a39771e7"
            },
            {
                name: "Southside Church of Christ Campership", // south25side
                code: "ebc3cb388af30b668dfcbda70155f22068eaaca6ae74414493e5a9b51491af49"
            },
            {
                name: "Northside Church of Christ Campership", // north25side
                code: "e49bcb9d50fc8ea2f015f2c33825c1f73907809e0580f118f2ca4a658f1a0047"
            },
            {
                name: "Marshall Church of Christ Campership", // marshall25camp
                code: "e97000997a3eb6b5b92bb5c2709422346c703f581c812f5b86468dbbb21a64eb"
            },
            {
                name: "North Meridian Church of Christ Campership", // north25meridian
                code: "e4021044c2facf523a01ca8809fd86e01c9f0df8944460d77d8bcd10d7a52dbd"
            },
            {
                name: "Clay City Church of Christ Campership", // clay25city
                code: "34f5013b8ea8821633e346799b525c9c5f1e4627634472b5c0e7cdc8f7385ed3"
            },
            {
                name: "Mt. Carmel Church of Christ Campership", // mt25carmel
                code: "47d7cb5641702117c08091842ab5b18f764519e3b427a048ff26e0314fda53e8"
            }
        ];

        const code = await sha256($("#campershipcode").val());
        var valid = false;
        // Find code in camperships array.
        for (var i = 0; i < camperships.length; i++) {
            if (code == camperships[i]["code"]) {
                // Found the code, add the name to the form.
                valid = camperships[i]["name"];
                break;
            }
        }

        // Add option to payment_method select and set it as selected.
        if (valid) {
            $("#payment_method option[value='Campership']").remove(); // Remove if it already exists.
            $("#payment_method").append('<option value="Campership">' + valid + '</option>');
            $("#payment_method").val("Campership");
            $("#campership").val(valid);
            $("#campershipresult").html("Successfully Applied Campership.");
        }

        // Remove Campership option if it exists and give message in campershipresult
        if (!valid) {
            $("#payment_method option[value='Campership']").remove();
            console.log("Invalid Campership Code.");
            $("#campershipresult").html("Invalid Campership Code.");
        }
        updateMessage();
    });
});

async function sha256(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return hashHex;
}