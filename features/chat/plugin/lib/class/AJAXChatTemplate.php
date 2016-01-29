<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Class to handle HTML templates
class AJAXChatTemplate {

	var $ajaxChat;
	var $_regExpTemplateTags;
	var $_templateFile;
	var $_contentType;
	var $_content;
	var $_parsedContent;

	// Constructor:
	function AJAXChatTemplate(&$ajaxChat, $templateFile, $contentType=null) {
		$this->ajaxChat = $ajaxChat;
		$this->_regExpTemplateTags = '/\[(\w+?)(?:(?:\/)|(?:\](.+?)\[\/\1))\]/s';		
		$this->_templateFile = $templateFile;
		$this->_contentType = $contentType;
	}

	function getParsedContent() {
		if(!$this->_parsedContent) {
			$this->parseContent();
		}
		return $this->_parsedContent;
	}

	function getContent() {
		if(!$this->_content) {
			$this->_content = AJAXChatFileSystem::getFileContents($this->_templateFile);
		}
		return $this->_content;
	}

	function parseContent() {
		$this->_parsedContent = $this->getContent();
		
		// Remove the XML declaration if the content-type is not xml:		
		if($this->_contentType && (strpos($this->_contentType,'xml') === false)) {
			$doctypeStart = strpos($this->_parsedContent, '<!DOCTYPE ');
			if($doctypeStart !== false) {
				// Removing the XML declaration (in front of the document type) prevents IE<7 to go into "Quirks mode":
				$this->_parsedContent = substr($this->_parsedContent, $doctypeStart);	
			}		
		}

		// Replace template tags ([TAG/] and [TAG]content[/TAG]) and return parsed template content:
		$this->_parsedContent = preg_replace_callback($this->_regExpTemplateTags, function($m) { return $this->replaceTemplateTags($m); }, $this->_parsedContent);
	}

	function replaceTemplateTags($t) {
		switch($t[1]) {
			case 'AJAX_CHAT_URL':
				return $this->ajaxChat->getChatURL();
			case 'PAGEID':
				return PAGEID;
			case 'LANG':
				return $this->ajaxChat->htmlEncode($this->ajaxChat->getLang($t[2]));				
			case 'LANG_CODE':
				return $this->ajaxChat->getLangCode();
			case 'BASE_DIRECTION':
				return $this->getBaseDirectionAttribute();
			case 'CONTENT_ENCODING':
				return $this->ajaxChat->getConfig('contentEncoding');
			case 'CONTENT_TYPE':
				return $this->_contentType;
			case 'LOGIN_URL':
				return ($this->ajaxChat->getRequestVar('view') == 'logs') ? './?view=logs' : './';
			case 'USER_NAME_MAX_LENGTH':
				return $this->ajaxChat->getConfig('userNameMaxLength');
			case 'MESSAGE_TEXT_MAX_LENGTH':
				return $this->ajaxChat->getConfig('messageTextMaxLength');
			case 'SESSION_NAME':
				return $this->ajaxChat->getConfig('sessionName');
			case 'CHAT_BOT_NAME':
				return $this->ajaxChat->htmlEncode($this->ajaxChat->getConfig('chatBotName'));
			case 'CHAT_BOT_ID':
				return $this->ajaxChat->getConfig('chatBotID');
			case 'PASSWORD':
				return MYPASSWORD;	
			case 'USER_NAME':
				return USERNAME;
			case 'USER_ID':
				return USERID;
			case 'STYLE_SHEETS':
				return $this->getStyleSheetLinkTags();
			case 'CHANNEL_OPTIONS':
				return $this->getChannelOptionTags();
			case 'STYLE_OPTIONS':
				return $this->getStyleOptionTags();
			case 'HELP_LIST':
				return $this->getHelpListTable();
			case 'ERROR_MESSAGES':
				return $this->getErrorMessageTags();
			case 'LOGS_CHANNEL_OPTIONS':
				return $this->getLogsChannelOptionTags();
			case 'LOGS_YEAR_OPTIONS':
				return $this->getLogsYearOptionTags();
			case 'LOGS_MONTH_OPTIONS':
				return $this->getLogsMonthOptionTags();
			case 'LOGS_DAY_OPTIONS':
				return $this->getLogsDayOptionTags();
			case 'LOGS_HOUR_OPTIONS':
				return $this->getLogsHourOptionTags();
			default:
				return $this->ajaxChat->replaceCustomTemplateTags($t[1], $t[2]);
		}
	}

	// Function to display alternating table row colors:
	function alternateRow($rowOdd='rowOdd', $rowEven='rowEven') {
		static $i;
		$i += 1;
		if($i % 2 == 0) {
			return $rowEven;
		} else {
			return $rowOdd;
		}
	}

	function getBaseDirectionAttribute() {
		$langCodeParts = explode('-', $this->ajaxChat->getLangCode());
		switch($langCodeParts[0]) {
			case 'ar':
			case 'he':
				return 'rtl';
			default:
				return 'ltr';
		}
	}

