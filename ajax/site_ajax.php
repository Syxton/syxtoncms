<?php
/***************************************************************************
* site_ajax.php - Main backend ajax script.  Usually sends off to feature libraries.
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/18/2021
* Revision: 2.9.8
***************************************************************************/

include('header.php');

callfunction();

function login() {
global $MYVARS;
	$username = dbescape($MYVARS->GET["username"]);
	$password = md5($MYVARS->GET["password"]);
	if ($row = authenticate($username, $password)) {
		$reroute = '';
		if ($row["alternate"] == $password) {
			$reroute = template_use("templates/site_ajax.template", array("userid" => $row["userid"], "password" => $password), "password_change_reroute_template");
		}
    echo 'true**' . $reroute;
	} else {
		echo "false**" . get_error_message("no_login");
	}
}

function unique_email(){
global $MYVARS;
	$email = dbescape($MYVARS->GET["email"]);
	$SQL = "SELECT *
						FROM users
					 WHERE email = '$email'";

	if (get_db_count($SQL)) {
		echo "false";
	} else {
		echo "true";
	}
}

function reset_password() {
global $MYVARS;
	$userid = dbescape($MYVARS->GET["userid"]);
	$password = md5($MYVARS->GET["password"]);

	$SQL = "UPDATE users
						 SET alternate = '',
						 		 password = '$password'
					 WHERE userid = '$userid'";

	$success = false;
	if ($success = execute_db_sql($SQL)) {
		log_entry("user", null, "Password changed"); // Log
	} else {
		log_entry("user", null, "Password change failed"); // Log
	}

	echo template_use("templates/site_ajax.template", array("success" => $success), "reset_password_passfail_template");
}

function change_profile() {
global $CFG, $MYVARS;
  $userid = dbescape($MYVARS->GET["userid"]);
	$email = dbescape($MYVARS->GET["email"]);
	$fname = dbescape(nameize($MYVARS->GET["fname"]));
	$lname = dbescape(nameize($MYVARS->GET["lname"]));
  $passchanged = empty($MYVARS->GET["password"]) ? false : true;
  $password = md5($MYVARS->GET["password"]);
  $passwordsql = $passchanged ? ", alternate = '', password = '$password'" : "";
	$success = false; $notused = false;

	$SQL = "SELECT *
						FROM users
					 WHERE email = '$email'
					 	 AND userid != '$userid'";
  if (!get_db_row($SQL)) { // email address isn't being used by another user
		$SQL = "UPDATE users
							 SET fname = '$fname',
							 		 lname = '$lname',
									 email = '$email'
									 $passwordsql
						 WHERE userid = '$userid'";
		$notused = true;
    if ($success = execute_db_sql($SQL)) {
      log_entry("user", null, "Profile changed"); // Log
    } else {
      log_entry("user", null, "Profile change failed"); // Log
    }
  }
	echo template_use("templates/site_ajax.template", array("success" => $success, "notused" => $notused), "change_profile_template");
}

function save_settings() {
global $CFG, $MYVARS;
	$settingid = dbescape($MYVARS->GET["settingid"]);
	$setting = dbescape((urldecode($MYVARS->GET["setting"])));
	$extra = isset($MYVARS->GET["extra"]) ? dbescape((urldecode($MYVARS->GET["extra"]))) : false;

	if ($success = make_or_update_setting($settingid, false, false, false, false, $setting, $extra, false)) {
		log_entry("setting", $settingid . ":" . $setting, "Setting Changed"); // Log
	}
	echo template_use("templates/page.template", array("wwwroot" => $CFG->wwwroot, "success" => $success), "setting_change_template");
}

