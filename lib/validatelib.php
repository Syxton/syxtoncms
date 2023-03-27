<?php
/***************************************************************************
* validatelib.php - Validatation script library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/09/2013
* Revision: 0.0.4
***************************************************************************/

if (!isset($LIBHEADER)) include('header.php');
$VALIDATELIB = true;

function create_validation_script($formname, $function, $ajax=false) {
global $CFG;
	$setup = '';
	$script = '
	$.validator.setDefaults({
	done: \'valid\'
	});

	$(document).ready(function() {
		$(\'#'.$formname.'\').validate({
			meta: \'validate\',
			submitHandler: function() { '.$function.' },
            ignore: \'.calendarDateInput\'
		});
	});
';

    //Text fields and new HTML5 types
    $setup .= '$(document).ready(function() {
    			$(\'.formContainer input[type=text],[type=email],[type=search],[type=url]\').focus(function() {
    		        $(this).parent().find(\'label.error\').css(\'display\', \'none\');
    		        $(this).parent().find(\'.info\').css(\'display\', \'block\');
    		    }).blur(function() {
    		        $(this).parent().find(\'.info\').css(\'display\', \'none\');
    		    });
    		});';

    //Password fields
    $setup .= '$(document).ready(function() {
    			$(\'.formContainer input[type=password]\').focus(function() {
    		        $(this).parent().find(\'label.error\').css(\'display\', \'none\');
    		        $(this).parent().find(\'.info\').css(\'display\', \'block\');
    		    }).blur(function() {
    		        $(this).parent().find(\'.info\').css(\'display\', \'none\');
    		    });
    		});';

    //Textarea fields
    $setup .= '$(document).ready(function() {
    			$(\'.formContainer textarea\').focus(function() {
    		        $(this).parent().find(\'label.error\').css(\'display\', \'none\');
    		        $(this).parent().find(\'.info\').css(\'display\', \'block\');
    		    }).blur(function() {
    		        $(this).parent().find(\'.info\').css(\'display\', \'none\');
    		    });
    		});';

    //CHECK fields
    $setup .= '$(document).ready(function() {
    			$(\'.formContainer input[type=checkbox]\').focus(function() {
    		        $(this).parent().find(\'label.error\').css(\'display\', \'none\');
    		        $(this).parent().find(\'.info\').css(\'display\', \'block\');
    		    }).blur(function() {
    		        $(this).parent().find(\'.info\').css(\'display\', \'none\');
    		    });
    		});';

    //SELECT fields
    $setup .= '$(document).ready(function() {
    			$(\'.formContainer select\').focus(function() {
    		        $(this).parent().find(\'label.error\').css(\'display\', \'none\');
    		        $(this).parent().find(\'.info\').css(\'display\', \'block\');
    		    }).blur(function() {
    		        $(this).parent().find(\'.info\').css(\'display\', \'none\');
    		    });
    		});';

    if ($ajax) {
	   return $script . $setup;
	} else {
	   return js_code_wrap($script . $setup, "defer");
	}

}
?>
