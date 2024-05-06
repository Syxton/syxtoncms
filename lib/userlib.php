<?php
/***************************************************************************
* userlib.php - User function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 2.8.0
***************************************************************************/

if (!isset($LIBHEADER)) { include('header.php'); }
$USERLIB = true;

function random_quote() {
global $CFG;
    $returnme = '<div id="carousel"  data-flickity=\'{ "autoPlay": 10000, "pageDots": false, "imagesLoaded": true, "percentPosition": false, "wrapAround": true }\'>';

    if (!$img = randomimages("images/carousel/")) { return ''; } // No carousel if no images are found.

    $count = count($img) >= 5 ? 5 : count($img); // Images found, find out how many.
	$loading = "";
    // Get enough quotes to add to the images.
    if ($result = get_db_result("SELECT quote, author FROM quotes ORDER BY RAND() LIMIT 0, $count")) {
        while ($row = fetch_row($result)) {
            // Set quotes and authors.
            $quote = empty($row['quote']) ? '' : '<div>' . $row['quote'] . '</div>';
            $author = empty($row['author']) ? '' : '<br /><div style="float:right;">-- ' . $row['author'] . '</div>';

            // Get random image index from $img array.
            $randindex = array_rand($img);
            $returnme .= "<div class='carousel-cell'>" .
                            "<div><img $loading class='carouselslides' src='" . $img[$randindex]."' alt='carousel image with a quote' /></div>" .
                            "<div class='carouselquotes'>" . $quote . $author . "</div>" .
                         "</div>";
            // Unset the random image so it can't be used twice.
            unset($img[$randindex]);
			$loading = "loading='lazy'";
        }
    }
    return $returnme . '</div>';
}

function randomimages($dir) {
    $images = glob($dir . '*.{jpg,jpeg,png,gif,webp,avif}', GLOB_BRACE);
    if (empty(count($images))) { return false; }
    return $images;
}

function load_user_cookie() {
global $CFG, $USER;
	if (!empty($_SESSION['userid'])) { //cookie exists
        $time = get_timestamp();
        $advanced_login = !empty($_SESSION["lia_original"]) ? "" : " AND ($time - last_activity < " . $CFG->cookietimeout.")";
		$SQL = "SELECT * FROM users WHERE userid='" . $_SESSION['userid'] . "' $advanced_login";
        if ($row = get_db_row($SQL)) { //Get user info from db, load into $USER global
            $temp = new \stdClass;
    		$temp->userid = $row['userid'];
    		$temp->fname = $row['fname'];
    		$temp->lname = $row['lname'];
    		$temp->email = $row['email'];
    		$temp->key = $row['userkey'];
    		$temp->ip = $row['ip'];
    		$_SESSION['userid'] = $temp->userid; //Used for CKeditor to know if user is logged in.
		} else {
            $temp = new \stdClass;
            $temp->userid = 0; $_SESSION['userid'] = "";
		}
	} else {
        $temp = new \stdClass;
        $temp->userid = 0; $_SESSION['userid'] = "";
    }
    $USER = $temp;
}

function update_user_cookie() {
global $CFG, $USER;
	$time = get_timestamp();
	if (!is_logged_in()) { //check to see if $USER global is set
        load_user_cookie(); //if $USER global isn't set, see if there is an existing cookie and have it loaded into $USER
		if (is_logged_in()) { //check if $USER global is set now.
            $_SESSION['userid'] = $USER->userid;
			execute_db_sql("UPDATE users SET last_activity='$time' WHERE userid='" . $USER->userid."'"); //update last active timestamp
		} else { //not currently logged in
            $temp = new \stdClass;
            $temp->userid = 0;
            $USER = $temp;
		}
	} else { //User is logged in
        $_SESSION['userid'] = $USER->userid;
		execute_db_sql("UPDATE users SET last_activity='$time' WHERE userid='" . $USER->userid."'"); //update last active timestamp
	}
}

