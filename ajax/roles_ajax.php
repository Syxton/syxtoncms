<?php
/***************************************************************************
* roles_ajax.php - Roles Ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.0.4
***************************************************************************/

include ('header.php');
update_user_cookie();

callfunction();

/**
 * Performs a name search for users to add to a group
 *
 * @global \stdClass $CFG The global config object
 * @global \stdClass $USER The current user object
 * @global \stdClass $MYVARS The global $_GET and $_POST array
 * @return void
 */
function name_search() {
global $CFG, $USER, $MYVARS;
	 // Search for users based on name, email, or username
	 $pageid = clean_myvar_opt("pageid", "int", false); // The page to search on
	 $type = clean_myvar_opt("type", "string", "per_page_"); // The type of search being performed (per_page_ or feature specific)
	 $featureid = clean_myvar_opt("featureid", "int", false); // The feature ID, if feature specific
	 $feature = clean_myvar_opt("feature", "string", false); // The feature name, if feature specific

	 $searchstring = "";
	 $searcharray = explode(" ", $MYVARS->GET["searchstring"]);
	 $i = 0;
	 foreach ($searcharray as $search) {
		  $searchstring .= $i == 0 ? "" : " OR ";
		  $searchstring .= "(fname LIKE '%$search%' OR lname LIKE '%$search%' OR email LIKE '%$search%')";
		  $i++;
	 }

	 $fields = "u.userid, u.fname, u.lname, u.email";
	 if ($pageid == $CFG->SITEID && is_siteadmin($USER->userid)) {
		  // If admin on site, search all users
		  $SQL = "SELECT $fields
						FROM users u
					  WHERE $searchstring
				  ORDER BY u.lname";
	 } else {
		  // Get the user's role on the page
		  $myroleid = user_role($USER->userid, $pageid);
		  if ($type != "per_page_") { // Feature specific role assignment search. (only searches people that already have page privs)
				// Search for users with a higher role on the page
				$SQL = "SELECT $fields
							 FROM users u
							WHERE $searchstring
							  AND u.userid IN (SELECT ra.userid
														FROM roles_assignment ra
													  WHERE ra.pageid = '$pageid'
														 AND ra.roleid > '$myroleid')  
						ORDER BY u.lname";
		  } else {  // Page role assignment search.
				// Search for users with a role lower than the user's on the page
				$SQL = "SELECT $fields
							 FROM users u
							WHERE $searchstring
							  AND u.userid NOT IN (SELECT ra.userid
															 FROM roles_assignment ra
															WHERE ra.pageid = '$pageid'
															  AND ra.roleid <= '$myroleid')
						ORDER BY u.lname";
		  }
	 }

	 // Add the search results to the page template
	 $params = [ "refreshroles" => (isset($MYVARS->GET["refreshroles"]) && $MYVARS->GET["refreshroles"] == "refreshroles"),
					 "type" => $type,
					 "pageid" => $pageid,
					 "featureid" => $featureid,
					 "feature" => $feature,
	 ];
	 $options = "";
	 if ($users = get_db_result($SQL)) {
		  while ($row = fetch_row($users)) {
				$vars = [ "selected" => "",
							 "value" => $row['userid'],
							 "display" => fill_string("{fname} {lname} ({email})", $row),
				];
				$options .= fill_template("tmp/page.template", "select_options_template", false, $vars);
		  }
	 }
	 $params["options"] = $options;
	 echo fill_template("tmp/roles_ajax.template", "name_search_template", false, $params);
}

