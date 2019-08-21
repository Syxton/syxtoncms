var folder = "simple_contact_form";

function submit_simple_contact_form_registration(){
    //Hide form during validation
    document.form1.style.display = 'none';
    var reqstring = create_request_string('form1');
    ajaxapi("/features/events/templates/" + folder + "/backend.php",'register',reqstring,function(){ $("#camp").html(""); simple_display('registration_div');});
}

function show_form_again(eventid, regid, autofill){
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events.php?action=show_registration&i=!&v=!&total_owed="+$("#total_owed").val()+"&items="+$("#items").val()+"&eventid=" + eventid + "&regid=" + regid + "&show_again=1&autofill=" + autofill;
    window.location=url;
}

function updateTotal(){
    var total = parseFloat($("#payment_amount").val());
    $("#owed").val(total);
}

function final_form_prep(){
    $("input,select,textarea").bind("focus",function(){ $(this).closest(".rowContainer").css("background-color","whitesmoke"); });
    $("input,select,textarea").bind("blur",function(){ $(this).closest(".rowContainer").css("background-color","initial"); });
}