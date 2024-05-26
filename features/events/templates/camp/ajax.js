var WWW_ROOT = location.protocol + '//' + location.host;
var folder = "camp";
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

if(!xmlHttp && typeof XMLHttpRequest != 'undefined'){ xmlHttp = new XMLHttpRequest(); }

function camp_submit_registration(){
    //Hide form during validation
    document.form1.style.display = 'none';
	if(validate()){
	var reqstring = create_request_string('form1');
        // Build the URL to connect to
        var url = WWW_ROOT + dirfromroot + "/features/events/templates/" + folder + "/backend.php";
        var d = new Date();
        var parameters = "action=register&currTime=" + d.toUTCString() + reqstring;
        
        xmlHttp.open('POST', url, false);
        xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xmlHttp.setRequestHeader("Content-length", parameters.length);
        xmlHttp.setRequestHeader("Connection", "close");
        xmlHttp.send(parameters);
        
        // Setup a function for the server to run when it's done
        document.getElementById("camp").innerHTML = "";
        simple_display("registration_div");
		}
    //Show form
    document.form1.style.display = 'block';    
}

function show_form_again(eventid, regid, autofill){
    // Build the URL to connect to
    var url = WWW_ROOT + dirfromroot + "/features/events/templates/" + folder + "/backend.php";
    var d = new Date();
    var parameters = "action=show_form_again&currTime=" + d.toUTCString() + "&eventid=" + eventid + "&regid=" + regid + "&show_again=1&autofill=" + autofill;
    
    xmlHttp.open('POST', url, false);
    xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xmlHttp.setRequestHeader("Content-length", parameters.length);
    xmlHttp.setRequestHeader("Connection", "close");
    xmlHttp.send(parameters);
    
    // Setup a function for the server to run when it's done
    display_backup("camp","backup");
    document.getElementById("registration_div").innerHTML = "";
}

function updateMessage(){
    var message = "";
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
        message = "Print this completed form and mail with check to:\n\n";
        message = message + "Camp Wabashi\n";
        message = message + "3525 East Harlan Drive\n";
        message = message + "Terre Haute, IN 47802";
    }
    document.form1.payment_note.value = message;
    return true;
}

function updateTotal(){
    var total = parseFloat(document.form1.payment_amount.value);
    if(document.form1.Camper_Picture && document.form1.Camper_Picture[0].checked){
        total += parseFloat(document.form1.Camper_Picture[0].value); 
    }
    document.form1.paypal_amount.value = total;
}

function checkLength(str){
    if(str.length > 0){ return 0; }
    return -1;
}

function checkEmail(str){
    if(str.indexOf("@") > -1 && str.indexOf(".") > -1 && str.lastIndexOf(".") > str.lastIndexOf("@")){ return 0; }
    return -1;
}

function checkNum(str){
    for(i=0; i<str.length; i++){
        switch(str.charAt(i)){
            case "0" : case "1" :
            case "2" : case "3" :
            case "4" : case "5" :
            case "6" : case "7" :
            case "8" : case "9" :
            case "." : case "-" :
            case "/" : case "-" :
            case "(" : case ")" :
            break;
            default :
            return -1;
        }
    }
    return checkLength(str);
}