function forgot_password() {
global $CFG, $MYVARS;
	if (!isset($COMLIB)) { include_once ($CFG->dirroot . '/lib/comlib.php'); }
  $admin = isset($MYVARS->GET["admin"]) ? true : false;
  if ($admin && isset($MYVARS->GET["userid"])) {
      $MYVARS->GET["email"] = get_db_field("email", "users", "userid='" . $MYVARS->GET["userid"] . "'");
  }

  if (isset($MYVARS->GET["email"])) {
    $email = dbescape($MYVARS->GET["email"]);

	 	// Check to see if email matches an existing user.
		$SQL = "SELECT *
							FROM users
						 WHERE email = '$email'";

		if ($user = get_db_row($SQL)) {
  		$alternate = create_random_password();

      // Check to see if account is activated
  		if (strlen($user["temp"]) > 0) {
				$SQL = "UPDATE users
									 SET password = '" . md5($alternate) . "'
								 WHERE email = '$email'";
        $userid = execute_db_sql($SQL);
  		} else {
				$SQL = "UPDATE users
									 SET alternate = '" . md5($alternate) . "'
								 WHERE email = '$email'";
  			$userid = execute_db_sql($SQL);
      }

  		// Email new password to the email address.
			$TOUSER = new \stdClass;
  		$TOUSER->userid = $user['userid'];
  		$TOUSER->fname = $user['fname'];
  		$TOUSER->lname = $user['lname'];
  		$TOUSER->email = $email;

      $FROMUSER = new \stdClass;
  		$FROMUSER->fname = $CFG->sitename;
  		$FROMUSER->lname = '';
  		$FROMUSER->email = $CFG->siteemail;

			$params = array("user" => $user, "email" => $email, "alternate" => $alternate, "sitename" => $CFG->sitename, "siteowner" => $CFG->siteowner, "siteemail" => $CFG->siteemail);
			$message = template_use("templates/site_ajax.template", $params, "forgot_password_email_template");

  		$subject = $CFG->sitename . ' Password Reset';
			$success = false;
  		if (!$userid || send_email($TOUSER, $FROMUSER, null, $subject, $message)) {
				$success = true;
  			send_email($FROMUSER, $FROMUSER, null, $subject, $message); // Send a copy to the site admin
  			log_entry("user", $TOUSER->email, "Password Reset"); // Log
  		}
  	}
		echo template_use("templates/site_ajax.template", array("wwwroot" => $CFG->wwwroot, "success" => $success, "user" => $user, "admin" => $admin), "forgot_password_template");
  }
}

function add_new_user() {
global $MYVARS;
  $newuser = new \stdClass;
	$newuser->email = trim($MYVARS->GET["email"]);
	$newuser->fname = nameize($MYVARS->GET["fname"]);
	$newuser->lname = nameize($MYVARS->GET["lname"]);
	$newuser->password = md5(trim($MYVARS->GET["password"]));
	echo create_new_user($newuser);
}

function delete_user() {
global $MYVARS, $USER;
	$userid = $MYVARS->GET["userid"];
	$user = false;

	$yourself = ($USER->userid == $userid);
	$admin = is_siteadmin($userid);

	if (!$yourself && !$admin) { // Can't delete yourself or an admin account.
		$SQL = "SELECT *
							FROM users
						 WHERE userid = '$userid'";
		if ($user = get_db_row($SQL)) {
      $SQL = "DELETE
								FROM users
							 WHERE userid = '$userid'";
      if (execute_db_sql($SQL)) {
        //Remove all role assignments on site
      	remove_all_roles($userid);
        //Delete all logs of the user
        $SQL = "DELETE
									FROM logfile
								 WHERE userid = '$userid'";
        execute_db_sql($SQL);
      }
  	}
	}
	$params = array("yourself" => $yourself, "admin" => $admin, "user" => $user);
	echo template_use("templates/site_ajax.template", $params, "delete_user_template");
}

function refresh_user_alerts() {
global $MYVARS;
  $userid = empty($MYVARS->GET["userid"]) ? false : $MYVARS->GET["userid"];
  get_user_alerts($userid, false, false);
}

function allow_page_request() {
global $MYVARS;
  $approve = empty($MYVARS->GET["approve"]) ? false : true;
  $requestid = empty($MYVARS->GET["requestid"]) ? false : $MYVARS->GET["requestid"];

  if ($approve) { // confirmed request
      $SQL = "UPDATE roles_assignment
								 SET confirm = 0
							 WHERE assignmentid = '$requestid'";
  } else { // denied request
      $SQL = "DELETE
								FROM roles_assignment
							 WHERE assignmentid = '$requestid'";
  }

  if (execute_db_sql($SQL)) {
      donothing();
  } else {
      echo "false";
  }
}

function subscribe() {
global $MYVARS;
	update_user_cookie();
	echo subscribe_to_page($MYVARS->GET["pageid"]);
}

