jQuery.validator.addMethod("ajax1", function(value, element, param){
    param = param.split("::");
	//Matches the previous value.
	if(param[4] && param[4] == value){ return true; }
   
    var modparam = param[2];
    //finds other field info marked with #name
    if(modparam.indexOf("#") != -1){
        var varname = modparam.match('[#]+[a-z]*');
        modparam = modparam.replace(varname.toString(),$(varname.toString()).val());
    }

    if(param == value){ return true; }
    var d = new Date();   
	var resp = $.ajax({
		async: false,
		type: 'post',
		url: '../'+param[0], data: 'action='+param[1]+modparam+ value + "&currTime=" + d.toUTCString()
	}).responseText;
	
	if(param[3] && param[3] == "true"){ if(resp == "true"){ return true; } }
	else{ if(resp == "false"){ return true; } }
},jQuery.validator.format("This value is not allowed."));

jQuery.validator.addMethod("ajax2", function(value, element, param){
    param = param.split("::");
	//Matches the previous value.
	if(param[4] && param[4] == value){ return true; }
   
    var modparam = param[2];
    //finds other field info marked with #name
    if(modparam.indexOf("#") != -1){
        var varname = modparam.match('[#]+[a-z]*');
        modparam = modparam.replace(varname.toString(),$(varname.toString()).val());
    }

    if(param == value){ return true; }
    var d = new Date();   
	var resp = $.ajax({
		async: false,
		type: 'post',
		url: '../'+param[0], data: 'action='+param[1]+modparam+ value + "&currTime=" + d.toUTCString()
	}).responseText;
	
	if(param[3] && param[3] == "true"){ if(resp == "true"){ return true; } }
	else{ if(resp == "false"){ return true; } }
},jQuery.validator.format("This value is not allowed."));

jQuery.validator.addMethod("ajax3", function(value, element, param){
    param = param.split("::");
	//Matches the previous value.
	if(param[4] && param[4] == value){ return true; }
    
    var modparam = param[2];
    //finds other field info marked with #name
    if(modparam.indexOf("#") != -1){
        var varname = modparam.match('[#]+[a-z]*');
        modparam = modparam.replace(varname.toString(),$(varname.toString()).val());
    }

    if(param == value){ return true; }
    var d = new Date();   
	var resp = $.ajax({
		async: false,
		type: 'post',
		url: '../'+param[0], data: 'action='+param[1]+modparam+ value + "&currTime=" + d.toUTCString()
	}).responseText;
	
	if(param[3] && param[3] == "true"){ if(resp == "true"){ return true; } }
	else{ if(resp == "false"){ return true; } }
},jQuery.validator.format("This value is not allowed."));

//Captcha
jQuery.validator.addMethod("captcha", function(value, element, param) {
    param = param.split("::");
	var number = $(param[2]).val().split("|");
    var d = new Date();
	var resp = $.ajax({
		async: false,
		type: 'post',
		url: '../'+param[0], data: 'action='+param[1]+'&orig='+number[0]+'&matchwith=' + value + "&currTime=" + d.toUTCString()
	}).responseText;
	
	if(resp == "true"){ return true; }
},jQuery.validator.format("This does not match the image."));

//Phone
jQuery.validator.addMethod("phone", function(value,element) {
    return this.optional(element) || /^\(?(\d{3})\)?[- ]?(\d{3})[- ]?(\d{4})$/.test(value);
},jQuery.validator.format("Enter a valid phone number."));

//Future Date
jQuery.validator.addMethod("futuredate", function(value, element, param) {
	var myDate = new Date()
	if(param && (!/Invalid|NaN/.test(new Date($(param).prop("value"))))){ myDate = new Date($(param).prop("value"))	}
	return this.optional(element) || myDate < (new Date(value));
},jQuery.validator.format("Enter a date in the future."));

//Past Date
jQuery.validator.addMethod("pastdate", function(value, element, param) {
	var myDate = new Date()
	if(param && (!/Invalid|NaN/.test(new Date($(param).prop("value"))))){ myDate = new Date($(param).prop("value"))	}
	return this.optional(element) || myDate > (new Date(value));
},jQuery.validator.format("Enter a date in the past."));

//Letters only
jQuery.validator.addMethod("letters", function(value,element) {
	return this.optional(element) || /([a-zA-Z])/.test(value);
},jQuery.validator.format("This can contain only letters."));

//No Numbers
jQuery.validator.addMethod("nonumbers", function(value,element) {
	return this.optional(element) || /^[^0-9]+$/.test(value);
},jQuery.validator.format("This cannot contain numbers."));

//Custom
jQuery.validator.addMethod("custom", function(value, element, param) {
    var ptrn = "/" + param + "/";
    var pattern=new RegExp(ptrn)
    return this.optional(element) || pattern.test(value)
},jQuery.validator.format("This is not a valid entry"));
