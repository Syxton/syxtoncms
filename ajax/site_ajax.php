<?php
/***************************************************************************
* site_ajax.php - Main backend ajax script.  Usually sends off to feature libraries.
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/13/2012
* Revision: 2.9.6
***************************************************************************/

include('header.php');

callfunction();

function login(){
global $CFG, $USER, $MYVARS;
	$reroute = '';
	$username = dbescape($MYVARS->GET["username"]);
	$password = md5($MYVARS->GET["password"]);
	if($row = authenticate($username, $password)) {
		if($row["alternate"] == $password){ $reroute = '<input type="hidden" id="reroute" value="/pages/user.php?action=reset_password&amp;userid=' . $row["userid"] . '&amp;alternate=' . $password . '" />';}
        echo 'true**' . $reroute;
	}else{ echo "false**" . get_error_message("no_login"); }
}

function unique_email(){
global $CFG, $MYVARS;
	$email = dbescape($MYVARS->GET["email"]);
	if(get_db_count("SELECT * FROM users WHERE email='$email'")){ echo "false";
	}else{  echo "true";}
}

function reset_password(){
global $CFG, $MYVARS;
	$userid = dbescape($MYVARS->GET["userid"]);
	$password = md5($MYVARS->GET["password"]);
	if(execute_db_sql("UPDATE users SET alternate='',password='$password' WHERE userid='$userid'")){
		//Log
		log_entry("user", null, "Password changed");
		echo '<br /><br /><span class="centered_span">Password changed successfully.</span>';
	}else{
		//Log
		log_entry("user", null, "Password change failed");
		echo '<br /><span class="centered_span">Password change failed.</span>';
	}
}

function change_profile(){
global $CFG, $MYVARS;
    $userid = dbescape($MYVARS->GET["userid"]);
	$email = dbescape($MYVARS->GET["email"]);
	$fname = dbescape($MYVARS->GET["fname"]);
	$lname = dbescape($MYVARS->GET["lname"]);
    $passchanged = empty($MYVARS->GET["password"]) ? false : true;
    $password = md5($MYVARS->GET["password"]);
    $passwordsql = $passchanged ? ",alternate='',password='$password'" : "";

    if(!get_db_row("SELECT * FROM users WHERE email='$email' AND userid !='$userid'")){
        if(execute_db_sql("UPDATE users SET fname='$fname',lname='$lname',email='$email'$passwordsql WHERE userid='$userid'")){
            //Log
            log_entry("user", null, "Profile changed");
            echo '<br /><br /><span class="centered_span">Profile changed successfully.</span>';    
        }else{
            //Log
            log_entry("user", null, "Profile change failed");
            echo '<br /><br /><span class="centered_span">Profile change failed.</span>';                 
        }
    }else{ echo '<br /><br /><span class="centered_span">This email address is already associated with another account.</span>'; }
}

function save_settings(){
global $CFG, $MYVARS;
	$settingid = dbescape($MYVARS->GET["settingid"]);
	$setting = dbescape((urldecode($MYVARS->GET["setting"])));
	$extra = isset($MYVARS->GET["extra"]) ? dbescape((urldecode($MYVARS->GET["extra"]))) : false;
	
	if(make_or_update_setting($settingid,false,false,false,false,$setting,$extra,false)){
		//Log
		log_entry("setting", $settingid . ":" . $setting, "Setting Changed");
		echo '<img src="'.$CFG->wwwroot.'/images/checked.gif" />';
	}else{
		echo '<img src="'.$CFG->wwwroot.'/images/error.gif" />';
	}
}