function add_to_group_search() {
global $CFG, $ROLES, $USER, $MYVARS;
  $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
  $type = clean_myvar_opt("type", "string", "per_page_"); //Should always be passed
  $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
  $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
  $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

  $searchstring = "";	$searcharray = explode(" ", $MYVARS->GET["searchstring"]);
  $i=0;
  foreach ($searcharray as $search) {
	 $searchstring .= $i == 0 ? "" : " OR ";
	 $searchstring .= "(fname LIKE '%$search%' OR lname LIKE '%$search%' OR email LIKE '%$search%')";
	 $i++;
  }

	$myroleid = user_role($USER->userid, $pageid);
  $SQL = "SELECT u.*
				FROM users u
			  WHERE $searchstring
				 AND ('$pageid' = '$CFG->SITEID'
						OR u.userid IN (SELECT ra.userid
												FROM roles_assignment ra
											  WHERE ra.pageid = '$pageid'))
				 AND u.userid NOT IN (SELECT ra.userid
												FROM roles_assignment ra
											  WHERE ra.pageid = '$CFG->SITEID'
												 AND ra.roleid = '$ROLES->admin')
				 AND u.userid NOT IN (SELECT ra.userid
												FROM roles_assignment ra
											  WHERE ra.pageid = '$pageid'
												 AND ra.roleid <= '$myroleid')
				 AND u.userid != '$USER->userid'
				 AND u.userid NOT IN (SELECT userid
												FROM groups_users
											  WHERE groupid = '$groupid')
		  ORDER BY u.lname";

  $options = '';
	if ($users = get_db_result($SQL)) {
		while ($row = fetch_row($users)) {
		$mygroups = "";
		$SQL = "SELECT *
					 FROM `groups`
					WHERE groupid IN (SELECT groupid
											  FROM groups_users
											 WHERE userid = '" . $row['userid'] . "'
												AND pageid = '$pageid')";
		if ($groups = get_db_result($SQL)) {
		  while ($group_info = fetch_row($groups)) {
				$mygroups .= " " . $group_info["name"];
		  }
		}

		$params = [
			 "selected" => "",
			 "value" => $row['userid'],
			 "display" => fill_string("{fname} {lname} ({email})", $row) . $mygroups,
		];
		$options .= fill_template("tmp/page.template", "select_options_template", false, $params);
		}
	}

  echo fill_template("tmp/roles_ajax.template", "add_to_group_search_template", false, ["options" => $options]);
}

function refresh_group_users() {
global $CFG, $MYVARS, $USER;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	$groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
	$featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	$feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

	$sqlparams = [];
	$sqlparams["pageid"] = $pageid;
	$sqlparams["groupid"] = $groupid;
 	if ($pageid == $CFG->SITEID && is_siteadmin($USER->userid)) {
		$SQL = "SELECT u.*
					FROM users u
					WHERE u.userid IN (
											SELECT userid
											FROM groups_users
											WHERE pageid = ||pageid||
											AND groupid = ||groupid||
											)
					ORDER BY u.lname";
	} else {
		$sqlparams["siteid"] = $CFG->SITEID;
		$SQL = "SELECT u.*
					FROM users u
					WHERE (
							||pageid|| = ||siteid||
							OR u.userid IN (
												SELECT ra.userid
												FROM roles_assignment ra
												WHERE ra.pageid = ||pageid||
												)
							)
					AND u.userid IN (
										SELECT userid
										FROM groups_users
										WHERE pageid = ||pageid||
										AND groupid = ||groupid||
										)
					ORDER BY u.lname";
	}

	$options = '';
	if ($users = get_db_result($SQL, $sqlparams)) {
		while ($row = fetch_row($users)) {
			$p = [
				"value" => $row['userid'],
				"display" => fill_string("{fname} {lname} ({email})", $row),
			];
			$options .= fill_template("tmp/page.template", "select_options_template", false, $p);
		}
	} else {
		$p = [
			"selected" => "",
			"value" => "0",
			"display" => "No users in this group.",
		];
		$options .= fill_template("tmp/page.template", "select_options_template", false, $p);
	}

	$params = [
		"wwwroot" => $CFG->wwwroot,
		"groupname" => get_db_field("name", "groups", "groupid = ||groupid||", ["groupid" => $groupid]),
		"pageid" => $pageid,
		"groupid" => $groupid,
		"feature" => $feature,
		"featureid" => $featureid,
		"canmanage" => user_is_able($USER->userid, "manage_groups", $pageid),
		"options" => $options,
	];
	echo fill_template("tmp/roles_ajax.template", "refresh_group_users_template", false, $params);
}

