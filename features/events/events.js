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
if(!xmlHttp && typeof XMLHttpRequest != 'undefined'){ xmlHttp = new XMLHttpRequest(); }
function movetonextbox(e){
    var unicode=e.keyCode? e.keyCode : e.charCode;
    if(unicode == 8 || unicode == 46){ return false; }
    if(document.getElementById("lasthint").value.match("_1") && document.getElementById(document.getElementById("lasthint").value).value.length == 3){ document.getElementById(document.getElementById("lasthint").value.replace("_1","_2")).focus(); }
    if(document.getElementById("lasthint").value.match("_2") && document.getElementById(document.getElementById("lasthint").value).value.length == 3){ document.getElementById(document.getElementById("lasthint").value.replace("_2","_3")).focus();}
}
function submit_registration(eventid,formlist){
	if(validate_fields()){
    	var d = new Date();
    	var parameters = "action=pick_registration&eventid=" + eventid + "&currTime=" + d.toUTCString();
    	var elements = formlist.split("*");
    	var i = 0;
    	while(elements[i]){
    		elparam = elements[i].split(":");
    		if(elparam[0] == "text"){ 
    			parameters += "&" + elparam[1] + "=";
    			parameters += document.getElementById(elparam[1]).value;
    		}else if(elparam[0] == "email"){
    			parameters += "&" + elparam[1] + "=";
    			parameters += document.getElementById(elparam[1]).value;
    			parameters += "&email=" + document.getElementById(elparam[1]).value;
    		}else if(elparam[0] == "contact"){
    			parameters += "&" + elparam[1] + "=";
    			parameters += document.getElementById(elparam[1]).value;
    			parameters += "&email=" + document.getElementById(elparam[1]).value;
    		}else if(elparam[0] == "phone"){
    				parameters += "&" + elparam[1] + "=";
    				parameters += document.getElementById(elparam[1]+"_1").value + "-" + document.getElementById(elparam[1]+"_2").value + "-" + document.getElementById(elparam[1]+"_3").value;
    		}else if(elparam[0] == "payment"){
    			if(document.getElementById("payment_amount")){
    				parameters += "&payment_amount=" + document.getElementById("payment_amount").value;
    				parameters += "&payment_method=" + document.getElementById("payment_method").value;
    				parameters += document.getElementById("total_owed") ? "&total_owed=" + document.getElementById("total_owed").value : "&total_owed=0";
    				if(document.getElementById("items")) parameters += "&items=" + document.getElementById("items").value;
    			}
    		}
    		i++;
    	}
    	// Build the URL to connect to
      	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    	// Open a connection to the server\
    	ajaxpost(url,parameters);
    	// Setup a function for the server to run when it's done
      	simple_display("registration_div");
  	}
}
function clear_limits(){
	document.getElementById("limit_form").innerHTML = "";
	document.getElementById("custom_limits").innerHTML = '<input type="hidden" id="hard_limits" value="" /><input type="hidden" id="soft_limits" value="" />';
}
function get_end_time(starttime){
	if(document.getElementById("begin_time").value != ""){
    	var d = new Date();
    	var endtime = document.getElementById("end_time") && document.getElementById("end_time").value != "" ? "&endtime=" + document.getElementById("end_time").value : "";
    	var limit = document.getElementById("multiday").value == 1 ? "&limit=0" : "&limit=1";
    	var parameters = "action=get_end_time&starttime=" + starttime + endtime + limit + "&currTime=" + d.toUTCString();
    	// Build the URL to connect to
      	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    	// Open a connection to the server\
    	ajaxpost(url,parameters);
    	simple_display("end_time_span");
	}
}
function lookup_reg(code){
	var d = new Date();
	var parameters = "action=lookup_reg&code=" + code + "&currTime=" + d.toUTCString();
	// Build the URL to connect to
  	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
	// Open a connection to the server\
	ajaxpost(url,parameters);
	simple_display("payarea");
}
function get_limit_form(template_id){
	var d = new Date();
	var parameters = "action=get_limit_form&template_id=" + template_id + "&currTime=" + d.toUTCString();
	// Build the URL to connect to
  	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
	// Open a connection to the server\
	ajaxpost(url,parameters);
	simple_display("limit_form");
}
function validate_limit(){
    var valid = true;
	if(!document.getElementById("custom_limit_value").value.length > 0){
  		document.getElementById("custom_limit_value_error").innerHTML = "You must add a field value.";
  		valid = false;
  	}else if(!IsNumeric(document.getElementById("custom_limit_value").value) && (document.getElementById("operators").value == "gt" || document.getElementById("operators").value == "gteq" ||	document.getElementById("operators").value == "lt" || document.getElementById("operators").value == "lteq")){
  		document.getElementById("custom_limit_value_error").innerHTML = "Value must be a number.";
  		valid = false;
  	}else{ document.getElementById("custom_limit_value_error").innerHTML = ""; }
	
    if(!document.getElementById("custom_limit_num").value.length > 0){
  		document.getElementById("custom_limit_num_error").innerHTML = "You must add a limit amount.";
  		valid = false;
  	}else if(! IsNumeric(document.getElementById("custom_limit_num").value)){
  		document.getElementById("custom_limit_num_error").innerHTML = "Value must be a number.";
  		valid = false;
  	}else{ document.getElementById("custom_limit_num_error").innerHTML = ""; }
  	return valid;
}
function add_custom_limit(){
	if(validate_limit()){
    	var d = new Date();
    	if(document.getElementById("custom_limit_sorh").value == 0){
        	var hard = document.getElementById("hard_limits") && document.getElementById("hard_limits").value == "" ? document.getElementById("custom_limit_fields").value + ":" + document.getElementById("operators").value + ":" + document.getElementById("custom_limit_value").value + ":" + document.getElementById("custom_limit_num").value : document.getElementById("hard_limits").value + "*" + document.getElementById("custom_limit_fields").value + ":" + document.getElementById("operators").value + ":" + document.getElementById("custom_limit_value").value + ":" + document.getElementById("custom_limit_num").value;
        	var soft = document.getElementById("soft_limits") ? document.getElementById("soft_limits").value : "";
    	}else{
        	var soft = document.getElementById("soft_limits") && document.getElementById("soft_limits").value == "" ? document.getElementById("custom_limit_fields").value + ":" + document.getElementById("operators").value + ":" + document.getElementById("custom_limit_value").value + ":" + document.getElementById("custom_limit_num").value : document.getElementById("soft_limits").value + "*" + document.getElementById("custom_limit_fields").value + ":" + document.getElementById("operators").value + ":" + document.getElementById("custom_limit_value").value + ":" + document.getElementById("custom_limit_num").value;
        	var hard = document.getElementById("hard_limits") ? document.getElementById("hard_limits").value : "";
    	}
    	var template_id = "&template_id=" + document.getElementById("template").value;
    	var hard_limits = "&hard_limits=" + hard;
    	var soft_limits = "&soft_limits=" + soft;
    	var parameters = "action=add_custom_limit" + hard_limits + soft_limits + template_id + "&currTime=" + d.toUTCString();
    	// Build the URL to connect to
      	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    	ajaxpost(url,parameters);
    	simple_display("custom_limits");
    	document.getElementById("limit_form").innerHTML = "";
	}
}
function delete_limit(limit_type,limit_num){
	var hard_limits = "&hard_limits=" + document.getElementById("hard_limits").value;
	var soft_limits = "&soft_limits=" + document.getElementById("soft_limits").value;
	var template_id = "&template_id=" + document.getElementById("template").value;
	var limit_type = "&limit_type=" + limit_type;
	var limit_num = "&limit_num=" + limit_num;
	var d = new Date();
	var parameters = "action=delete_limit" + hard_limits + soft_limits + template_id + limit_type + limit_num + "&currTime=" + d.toUTCString();
	// Build the URL to connect to
  	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
	// Open a connection to the server\
	ajaxpost(url,parameters);
	simple_display("custom_limits");
}
function add_location_form(formtype,eventid){
	if(document.getElementById("location_menu").style.display == "inline"){
    	var d = new Date();
    	var parameters = "action=add_location_form&formtype=" + formtype + "&eventid=" + eventid + "&currTime=" + d.toUTCString();
    	// Build the URL to connect to
      	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    	// Open a connection to the server\
    	ajaxpost(url,parameters);
    	simple_display("location_menu");
    	prepareInputsForHints();
	}
}
function copy_location(location,eventid){
	if(location != "false"){
    	var d = new Date();
    	var parameters = "action=copy_location&location=" + location + "&eventid=" + eventid + "&currTime=" + d.toUTCString();
    	// Build the URL to connect to
      	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    	// Open a connection to the server\
    	ajaxpost(url,parameters);
    	simple_display("select_location");
    	document.getElementById("location_status").innerHTML = "Location Added"
    	hide_show_buttons("addtolist");
    	hide_show_buttons("hide_menu");
    	hide_show_buttons("new_button");
    	hide_show_buttons("or");
    	hide_show_buttons("location_menu");
    	hide_show_buttons("add_location_div");
    	setTimeout("clear_display(\'location_status\')",2000);
	}
}
function get_location_details(location){
    if(location != "false"){
    	var d = new Date();
    	var parameters = "action=get_location_details&location=" + location + "&currTime=" + d.toUTCString();
    	// Build the URL to connect to
      	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    	// Open a connection to the server\
    	ajaxpost(url,parameters);
    	simple_display("location_details_div");
   	}else{
    	clear_display("location_details_div");
    }
}
function valid_new_location(){
	var valid = true;
	if(!document.getElementById("location_name").value.length > 0){
  		document.getElementById("location_name_error").innerHTML = "This is a required field.";
  		valid = false;
  	}else{ 
		// Build the URL to connect to
	  	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php?action=unique_relay&table=events_locations&key=location&value="+document.getElementById("location_name").value;
		// Open a connection to the server\
	    var d = new Date();
	  	xmlHttp.open("GET", url + "&currTime=" + d.toUTCString(), false);
	  	// Send the request
		xmlHttp.send(null);
		if(!istrue()){
			document.getElementById("location_name_error").innerHTML = "This value already exists in our database.";
			valid = false;
		}else{ document.getElementById("location_name_error").innerHTML = ""; }
	}
	if(!document.getElementById("location_address_1").value.length > 0){
  		document.getElementById("location_address_1_error").innerHTML = "This is a required field.";
  		valid = false;
  	}else{ document.getElementById("location_address_1_error").innerHTML = ""; }
	if(!document.getElementById("location_address_2").value.length > 0){
  		document.getElementById("location_address_2_error").innerHTML = "This is a required field.";
  		valid = false;
  	}else{ document.getElementById("location_address_2_error").innerHTML = ""; }
	if(!document.getElementById("zip").value.length > 0){
  		document.getElementById("zip_error").innerHTML = "This is a required field.";
  		valid = false;
  	}else if(document.getElementById("zip").value.length < 5){
  		document.getElementById("zip_error").innerHTML = "This is an invalid zipcode.";
  		valid = false;
  	}else if(! IsNumeric(document.getElementById("zip").value)){
  		document.getElementById("zip_error").innerHTML = "This is an invalid zipcode.";
  		valid = false;
  	}else{ document.getElementById("zip_error").innerHTML = ""; }
	if(document.getElementById("opt_location_phone").value == 0 || (document.getElementById("opt_location_phone").value != 0 && (document.getElementById("location_phone_1").value.length > 0 || document.getElementById("location_phone_2").value.length > 0 || document.getElementById("location_phone_3").value.length > 0))){
		//Phone # validity test
		if(document.getElementById("location_phone_1").value.length == 3 && document.getElementById("location_phone_2").value.length == 3 && document.getElementById("location_phone_3").value.length == 4){
			if(!(IsNumeric(document.getElementById("location_phone_1").value) && IsNumeric(document.getElementById("location_phone_2").value) && IsNumeric(document.getElementById("location_phone_3").value))){
				document.getElementById("location_phone_error").innerHTML = "Not a valid phone #";
	  			valid = false;
			}else{ document.getElementById("location_phone_error").innerHTML = ""; }
		}else{
	  		document.getElementById("location_phone_error").innerHTML = "Phone # is not complete.";
	  		valid = false;
	  	}
	}
   return valid;
}
function valid_new_event(){
		var valid = true;
		var Today = new Date();
		Today.setDate(Today.getDate()-1)
		//event name
		if(!document.getElementById("event_name").value.length > 0){
	  		document.getElementById("event_name_error").innerHTML = "This is a required field.";
	  		valid = false;
	  	}else{ document.getElementById("event_name_error").innerHTML = ""; }
		//contact name
		if(!document.getElementById("contact").value.length > 0){
	  		document.getElementById("contact_error").innerHTML = "This is a required field.";
	  		valid = false;
	  	}else{ document.getElementById("contact_error").innerHTML = ""; }
		//contact email
		if(document.getElementById("email").value.length > 0){
			if(echeck(document.getElementById("email").value)){
				document.getElementById("email_error").innerHTML = "";
		  	}else{
				document.getElementById("email_error").innerHTML = "Email address is not valid.";
				valid = false;
			}
		}else{
	  		document.getElementById("email_error").innerHTML = "Email address is required.";
	  		valid = false;
	  	}
	  	//contact phone #
		if(document.getElementById("phone_1").value.length == 3 && document.getElementById("phone_2").value.length == 3 && document.getElementById("phone_3").value.length == 4){
			if(!(IsNumeric(document.getElementById("phone_1").value) && IsNumeric(document.getElementById("phone_2").value) && IsNumeric(document.getElementById("phone_3").value))){
				document.getElementById("phone_error").innerHTML = "Not a valid phone #";
	  			valid = false;
			}else{ document.getElementById("phone_error").innerHTML = ""; }
		}else{
	  		document.getElementById("phone_error").innerHTML = "Phone # is not complete.";
	  		valid = false;
	  	}
		if(document.getElementById("fee").value == "1"){ //Fee = YES
			//min fee
			if(! IsNumeric(document.getElementById("min_fee").value)){
				document.getElementById("event_min_fee_error").innerHTML = "Must be a numeric value.";
			  	valid = false;
			}else if(parseInt(document.getElementById("min_fee").value) > parseInt(document.getElementById("full_fee").value)){
				document.getElementById("event_min_fee_error").innerHTML = "Cannot be greater than full fee.";
			  	valid = false;
			}else{	document.getElementById("event_min_fee_error").innerHTML = ""; }
			//full fee
			if(! IsNumeric(document.getElementById("full_fee").value)){
				document.getElementById("event_full_fee_error").innerHTML = "Must be a numeric value.";
			  	valid = false;
			}else if(document.getElementById("full_fee").value == "0"){
				document.getElementById("event_full_fee_error").innerHTML = "Must be greater than 0.";
			  	valid = false;
			}else{ document.getElementById("event_full_fee_error").innerHTML = ""; }
			//sale fee
			if(parseInt(document.getElementById("sale_fee").value) != 0){
				if(! IsNumeric(document.getElementById("sale_fee").value)){
					document.getElementById("event_sale_fee_error").innerHTML = "Must be a numeric value.";
				  	valid = false;
				}else{ document.getElementById("event_sale_fee_error").innerHTML = ""; }
				if(sale_end_Object.picked.date < Today){
					document.getElementById("sale_end_error").innerHTML = 'Cannot select a date in the past.';
					valid = false;
				}else{ document.getElementById("sale_end_error").innerHTML = ''; }
			}
			//payable to
			if(document.getElementById("payableto").value == ""){
				document.getElementById("event_payableto_error").innerHTML = "This is a required field.";
			  	valid = false;
			}else{	document.getElementById("event_payableto_error").innerHTML = ""; }
			//checksaddress to
			if(document.getElementById("checksaddress").value == ""){
				document.getElementById("event_checksaddress_error").innerHTML = "This is a required field.";
			  	valid = false;
			}else{ document.getElementById("event_checksaddress_error").innerHTML = ""; }
			//paypal
			if(document.getElementById("paypal").value != "" && !echeck(document.getElementById("paypal").value)){
				document.getElementById("event_paypal_error").innerHTML = "This is not a valid email address.";
			  	valid = false;
			}else{	document.getElementById("event_paypal_error").innerHTML = ""; }
		}		
		//event location
		if(document.getElementById("location")){
			document.getElementById("location_error").innerHTML = "";
		}else{
			document.getElementById("location_error").innerHTML = "Add a location for your event.";
		  	valid = false;
		}
		//multiday event
		//event_begin_date	
		if (event_begin_date_Object.picked.date < Today){
			document.getElementById("event_begin_date_error").innerHTML = 'Cannot select a date in the past.';
			valid = false;
        }else{ document.getElementById("event_begin_date_error").innerHTML = ''; }
		if(document.getElementById("multiday").value == "1"){ //Multi day = YES
   			//event_end_date	
			if (event_end_date_Object.picked.date <= event_begin_date_Object.picked.date){
				document.getElementById("event_end_date_error").innerHTML = 'Must select a date after the event start date';
				valid = false;
			}else if (event_end_date_Object.picked.date < Today){
				document.getElementById("event_end_date_error").innerHTML = 'Cannot select a date in the past.';
				valid = false;
			}else{ document.getElementById("event_end_date_error").innerHTML = ''; }
		}
		if(document.getElementById("allday").value == "0"){ //All day = NO
			//begin time
			if(document.getElementById("begin_time").value == ""){
				document.getElementById("time_error").innerHTML = "You must select a start time.";
			  	valid = false;
			}else if(document.getElementById("end_time").value == ""){
				document.getElementById("time_error").innerHTML = "You must select an end time.";
			  	valid = false;
			}else{ document.getElementById("time_error").innerHTML = ""; }
		}
		if(document.getElementById("reg").value == "1"){ //Registration = YES
			if(document.getElementById("max").value == ""){ document.getElementById("max").value = "0"; }
			if(document.getElementById("limits").value == "1"){
				//max reg
				if(!IsNumeric(document.getElementById("max").value)){
					document.getElementById("max_error").innerHTML = "Must be a numeric value.";
				  	valid = false;
				}else{ document.getElementById("max_error").innerHTML = ""; }
			}
			//registration dates
   			//start_reg	
			if(start_reg_Object.picked.date < Today && !document.getElementById("eventid")){
				document.getElementById("start_reg_error").innerHTML = 'Cannot select a date in the past.';
				valid = false;
			}else if (start_reg_Object.picked.date >= event_begin_date_Object.picked.date){ 
				document.getElementById("start_reg_error").innerHTML = 'Registration must start and end before event starts.';
				valid = false
   			}else{ document.getElementById("start_reg_error").innerHTML = ''; }
			//stop_reg	
			if(stop_reg_Object.picked.date < Today && !document.getElementById("eventid")){
				document.getElementById("stop_reg_error").innerHTML = 'Cannot select a date in the past.';
				valid = false;
			}else if (stop_reg_Object.picked.date <= start_reg_Object.picked.date){ 
				document.getElementById("stop_reg_error").innerHTML = 'Must be a date after registration start.';
				valid = false
   			}else if (stop_reg_Object.picked.date > event_begin_date_Object.picked.date){ 
				document.getElementById("stop_reg_error").innerHTML = 'Registration must start and end before event starts.';
				valid = false
   			}else{ document.getElementById("stop_reg_error").innerHTML = ''; }
		}
	return valid;
}
function new_event_submit(pageid){
	if(valid_new_event()){
    	var d = new Date();
    	var eventid = document.getElementById("eventid") ? '&eventid=' + document.getElementById("eventid").value: ''; //Event id if update event
    	var event_name = "&event_name=" + escape(document.getElementById("event_name").value); //Event name
    	var contact = "&contact=" + escape(document.getElementById("contact").value); //Contacts name
    	var email = "&email=" + escape(document.getElementById("email").value); //Contacts email
    	var phone = "&phone=" + document.getElementById("phone_1").value + "-" + document.getElementById("phone_2").value + "-" + document.getElementById("phone_3").value; //Event name
    	var extrainfo = "&extrainfo=" + escape(document.getElementById("extrainfo").value); //Event description
    	var siteviewable = "&siteviewable=" + document.getElementById("siteviewable").value; //If event is viewable on front page
    	var location = "&location=" + document.getElementById("location").value; //Where event is located
    	var category = "&category=" + document.getElementById("category").value; //Event category (birthday, aniversary..)
    	var multiday = "&multiday=" + document.getElementById("multiday").value; //If the event is more than 1 day
    	var allday = "&allday=" + document.getElementById("allday").value; //all day event?
        var workers = "&workers=" + document.getElementById("workers").value; //all day event?
    	var event_begin_date = "&event_begin_date=" + event_begin_date_Object.picked.date; //when event begins
    	var event_end_date = document.getElementById("multiday").value == "1" ? "&event_end_date=" + event_end_date_Object.picked.date : ""; //when event ends
    	var begin_time = document.getElementById("allday").value == "1" ? "" : "&begin_time=" + document.getElementById("begin_time").value; //If not an all day event, when does it begin
    	var end_time = document.getElementById("allday").value == "1" ? "" : "&end_time=" + document.getElementById("end_time").value; //If not an all day event, when does it end
        var max = document.getElementById("reg").value == "1" ? "&max=" + document.getElementById("max").value : ""; //Maximum registrations HARD
    	var hard_limits = document.getElementById("hard_limits") && document.getElementById("hard_limits").value != "" ? "&hard_limits=" + document.getElementById("hard_limits").value : ""; //custom limits that keep people from registering
    	var soft_limits = document.getElementById("soft_limits") && document.getElementById("soft_limits").value != ""  ? "&soft_limits=" + document.getElementById("soft_limits").value : ""; //custom limits that place people in queue
        var fee = "&fee=" + document.getElementById("fee").value; //Are there fees associated with this reg page?
    	var min_fee = "&min_fee=" + document.getElementById("min_fee").value; //minimum amount needed to pay to register
    	var full_fee = "&full_fee=" + document.getElementById("full_fee").value; //full payment for registration
    	var sale_fee = "&sale_fee=" + document.getElementById("sale_fee").value; //temporary sale payment
    	var sale_end = document.getElementById("sale_fee").value != "" ? "&sale_end=" + sale_end_Object.picked.date : ""; //when temporary sale price ends
    	var checksaddress = "&checksaddress=" + escape(document.getElementById("checksaddress").value); //Address to send checks to
    	var payableto = "&payableto=" + escape(document.getElementById("payableto").value); //Make checks payable to
    	var paypal = "&paypal=" + document.getElementById("paypal").value; //Paypal account
    	var reg = "&reg=" + document.getElementById("reg").value; //Event has a registration page
    	var allowinpage = document.getElementById("reg").value == "1" ? "&allowinpage=" + document.getElementById("allowinpage").value : ""; //If a logged in user registers...allow them into the page that this event was created in.
    	var template = document.getElementById("reg").value == "1" ? "&template=" + document.getElementById("template").value : ""; //registration template
        var template_settings = create_request_string('template_settings_form');
        var start_reg = document.getElementById("reg").value == "1" ? "&start_reg=" + start_reg_Object.picked.date : ""; //Registration open date
    	var stop_reg = document.getElementById("reg").value == "1" ? "&stop_reg=" + stop_reg_Object.picked.date : ""; //Registration ending date
    	var parameters = "action=submit_new_event&pageid=" + pageid + workers + email + contact + phone + fee + min_fee + full_fee + sale_fee + sale_end + hard_limits + soft_limits + checksaddress + payableto + paypal + eventid + event_name + category + extrainfo + siteviewable + location + multiday + template + event_begin_date + event_end_date + allday + begin_time + end_time + reg + allowinpage + max + start_reg + stop_reg + template_settings + "&currTime=" + d.toUTCString();
        // Build the URL to connect to
      	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    	// Open a connection to the server\
    	ajaxpost(url,parameters);
    	// Setup a function for the server to run when it's done
      	
        simple_display("add_event_div");
      	close_modal();
  	}	
}

