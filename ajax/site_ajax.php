<?php
/***************************************************************************
* site_ajax.php - Main backend ajax script.  Usually sends off to feature libraries.
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
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
			$reroute = use_template("tmp/site_ajax.template", ["userid" => $row["userid"], "password" => $password], "password_change_reroute_template");
		}
  		echo 'true**' . $reroute;
	} else {
		echo "false**" . error_string("no_login");
	}
}

function unique_email() {
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
				 SET alternate = '', password = '$password'
			 WHERE userid = '$userid'";

	$success = false;
	if ($success = execute_db_sql($SQL)) {
		log_entry("user", null, "Password changed"); // Log
	} else {
		log_entry("user", null, "Password change failed"); // Log
	}

	echo use_template("tmp/site_ajax.template", ["success" => $success], "reset_password_passfail_template");
}

function change_profile() {
global $CFG, $MYVARS;
	$userid = dbescape($MYVARS->GET["userid"]);
	$email = dbescape($MYVARS->GET["email"]);
	$fname = dbescape(nameize($MYVARS->GET["fname"]));
	$lname = dbescape(nameize($MYVARS->GET["lname"]));

	$success = false;
	$notused = false;

	$SQL = "SELECT *
			FROM users
			WHERE email = '$email'
			AND userid != '$userid'";
	if (!get_db_row($SQL)) { // email address isn't being used by another user
		$passchanged = empty($MYVARS->GET["password"]) ? false : true;
		$password = md5($MYVARS->GET["password"]);
		$passwordsql = $passchanged ? ", alternate = '', password = '$password'" : "";

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
	echo use_template("tmp/site_ajax.template", ["success" => $success, "notused" => $notused], "change_profile_success_template");
}

function save_settings() {
global $CFG, $MYVARS;
	$settingid = dbescape($MYVARS->GET["settingid"]);
	$setting = dbescape((urldecode($MYVARS->GET["setting"])));
	$extra = isset($MYVARS->GET["extra"]) ? dbescape((urldecode($MYVARS->GET["extra"]))) : false;

	if ($success = save_setting($settingid, [], $setting, $extra)) {
		log_entry("setting", $settingid . ":" . $setting, "Setting Changed"); // Log
	}
	echo use_template("tmp/page.template", ["wwwroot" => $CFG->wwwroot, "success" => $success], "setting_change_template");
}

/**
 * Reset user's password if email is valid and account is activated.
 *
 * If email matches an existing user, a new random password is generated
 * and sent to the user via email. The new password is also saved in the
 * database. If the user's account is not activated, a new password will be
 * generated and saved, but not sent to the user via email.
 *
 * @global \stdClass $CFG The global config object. Contains site settings.
 * @global \stdClass $MYVARS The global MYVARS object. Contains all the user-inputted variables
 * from the URL.
 */
