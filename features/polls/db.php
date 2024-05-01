<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2010
* Revision: 0.0.2
***************************************************************************/

function polls_upgrade()
{
	global $CFG;
    $version = get_db_field("version","features","feature='polls'");
    //Polls update to make non limited answer amount and integrate Google Charts API
	$thisversion = 20100817;
	if ($version < $thisversion) { # = new version number.  If this is the first...start at 1
		$SQL = "CREATE TABLE IF NOT EXISTS `polls_answers` (
        `answerid` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `pollid` INT NOT NULL ,
        `answer` VARCHAR( 200 ) NOT NULL ,
        `sort` INT NOT NULL ,
        INDEX ( `pollid` , `sort` )
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
        
		if (execute_db_sql($SQL)) //if successful creating table, go ahead with upgrade
		{
            if ($result = get_db_result("SELECT * FROM polls")) { //loop through all polls and convert answers to new format
                while ($row = fetch_row($result)) {
                    $i=1;$sort=1;
                    while (isset($row["a$i"])) {
                        if ($row["a$i"] != "") { //a1, a2, a3 used to be the answer format.  
                            $answerid = execute_db_sql("INSERT INTO polls_answers (pollid,answer,sort) VALUES('" . $row["pollid"] . "','" . $row["a$i"] . "','$sort')");
                            execute_db_sql("UPDATE polls_response SET answer='$answerid' WHERE pollid='" . $row["pollid"] . "' AND answer='$i'"); //update all responses
                            $sort++;    
                        }
                        $i++;     
                    }    
                }    
            }
            
            //Drop old fields
            $SQL2 = "ALTER TABLE `polls` DROP `a1`, DROP `a2`, DROP `a3`, DROP `a4`, DROP `a5`, DROP `a6`, DROP `a7`, DROP `a8`, DROP `a9`, DROP `a10`;";
            
            if (execute_db_sql($SQL2)) //if successful creating table, go ahead with upgrade
		    {
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='polls'");
		    }
		}
	}

}
?>