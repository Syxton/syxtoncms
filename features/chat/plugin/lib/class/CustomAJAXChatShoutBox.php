<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

class CustomAJAXChatShoutBox extends CustomAJAXChat {

	function initialize() {
		// Initialize configuration settings:
		$this->initConfig();
	}

	function getShoutBoxContent() {
		$template = new AJAXChatTemplate($this, AJAX_CHAT_PATH.'lib/template/shoutbox.html');
		
		// Return parsed template content:
		return $template->getParsedContent();
	}	

}
?>