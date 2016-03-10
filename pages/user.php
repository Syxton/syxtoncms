<?php
/***************************************************************************
* user.php - User thickbox page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/09/2013
* Revision: 0.4.5
***************************************************************************/

include('header.php');
echo '
	 <script type="text/javascript">
	 var dirfromroot = "'.$CFG->directory.'";
	 </script>
	 <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'ajax/siteajax.js"></script>
     <link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'styles/styles_main.css" />
';

callfunction();

echo '</body></html>';

function new_user(){
global $MYVARS, $CFG;
	
	if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }
	$content = '
        <div class="formDiv" id="new_user_div">
        <br /><br />
    		<input id="hiddenusername" type="hidden" /><input id="hiddenpassword" type="hidden" />
    		<form id="signup_form">
    			<fieldset class="formContainer">
    				<div class="rowContainer">
    					<label class="rowTitle" for="email">Email Address</label><input type="text" id="email" name="email" data-rule-required="true" data-rule-email="true" data-rule-ajax1="ajax/site_ajax.php::unique_email::&email=::true" data-msg-required="'.get_error_message('valid_req_email').'" data-msg-email="'.get_error_message('valid_email_invalid').'" data-msg-ajax1="'.get_error_message('valid_email_unique').'" /><div class="tooltipContainer info">'.get_help("input_email").'</div>
    				    <div class="spacer" style="clear: both;"></div>
                    </div>
    				<div class="rowContainer">
    					<label class="rowTitle" for="fname">First Name</label><input type="text" id="fname" name="fname" data-rule-required="true" data-msg-required="'.get_error_message('valid_req_fname').'" /><div class="tooltipContainer info">'.get_help("input_fname").'</div>
    				    <div class="spacer" style="clear: both;"></div>
                    </div>
    				<div class="rowContainer">
    					<label class="rowTitle" for="lname">Last Name</label><input type="text" id="lname" name="lname" data-rule-required="true" data-msg-required="'.get_error_message('valid_req_lname').'" /><div class="tooltipContainer info">'.get_help("input_lname").'</div>
                        <div class="spacer" style="clear: both;"></div>
                    </div>
    			  	<div class="rowContainer">
    			  		<label class="rowTitle" for="mypassword">Password</label><input type="password" id="mypassword" name="mypassword" data-rule-required="true" data-rule-minlength="6" data-msg-required="'.get_error_message('valid_req_password').'" data-msg-minlength="'.get_error_message('valid_password_length').'" /><div class="tooltipContainer info">'.get_help("input_password").'</div>
                        <div class="spacer" style="clear: both;"></div>
                    </div>
    			  	<div class="rowContainer">
    				  	<label class="rowTitle" for="vpassword">Verify Password</label><input type="password" id="vpassword" name="vpassword" data-rule-required="true" data-rule-equalTo="#mypassword" data-msg-required="'.get_error_message('valid_req_vpassword').'" data-msg-equalTo="'.get_error_message('valid_vpassword_match').'" /><div class="tooltipContainer info">'.get_help("input_vpassword").'</div><br/>
                        <div class="spacer" style="clear: both;"></div>                   
                    </div>
    		  		<input class="submit" name="submit" type="submit" value="Sign Up" style="margin: auto;width: 80px;display: block;" />	
    			</fieldset>
    		</form>
    	</div>
    ';
       
	echo create_validation_script("signup_form" , "ajaxapi('/ajax/site_ajax.php','add_new_user','&email=' + escape(document.getElementById('email').value) + '&fname=' + escape(document.getElementById('fname').value) + '&lname=' + escape(document.getElementById('lname').value) + '&password=' + escape(document.getElementById('mypassword').value),function(){ var returned = trim(xmlHttp.responseText).split('**'); if(returned[0] == 'true'){ document.getElementById('new_user_div').innerHTML = returned[1];}else{ document.getElementById('new_user_div').innerHTML = returned[1];}});");
    echo format_popup($content,$CFG->sitename.' Signup',"500px");
}

function reset_password(){
global $MYVARS, $CFG;
	$userid = $MYVARS->GET["userid"];
	$alternate = $MYVARS->GET["alternate"];
	echo '<script src="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts&f=jquery.min.js,jqvalidate.js,jqvalidate_addon.js,jqmetadata.js" type="text/javascript"></script>';
	
	if(get_db_row("SELECT * FROM users WHERE userid='$userid' AND alternate='$alternate'")){
		if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }
		$content = '<div id="forgot_password">
            			Please type a new password then verify it.  After submitting your new password, you will be logged into the site and your new password will be set.
            			<br /><br />
                        <form id="password_request_form">
            				<fieldset class="formContainer">
            				  	<div class="rowContainer">
            			  			<label class="rowTitle" for="mypassword">Password</label><input value="" type="password" id="mypassword" name="mypassword" data-rule-required="true" data-rule-minlength="6" data-msg-required="'.get_error_message('valid_req_password').'" data-msg-minlength="'.get_error_message('valid_password_length').'" /><div class="tooltipContainer info">'.get_help("input_password").'</div>
                                    <div class="spacer" style="clear: both;"></div>
                                </div>
            				  	<div class="rowContainer">
            					  	<label class="rowTitle" for="vpassword">Verify Password</label><input value="" type="password" id="vpassword" name="vpassword" data-rule-required="true" data-rule-equalTo="#mypassword" data-msg-required="'.get_error_message('valid_req_vpassword').'" data-msg-equalTo="'.get_error_message('valid_vpassword_match').'" /><div class="tooltipContainer info">'.get_help("input_vpassword").'</div><br/>
                                    <div class="spacer" style="clear: both;"></div>
                                </div>
            			  		<input class="submit" name="submit" type="submit" value="Save" style="margin: auto;width: 80px;display: block;" />	
            				</fieldset>
            			</form>
            			<script type="text/javascript">
            			setTimeout(function(){
            				document.getElementById("mypassword").value = "";
            				document.getElementById("vpassword").value = "";
            				document.getElementById("mypassword").focus();
            			},500
            			);
            			</script>
            		</div>';
        
        echo create_validation_script("password_request_form" , "ajaxapi('/ajax/site_ajax.php','reset_password','&userid=$userid&password='+document.getElementById('mypassword').value,function() { go_to_page(1); });");
        echo format_popup($content,'Change Password',"500px");   
    }else{
		echo '<script type="text/javascript">go_to_page(1);</script>';
	}
}

