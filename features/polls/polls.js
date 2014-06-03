function valid_poll_fields(){
	var valid = true;
  	if(!document.getElementById("polls_question").value.length > 0){
  		document.getElementById("question_error").innerHTML = "Poll question is required.";
  		valid = false;
  	}else{ document.getElementById("question_error").innerHTML = ""; }
  	
 	if(document.getElementById("polls_answers").value.indexOf(',') == -1){
  		document.getElementById("answers_error").innerHTML = "At least 2 poll answers are required.";
  		valid = false;
  	}else{ document.getElementById("answers_error").innerHTML = ""; }
  	
	if(document.getElementById("startdateenabled").checked == 1){
		var error = validatestartdate();
		if(error != "true"){ 
			document.getElementById("startdate_error").innerHTML = error;
			valid = false;
		}
	}else{ document.getElementById("startdate_error").innerHTML = ""; }
	
	if(document.getElementById("stopdateenabled").checked == 1){
		var error = validatestopdate();
		if(error != "true"){ 
			document.getElementById("stopdate_error").innerHTML = error;
			valid = false;
		}
	}else{ document.getElementById("stopdate_error").innerHTML = ""; }
  	return valid;
}

function validatestartdate(){
   var Today = new Date();
   if (startdate_Object.picked.date < Today){ return 'Must select a date in the future.';
   }else if(Today.getFullYear() - startdate_Object.picked.yearValue > 10){ return 'Cannot select dates beyond 10 years from now.'; }
   return "true";
}

function validatestopdate(){
   var Today = new Date();
   if (stopdate_Object.picked.date < Today){ return 'Cannot select a date in the past.';
   }else if(stopdate_Object.picked.yearValue - Today.getFullYear() > 10){ return 'Cannot select dates beyond 10 years from now.'; }
   return "true";
}

function zeroout(name){
	var divname = name + "div";
	document.getElementById(name).value = '0';
	document.getElementById(divname).innerHTML = '';
}

function hide_show_span(span_name){
	document.getElementById(span_name).style.display = document.getElementById(span_name).style.display=='none' || document.getElementById(span_name).style.display.length==0 ? 'inline' : 'none'	
}