function forgot_password(){
global $CFG, $MYVARS;
	if(!isset($COMLIB)){ include_once ($CFG->dirroot . '/lib/comlib.php'); }
    $admin = isset($MYVARS->GET["admin"]) ? true : false;
    if($admin){
        $email = get_db_field("email","users","userid='".isset($MYVARS->GET["admin"])."'");
    }else{
        $email = dbescape($MYVARS->GET["email"]);
    }
    
	//Check to see if email matches an existing user.
	if($user = get_db_row("SELECT * FROM users WHERE email='$email'")){
		$alternate = create_random_password();
        
        //check to see if account is activated
		if(strlen($user["temp"]) > 0){ 
            $userid = execute_db_sql("UPDATE users SET password='" . md5($alternate) . "' WHERE email='$email'");
		}else{
			$userid = execute_db_sql("UPDATE users SET alternate='" . md5($alternate) . "' WHERE email='$email'");
        }
		//Email new password to the email address.
        $USER = new stdClass();
		$USER->userid = $user['userid'];
		$USER->fname = $user['fname'];
		$USER->lname = $user['lname'];
		$USER->email = $email;
        $FROMUSER = new stdClass();
		$FROMUSER->fname = $CFG->sitename;
		$FROMUSER->lname = '';
		$FROMUSER->email = $CFG->siteemail;
		$message = '
			<p><font face="Tahoma"><font size="3" color="#993366">Dear <strong>' . $user['fname'] . ' ' . $user['lname'] . '</strong>,</font><br />
			</font></p>
			<blockquote>
			<p><font size="3" face="Tahoma"><strong>' . $CFG->sitename . '</strong> has recieved notification that you have forgotten your password.&nbsp; A new temporary password is being sent to you in this email.</font></p>
			</blockquote>
			<p>&nbsp;</p>
			<hr width="100%" size="2" />
			<p>&nbsp;</p>
			<blockquote>
			<p align="left"><font face="Tahoma"><strong>Username:</strong> <font color="#3366ff">' . $email . '</font></font></p>
			<p align="left"><font face="Tahoma"><strong>Password:</strong> <font color="#3366ff">' . $alternate . '</font></font></p>
			</blockquote>
			<p>&nbsp;</p>
			<hr width="100%" size="2" />
			<blockquote>
			<p><font size="3" face="Tahoma">After you have successfully logged into the site using the password provided a password reset form will open up.  Please create a new password at that time.  If you somehow exit this form without entering a new password, your forgotten password will still be valid and the password in this email will still be valid.  If you have any questions during your use of the site, feel free to contact us at <font color="#ff0000">' . $CFG->siteemail . '</font>.<br />
			</font></p>
			</blockquote>
			<p>&nbsp;</p>
			<p><font face="Tahoma"><strong><font size="3" color="#666699">Enjoy the site,</font></strong></font></p>
			<p><font size="3" face="Tahoma"><em>' . $CFG->siteowner . ' </em></font><font size="3" face="Tahoma" color="#ff0000">&lt;' . $CFG->siteemail . '</font><font face="Tahoma"><font size="3" color="#ff0000">&gt;</font></font></p>
			<p>&nbsp;</p>';
		$subject = $CFG->sitename . ' Password Reset';
		if(!$userid || send_email($USER, $FROMUSER, null, $subject, $message)){
			send_email($FROMUSER, $FROMUSER, null, $subject, $message); //Send a copy to the site admin
			//Log
			log_entry("user", $USER->email, "Password Reset");
			if(!$admin){ echo '<div class="centered_div">An email has been sent to your address that contains a new temporary password. <br />Your forgotten password will still work until you log into the site with the new password.<br />If you remember your password and log into the site, the password contained in the email will no longer work.</div>'; 
            }else{ echo '<img src="'.$CFG->wwwroot.'/images/reset_disabled.png" />'; }
		}else{
            echo '<br /><br /><span class="centered_span">A password reset could not be done at this time.  Please try again later.</span>';
		}
	}else{  
	   if(!$admin){ echo '<br /><br /><span class="centered_span">There is no user with this email address.</span>'; } 
    }
}

function add_new_user(){
global $CFG, $MYVARS;
    $newuser = new stdClass();
	$newuser->email = trim(urldecode($MYVARS->GET["email"]));
	$newuser->fname = trim(urldecode($MYVARS->GET["fname"]));
	$newuser->lname = trim(urldecode($MYVARS->GET["lname"]));
	$newuser->password = md5(trim(urldecode($MYVARS->GET["password"])));
	echo create_new_user($newuser);
}

function delete_user(){
global $CFG, $MYVARS, $USER;
	$userid = $MYVARS->GET["userid"];

	if($USER->userid == $userid){ echo "You can't delete yourself!"; 
    }elseif(is_siteadmin($userid)){ echo "You can't delete admins!"; 
    }else{
		if($user = get_db_row("SELECT * FROM users WHERE userid = '$userid'")){
            $SQL = "DELETE FROM users WHERE userid='$userid'";
            if(execute_db_sql($SQL)){
                //Remove all role assignments on site
		        remove_all_roles($userid);
                
                //Delete all logs of the user
                $SQL = "DELETE FROM logfile WHERE userid='$userid'";
                execute_db_sql($SQL);
                echo "User deleted."; 
            }
        }
	}
}

//function run_feature_function(){
//global $CFG, $PAGE, $USER, $MYVARS;
//    $featuretype = !empty($MYVARS->GET["featuretype"]) ? $MYVARS->GET["featuretype"] : null;
//    $featureid = !empty($MYVARS->GET["featureid"]) ? $MYVARS->GET["featureid"] : null;
//    $functionname = !empty($MYVARS->GET["functionname"]) ? $MYVARS->GET["functionname"] : null;
//    $extra = !empty($MYVARS->GET["extra"]) ? $MYVARS->GET["extra"] : null;
//    $parameters = !empty($MYVARS->GET["parameters"]) ? $MYVARS->GET["parameters"] : null;
//    
//	if($featuretype){
//		$featuretype = str_replace("_features", "", $featuretype);
//		all_features_function(false,$featuretype,"",$functionname,false,$MYVARS->GET["pageid"],$featureid,$extra,null,false);
//	}
//	update_user_cookie();
//}

function refresh_user_alerts(){
global $CFG, $MYVARS;
    $userid = empty($MYVARS->GET["userid"]) ? false : $MYVARS->GET["userid"];  
    
    get_user_alerts($userid,false,false);
}