function manage_group_users_form() {
global $CFG, $MYVARS;
	 $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	 $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
	 $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	 $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

	 echo refresh_manage_groups($pageid, $groupid, $feature, $featureid);
}

function add_group_user() {
global $CFG, $MYVARS;
	 $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	 $userid = clean_myvar_opt("userid", "int", false); //Should always be passed
	 $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
	 $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	 $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

	 $SQL = "INSERT INTO groups_users (userid, pageid, groupid)
					 VALUES('$userid', '$pageid', '$groupid')";
	 execute_db_sql($SQL);

	 echo refresh_manage_groups($pageid, $groupid);
}

function remove_group_user() {
global $CFG, $MYVARS;
	 $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	 $userid = clean_myvar_opt("userid", "int", false); //Should always be passed
	 $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
	 $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	 $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

	 $SQL = "DELETE
				FROM groups_users
				WHERE userid = '$userid'
				AND pageid = '$pageid'
				AND groupid = '$groupid'";
	 execute_db_sql($SQL);

	 echo refresh_manage_groups($pageid, $groupid);
}

function refresh_manage_groups($pageid, $groupid, $feature = false, $featureid = false) {
global $CFG, $MYVARS, $ROLES, $USER;
	 $myroleid = user_role($USER->userid, $pageid);
	 $SQL = "SELECT u.*
				  FROM users u
				 WHERE ('$pageid' = '$CFG->SITEID'
						  OR u.userid IN (SELECT ra.userid
												  FROM roles_assignment ra
												 WHERE ra.pageid = '$pageid'))
					AND u.userid NOT IN (SELECT ra.userid
												  FROM roles_assignment ra
												 WHERE ra.pageid = '$pageid'
													AND ra.roleid <= '$myroleid')
					AND u.userid NOT IN (SELECT userid
												  FROM groups_users
												 WHERE groupid = '$groupid')
			 ORDER BY u.lname";

	 $options1 = "";
	if ($pageid == $CFG->SITEID) {
		  $p = [
				"selected" => "selected",
				"value" => "0",
				"display" => "Search results will be shown here.",
		  ];
		  $options1 = fill_template("tmp/page.template", "select_options_template", false, $p);
	 } elseif ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
				$mygroups = "";
				$SQL = "SELECT *
								FROM groups
						  WHERE groupid IN (SELECT groupid
													 FROM groups_users
													 WHERE userid = '" . $row['userid'] . "'
														  AND pageid = '$pageid')";
				if ($groups = get_db_result($SQL)) {
					 while ($group_info = fetch_row($groups)) {
						  $mygroups .= fill_string(" {name}", $group_info);
					 }
				}
				$p = ["selected" => "",
						  "value" => $row['userid'],
						  "display" => fill_string("{fname} {lname} ({email})", $row) . $mygroups,
				];
				$options1 .= fill_template("tmp/page.template", "select_options_template", false, $p);
		}
	}

	 $options2 = "";
	 $SQL = "SELECT u.*
				FROM users u
				WHERE u.userid NOT IN (SELECT ra.userid
											  FROM roles_assignment ra
											  WHERE ra.pageid = '$pageid'
											  AND ra.roleid <= '$myroleid')
				AND u.userid IN (SELECT userid
									  FROM groups_users
									  WHERE groupid = '$groupid')
				ORDER BY u.lname";

	 if ($roles = get_db_result($SQL)) {
		  while ($row = fetch_row($roles)) {
				$mygroups = "";
				$SQL = "SELECT *
						  FROM `groups`
						  WHERE groupid IN (SELECT groupid
												  FROM groups_users
												  WHERE userid = '" . $row['userid'] . "'
												  AND pageid='$pageid')";
				if ($groups = get_db_result($SQL)) {
					 while ($group_info = fetch_row($groups)) {
						  $mygroups .= fill_string(" {name}", $group_info);
					 }
				}
				$p = ["selected" => "",
						  "value" => $row['userid'],
						  "display" => fill_string("{fname} {lname} ({email})", $row) . $mygroups,
				];
				$options2 .= fill_template("tmp/page.template", "select_options_template", false, $p);
		  }
	}

	 $params = [
		  "wwwroot" => $CFG->wwwroot,
		  "groupname" => get_db_field("name", "groups", "groupid = '$groupid'"),
		  "pageid" => $pageid,
		  "groupid" => $groupid,
		  "feature" => $feature,
		  "featureid" => $featureid,
		  "options1" => $options1,
		  "options2" => $options2,
	 ];
	 return fill_template("tmp/roles_ajax.template", "refresh_manage_groups_template", false, $params);
}

