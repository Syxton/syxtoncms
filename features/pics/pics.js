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
	if ($("#gallery_name").val().length && $('#pics_files')[0].files.length) {
		$('#pics_form').submit();
		return false;
	}

	alert('A gallery must be selected or created and files must be attached.');
}