function add_new_location(eventid){
	if(valid_new_location()){
    	var d = new Date();
    	var name = "&name=" + escape(document.getElementById("location_name").value);
    	var add1 = "&add1=" + escape(document.getElementById("location_address_1").value);
    	var add2 = "&add2=" + escape(document.getElementById("location_address_2").value);
    	var zip = "&zip=" + document.getElementById("zip").value;
    	var eventid = "&eventid=" + eventid;
    	var share = document.getElementById("share").checked == true ? "&share=1" : "";
    	var phone = document.getElementById("location_phone_1").value != "" ? "&phone=" + document.getElementById("location_phone_1").value + "-" + document.getElementById("location_phone_2").value + "-" + document.getElementById("location_phone_3").value : "";
    	var parameters = "action=add_new_location" + name + add1 + add2 + zip + share + phone + eventid + "&currTime=" + d.toUTCString();
    	// Build the URL to connect to
      	var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events_ajax.php";
    	// Open a connection to the server\
    	ajaxpost(url,parameters);
    	simple_display("select_location");
    	document.getElementById("location_status").innerHTML = "Location Added"
    	hide_show_buttons("addtolist");
    	hide_show_buttons("hide_menu");
    	hide_show_buttons("new_button");
    	hide_show_buttons("or");
    	hide_show_buttons("location_menu");
    	hide_show_buttons("add_location_div");
    	setTimeout("clear_display(\'location_status\')",2000);
	}	
}
function clear_display(divname){
	if(document.getElementById(divname)){ document.getElementById(divname).innerHTML = ""; }
}
function init_event_menu(){
    $('#event_menu_button').click( function(event){
        event.stopPropagation();
        $('#event_menu').toggle();
    });

    $(document).click( function(){
        $('#event_menu').hide();
    });
}