function delete_group() {
	 $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	 $groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
	 $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	 $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

	 if ($pageid && $groupid) {
	 $SQL = "DELETE
				FROM `groups`
				WHERE groupid = '$groupid'
				AND pageid = '$pageid'";
	 execute_db_sql($SQL);

	 $SQL = "DELETE
				FROM groups_users
				WHERE groupid = '$groupid'
				AND pageid = '$pageid'";
	 execute_db_sql($SQL);

	 $SQL = "DELETE
				FROM roles_ability_perfeature_pergroup
				WHERE groupid = '$groupid'
				AND pageid = '$pageid'";
	 execute_db_sql($SQL);

	 $SQL = "DELETE
				FROM roles_ability_pergroup
				WHERE groupid = '$groupid'
				AND pageid = '$pageid'";
	 execute_db_sql($SQL);
	 }

	 echo group_page($pageid, $feature, $featureid);
}

function refresh_groups_page() {
  $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
  $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
  $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

  echo group_page($pageid, $feature, $featureid);
}

function refresh_groups_list() {
  $pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
  $groupid = clean_myvar_opt("groupid", "int", false); //Only passed when editing
  $featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
  $feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

  echo groups_list($pageid, $feature, $featureid, true, $groupid);
}

function create_edit_group_form() {
global $CFG;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	$groupid = clean_myvar_opt("groupid", "int", false); //Only passed when editing
	$featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	$feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

	$name = "";
	if ($groupid) { // EDITING: get form values to fill in
		$group = get_db_row(fetch_template("dbsql/roles.sql", "get_group"), ["groupid" => $groupid]);
		$name = $group["name"];
		$parents = groups_list($pageid, false, false, false, $group["parent"], $groupid, $groupid, "80%", "per_group_edit_group_select", "per_group_edit_group_select");
	} else { // CREATING
		$parents = groups_list($pageid, false, false, false, null, null, null, "80%", "per_group_edit_group_select", "per_group_edit_group_select");
	}

	$params = [
		"wwwroot" => $CFG->wwwroot,
		"name" => $name,
		"parents" => $parents,
		"pageid" => $pageid,
		"groupid" => $groupid,
		"feature" => $feature,
		"featureid" => $featureid,
	];
	echo fill_template("tmp/roles_ajax.template", "create_edit_group_form_template", false, $params);
}

function save_group() {
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
	$name = clean_myvar_req("name", "string", false); // Should always be passed
	$parent = clean_myvar_opt("parent", "int", 0); // Should always be passed
	$groupid = clean_myvar_opt("groupid", "int", false); // Only passed when editing
	$featureid = clean_myvar_opt("featureid", "int", false); // Only passed on feature specific managing
	$feature = clean_myvar_opt("feature", "string", false); // Only passed on feature specific managing

	$params = [
		"pageid" => $pageid,
		"groupid" => $groupid,
		"name" => $name,
		"parent" => $parent,
	];
	$SQL = fetch_template("dbsql/roles.sql", "save_group", false, ["is_editing" => boolval($groupid)]);
	execute_db_sql($SQL, $params);

	echo group_page($pageid, $feature, $featureid);
}