function create_new_user($user) {
global $CFG, $USER;
	if (!isset($COMLIB)) { include_once($CFG->dirroot . '/lib/comlib.php'); }
	$temp = create_random_password();
	$key = md5($user->email) . md5(time());
	$userid = execute_db_sql("INSERT INTO users (email,fname,lname,temp,password,userkey,joined) VALUES('" . dbescape($user->email) . "','" . dbescape($user->fname) . "','" . dbescape($user->lname) . "','" . dbescape($user->password) . "','".md5($temp) . "','$key','" . get_timestamp() . "')");
	$defaultrole = get_db_field("default_role", "pages", "pageid='" . $CFG->SITEID."'");
	$role_assignment = execute_db_sql("INSERT INTO roles_assignment (userid,roleid,pageid) VALUES('$userid','$defaultrole','" . $CFG->SITEID."')");

    if ($userid && $role_assignment) {
    	$USER->userid = $userid;
        $USER->fname = $user->fname;
        $USER->lname = $user->lname;
        $USER->email = $user->email;
        $FROMUSER = new \stdClass;
    	$FROMUSER->fname = $CFG->sitename;
        $FROMUSER->lname = '';
    	$FROMUSER->email = $CFG->siteemail;
    	$message = write_confirmation_email($user, $temp);
    	$subject = $CFG->sitename . ' New User Confirmation';

		if (send_email($USER, $FROMUSER, $subject, $message)) {
			send_email($FROMUSER, $FROMUSER, $subject, $message);
			return "true**" . new_user_confirmation($user);
		}
	} else {
		if ($userid) {
			execute_db_sql("DELETE FROM users WHERE userid='$userid'");
			execute_db_sql("DELETE FROM roles_assignment WHERE userid='$userid'");
		}
		return "false**" . error_string("user_not_added");
	}
}

function create_random_password() {
	//Make random password and activation code
	$pass1 = array("little", "big", "loud", "quiet", "short", "tall", "tiny", "huge", "old", "young", "nice", "mean", "scary", "sneaky", "snooty", "pretty", "happy", "sneezy", "itchy");
	$rnd1 = array_rand($pass1);
	srand ((double) microtime( )*1000000);
	$pass2 = rand(1,9);
	$pass3 = array("cat", "dog", "chicken", "mouse", "deer", "snake", "fawn", "rat", "lion", "tiger", "chipmunk", "owl", "bear", "rooster", "whale", "fish", "puma", "panther", "horse");
	$rnd3 = array_rand($pass3);
	return $pass1[$rnd1] . $pass2 . $pass3[$rnd3];
}

function new_user_confirmation($user) {
global $CFG;
	return '
    	<p><font size="3" face="Tahoma"><strong>' . ucfirst($user->fname) . ' ' . ucfirst($user->lname) . '\'s</strong> account was created </font><font size="3" face="Tahoma" color="#999999">successfully!</font></p>
    	<p><font face="Tahoma">An email has been sent to your email </font><font face="Tahoma" color="#3366ff">(<strong><em>' . $user->email . '</em></strong>) </font><font face="Tahoma">account to verify your ability to check this account.&nbsp; </font></p>
    	<p><font face="Tahoma">Instructions of how to log into your </font><font face="Tahoma"><strong>' . $CFG->sitename . ' </strong></font><font face="Tahoma">account will be given inside this email address.&nbsp; This will includes a randomly generated password that is required to enter the site for the first time.&nbsp; </font></p>
    	<p><font face="Tahoma">After your first login, the password that you specified will automatically be used for every login thereafter. </font></p>
    	<p><br />
    	<font face="Tahoma">Thank you for using <strong>' . $CFG->sitename . '</strong><br />
    	</font></p>
    	';
}