	function getStyleSheetLinkTags() {
		$styleSheets = '';
		foreach($this->ajaxChat->getConfig('styleAvailable') as $style) {
			$alternate = ($style == $this->ajaxChat->getConfig('styleDefault')) ? '' : 'alternate ';
			$styleSheets .= '<link rel="'.$alternate.'stylesheet" type="text/css" href="css/'.rawurlencode($style).'.css" title="'.$this->ajaxChat->htmlEncode($style).'"/>';
		}
		return $styleSheets;
	}

	function getChannelOptionTags() {
		$channelOptions = '';
		$channelSelected = false;
		foreach($this->ajaxChat->getChannels() as $key=>$value) {
			if($this->ajaxChat->isLoggedIn()) {
				$selected = ($value == $this->ajaxChat->getChannel()) ? ' selected="selected"' : '';
			} else {
				$selected = ($value == $this->ajaxChat->getConfig('defaultChannelID')) ? ' selected="selected"' : '';
			}
			if($selected)
				$channelSelected = true;
			$channelOptions .= '<option value="'.$this->ajaxChat->htmlEncode($key).'"'.$selected.'>'.$this->ajaxChat->htmlEncode($key).'</option>';
		}
		// If current channel is not in the list, try to retrieve the channelName:
		if(!$channelSelected) {
			$channelName = $this->ajaxChat->getChannelName();
			if($channelName !== null) {
				$channelOptions .= '<option value="'.$this->ajaxChat->htmlEncode($channelName).'" selected="selected">'.$this->ajaxChat->htmlEncode($channelName).'</option>';
			} else {
				// Show an empty selection:
				$channelOptions .= '';
			}
		}
		return $channelOptions;
	}

	function getStyleOptionTags() {
		$styleOptions = '';
		foreach($this->ajaxChat->getConfig('styleAvailable') as $style) {
			$selected = ($style == $this->ajaxChat->getConfig('styleDefault')) ? ' selected="selected"' : '';
			$styleOptions .= '<option value="'.$this->ajaxChat->htmlEncode($style).'"'.$selected.'>'.$this->ajaxChat->htmlEncode($style).'</option>';
		}
		return $styleOptions;
	}

	function getHelpListTable() {
		$helpList = '<table>';
		foreach($this->ajaxChat->getLang() as $key=>$value) {
			if(strpos($key, 'helpItemDesc') === 0) {
				$helpList .= '<tr class="'.$this->alternateRow().'"><td class="desc">'.$this->ajaxChat->htmlEncode($value).'</td>';
			} else if(strpos($key, 'helpItemCode') === 0) {
				$helpList .= '<td class="code">'.$this->ajaxChat->htmlEncode($value).'</td></tr>';
			}
		}
		$helpList .= '</table>';
		return $helpList;
	}

	function getErrorMessageTags() {
		$errorMessages = '';
		foreach($this->ajaxChat->getInfoMessages('error') as $error) {
			$errorMessages .= '<div>'.$this->ajaxChat->htmlEncode($this->ajaxChat->getLang($error)).'</div>';
		}
		return $errorMessages;
	}

	function getLogsChannelOptionTags() {
		$channelOptions = '';
		$channelOptions .= '<option value="-3">------</option>';
		foreach($this->ajaxChat->getChannels() as $key=>$value) {
			$channelOptions .= '<option value="'.$value.'">'.$this->ajaxChat->htmlEncode($key).'</option>';
		}
		$channelOptions .= '<option value="-1">'.$this->ajaxChat->htmlEncode($this->ajaxChat->getLang('logsPrivateChannels')).'</option>';
		$channelOptions .= '<option value="-2">'.$this->ajaxChat->htmlEncode($this->ajaxChat->getLang('logsPrivateMessages')).'</option>';
		return $channelOptions;
	}

	function getLogsYearOptionTags() {
		$yearOptions = '';
		$yearOptions .= '<option value="-1">----</option>';
		for($year=date('Y'); $year>=$this->ajaxChat->getConfig('logsFirstYear'); $year--) {
			$yearOptions .= '<option value="'.$year.'">'.$year.'</option>';
		}
		return $yearOptions;
	}
	
	function getLogsMonthOptionTags() {
		$monthOptions = '';
		$monthOptions .= '<option value="-1">--</option>';
		for($month=1; $month<=12; $month++) {
			$monthOptions .= '<option value="'.$month.'">'.sprintf("%02d", $month).'</option>';
		}
		return $monthOptions;
	}
	
	function getLogsDayOptionTags() {
		$dayOptions = '';
		$dayOptions .= '<option value="-1">--</option>';
		for($day=1; $day<=31; $day++) {
			$dayOptions .= '<option value="'.$day.'">'.sprintf("%02d", $day).'</option>';
		}
		return $dayOptions;
	}
	
	function getLogsHourOptionTags() {
		$hourOptions = '';
		$hourOptions .= '<option value="-1">-----</option>';
		for($hour=0; $hour<=23; $hour++) {
			$hourOptions .= '<option value="'.$hour.'">'.sprintf("%02d", $hour).':00</option>';
		}
		return $hourOptions;
	}

}
?>