function refresh_edit_roles() {
  $pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
  $roleid = clean_myvar_req("roleid", "int"); // Should always be passed
  $feature = clean_myvar_opt("feature", "string", false); // Only passed on feature specific managing
  $featureid = clean_myvar_opt("featureid", "int", false); // Only passed on feature specific managing

  if ($pageid && $roleid) {
	 echo print_abilities($pageid, "per_role_", $roleid, false, $feature, $featureid);
  } else {
	 echo error_string("generic_error");
	 return;
  }
}

//TOP LEVEL PER USER OVERRIDES
function refresh_user_abilities() {
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
	$userid = clean_myvar_req("userid", "int"); // Should always be passed
	$featureid = clean_myvar_opt("featureid", "int", false); // Only passed on feature specific managing
	$feature = clean_myvar_opt("feature", "string", false); // Only passed on feature specific managing

	if ($pageid && $userid) {
		echo print_abilities($pageid, "per_user_", false, $userid, $feature, $featureid);
		return;
	}
	echo error_string("generic_error");
}

function refresh_group_abilities() {
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
	$groupid = clean_myvar_req("groupid", "int"); // Should always be passed
	$featureid = clean_myvar_opt("featureid", "int", false); // Only passed on feature specific managing
	$feature = clean_myvar_opt("feature", "string", false); // Only passed on feature specific managing

	if ($pageid && $groupid) {
		echo '<form id="per_group_roles_form">' .
					print_abilities($pageid, "per_group_", false, false, $feature, $featureid, $groupid) .
				'</form>';
	} else {
		echo error_string("generic_error");
		return;
	}
}

/**
 * Save the changes to the role abilities
 *
 * This function is called via AJAX from the ability manager page
 * It takes the post variables and uses them to update the roles_ability
 * table with the new abilities
 *
 * @global object $CFG The global config object
 * @global object $MYVARS The global variables object
 */