function validate(){
    updateTotal();
   
    if(checkEmail(document.form1.email.value) == -1){
        alert("Please enter a valid e-mail address.");
        document.form1.email.focus();
        return false;
    }
    if(checkLength(document.form1.Camper_Name.value) == -1){
        alert("Please enter camper's full name.");
        document.form1.Camper_Name.focus();
        return false;
    }
    if(checkNum(document.form1.Camper_Birth_Date.value) == -1){
        alert("Please enter camper's date of birth.");
        document.form1.Camper_Birth_Date.focus();
        return false;
    }
    if(checkNum(document.form1.Camper_Age.value) == -1){
        alert("Please enter camper's age as of camp week.");
        document.form1.Camper_Age.focus();
        return false;
    }
    if(checkNum(document.form1.Camper_Grade.value) == -1){
        alert("Please enter camper's last grade completed as of camp week.");
        document.form1.Camper_Grade.focus();
        return false;
    }
    if(document.form1.Camper_Gender.selectedIndex == 0){
        alert("Please select whether the campers is a male or female.");
        document.form1.Camper_Gender.focus();
        return false;
    }
    if(document.form1.mailfrom && checkEmail(document.form1.mailfrom.value) == -1){
        if(document.form1.mailfrom.value == ""){ document.form1.mailfrom.value = document.form1.email.value
        }else{
            alert("Please enter a valid e-mail address for parent or guardian.");
            document.form1.mailfrom.focus();
            return false;
        }
    }
    if(document.form1.namefrom && checkLength(document.form1.namefrom.value) == -1){
        alert("Please enter full name of parent or guardian.");
        document.form1.namefrom.focus();
        return false;
    }
    if(checkLength(document.form1.Parent_Address_Line1.value) == -1){
        alert("Please enter mailing address of parent or guardian.");
        document.form1.Parent_Address_Line1.focus();
        return false;
    }
    if(checkLength(document.form1.Parent_Address_City.value) == -1){
        alert("Please enter mailing address city of parent or guardian.");
        document.form1.Parent_Address_City.focus();
        return false;
    }
    if(checkLength(document.form1.Parent_Address_State.value) == -1){
        alert("Please enter mailing address state abbreviation of parent or guardian.");
        document.form1.Parent_Address_State.focus();
        return false;
    }
    if(checkNum(document.form1.Parent_Address_Zipcode.value) == -1){
        alert("Please enter mailing address zipcode for parent or guardian.");
        document.form1.Parent_Address_Zipcode.focus();
        return false;
    }
    if(checkNum(document.form1.Parent_Phone1.value) == -1){
        alert("Please enter a primary contact phone number for parent or guardian.");
        document.form1.Parent_Phone1.focus();
        return false;
    }
    if(checkNum(document.form1.Parent_Phone2.value) == -1){
        alert("Please enter a secondary contact phone number for parent or guardian.");
        document.form1.Parent_Phone2.focus();
        return false;
    }
    if(checkNum(document.form1.HealthConsentFrom.value) == -1){
        alert("Please enter the beginning date of this consent.");
        document.form1.HealthConsentFrom.focus();
        return false;
    }
    if(checkNum(document.form1.HealthConsentTo.value) == -1){
        alert("Please enter the ending date of this consent.");
        document.form1.HealthConsentTo.focus();
        return false;
    }
    if(checkLength(document.form1.HealthMemberName.value) == -1){
        alert("Please enter the primary name on the medical insurance policy.");
        document.form1.HealthMemberName.focus();
        return false;
    }
    if(checkLength(document.form1.HealthRelationship.value) == -1){
        alert("Please describe the relationship between the primary and camper.");
        document.form1.HealthRelationship.focus();
        return false;
    }
    if(checkLength(document.form1.HealthInsurance.value) == -1){
        alert("Please enter the name of the medical insurance carrier.");
        document.form1.HealthInsurance.focus();
        return false;
    }
    if(checkNum(document.form1.HealthExpirationDate.value) == -1){
        alert("Please enter the expiration date of the medical policy.");
        document.form1.HealthExpirationDate.focus();
        return false;
    }
    if(checkNum(document.form1.HealthTetanusDate.value) == -1){
        alert("Please enter the date of the camper's last tetanus shot.");
        document.form1.HealthTetanusDate.focus();
        return false;
    }
    if(document.form1.payment_method.selectedIndex == 0){
        alert("Please select a method of payment.");
        document.form1.payment_method.focus();
        return false;
    }
    return true;
}

//Replace current list of events
function exit_display(pageid){
	self.parent.close_modal();
	self.parent.go_to_page(pageid);
}
function trim(stringToTrim){
	return stringToTrim.replace(/^\s+|\s+$/g,"");
}
function ltrim(stringToTrim) {
	return stringToTrim.replace(/^\s+/,"");
}
function rtrim(stringToTrim) {
	return stringToTrim.replace(/\s+$/,"");
}