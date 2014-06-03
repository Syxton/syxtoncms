<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Class to provide methods for file system access:
class AJAXChatFileSystem {

	public static function getFileContents($file) {
		if(function_exists('file_get_contents')) {
			return file_get_contents($file);
		} else {
			return(implode('', file($file)));
		}
	}

}
?>