function save_ability_changes() {
global $CFG, $MYVARS;
	// Get the ability list from the post
	$abilities = explode("**", clean_myvar_opt("per_role_rightslist", "string", ""));

	// Extract the page and role ids from the post
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); // Should always be passed
	$roleid = clean_myvar_opt("per_role_roleid", "int", false); // Should always be passed

	// Extract the feature and feature id from the post (if present)
	$featureid = clean_myvar_opt("featureid", "int", false);
	$feature = clean_myvar_opt("feature", "string", false);

	// Initialize the success flag
	$success = false;

  	try {
		start_db_transaction();
		// Loop through the abilities and update the database
		$i = 0;
		while (isset($abilities[$i])) {
			$ability = $abilities[$i];
			$setting = clean_myvar_opt($ability, "int", 0) == 1 ? 1 : 0;

			// Create the paramaters for the SQL queries
			$params = [
					"ability" => $ability,
					"pageid" => $pageid,
					"roleid" => $roleid,
					"setting" => $setting,
					"feature" => $feature,
					"featureid" => $featureid,
			];

			// If this is a site-wide ability
			if ($pageid == $CFG->SITEID && !$featureid) {
				// Check if there is already a default value for this ability
				$default = get_db_field("allow", "roles_ability", "roleid = '$roleid' AND ability = '$ability'");
				$params["section"] = get_db_field("section", "abilities", "ability = '$ability'");

				// If there is a default, check if the default should be changed
				if ($default !== false && $default !== $setting) {
					// If the default is being changed, remove the old default
					execute_db_sql(fetch_template("dbsql/roles.sql", "remove_role_override"), $params) ? true : false;
				}

				// Insert the new default
				execute_db_sql(fetch_template("dbsql/roles.sql", "insert_role_override"), $params) ? true : false;
			} else { // If this is a feature-specific ability
				// Check if there is already an override for this ability
				$default = get_db_field("allow", "roles_ability", "roleid = '$roleid' AND ability = '$ability'");

				if ($feature && $featureid) { // If this is a feature-specific ability
					$alreadyset = get_db_count(fetch_template("dbsql/roles.sql", "get_page_role_feature_override"), $params);

					if ($alreadyset) { // If there is an override, check if it should be changed
						if ($setting == $default) { // If the override should be removed         
							execute_db_sql(fetch_template("dbsql/roles.sql", "remove_page_role_feature_override"), $params) ? true : false;
						} else { // If the override should be changed
							execute_db_sql(fetch_template("dbsql/roles.sql", "update_page_role_feature_override"), $params) ? true : false;
						}
					} elseif ($setting != $default && !$alreadyset) { // If the override should be added
						execute_db_sql(fetch_template("dbsql/roles.sql", "insert_page_role_feature_override"), $params) ? true : false;
					}
				} else { // If this is a page-specific ability
					$alreadyset = get_db_count(fetch_template("dbsql/roles.sql", "get_page_role_override"), $params);

					if ($alreadyset) { // If there is an override, check if it should be changed
						if ($setting == $default) { // If the override should be removed
							execute_db_sql(fetch_template("dbsql/roles.sql", "remove_page_role_override"), $params) ? true : false;
						} else { // If the override should be changed
							execute_db_sql(fetch_template("dbsql/roles.sql", "update_page_role_override"), $params) ? true : false;
						}
					} elseif ($setting != $default && !$alreadyset) { // If the override should be added
						execute_db_sql(fetch_template("dbsql/roles.sql", "insert_page_role_override"), $params) ? true : false;
					}
				}
			}
			$i++;
		}
		commit_db_transaction();
		$success = true;
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
	}

	// If the updates were successful, return a success message
	if ($success) {
		echo "Changes Saved";
	} else { // Otherwise, return a failure message
		echo "Save Failed";
	}
}

