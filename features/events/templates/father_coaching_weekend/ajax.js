var folder = "father_coaching_weekend";

function submit_father_coaching_weekend_registration(){
    //Hide form during validation
    document.form1.style.display = 'none';
    var reqstring = create_request_string('form1');
    ajaxapi("/features/events/templates/" + folder + "/backend.php",'register',reqstring,function(){ $("#camp").html(""); simple_display('registration_div'); document.form1.style.display = 'block'; });
}

function show_form_again(eventid, regid, autofill){
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events.php?action=show_registration&i=!&v=!&total_owed="+$("#total_owed").val()+"&items="+$("#items").val()+"&eventid=" + eventid + "&regid=" + regid + "&show_again=1&autofill=" + autofill;
    window.location=url;
}

function updateMessage(){
    var message = "After you select a payment method, you can put a message here.\n\nDo you have a cabin preference or cabin-mates?\nDo you have a question for the director?";
    if(document.form1.payment_method.selectedIndex == 1){
        message = "We use PayPal to process payments for our online registration. ";
        message += "Your credit card or bank account information is not transmitted to us. ";
        message += "You do not need a PayPal account to make payment using most major credit cards, ";
        message += "but we do encourage you to become a verified member. ";
        message += "A PayPal payment button will be displayed after you click on the Send Application button.\n\n";
        message += "PayPal timeout issues can be avoided by clearing your temporary internet files and cookies ";
        message += "and adding https://www.paypal.com/ to your list of trusted sites.";
    }

    if(document.form1.payment_method.selectedIndex == 2){
        message = "Make the check or money order out to: Camp Wabashi\n\n";
        message = message + "Please write the name of the camper in the memo field and send it to:\n\n";
        message = message + "3525 East Harlan Drive\n";
        message = message + "Terre Haute, IN 47802";
    }
    document.form1.payment_note.value = message;
    return true;
}

function updateTotal(){
    var total = parseFloat($("#payment_amount").val());
    $("#owed").val(total);
}

function final_form_prep(){
    $("input,select,textarea").bind("focus",function(){ $(this).closest(".rowContainer").css("background-color","whitesmoke"); });
    $("input,select,textarea").bind("blur",function(){ $(this).closest(".rowContainer").css("background-color","initial"); });
}