function change_profile(){
global $MYVARS, $CFG, $USER, $PAGE;
	if(!empty($USER->userid)){
	   $userid = $USER->userid;
	
		if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }
        $content = '
		<div id="change_profile">
			You can change you profile details here.
			<br /><br />
            <form id="profile_change_form">
				<fieldset class="formContainer">
				  	<div class="rowContainer">
			  			<label class="rowTitle" for="myfname">First Name</label><input value="'.$USER->fname.'" type="text" id="myfname" name="myfname" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_fname").'</div>
                        <div class="spacer" style="clear: both;"></div>
                    </div>
                    <div class="rowContainer">
			  			<label class="rowTitle" for="mylname">Last Name</label><input value="'.$USER->lname.'" type="text" id="mylname" name="mylname" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_fname").'</div>
                        <div class="spacer" style="clear: both;"></div>
                    </div>
                    <div class="rowContainer">
				        <label class="rowTitle" for="email">Email Address</label><input type="text" value="'.$USER->email.'" id="email" name="email" data-rule-required="true" data-rule-email="true" data-rule-ajax1="ajax/site_ajax.php::unique_email::&email=::true::'.$USER->email.'" data-msg-required="'.get_error_message('valid_req_email').'" data-msg-email="'.get_error_message('valid_email_invalid').'" data-msg-ajax1="'.get_error_message('valid_email_unique').'" /><div class="tooltipContainer info">'.get_help("input_email").'</div>
				        <div class="spacer" style="clear: both;"></div>
                    </div>
 				  	<div class="rowContainer">
			  			<label class="rowTitle" for="mypassword">Password</label><input type="password" id="mypassword" name="mypassword" data-rule-minlength="6" data-msg-minlength="'.get_error_message('valid_password_length').'" /><div class="tooltipContainer info">'.get_help("input_password").'</div>
                        <div class="spacer" style="clear: both;"></div>
                    </div>
    			  	<div class="rowContainer">
					  	<label class="rowTitle" for="vpassword">Verify Password</label><input type="password" id="vpassword" name="vpassword" data-rule-equalTo="#mypassword" data-msg-equalTo="'.get_error_message('valid_vpassword_match').'" /><div class="tooltipContainer info">'.get_help("input_vpassword").'</div><br/>
                        <div class="spacer" style="clear: both;"></div> 
                    </div>
			  		<input class="submit" name="submit" type="submit" value="Save" style="margin: auto;width: 80px;display: block;" />	
				</fieldset>
			</form>
		</div>';		
		echo create_validation_script("profile_change_form" , "ajaxapi('/ajax/site_ajax.php','change_profile','&userid=$userid&password='+$('#mypassword').val()+'&email='+$('#email').val()+'&fname='+$('#myfname').val()+'&lname='+$('#mylname').val(),function() { simple_display('change_profile'); });");
        echo format_popup($content,'Edit Profile',"500px");
	}else{
		echo '<script type="text/javascript">go_to_page(1);</script>';
	}
}

function forgot_password(){
global $MYVARS, $CFG;
	
	if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }
    $content = '
	<div id="forgot_password">
	Please type the email address that is associated with your user account.  A new temporary password will be sent to this address.  You will then be able to log into the website and change your password.<br /><br />
		<form id="password_request_form">
			<fieldset class="formContainer">
				<div class="rowContainer">
					<label class="rowTitle" for="email">Email Address</label><input type="text" id="email" name="email" data-rule-required="true" data-rule-email="true" data-rule-ajax1="ajax/site_ajax.php::unique_email::&email=::false" data-msg-required="'.get_error_message('valid_req_email').'" data-msg-email="'.get_error_message('valid_email_invalid').'" data-msg-ajax1="'.get_error_message('valid_email_used').'" /><div class="tooltipContainer info">'.get_help("input_email").'</div>
				    <div class="spacer" style="clear: both;"></div>
                </div>
		  		<input class="submit" name="submit" type="submit" value="Check" style="margin: auto;width: 80px;display: block;" />	
			</fieldset>
		</form>
	</div>';	
	echo create_validation_script("password_request_form" , "ajaxapi('/ajax/site_ajax.php','forgot_password','&email='+document.getElementById('email').value,function() { simple_display('forgot_password'); });");
    echo format_popup($content,'Forgot Password',"500px");
}

function user_alerts(){
global $MYVARS, $CFG, $USER;
	echo '<div id="user_alerts_div">';
	   get_user_alerts($MYVARS->GET["userid"],false, false);
	echo '</div>';
}
?>