function save_user_ability_changes() {
global $CFG, $MYVARS;
	$abilities = explode("**", clean_myvar_opt("per_user_rightslist", "string", ""));
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	$userid = clean_myvar_opt("userid", "int", false); //Should always be passed
	$featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	$feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

  $i = 0;
	while (isset($abilities[$i])) {
		$ability = $abilities[$i];
		$setting = clean_myvar_opt($ability, "int", 0) == 1 ? 1 : 0;
		$roleid = user_role($userid, $pageid);

	 //$default = $featureid ? (user_is_able($userid, $ability, $pageid, $feature, $featureid) ? "1" : "0") : (user_is_able($roleid, $ability, $pageid, $feature) ? "1" : "0");
	 //figure out the default
	 if ($featureid) { // feature specific ability change
		$default = user_is_able($userid, $ability, $pageid, $feature, $featureid) ? 1 : 0;
	 } else { // page specific ability change
		$default = user_is_able($userid, $ability, $pageid) ? 1 : 0;
	 }

		if ($feature && $featureid) {
		$SQL = "SELECT *
					 FROM roles_ability_perfeature_peruser
					WHERE userid = '$userid'
					  AND pageid = '$pageid'
					  AND feature = '$feature'
					  AND featureid = '$featureid'
					  AND ability = '$ability'";
		$alreadyset = get_db_count($SQL);
	 } else {
		$SQL = "SELECT *
					 FROM roles_ability_peruser
					WHERE userid = '$userid'
					  AND pageid = '$pageid'
					  AND ability = '$ability'";
		$alreadyset = get_db_count($SQL);
		}

		if ($alreadyset) {
			if ($alreadyset && $setting == $default) {
		  if ($feature && $featureid) {
			 $SQL = "DELETE
						  FROM roles_ability_perfeature_peruser
						 WHERE pageid = '$pageid'
							AND userid = '$userid'
							AND feature = '$feature'
							AND featureid = '$featureid'
							AND ability = '$ability'";
			 execute_db_sql($SQL);
		  } else {
			 $SQL = "DELETE
						  FROM roles_ability_peruser
						 WHERE pageid = '$pageid'
							AND userid = '$userid'
							AND ability = '$ability'";
			 execute_db_sql($SQL);
		  }
		} else {
		  if ($feature && $featureid) {
			 $SQL = "UPDATE roles_ability_perfeature_peruser
							SET allow = '$setting'
						 WHERE userid = '$userid'
							AND pageid = '$pageid'
							AND feature = '$feature'
							AND featureid = '$featureid'
							AND ability = '$ability'";
			 execute_db_sql($SQL);
		  } else {
			 $SQL = "UPDATE roles_ability_peruser
							SET allow = '$setting'
						 WHERE userid = '$userid'
							AND pageid = '$pageid'
							AND ability = '$ability'";
			 execute_db_sql($SQL);
		  }
		}
		} elseif ($setting != $default && !$alreadyset) {
		if ($feature && $featureid) {
		  $SQL = "INSERT INTO roles_ability_perfeature_peruser (userid, pageid, feature, featureid, ability, allow)
							VALUES('$userid', '$pageid', '$feature', '$featureid', '$ability', '$setting')";
		  execute_db_sql($SQL);
		} else {
		  $SQL = "INSERT INTO roles_ability_peruser (userid, pageid, ability, allow)
							VALUES ('$userid', '$pageid', '$ability', '$setting')";
		  execute_db_sql($SQL);
  		}
		}
		$i++;
	}
	echo "Changes Saved";
}

function save_group_ability_changes() {
global $CFG, $MYVARS;
	$abilities = explode("**", clean_myvar_opt("per_group_rightslist", "string", ""));
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	$groupid = clean_myvar_opt("groupid", "int", false); //Should always be passed
	$featureid = clean_myvar_opt("featureid", "int", false); //Only passed on feature specific managing
	$feature = clean_myvar_opt("feature", "string", false); //Only passed on feature specific managing

  $i = 0;
	while (isset($abilities[$i])) {
		$ability = $abilities[$i];
		$allow = clean_myvar_opt($ability, "int", false);
		$allow = $MYVARS->GET[$ability] === 1 ? 1 : $MYVARS->GET[$ability]; //If ability is SET to 1
		$allow = $allow === 0 ? 0 : $allow; //If ability is SET to 0
		$allow = $allow === '' ? false : $allow; //If ability is NOT SET

		if ($feature && $featureid) {
		$SQL = "SELECT *
					 FROM roles_ability_perfeature_pergroup
					WHERE groupid = '$groupid'
					  AND pageid = '$pageid'
					  AND feature = '$feature'
					  AND featureid = '$featureid'
					  AND ability = '$ability'";
		$alreadyset = get_db_count($SQL);
		} else {
		$SQL = "SELECT *
					 FROM roles_ability_pergroup
					WHERE groupid = '$groupid'
					  AND pageid = '$pageid'
					  AND ability = '$ability'";
		$alreadyset = get_db_count($SQL);
		}

		if ($alreadyset) {
			if ($alreadyset && $allow === false) { //If ability is NOT SET to 1 or 0 but is set in the db
		  if ($feature && $featureid) {
			 $SQL = "DELETE
						  FROM roles_ability_perfeature_pergroup
						 WHERE pageid = '$pageid'
							AND groupid = '$groupid'
							AND feature = '$feature'
							AND featureid = '$featureid'
							AND ability = '$ability'";
			 execute_db_sql($SQL);
		  } else {
			 $SQL = "DELETE
						  FROM roles_ability_pergroup
						 WHERE pageid = '$pageid'
							AND groupid = '$groupid'
							AND ability = '$ability'";
			 execute_db_sql($SQL);
		  }
		} else { //If ability is SET to 1 or 0 and is already set in the db
		  if ($feature && $featureid) {
			 $SQL = "UPDATE roles_ability_perfeature_pergroup
							SET allow = '$allow'
						 WHERE groupid = '$groupid'
							AND pageid = '$pageid'
							AND feature = '$feature'
							AND featureid = '$featureid'
							AND ability = '$ability'";
			 execute_db_sql($SQL);
		  } else {
			 $SQL = "UPDATE roles_ability_pergroup
							SET allow = '$allow'
						 WHERE groupid = '$groupid'
							AND pageid = '$pageid'
							AND ability = '$ability'";
			 execute_db_sql($SQL);
		  }
		}
		} elseif ($allow !== false && !$alreadyset) { //If ability is SET to 1 or 0 and isn't already set in the db
		if ($feature && $featureid) {
		  $SQL = "INSERT INTO roles_ability_perfeature_pergroup (groupid, pageid, feature, featureid, ability, allow)
							VALUES('$groupid', '$pageid', '$feature', '$featureid', '$ability', '$allow')";
		  execute_db_sql($SQL);
		} else {
		  $SQL = "INSERT INTO roles_ability_pergroup (groupid, pageid, ability, allow)
							VALUES('$groupid', '$pageid', '$ability', '$allow')";
		  execute_db_sql($SQL);
  		}
		}
		$i++;
	}
	echo "Changes Saved";
}

