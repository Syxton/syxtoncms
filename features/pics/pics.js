function handleEnter (field, event) {
	var keyCode = event.keyCode ? event.keyCode : event.which ? event.which : event.charCode;
	if (keyCode == 13) {
		var i;
		for (i = 0; i < field.form.elements.length; i++)
			if (field == field.form.elements[i])
				break;
		i = (i + 1) % field.form.elements.length;
		field.form.elements[i].focus();
		return false;
	}
	return true;
}
		    
function update_picslist(){
    var cansubmit = true;
	var reqStr = get_file_names($("#pics_form"));

	$("#filenames").val(reqStr);
	if($("#gallery_name").val().length == 0){ cansubmit = false; }
	if($("#filenames").val().length == 0){ cansubmit = false; }
	if(cansubmit){
		 $('#pics_form').submit();   
	}else{
		 alert('A gallery must be selected or created and files must be attached.');
	} 
}

function get_file_names(theForm){
    var reqStr = "";
    $('.MultiFile-title').each(function(){
        reqStr += reqStr == "" ? $(this).html() : "**" + $(this).html();    
    });
    return reqStr;
    
	var reqStr = "";
	for(i=0; i < theForm.elements.length; i++){
		switch (theForm.elements[i].tagName){
			case "INPUT":
			switch (theForm.elements[i].type){
                case "file":
                reqStr += reqStr == "" ? theForm.elements[i].name : "**" + theForm.elements[i].name;
                break;
			}
		}
	}
	return reqStr;
} 