function write_confirmation_email($user, $temp) {
global $CFG;
	return '
        <p><font face="Tahoma"><font size="3" color="#993366">Dear <strong>' . $user->fname . ' ' . $user->lname . '</strong>,</font><br />
    	</font></p>
    	<blockquote>
    	<p><font size="3" face="Tahoma">Thank you for joining <strong>' . $CFG->sitename . '</strong>.&nbsp; To finalize the account creation process, use the following instructions to log into the site.</font></p>
    	</blockquote>
    	<p>&nbsp;</p>
    	<hr width="100%" size="2" />
    	<p>&nbsp;</p>
    	<blockquote>
    	<p align="left"><font face="Tahoma"><strong>Username:</strong> <font color="#3366ff">' . $user->email . '</font></font></p>
    	<p align="left"><font face="Tahoma"><strong>Password:</strong> <font color="#3366ff">' . $temp . '</font></font></p>
    	</blockquote>
    	<p>&nbsp;</p>
    	<hr width="100%" size="2" />
    	<blockquote>
    	<p><font size="3" face="Tahoma">After you have successfully logged into the site using the password provided, your account will be finalized and your password will then be changed to the password you specified inside the account creation form.&nbsp; Again, we would like to thank you for joining <strong>' . $CFG->sitename . '</strong> and if you have any questions during your use of the site, feel free to contact us at <font color="#ff0000">' . $CFG->siteemail . '</font>.<br />
    	</font></p>
    	</blockquote>
    	<p>&nbsp;</p>
    	<p><font face="Tahoma"><strong><font size="3" color="#666699">Enjoy the site,</font></strong></font></p>
    	<p><font size="3" face="Tahoma"><em>' . $CFG->siteowner . ' </em></font><font size="3" face="Tahoma" color="#ff0000">&lt;' . $CFG->siteemail . '</font><font face="Tahoma"><font size="3" color="#ff0000">&gt;</font></font></p>
    	<p>&nbsp;</p>';
}

function get_user_name($userid) {
	if ($user = get_db_row("SELECT * FROM users WHERE userid='$userid' LIMIT 1")) { return $user['fname'] . " " . $user['lname']; }
	return "Anonymous";
}

function is_logged_in($userid = false) {
global $CFG, $USER;
	if (!$userid) {
		if (empty($USER->userid)) { 
			return false; 
		}
		$userid = $USER->userid;
	}

	$userid = $userid ? $userid : $USER->userid;
	if (!empty($userid)) {
	   recursive_mkdir($CFG->userfilespath . '/' . $userid);
       return true;
    }
	return false;
}

function nameize($str, $a_char = ["'", "-", " ", '"', '.']) {
    $str = stripslashes(trim($str));
    $str = preg_replace('!\s+!', ' ', $str);
    $name_parts = explode(" ", $str);

    if (count($name_parts) > 1) {
        $output = "";
        foreach ($name_parts as $np) {
            $output .= nameize($np) . " ";
        }
        return trim($output);
    } else {
        //the tricky part is finding names like DeMarco: 2 capitals
    	for ($i=0;$i<strlen($str);$i++) {
    		if ($i > 0 && ctype_lower($str[($i - 1)]) && ctype_upper($str[$i]) && isset($str[($i + 1)]) && ctype_lower($str[($i + 1)])) {
    			$temp = $str;
    			$str = substr($temp,0,($i)) . "+ " . substr($temp,($i),(strlen($str)-($i)));
    			$i++; $i++;
    		}
    	}

    	//$str contains the complete raw name string
        //$a_char is an array containing the characters we use as separators for capitalization. If you don't pass anything, there are three in there as default.
    	$string = strtolower($str);
        foreach ($a_char as $temp) {
            $pos = strpos($string, $temp);
            if ($pos !== -1) {
                //we are in the loop because we found one of the special characters in the array, so lets split it up into chunks and capitalize each one.
                $mend = '';
                $a_split = explode($temp, $string);
                foreach ($a_split as $temp2) {
                    //capitalize each portion of the string which was separated at a special character
                    $mend .= ucfirst($temp2).$temp;
                }
                $string = substr($mend,0,-1);
            }
        }

        $str = "";
       	for ($i=0;$i<strlen($string);$i++) {
    		if (array_search($string[$i], $a_char)) {
                if ($string[$i] !== $string[(strlen($string)-$i - 1)]) {
                    $str .= $string[$i];
                }
    		} else {
                $str .= $string[$i];
    		}
    	}
        $str = str_replace("+ ", "", $str);
        $str = str_replace("+", "", $str);
        $str = str_replace('""','"', $str);
        return trim(ucfirst($str));
    }
}
?>