function forgot_password() {
global $CFG;
	$admin = clean_myvar_opt("admin", "bool", false);
	$userid = clean_myvar_opt("userid", "int", false);
	$email = clean_myvar_opt("email", "string", false);
	$success = false;

	try {
		start_db_transaction();
		// Load COMLIB if it isn't already loaded.
		if (!defined('COMLIB')) { include_once ($CFG->dirroot . '/lib/comlib.php'); }

		// If the admin request is valid, use the userid parameter to retrieve the email address from the database.
		if ($admin && $userid) {
			$email = get_db_field("email", "users", "userid = ||userid||", ["userid" => $userid]);
		}

		// Make sure we have an email address to work with.
		if ($email) {
			// Check to see if email matches an existing user.
			if ($user = get_db_row("SELECT * FROM users WHERE email = ||email||", ["email" => $email])) {
				// Generate a new random password
				$alternate = create_random_password();

				// Check to see if account is activated. If so, save the new password in the database.
				// Place new password in password field (if real password is still in temp field)
				// Plane new password in alternate field (if temp field is empty, meaning real password is in password field) 
				$field = empty(trim($user["temp"])) ? "alternate" : "password";
				$SQL = "UPDATE users SET $field = ||password|| WHERE email = ||email||";
				$reset = execute_db_sql($SQL, ["password" => md5($alternate), "email" => $email]);

				if (!$reset) {
					throw new \Exception("Could not reset password.");
				}

				// Email new password to the email address.
				$SITEUSER = new \stdClass;
				$SITEUSER->fname = $CFG->sitename;
				$SITEUSER->lname = '';
				$SITEUSER->email = $CFG->siteemail;

				$params = [
					"user" => $user,
					"email" => $email,
					"alternate" => $alternate,
					"sitename" => $CFG->sitename,
					"siteowner" => $CFG->siteowner,
					"siteemail" => $CFG->siteemail,
				];
				$message = use_template("tmp/site_ajax.template", $params, "forgot_password_email_template");

				$subject = $CFG->sitename . ' Password Reset';
				
				if (@send_email($user, $SITEUSER, $subject, $message)) {
					$success = true;
					@send_email($SITEUSER, $SITEUSER, $subject, $message); // Send a copy to the site admin
					log_entry("user", $user->email, "Password Reset"); // Log
				}
			}
		}
		commit_db_transaction();
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
		$success = false;
	}

	$params = [
		"wwwroot" => $CFG->wwwroot,
		"success" => $success,
		"user" => $user,
		"admin" => $admin,
	];
	echo use_template("tmp/site_ajax.template", $params, "forgot_password_submitted_template");
}

function add_new_user() {
	$email = clean_myvar_req("email", "string");
	$fname = clean_myvar_req("fname", "string");
	$lname = clean_myvar_req("lname", "string");
	$password = clean_myvar_req("password", "string");

	if (!$email || !$fname || !$lname || !$password) {
		echo "false";
		return;
	}

	// Add the new user to the database.
	$newuser = [
		"email" => $email,
		"fname" => $fname,
		"lname" => $lname,
		"password" => $password,
	];

	echo create_new_user($newuser);
}

/**
 * Delete a user.
 *
 * This function deletes a user from the database. The user can be
 * deleted if they are not themselves or an admin account.
 *
 * @global \stdClass $MYVARS The global MYVARS object. Contains all the user-inputted variables
 * from the URL.
 * @global \stdClass $USER The global USER object. Contains user information.
 */
function delete_user() {
	$userid = clean_myvar_opt("userid", "int", false);
	$user = false;

	$yourself = ($USER->userid == $userid);
	$admin = is_siteadmin($userid);

	// Can't delete yourself or an admin account.
	if (!$yourself && !is_siteadmin($userid)) {
		try {
			start_db_transaction();
			// Get the user's information from the database.
			$user = get_db_row(fetch_template("dbsql/users.sql", "get_user"), ["userid" => $userid]);

			// Delete the user from the database.
			execute_db_sql(fetch_template("dbsql/users.sql", "delete_user"), ["userid" => $userid]);

			// Remove all roles from the user.
			remove_all_roles($userid);

			// Delete all logs of the user.
			execute_db_sql(fetch_template("dbsql/users.sql", "delete_user_logs"), ["userid" => $userid]);
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		}
	}

	$params = ["yourself" => $yourself, "admin" => $admin, "user" => $user];
	echo use_template("tmp/site_ajax.template", $params, "delete_user_template");
}

function refresh_user_alerts() {
	$userid = clean_myvar_req("userid", "int");
	if ($userid) {
		echo get_user_alerts($userid, false);
	}
}

function allow_page_request() {
	$requestid = clean_myvar_opt("approve", "int", false);
	$approve = clean_myvar_opt("approve", "int", false);

	if (!$requestid) {
		echo "false";
		return;
	}
	
	if ($approve) { // confirmed request
		$SQL = fetch_template("dbsql/roles.sql", "confirm_role_assignment");
	} else { // denied request
		$SQL = fetch_template("dbsql/roles.sql", "remove_role_assignment");
	}

	if (execute_db_sql($SQL, ["assignmentid" => $requestid])) {
		emptyreturn();
	} else {
		echo "false";
	}
}