function refresh_user_roles() {
global $CFG, $USER, $MYVARS, $ROLES;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid()); //Should always be passed
	$userid = clean_myvar_opt("userid", "int", false); //Should always be passed
	$myroleid = user_role($USER->userid, $pageid);
	$roleid = user_role($userid, $pageid, true);

	if (isset($roleid)) {
		if (is_siteadmin($userid)) {
		$rolename = "Site Admin";
		} else {
		$rolename = get_db_field("display_name", "roles", "roleid = '$roleid'");
		}
	} else {
		$roleid = 0;
		$rolename = "Unassigned";
	}

	$sql_admin = $pageid !== $CFG->SITEID ? " WHERE roleid <> '$ROLES->admin'" : "";
	$SQL = "SELECT *
				FROM roles
				$sql_admin
				ORDER BY roleid";
	$options = '';
	if ($roles = get_db_result($SQL)) {
		while ($row = fetch_row($roles)) {
			if ($row['roleid'] != $roleid && $row['roleid'] >= $myroleid) {
				$p = ["selected" => "",
						"value" => $row['roleid'],
						"display" => stripslashes($row['display_name']),
				];
				$options .= fill_template("tmp/page.template", "select_options_template", false, $p);
			}
		}
	}

	$params = [ "rolename" => $rolename,
					"pageid" => $pageid,
					"userid" => $userid,
					"options" => $options,
	];
	echo fill_template("tmp/roles_ajax.template", "refresh_user_roles_template", false, $params);
}

function assign_role() {
global $MYVARS, $ROLES;
	$roleid = clean_myvar_opt("roleid", "int", false);
	$userid = clean_myvar_req("userid", "int");
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	if (execute_db_sql(fetch_template("dbsql/roles.sql", "remove_user_role_assignment"), ["userid" => $userid, "pageid" => $pageid])) {
		if ($roleid !== $ROLES->none) { // No role besides "No Role" was given
			$SQL = fetch_template("dbsql/roles.sql", "insert_role_assignment");
			if (execute_db_sql($SQL, ["userid" => $userid, "pageid" => $pageid, "roleid" => $roleid, "confirm" => 0])) {
				echo "Changes Saved";
			} else {
				echo "No Role Given";
			}
		} else {
			echo "Role Removed";
		}
	} else {
		echo "Changes Not Saved";
	}
}
?>
