<?php
/**************************************************************************
* db.php - site db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2010
* Revision: 0.0.2
***************************************************************************/

function site_upgrade(){
global $CFG;
    $version = get_db_field("setting","settings","setting_name='version' AND type='site'");
	$thisversion = 1; //Version number 1
	if($version < $thisversion){
		$SQL = "ALTER TABLE `features` ADD `version` INT NOT NULL";
		if(execute_db_sql($SQL)){ //if successful upgrade
			execute_db_sql("UPDATE settings SET setting='$thisversion' WHERE type='site' AND setting_name='version'");
		}
	}
    
	$thisversion = 20100818; //Add the joined field to the user table
	if($version < $thisversion){
		$SQL = "ALTER TABLE `users` ADD `joined` INT NOT NULL";
		if(execute_db_sql($SQL)){ //if successful upgrade
            $SQL = "SELECT * FROM users ORDER BY userid";
            if($result = get_db_result($SQL)){
                while($row = fetch_row($result)){
                    $madeuptime = time();
                    $madeuptime -= (rand(40000000,61516800)/$row["userid"]); //these accounts are somewhere between 6 months and 2 years old.
                    
                    if($row["first_activity"] == ""){ //Now we have to make something up
                        if($row["last_activity"] == ""){
                            execute_db_sql("UPDATE users SET joined='".$madeuptime."' WHERE userid='".$row["userid"]."'"); 
                        }else{
                            execute_db_sql("UPDATE users SET joined='".$madeuptime."',first_activity='".$row["last_activity"]."' WHERE userid='".$row["userid"]."'");
                        }
                    }else{ //Make the joined time a few minutes before first_activity time
                        if($row["last_activity"] == ""){
                            execute_db_sql("UPDATE users SET joined='".($row["first_activity"]-1200).",last_activity='".$row["first_activity"]."' WHERE userid='".$row["userid"]."'"); 
                        }else{
                            execute_db_sql("UPDATE users SET joined='".($row["first_activity"]-1200)."' WHERE userid='".$row["userid"]."'");
                        }
                    }
                }
            }
			execute_db_sql("UPDATE settings SET setting='$thisversion' WHERE type='site' AND setting_name='version'");
		}
	}
    
	$thisversion = 20100819; //Fix joined field for users where joined date was after first_activity
	if($version < $thisversion){
        $SQL = "SELECT * FROM users WHERE joined > first_activity";
        if($result = get_db_result($SQL)){
            while($row = fetch_row($result)){
                execute_db_sql("UPDATE users SET joined=(first_activity-2000) WHERE userid='".$row["userid"]."'"); 
            }
        }
		execute_db_sql("UPDATE settings SET setting='$thisversion' WHERE type='site' AND setting_name='version'");
	}

	$thisversion = 20130403; //Menu system no longer needs thickbox
	if($version < $thisversion){
        $SQL = "ALTER TABLE `menus` DROP `thickbox`, DROP `param`;";
        if(execute_db_sql($SQL)){
            execute_db_sql("UPDATE settings SET setting='$thisversion' WHERE type='site' AND setting_name='version'");    
        }
	}
    
    $thisversion = 20140324;
	if($version < $thisversion){ 
       add_role_ability('user','editprofile','User','1','Change user name,email,and password','1','1','1','0');
       execute_db_sql("UPDATE settings SET setting='$thisversion' WHERE type='site' AND setting_name='version'"); 
	}
    
    $thisversion = 20140325; //Switch to tinyMCE and Responsive File Manager
	if($version < $thisversion){
        $SQL = "DROP TABLE `kfm_directories`, `kfm_files`, `kfm_files_images`, `kfm_files_images_thumbs`, `kfm_parameters`, `kfm_plugin_extensions`, `kfm_session`, `kfm_session_vars`, `kfm_settings`, `kfm_tagged_files`, `kfm_tags`, `kfm_translations`, `kfm_users`, `tagged_files`, `tags`;";
        if(execute_db_sql($SQL)){
            execute_db_sql("UPDATE settings SET setting='$thisversion' WHERE type='site' AND setting_name='version'");    
        }
	}
}

?>