function get_login_box() {
global $USER;
	$logout = clean_myvar_opt("logout", "int", 0);
	if ($logout) {
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
	$pageid = clean_myvar_req("pageid", "int") ?? get_pageid();
	$check = clean_myvar_opt("check", "int", false);
	if (is_logged_in()) {
		if ($check) {
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
	$cname = clean_myvar_opt("cname", "string", false);
	if ($cname && isset($_COOKIE["$cname"])) {
		echo $_SESSION["$cname"];
	}
	emptyreturn();
}

function addfeature() {
global $MYVARS;
	update_user_cookie();
	add_page_feature($MYVARS->GET["pageid"], $MYVARS->GET["feature"]);
}

function delete_feature() {
global $PAGE;
	update_user_cookie();
    $type = clean_myvar_req("featuretype", "string");
    $pageid = clean_myvar_opt("pageid", "int", false);
	$featureid = clean_myvar_opt("featureid", "int", false);
	$subid = clean_myvar_opt("subid", "int", false);

	$featuretype = str_replace("_features", "", $type);
	$action = $featuretype . "_delete";

	$var1 = $pageid ?? "#false#";
	$var2 = $featureid ?? "#false#";
	$var3 = $subid ?? "#false#";

	all_features_function(false, $featuretype, "", "_delete", false, $var1, $var2, $var3);
	log_entry($featuretype, null, "Deleted Feature"); // Log
}

function change_locker_state() {
	update_user_cookie();
	$pageid = clean_myvar_req("pageid", "int") ?? get_pageid();
	$feature = clean_myvar_req("featuretype", "string");
	$featureid = clean_myvar_req("featureid", "int");
	$direction = clean_myvar_req("direction", "string");

	try {
		start_db_transaction();
		if (set_pageid($pageid)) {
			// Every sql below uses the same where clause.
			$where = "pageid = ||pageid|| AND feature = ||feature|| AND featureid = ||featureid||";
			$params = ["pageid" => $pageid, "feature" => $feature, "featureid" => $featureid];

			$current_position = get_db_field("sort", "pages_features", $where, $params);
			$area = get_db_field("area", "pages_features", $where, $params);
			if ($direction == 'released') {
				execute_db_sql("UPDATE pages_features SET area = 'middle', sort = '9999' WHERE $where", $params);
				resort_page_features($pageid);
				log_entry($featuretype, $featureid, "Released from Blog Locker"); // Log
			} elseif ($direction == 'locker') {
				execute_db_sql("UPDATE pages_features SET area = 'locker' WHERE $where", $params);
				resort_page_features($pageid);
				log_entry($featuretype, $featureid, "Moved to Blog Locker"); // Log
			}
		}
		commit_db_transaction();
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
	}

	emptyreturn();
}

function drop_move_feature() {
	$pageid = clean_myvar_req("pageid", "int") ?? get_pageid();
	$col1 = clean_myvar_req("col1", "array") ?? false;
	$col2 = clean_myvar_req("col2", "array") ?? false;
	$moved = clean_myvar_req("moved", "string") ?? false;

	if (!$pageid || !$col1 || !$col2 || !$moved) {
		emptyreturn();
	}

	try {
        start_db_transaction();
		$moved = explode("_", $moved);
		$movedtype = empty($moved[0]) ? false : $moved[0];
		$movedid = empty($moved[1]) ? false : $moved[1];

		$params = [
			"pageid" => $pageid,
			"column" => $col1,
			"movedtype" => $movedtype,
			"movedid" => $movedid,
			"area" => "middle",
		];
		move_features($params);

		$params["area"] = "side";
		$params["column"] = $col2;
		move_features($params);

		log_entry($movedtype, $movedid, "Move Feature"); // Log
		commit_db_transaction();
		echo "moved";
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
		echo "error on move feature";
	}
}
?>