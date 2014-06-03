/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Overrides client-side functionality for the logs view:

	ajaxChat.initializeDocumentNodes = function() {
		this.chatList				= document.getElementById(this.documentIDs['chatListID']);
		this.inputField				= document.getElementById(this.documentIDs['inputFieldID']);
		this.channelSelection		= document.getElementById(this.documentIDs['channelSelectionID']);
		this.styleSelection			= document.getElementById(this.documentIDs['styleSelectionID']);
		
		this.yearSelection			= document.getElementById(this.documentIDs['yearSelectionID']);
		this.monthSelection			= document.getElementById(this.documentIDs['monthSelectionID']);
		this.daySelection			= document.getElementById(this.documentIDs['daySelectionID']);
		this.hourSelection			= document.getElementById(this.documentIDs['hourSelectionID']);
	}

	ajaxChat.updateChat = function(paramString) {
		var requestUrl = this.url
						+ '&lastID='
						+ this.lastID;
		if(paramString) {
			requestUrl += paramString;
		}
		requestUrl += '&' + this.getLogsCommand();
		this.makeRequest(requestUrl,'GET',null);
	}

	ajaxChat.getLogs = function() {
		clearTimeout(this.timer);
		this.clearChatList();
		this.lastID = 0;
		this.logsCommand = null;
		this.makeRequest(this.url,'POST',this.getLogsCommand());
	}
	
	ajaxChat.getLogsCommand = function() {
		if(!this.logsCommand) {
			this.logsCommand = 'command=getLogs'
								+ '&channelID='	+ this.channelSelection.value
								+ '&year='		+ this.yearSelection.value
								+ '&month='		+ this.monthSelection.value
								+ '&day='		+ this.daySelection.value
								+ '&hour='		+ this.hourSelection.value
								+ '&search='	+ this.encodeText(this.inputField.value);
		}
		return this.logsCommand;
	}

	ajaxChat.logout = function() {
		clearTimeout(this.timer);
		this.makeRequest(this.url,'POST','logout=true');
	}

	ajaxChat.finalize = function() {
		this.persistStyle();
	}