function allow_page_request(){
global $CFG,$MYVARS;
    $approve = empty($MYVARS->GET["approve"]) ? false : true;
    $requestid = empty($MYVARS->GET["requestid"]) ? false : $MYVARS->GET["requestid"];  
    
    if($approve){ //confirmed request
        $SQL = "UPDATE roles_assignment SET confirm=0 WHERE assignmentid='$requestid'";    
    }else{ //denied request
        $SQL = "DELETE FROM roles_assignment WHERE assignmentid='$requestid'";
    }
    
    if(execute_db_sql($SQL)){
        echo "";
    }else{
        echo "false";
    }  
}

function subscribe(){
global $CFG, $USER, $MYVARS;
	update_user_cookie();
	echo subscribe_to_page($MYVARS->GET["pageid"]);
}

function get_login_box(){
global $CFG, $USER, $MYVARS;
	if(isset($MYVARS->GET["logout"])){
		setcookie("userid", "0", get_timestamp() - 60000, '/'); //set an expired cookie
		//Log
		log_entry("user", null, "Logout");
	}
	echo get_login_form();
}

function update_login_contents(){
global $CFG, $PAGE, $USER, $MYVARS;
	if(is_logged_in()){
		if(isset($MYVARS->GET['check'])){ echo "true**check";
		}else{
			update_user_cookie();
			echo "true**" . print_logout_button($USER->fname, $USER->lname, $MYVARS->GET['pageid']);
		}
	}else{ //Cookie has timed out or they haven't logged in yet.
        load_user_cookie();
		echo "false";
	}
}

function update_page_contents(){
global $CFG, $PAGE, $USER, $MYVARS;
	$pageid = $MYVARS->GET["pageid"];
	if(isset($pageid) && $pageid != "undefined"){ $PAGE->id = $pageid; 
    }else{ $pageid = $CFG->SITEID; }
	if(!isset($PAGE->id) || $PAGE->id == ""){ $PAGE->id = $CFG->SITEID;}
	if($MYVARS->GET["why"] == 'logout'){
		unset($USER);
		$PAGE->id = $CFG->SITEID;
		$CFG->extra = "logout";
		echo get_page_contents($PAGE->id, $MYVARS->GET["area"]);
	}else{
		echo get_page_contents($PAGE->id, $MYVARS->GET["area"]);
	}
}

function addfeature(){
global $MYVARS;
	update_user_cookie();
	add_page_feature($MYVARS->GET["pageid"], $MYVARS->GET["feature"]);
}

function delete_feature(){
global $CFG, $PAGE, $USER, $MYVARS;
	update_user_cookie();
	$PAGE->id = $MYVARS->GET["pageid"];
	$featuretype = str_replace("_features", "", $MYVARS->GET["featuretype"]);
	$action = $featuretype . "_delete";
	
	all_features_function(false,$featuretype,"","_delete",false,$MYVARS->GET["pageid"],$MYVARS->GET["featureid"],$MYVARS->GET["sectionid"]);

	//Log
	log_entry($featuretype, null, "Deleted Feature");
}

function move_feature(){
global $MYVARS;
	update_user_cookie();
	move_page_feature($MYVARS->GET["pageid"], $MYVARS->GET["featuretype"], $MYVARS->GET["featureid"], $MYVARS->GET["direction"]);
}

function drop_move_feature(){
global $MYVARS;
	$pageid = empty($MYVARS->GET["pageid"]) ? false : $MYVARS->GET["pageid"];
    $col1 = empty($MYVARS->GET["col1"]) ? false : $MYVARS->GET["col1"];
    $col2 = empty($MYVARS->GET["col2"]) ? false : $MYVARS->GET["col2"];
    $moved = empty($MYVARS->GET["moved"]) ? false : $MYVARS->GET["moved"];
    $refresh = false;
    
    $moved = explode("_",$moved);
    $movedtype = empty($moved[0]) ? false : $moved[0];
    $movedid = empty($moved[1]) ? false : $moved[1];
    
    $area = "middle"; $i = 1;
    foreach($col1 as $a){
        $a = explode("_", $a);
        $featuretype = empty($a[0]) ? false : $a[0];
        $featureid = empty($a[1]) ? false : $a[1];
        $current = get_db_row("SELECT * FROM pages_features WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
        if($movedtype == $featuretype && $movedid == $featureid && $current["area"] != $area){
            $refresh = true;    
        }
        execute_db_sql("UPDATE pages_features SET sort='$i', area='$area' WHERE id='".$current["id"]."'");   
        $i++;
    }

    $area = "side"; $i = 1;
    foreach($col2 as $a){
        $a = explode("_", $a);
        $featuretype = empty($a[0]) ? false : $a[0];
        $featureid = empty($a[1]) ? false : $a[1];
        $current = get_db_row("SELECT * FROM pages_features WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
        if($movedtype == $featuretype && $movedid == $featureid && $current["area"] != $area){
            $refresh = true;    
        } 
        execute_db_sql("UPDATE pages_features SET sort='$i', area='$area' WHERE id='".$current["id"]."'");   
        $i++;
    }
    
    if($refresh){
        echo "refresh";
    }else{
        echo "done";
    }    
    
	//Log
	log_entry($featuretype, $featureid, "Move Feature");
}


function donothing(){
	echo "";
}
?>