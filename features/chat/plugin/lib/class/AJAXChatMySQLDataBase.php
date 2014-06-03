<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Class to initialize the MySQL DataBase connection:
class AJAXChatDataBaseMySQL {

	var $_connectionID;
	var $_errno = 0;
	var $_error = '';

	function AJAXChatDataBaseMySQL(&$dbConnectionConfig) {
		$this->_connectionID = $dbConnectionConfig['link'];
	}
	
	// Method to connect to the DataBase server:
	function connect(&$dbConnectionConfig) {
		$this->_connectionID = @mysql_connect(
			$dbConnectionConfig['host'],
			$dbConnectionConfig['user'],
			$dbConnectionConfig['pass']
		);
		if(!$this->_connectionID) {
			$this->_errno = null;
			$this->_error = 'Database connection failed.';
			return false;
		}
		return true;
	}
	
	// Method to select the DataBase:
	function select(&$dbConnectionConfig) {
		if(!@mysql_select_db($dbConnectionConfig['name'], $this->_connectionID)) {
			$this->_errno = mysql_errno($this->_connectionID);
			$this->_error = mysql_error($this->_connectionID);
			return false;
		}
		return true;	
	}
	
	// Method to determine if an error has occured:
	function error() {
		return (bool)$this->_error;
	}
	
	// Method to return the error report:
	function getError() {
		if($this->error()) {
			$str = 'Error-Report: '	.$this->_error."\n";
			$str .= 'Error-Code: '.$this->_errno."\n";
		} else {
			$str = 'No errors.'."\n";
		}
		return $str;		
	}
	
	// Method to return the connection identifier:
	function &getConnectionID() {
		return $this->_connectionID;
	}
	
	// Method to prevent SQL injections:
	function makeSafe($value) {
		return "'".mysql_real_escape_string($value, $this->_connectionID)."'";
	}
	
	// Method to perform SQL queries:
	function sqlQuery($sql) {
		return new AJAXChatMySQLQuery($sql, $this->_connectionID);
	}

}
?>