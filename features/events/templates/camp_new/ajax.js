var folder = "camp_new";

function submit_camp_new_registration(){
    var reqstring = create_request_string('form1');
    ajaxapi_old("/features/events/templates/" + folder + "/backend.php",'register',reqstring,function(){ simple_display('registration_div'); });
}

function show_form_again(eventid, regid, autofill){
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events.php?action=show_registration&i=!&v=!&total_owed="+$("#total_owed").val()+"&items="+$("#items").val()+"&eventid=" + eventid + "&regid=" + regid + "&show_again=1&autofill=" + autofill;
    window.location=url;
}

function updateMessage(){
    var message = "After you select a payment method, you can put a message here.\n\nDo you have a cabin preference or cabin-mates?\nDo you have a question for the director?";
    $("#campershiprow").hide();
    if(document.form1.payment_method.selectedIndex == 1) {
        $(".costinfo").show();
        message = "We use PayPal to process payments for our online registration. ";
        message += "Your credit card or bank account information is not transmitted to us. ";
        message += "You do not need a PayPal account to make payment using most major credit cards, ";
        message += "but we do encourage you to become a verified member. ";
        message += "A PayPal payment button will be displayed after you click on the Send Application button.\n\n";
        message += "PayPal timeout issues can be avoided by clearing your temporary internet files and cookies ";
        message += "and adding https://www.paypal.com/ to your list of trusted sites.";
    }

    if(document.form1.payment_method.selectedIndex == 2) {
        $(".costinfo").not(".paywithapp").show();
        $(".paywithapp").hide();
        message = "Make the check or money order out to: Camp Wabashi\n\n";
        message = message + "Please write the name of the camper in the memo field and send it to:\n\n";
        message = message + "3525 East Harlan Drive\n";
        message = message + "Terre Haute, IN 47802";
    }

    if(document.form1.payment_method.selectedIndex == 3) {
        $("#campershiprow").show();
        $(".costinfo").hide();
        message = "Please be sure to name the congregation/organization that is funding your campership in the box above.\n\n";
        message = message + "To request a campership, type \"David Grubb Campership Fund\"\n\n";
    }
    document.form1.payment_note.value = message;
    return true;
}

function updateAge() {
	var bday = datetype("Camper_Birth_Date");
	var event = datetype("event_begin_date");
	difference = event.getFullYear() - bday.getFullYear() - 1;

	if(event > bday){
		difference++;
	}

	difference = difference > 0 ? difference : 0;
	difference = difference > 110 ? "Yikes!" : difference;

	$("#Camper_Age").val(difference);
	$("#Camper_Age").focus();$("#Camper_Age").blur();
}

function updateTotal(){
    var total = parseFloat($("#payment_amount").val());
    if($("#Camper_Picture")){
        total += parseFloat($("#Camper_Picture").val());
    }

    if($("#Camper_Shirt_Size")){
        if($("#Camper_Shirt_Price").val() != "0"){ total += parseFloat($("#Camper_Shirt_Price").val()); }
    }
    $("#owed").val(total);
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