function get_login_box() {
global $USER, $MYVARS;
	if (isset($MYVARS->GET["logout"])) {
    $_SESSION['userid'] = "0";
    session_destroy();
    session_write_close();
    unset($USER);
		log_entry("user", null, "Logout"); // Log
	}
	echo get_login_form();
}

function update_login_contents() {
global $USER, $MYVARS;
  $pageid = !empty($MYVARS->GET['pageid']) ? $MYVARS->GET['pageid'] : $_SESSION["pageid"];
	if (is_logged_in()) {
		if (isset($MYVARS->GET['check'])) {
      if (isset($_SESSION['userid'])) {
          $USER->userid = $_SESSION['userid'];
          echo "true**check";
      } else {
          load_user_cookie();
          echo "false";
      }
		} else {
			update_user_cookie();
			echo "true**" . print_logout_button($USER->fname, $USER->lname, $pageid);
		}
	} else { //Cookie has timed out or they haven't logged in yet.
  	load_user_cookie();
		echo "false";
	}
}

function get_cookie() {
global $MYVARS;
  $cname = $MYVARS->GET['cname'];
  if (isset($_SESSION["$cname"])) {
      echo $_SESSION["$cname"];
  }
  donothing();
}

function addfeature() {
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

	all_features_function(false, $featuretype, "", "_delete", false, $MYVARS->GET["pageid"], $MYVARS->GET["featureid"], $MYVARS->GET["sectionid"]);
	log_entry($featuretype, null, "Deleted Feature"); // Log
}

function move_feature() {
global $MYVARS;
	update_user_cookie();
	move_page_feature($MYVARS->GET["pageid"], $MYVARS->GET["featuretype"], $MYVARS->GET["featureid"], $MYVARS->GET["direction"]);
}

function drop_move_feature() {
global $MYVARS;
	$pageid = empty($MYVARS->GET["pageid"]) ? $_SESSION["pageid"] : $MYVARS->GET["pageid"];
  $col1 = empty($MYVARS->GET["col1"]) ? false : $MYVARS->GET["col1"];
  $col2 = empty($MYVARS->GET["col2"]) ? false : $MYVARS->GET["col2"];
  $moved = empty($MYVARS->GET["moved"]) ? false : $MYVARS->GET["moved"];
  $refresh = false;

  $moved = explode("_", $moved);
  $movedtype = empty($moved[0]) ? false : $moved[0];
  $movedid = empty($moved[1]) ? false : $moved[1];

  $area = "middle"; $i = 1;
  foreach ($col1 as $a) {
    $a = explode("_", $a);
    $featuretype = empty($a[0]) ? false : $a[0];
    $featureid = empty($a[1]) ? false : $a[1];

		$SQL = "SELECT *
							FROM pages_features
						 WHERE pageid = '$pageid'
						 	 AND feature = '$featuretype'
							 AND featureid = '$featureid'";
    $current = get_db_row($SQL);
    if ($movedtype == $featuretype && $movedid == $featureid && $current["area"] != $area) {
      $refresh = true;
    }

		$SQL = "UPDATE pages_features
							 SET sort = '$i',
							 		 area = '$area'
						 WHERE id = '" . $current["id"] . "'";
    execute_db_sql($SQL);
    $i++;
  }

  $area = "side"; $i = 1;
  foreach($col2 as $a){
    $a = explode("_", $a);
    $featuretype = empty($a[0]) ? false : $a[0];
    $featureid = empty($a[1]) ? false : $a[1];
		$SQL = "SELECT *
							FROM pages_features
						 WHERE pageid = '$pageid'
						 	 AND feature = '$featuretype'
							 AND featureid = '$featureid'";
    $current = get_db_row($SQL);
    if ($movedtype == $featuretype && $movedid == $featureid && $current["area"] != $area) {
      $refresh = true;
    }

		$SQL = "UPDATE pages_features
							 SET sort = '$i',
							 		 area = '$area'
						 WHERE id = '" . $current["id"] . "'";
    execute_db_sql($SQL);
    $i++;
  }

  if ($refresh) {
  	echo "refresh";
  } else {
    echo "done";
  }

	log_entry($featuretype, $featureid, "Move Feature"); // Log
}
?>
