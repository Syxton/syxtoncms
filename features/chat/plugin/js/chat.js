/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 * 
 * The SELFHTML documentation has been used throughout this project:
 * http://selfhtml.org
 * 
 * Stylesheet and cookie methods have been inspired by Paul Sowden (A List Apart):
 * http://www.alistapart.com/stories/alternate/
 */

// Ajax Chat client side logic:
var ajaxChat = {
	
	url: null,
	documentIDs: null,
	chatList: null,
	onlineList: null,
	inputField: null,
	channelSelection: null,
	styleSelection: null,
	emoticonsContainer: null,
	colorCodesContainer: null,
	settings: null,
	nonPersistentSettings: null,
	emoticonPath: null,
	timerRate: null,
	cookieExpiration: null,
	bbCodeTags: null,
	colorCodes: null,
	emoticonCodes: null,
	emoticonFiles: null,
	sessionName: null,
	chatBotName: null,
	chatBotID: null,
	userName: null,
	userID: null,
	usersList: null,
	lastMessageID: null,
	lastID: null,
	lang: null,
	langCode: null,
	baseDirection: null,
	httpRequest: null,
	
	init: function(config, lang, initSettings, initStyle, initialize) {	
		this.documentIDs							= new Object();
		this.settings								= new Object();
		this.usersList								= new Array();
		this.lastID									= 0;	
		this.lang									= lang;		
		
		this.initConfig(config);
		
		if(initSettings)
			this.initSettings();

		if(initStyle)
			this.initStyle();
			
		if(initialize)
			this.initialize();
	},
	
	initConfig: function(config) {
		this.url									= config['url'];
		this.nonPersistentSettings					= config['nonPersistentSettings'];
		this.emoticonPath							= config['emoticonPath'];
		this.timerRate								= config['timerRate'];
		this.cookieExpiration						= config['cookieExpiration'];
		this.bbCodeTags								= config['bbCodeTags'];
		this.colorCodes								= config['colorCodes'];
		this.emoticonCodes							= config['emoticonCodes'];
		this.emoticonFiles							= config['emoticonFiles'];
		this.sessionName							= config['sessionName'];
		this.chatBotName							= config['chatBotName'];
		this.chatBotID								= config['chatBotID'];
		this.userName								= config['userName'];
		this.userID									= config['userID'];

		this.initConfigDocumentIDs(config);
		this.initConfigSettings(config);
	},
	
	initConfigDocumentIDs: function(config) {
		this.documentIDs['chatListID']				= config['chatListID'];
		this.documentIDs['onlineListID']			= config['onlineListID'];
		this.documentIDs['inputFieldID']			= config['inputFieldID'];
		this.documentIDs['channelSelectionID']		= config['channelSelectionID'];
		this.documentIDs['styleSelectionID']		= config['styleSelectionID'];
		this.documentIDs['emoticonsContainerID']	= config['emoticonsContainerID'];
		this.documentIDs['colorCodesContainerID']	= config['colorCodesContainerID'];
	},
	
	initConfigSettings: function(config) {
		this.settings['bbCode']						= config['bbCode'];
		this.settings['hyperLinks']					= config['hyperLinks'];
		this.settings['lineBreaks']					= config['lineBreaks'];
		this.settings['emoticons']					= config['emoticons'];
		this.settings['autoFocus']					= config['autoFocus'];
		this.settings['autoScroll']					= config['autoScroll'];
		this.settings['maxMessages']				= config['maxMessages'];
		this.settings['wordWrap']					= config['wordWrap'];
		this.settings['maxWordLength']				= config['maxWordLength'];
		this.settings['breakString']				= config['breakString'];
		this.settings['dateFormat']					= config['dateFormat'];
		this.settings['persistFontColor']			= config['persistFontColor'];
		this.settings['fontColor']					= null;
	},
	
	initSettings: function() {
		var cookie = this.readCookie(this.sessionName + '_settings');
		if(cookie) {
			var settingsArray = cookie.split('&');
			var setting, key, value, number;
			for(var i=0; i<settingsArray.length; i++) {
				setting = settingsArray[i].split('=');
				if(setting.length == 2) {
					key = setting[0];
					if(this.inArray(this.nonPersistentSettings, key)) {
						// Ignore settings not to be stored in a session cookie:
						continue;
					}
					value = this.decodeText(setting[1]);
					switch(value) {
						case 'true':
							this.settings[key] = true;
							break;
						case 'false':
							this.settings[key] = false;
							break;
						case 'null':
							this.settings[key] = null;
							break;
						default:
							number = parseFloat(value);
							if(isNaN(number)) {
								this.settings[key] = value;	
							} else {
								this.settings[key] = number;
							}
					}
				}
			}
		}
	},

	persistSettings: function() {
		var settingsArray = new Array();
		for(var setting in this.settings) {
			if(this.inArray(this.nonPersistentSettings, setting)) {
				// Ignore settings not to be stored in a session cookie:
				continue;
			}
			settingsArray.push(setting + '=' + this.encodeText(this.settings[setting]));
		}
		this.createCookie(this.sessionName + '_settings', settingsArray.join('&'), this.cookieExpiration);
	},
	
	getSettings: function() {
		return this.settings;
	},
	
	getSetting: function(key) {
		// Only return null if setting is null or undefined, not if it is false:
		for(var property in this.settings) {
			if(property == key)
				return this.settings[key];
		}
		return null;
	},
	
	setSetting: function(key, value) {
		this.settings[key] = value;
	},
	
	initializeSettings: function() {
		if(this.settings['persistFontColor'] && this.settings['fontColor']) {
			// Set the inputField font color to the font color:
			if(this.inputField)
				this.inputField.style.color = this.settings['fontColor'];
		}
	},
	
	initialize: function() {
		this.initializeDocumentNodes();

		var htmlTag			= document.getElementsByTagName('html')[0];
		this.langCode		= htmlTag.getAttribute('lang')	? htmlTag.getAttribute('lang')	: 'en';
		this.baseDirection	= htmlTag.getAttribute('dir')	? htmlTag.getAttribute('dir')	: 'ltr';
		
		this.initEmoticons();
		this.initColorCodes();

		this.initializeSettings();		

		if(this.styleSelection)
			this.setSelectedStyle();

		this.customInitialize();

		if(this.inputField && this.settings['autoFocus'])
			this.inputField.focus();

		this.httpRequest = new Object();
		
		if(!this.isCookieEnabled()) {
			// Add error message to the list:
			this.addMessageToChatList(
				new Date(),
				this.chatBotID,
				this.chatBotName,
				this.getRoleClass('4'),
				null,
				this.replaceCommands('/error CookiesRequired'),
				null
			);
		} else {
			// Start the chat update and retrieve current user info:
			this.updateChatGetInfos(new Array('userID', 'userName'));	
		}
	},
	
	initializeDocumentNodes: function() {
		this.chatList				= document.getElementById(this.documentIDs['chatListID']);
		this.onlineList				= document.getElementById(this.documentIDs['onlineListID']);
		this.inputField				= document.getElementById(this.documentIDs['inputFieldID']);
		this.channelSelection		= document.getElementById(this.documentIDs['channelSelectionID']);
		this.styleSelection			= document.getElementById(this.documentIDs['styleSelectionID']);
		this.emoticonsContainer		= document.getElementById(this.documentIDs['emoticonsContainerID']);
		this.colorCodesContainer	= document.getElementById(this.documentIDs['colorCodesContainerID']);
	},
	
	initEmoticons: function() {
		for(var i in this.emoticonCodes) {
			// Replace specials characters in emoticon codes:
			this.emoticonCodes[i] = this.encodeSpecialChars(this.emoticonCodes[i]);
			if(this.emoticonsContainer) {
				this.emoticonsContainer.innerHTML 	+= '<a href="javascript:ajaxChat.insertText(\''
													+ this.encodeText(this.addSlashes(this.decodeSpecialChars(this.emoticonCodes[i])))
													+ '\');"><img src="'
													+ this.emoticonPath
													+ this.emoticonFiles[i]
													+ '" alt="'
													+ this.emoticonCodes[i]
													+ '" title="'
													+ this.emoticonCodes[i]
													+ '"/></a>';				
			}
		}
	},
	
	initColorCodes: function() {
		if(this.colorCodesContainer) {
			for(var i in this.colorCodes) {
				this.colorCodesContainer.innerHTML 	+= '<a href="javascript:ajaxChat.setFontColor(\''
													+ this.colorCodes[i]
													+ '\');" style="background-color:'
													+ this.colorCodes[i]
													+ ';" title="'
													+ this.colorCodes[i]
													+ '"></a>'
													+ "\n";
			}			
		}
	},
	
	getHttpRequest: function(identifier) {
		if(!this.httpRequest[identifier]) {
			if (window.XMLHttpRequest) {
				this.httpRequest[identifier] = new XMLHttpRequest();
				if (this.httpRequest[identifier].overrideMimeType) {
					this.httpRequest[identifier].overrideMimeType('text/xml');
				}
			} else if (window.ActiveXObject) {
				try {
					this.httpRequest[identifier] = new ActiveXObject("Msxml2.XMLHTTP");
				} catch (e) {
					try {
						this.httpRequest[identifier] = new ActiveXObject("Microsoft.XMLHTTP");
					} catch (e) {
					}
				}
			}
		}
		return this.httpRequest[identifier];
	},
	
	makeRequest: function(url, method, data) {
		try {
			var identifier;
			if(data) {
				if(!arguments.callee.identifier || arguments.callee.identifier > 10) {
					arguments.callee.identifier = 1;
				} else {
					arguments.callee.identifier++;
				}
				identifier = arguments.callee.identifier;
			} else {
				identifier = 0;
			}
			
			this.getHttpRequest(identifier).open(method, url, true);
	
			this.getHttpRequest(identifier).onreadystatechange = function() {
				try {
					ajaxChat.handleResponse(identifier);
				} catch(e) {
					try {
						clearTimeout(ajaxChat.timer);
					} catch(e) {
						//alert(e);
					}
					
					try {
						if(data) {
							// Add error message to the list:
							ajaxChat.addMessageToChatList(
								new Date(),
								ajaxChat.chatBotID,
								ajaxChat.chatBotName,
								ajaxChat.getRoleClass('4'),
								null,
								ajaxChat.replaceCommands('/error ConnectionTimeout'),
								null
							);
							ajaxChat.updateChatlistView();
						}
					} catch(e) {
						//alert(e);
					}
					
					// Try to update:
					try {				
						ajaxChat.timer = setTimeout('ajaxChat.updateChat(null);', ajaxChat.timerRate);
					} catch(e) {
						//alert(e);
					}
				}
			};
		
			if(method == 'POST') {
				this.getHttpRequest(identifier).setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			}
			
			this.getHttpRequest(identifier).send(data);
		} catch(e) {
			clearTimeout(this.timer);
			
			if(data) {
				// Add error message to the list:
				this.addMessageToChatList(
					new Date(),
					this.chatBotID,
					this.chatBotName,
					this.getRoleClass('4'),
					null,
					this.replaceCommands('/error ConnectionTimeout'),
					null
				);
				this.updateChatlistView();
			}
				
			// Try to update:
			this.timer = setTimeout('ajaxChat.updateChat(null);', this.timerRate);
		}
	},
		
	handleResponse: function(identifier) {
		if (this.getHttpRequest(identifier).readyState == 4) {
			if (this.getHttpRequest(identifier).status == 200) {
				var xmlDoc = this.getHttpRequest(identifier).responseXML;
			} else {
				// Add error message to the list:
				this.addMessageToChatList(
					new Date(),
					this.chatBotID,
					this.chatBotName,
					this.getRoleClass('4'),
					null,
					this.replaceCommands('/error ConnectionStatus ' + this.getHttpRequest(identifier).status),
					null
				);
				this.updateChatlistView();				
				return false;
			}
		}
		if(!xmlDoc)
			return false;
		
		this.handleXML(xmlDoc);	
		return true;
	},
	
	handleXML: function(xmlDoc) {
		var infos = xmlDoc.getElementsByTagName('info');
		for (i=0; i<infos.length; i++) {
			var infoType = infos[i].getAttribute('type');
			var infoData = infos[i].firstChild ? infos[i].firstChild.nodeValue : '';
			this.handleInfoMessage(infoType, infoData);
		}

		var userNode,userID,userName,userRoleClass,textNode,messageText;
		
		// Go through the users list:
		var onlineUsers = new Array();
		var users = xmlDoc.getElementsByTagName('user');
		for (i=0; i<users.length; i++) {
			userID = users[i].getAttribute('userID');
			onlineUsers.push(userID);
			if(!this.inArray(this.usersList, userID)) {
				userName = users[i].firstChild ? users[i].firstChild.nodeValue : '';
				userRoleClass = this.getRoleClass(users[i].getAttribute('userRole'));
				this.addUserToOnlineList(
					userID,
					userName,
					userRoleClass
				);
			}
		}
		// Clear the offline users from the online users list:
		for (i=0; i<this.usersList.length; i++) {
			if(!this.inArray(onlineUsers, this.usersList[i])) {
				this.removeUserFromOnlineList(this.usersList[i], i);
			}
		}	
		// Set the row classes of the online list:
		this.setOnlineListRowClasses();
		
		// Go through the chat messages:
		var messages = xmlDoc.getElementsByTagName('message');
		for (i=0; i<messages.length; i++) {
			userNode = messages[i].getElementsByTagName('username')[0];
			userName = userNode.firstChild ? userNode.firstChild.nodeValue : '';
			textNode = messages[i].getElementsByTagName('text')[0];
			messageText = textNode.firstChild ? textNode.firstChild.nodeValue : '';
			this.addMessageToChatList(
					new Date(messages[i].getAttribute('dateTime')),
					messages[i].getAttribute('userID'),
					userName,
					this.getRoleClass(messages[i].getAttribute('userRole')),
					messages[i].getAttribute('id'),
					messageText,
					messages[i].getAttribute('ip')
			);
		}
		
		if(messages.length != 0) {
			this.updateChatlistView();
			
			this.lastID = messages[messages.length-1].getAttribute('id');
		}
		
		this.timer = setTimeout('ajaxChat.updateChat(null);', this.timerRate);
	},

	handleInfoMessage: function(infoType, infoData) {
		switch(infoType) {
			case 'channelSwitch':
				// Clear the chat messages list:
				this.clearChatList();
				// Clear the online users list:
				this.clearOnlineUsersList();
				// Set the channel selection:
				this.setSelectedChannel(infoData);
				break;

			case 'userID':
				this.userID = infoData;
				break;
			
			case 'userName':
				this.userName = infoData;
				break;
			
			case 'logout':
				this.handleLogout(infoData);
				return;
				
			default:
				this.handleCustomInfoMessage(infoType, infoData);
		}
	},

	setSelectedChannel: function(channel) {
		if(this.channelSelection) {
			// Replace the entities in the channel name with their character equivalent:
			channel = this.decodeSpecialChars(channel);
			var channelSelected = false;
			for(var j = 0; j < this.channelSelection.options.length; j++) {
				if(this.channelSelection.options[j].value == channel) {
					this.channelSelection.options[j].selected = true;
					channelSelected = true;
					break;
				}
			}
			// The given channel is not in the list, add it:
			if(!channelSelected) {
				var option = document.createElement('option');
				var text = document.createTextNode(channel);
				option.appendChild(text);
				option.setAttribute('value', channel);
				option.setAttribute('selected', 'selected');			
				this.channelSelection.appendChild(option);
			}
		}
	},

	removeUserFromOnlineList: function(userID, index) {
		// Remove the user from the local users list:
		this.usersList.splice(index, 1);
		
		// Update the online list display:
		if(this.onlineList) {
			this.onlineList.removeChild(document.getElementById('chatUser_' + userID));
		}
	},
		
	addUserToOnlineList: function(userID, userName, userRoleClass) {
		// Add the user to the local users list:
		this.usersList.push(userID);
		
		// Update the online list display:
		if(this.onlineList) {
			this.onlineList.innerHTML	+= '<div id="chatUser_'
										+ userID
										+ '"><a href="javascript:ajaxChat.privateMessageWrapper(\''
										+ this.encodeText(this.addSlashes(this.decodeSpecialChars(userName)))
										+ '\');" title="'
										+ this.lang['sendPrivateMessage'].replace(/%s/, userName)
										+ '" class="'
										+ userRoleClass
										+ '">'
										+ userName
										+ '</a></div>';	
		}
	},

	setOnlineListRowClasses: function() {
		if(this.onlineList) {
			var node = this.onlineList.firstChild;
			var i = 0;
			while(node != null) {
				var rowClass = (i % 2 != 0) ? 'rowEven' : 'rowOdd';
				try{
					// IE needs className to set the class attribute:
					node.className = rowClass;
				} catch(e) {
					node.setAttribute('class', rowClass);
				}
				node = node.nextSibling;
				i++;
			}
		}
	},

	clearChatList: function() {
		while(this.chatList.hasChildNodes()) {
			this.chatList.removeChild(this.chatList.firstChild);
		}
	},

	clearOnlineUsersList: function() {
		this.usersList = new Array();
		if(this.onlineList) {
			while(this.onlineList.hasChildNodes()) {
				this.onlineList.removeChild(this.onlineList.firstChild);
			}
		}
	},

	addMessageToChatList: function(dateObject, userID, userName, userRoleClass, messageID, messageText, ip) {
		// Prevent adding the same message twice:
		if(messageID != null && messageID == this.lastMessageID)
			return;
		
		// Custom actions on new messages:
		if(!this.onNewMessage(dateObject, userID, userName, userRoleClass, messageID, messageText, ip))
			return;
		
		var rowClass = (this.chatList.childNodes && (this.chatList.childNodes.length % 2 != 0)) ? 'rowOdd' : 'rowEven';
		var title = (ip != null) ? ' title="IP: ' + ip + '"' : '';
		var dateTime = this.settings['dateFormat'] ? '<span class="dateTime">' + this.formatDate(this.settings['dateFormat'], dateObject) + '</span> ' : '';
		this.chatList.innerHTML += '<div class="'
								+ rowClass
								+ '">'
								+ dateTime
								+ '<span class="'
								+ userRoleClass
								+ '"'
								+ title
								+ ' dir="'
								+ this.baseDirection
								+ '">'
								+ userName
								+ '</span>: '
								+ this.replaceText(messageText)
								+ '</div>';
		
		this.lastMessageID = messageID;
	},
	
	updateChatlistView: function() {
		if(this.chatList.childNodes) {
			while(this.chatList.childNodes.length > this.settings['maxMessages']) {
				this.chatList.removeChild(this.chatList.firstChild);
			}
		}
			
		if(this.settings['autoScroll'])
			this.chatList.scrollTop = this.chatList.scrollHeight;
	},
	
	encodeText: function(text) {
		return encodeURIComponent(text);
	},

	decodeText: function(text) {
		return decodeURIComponent(text);
	},

	utf8Encode: function(plainText) {
		var utf8Text = '';
		for(var n=0; n<plainText.length; n++) {
			var c=plainText.charCodeAt(n);
			if(c<128) {
				utf8Text += String.fromCharCode(c);
			} else if((c>127) && (c<2048)) {
				utf8Text += String.fromCharCode((c>>6)|192);
				utf8Text += String.fromCharCode((c&63)|128);
			} else {
				utf8Text += String.fromCharCode((c>>12)|224);
				utf8Text += String.fromCharCode(((c>>6)&63)|128);
				utf8Text += String.fromCharCode((c&63)|128);
			}
		}
		return utf8Text;
	},

	utf8Decode: function(utf8Text) {
		var plainText = '';
		var i=0;
		var c=c1=c2=0;
		while(i<utf8Text.length) {
			c = utf8Text.charCodeAt(i);
			if(c<128) {
				plainText += String.fromCharCode(c);
				i++;
			} else if((c>191) && (c<224)) {
				c2 = utf8Text.charCodeAt(i+1);
				plainText += String.fromCharCode(((c&31)<<6) | (c2&63));
				i+=2;
			} else {
				c2 = utf8Text.charCodeAt(i+1);
				c3 = utf8Text.charCodeAt(i+2);
				plainText += String.fromCharCode(((c&15)<<12) | ((c2&63)<<6) | (c3&63));
				i+=3;
			}
		}
		return plainText;
	},

	encodeSpecialChars: function(text) {
		if (!arguments.callee.regExp) {
			arguments.callee.regExp = new RegExp('[&<>\'"]', 'g');
		}
		
		return text.replace(
			arguments.callee.regExp,
			this.encodeSpecialCharsCallback
		);
	},
	
	encodeSpecialCharsCallback: function(str) {
		switch(str) {
			case '&':
				return '&amp;';
			case '<':
				return '&lt;';
			case '>':
				return '&gt;';
			case '\'':
				// As &apos; is not supported by IE, we use &#39; as replacement for ('):
				return '&#39;';
			case '"':
				return '&quot;';
			default:
				return str;
		}
	},

	decodeSpecialChars: function(text) {
		if (!arguments.callee.regExp) {
			arguments.callee.regExp = new RegExp('(&amp;)|(&lt;)|(&gt;)|(&#39;)|(&quot;)', 'g');
		}
		
		return text.replace(
			arguments.callee.regExp,
			this.decodeSpecialCharsCallback
		);
	},
	
	decodeSpecialCharsCallback: function(str) {
		switch(str) {
			case '&amp;':
				return '&';
			case '&lt;':
				return '<';
			case '&gt;':
				return '>';
			case '&#39;':
				return '\'';
			case '&quot;':
				return '"';
			default:
				return str;
		}
	},

	inArray: function(haystack, needle) {
		var i = haystack.length;
		while(i--)
			if(haystack[i] === needle)
				return true;
		return false;
	},

	arraySearch: function(needle, haystack) {
	    for(var i in haystack){
	        if(haystack[i] == needle)
	        	return i;
	    }
	    return false;
	},

	stripTags: function(str) {
		if (!arguments.callee.regExp) {
			arguments.callee.regExp = new RegExp('<\\/?[^>]+?>', 'g');
		}
		
		return str.replace(arguments.callee.regExp, '');
	},

	stripBBCodeTags: function(str) {
		if (!arguments.callee.regExp) {
			arguments.callee.regExp = new RegExp('\\[\\/?[^\\]]+?\\]', 'g');
		}
		
		return str.replace(arguments.callee.regExp, '');
	},	

	escapeRegExp: function(text) {
		if (!arguments.callee.regExp) {
			var specials = new Array(
				'^', '$', '*', '+', '?', '.', '|', '/',
				'(', ')', '[', ']', '{', '}', '\\'
			);
			
			// arguments.callee inside a function always refers to the function itself,
			// so we can store our static regular expression as property of this function:
			arguments.callee.regExp = new RegExp(
				'(\\' + specials.join('|\\') + ')', 'g'
			);
		}
		return text.replace(arguments.callee.regExp, '\\$1');
	},
	
	addSlashes: function(text) {
		// Adding slashes in front of apostrophs and backslashes to ensure a valid JavaScript expression:
		return text.replace(/\\/g, '\\\\').replace(/\'/g, '\\\'');
	},

	formatDate: function(format, date) {
		date = (date == null) ? new date() : date;
		
		return format
		.replace(/%Y/g, date.getFullYear())
		.replace(/%m/g, this.addLeadingZero(date.getMonth()+1))
		.replace(/%d/g, this.addLeadingZero(date.getDate()))
		.replace(/%H/g, this.addLeadingZero(date.getHours()))
		.replace(/%i/g, this.addLeadingZero(date.getMinutes()))
		.replace(/%s/g, this.addLeadingZero(date.getSeconds()));
	},
	
	addLeadingZero: function(number) {
		number = number.toString();
		if(number.length < 2)
			number = '0'+number;
		return number;
	},
	
	getRoleClass: function(roleID) {
		switch(roleID) {
			case '0':
				return 'guest';
			case '1':
				return 'user';
			case '2':
				return 'moderator';
			case '3':
				return 'admin';
			case '4':
				return 'chatBot';
			default:
				return 'default';
		}
	},

	updateChatGetInfos: function(infoArray) {
		this.updateChat(
			'&getInfos=' + this.encodeText(infoArray.join(','))
		);
	},
	
	updateChat: function(paramString) {
		var requestUrl = this.url
						+ '&lastID='
						+ this.lastID;
		if(paramString) {
			requestUrl += paramString;
		}
		this.makeRequest(requestUrl,'GET',null);
	},
	
	sendMessage: function() {
		var text = this.inputField.value;
		if(!text)
			return;
		if(this.settings['persistFontColor'] && this.settings['fontColor'] && text.charAt(0) != '/') {
			// Add color tags with the font color set to each message:
			text = '[color=' + this.settings['fontColor'] + ']' + text + '[/color]';
		}
		clearTimeout(this.timer);
		var message = 	'lastID='
						+ this.lastID
						+ '&text='
						+ this.encodeText(text);				
		this.makeRequest(this.url,'POST',message);
		this.inputField.value = '';
		this.inputField.focus();
	},

	getScriptLinkValue: function(value) {
		// This method returns plainText encoded values from javascript links
		// The value has to be utf8Decoded for MSIE and Opera:
		switch(navigator.appName) {
			case 'Microsoft Internet Explorer':
				return this.utf8Decode(value);
			case 'Opera':
				return this.utf8Decode(value);
			default:
				return value;
		}
	},

	switchChannelWrapper: function(channel) {
		this.switchChannel(this.getScriptLinkValue(channel));
	},

	privateMessageWrapper: function(userName) {
		this.privateMessage(this.getScriptLinkValue(userName));
	},

	privateMessage: function(userName) {
		this.inputField.value = '';
		this.insertText('/msg ' + userName + ' ');
	},

	switchChannel: function(channel) {
		if(!channel)
			return;
		clearTimeout(this.timer);	
		var message = 	'lastID='
						+ this.lastID
						+ '&channelName='
						+ this.encodeText(channel);		
		this.makeRequest(this.url,'POST',message);
		if(this.inputField && this.settings['autoFocus'])
			this.inputField.focus();
	},

	logout: function() {
		clearTimeout(this.timer);
		var message = 'logout=true';
		this.makeRequest(this.url,'POST',message);
	},
	
	handleLogout: function(url) {
		window.location = url+"&pageid="+document.getElementById("pageid").value;
	},

	showHide: function(id, styleDisplay) {
		if(styleDisplay)
			document.getElementById(id).style.display = styleDisplay;
		else {
			if(document.getElementById(id).style.display == 'none') 
				document.getElementById(id).style.display = 'block'; 
			else
				document.getElementById(id).style.display = 'none';
		}
	},

	setPersistFontColor: function(bool) {
		this.settings['persistFontColor'] = bool;
		
		if(!this.settings['persistFontColor']) {
			// Reset the font color:
			this.settings['fontColor'] = null;
			// Reset the inputField font color:
			if(this.inputField)
				this.inputField.style.color = '';
		}
	},

	setFontColor: function(color) {
		if(this.settings['persistFontColor']) {
			// Set the font color:
			this.settings['fontColor'] = color;
			// Set the inputField font color to the font color:
			if(this.inputField)
				this.inputField.style.color = color;
			
			if(this.colorCodesContainer) {
				// Hide the color container and set the focus to the input field:
				this.colorCodesContainer.style.display = 'none';
			if(this.inputField)
				this.inputField.focus();
			}
		} else {
			this.insert('[color=' + color + ']', '[/color]');
		}
	},
	
	insertText: function(text) {
		this.insert(text, '');
	},
	
	insertBBCode: function(bbCode) {
		switch(bbCode) {			
			case 'url':
				var url = prompt(this.lang['urlDialog'], 'http://');
				if(url)
					this.insert(url,''); //it will automatically convert to link, so no need for bbcode for urls
				else
					this.inputField.focus();
				break;
			default:
				this.insert('[' + bbCode + ']', '[/' + bbCode + ']');		
		}
	},

	insert: function(startTag, endTag) {
		this.inputField.focus();
		// Internet Explorer:
		if(typeof document.selection != 'undefined') {
			// Insert the tags:
			var range = document.selection.createRange();
			var insText = range.text;
			range.text = startTag + insText + endTag;
			// Adjust the cursor position:
			range = document.selection.createRange();
			if (insText.length == 0) {
				range.move('character', -endTag.length);
			} else {
				range.moveStart('character', startTag.length + insText.length + endTag.length);			
			}
			range.select();
		}
		// Firefox, etc. (Gecko based browsers):
		else if(typeof this.inputField.selectionStart != 'undefined') {
			// Insert the tags:
			var start = this.inputField.selectionStart;
			var end = this.inputField.selectionEnd;
			var insText = this.inputField.value.substring(start, end);
			this.inputField.value = this.inputField.value.substr(0, start) + startTag + insText + endTag + this.inputField.value.substr(end);
			// Adjust the cursor position:
			var pos;
			if (insText.length == 0) {
				pos = start + startTag.length;
			} else {
				pos = start + startTag.length + insText.length + endTag.length;
			}
			this.inputField.selectionStart = pos;
			this.inputField.selectionEnd = pos;
		}
		// Other browsers:
		else {
			var pos = this.inputField.value.length;
			this.inputField.value = this.inputField.value.substr(0, pos) + startTag + endTag + this.inputField.value.substr(pos);
		}
	},
	
	replaceText: function(text) {
		try{
			text = this.replaceLineBreaks(text);
			// Replace commands and Chat Bot infos:
			if(text.charAt(0) == '/')
				text = this.replaceCommands(text);
			else {
				text = this.replaceBBCode(text);
				text = this.replaceHyperLinks(text);
				text = this.replaceEmoticons(text);
			}
			text = this.breakLongWords(text);
		} catch(e){
			//alert(e);
		}
		return text;
	},
		
	replaceCommands: function(text) {
		try {
			if(text.charAt(0) != '/')
				return text;
			
			textParts = text.split(' ');
				
			switch(textParts[0]) {
				case '/login':
					return	'<span class="chatBotMessage">'
							+ this.lang['login'].replace(/%s/, textParts[1])
							+ '</span>';
				case '/logout':
					var type = '';
					if(textParts.length == 3)
						type = textParts[2];
					return	'<span class="chatBotMessage">'
							+ this.lang['logout' + type].replace(/%s/, textParts[1])
							+ '</span>';
				case '/channelEnter':
					return	'<span class="chatBotMessage">'
							+ this.lang['channelEnter'].replace(/%s/, textParts[1])
							+ '</span>';
				case '/channelLeave':
					return	'<span class="chatBotMessage">'
							+ this.lang['channelLeave'].replace(/%s/, textParts[1])
							+ '</span>';
				case '/privmsg':
					var privMsgText = textParts.slice(1).join(' ');
					privMsgText = this.replaceBBCode(privMsgText);
					privMsgText = this.replaceHyperLinks(privMsgText);
					privMsgText = this.replaceEmoticons(privMsgText);
					return	'<span class="privmsg">'
							+ this.lang['privmsg']
							+ '</span> '
							+ privMsgText;
				case '/privmsgto':
					var privMsgText = textParts.slice(2).join(' ');
					privMsgText = this.replaceBBCode(privMsgText);
					privMsgText = this.replaceHyperLinks(privMsgText);
					privMsgText = this.replaceEmoticons(privMsgText);
					return	'<span class="privmsg">'
							+ this.lang['privmsgto'].replace(/%s/, textParts[1])
							+ '</span> '
							+ privMsgText;
				case '/privaction':
					var privActionText = textParts.slice(1).join(' ');
					privActionText = this.replaceBBCode(privActionText);
					privActionText = this.replaceHyperLinks(privActionText);
					privActionText = this.replaceEmoticons(privActionText);
					return	'<span class="privmsg">'
							+ this.lang['privmsg']
							+ '</span> <span class="action">'
							+ privActionText
							+ '</span>';
				case '/privactionto':
					var privActionText = textParts.slice(2).join(' ');
					privActionText = this.replaceBBCode(privActionText);
					privActionText = this.replaceHyperLinks(privActionText);
					privActionText = this.replaceEmoticons(privActionText);
					return	'<span class="privmsg">'
							+ this.lang['privmsgto'].replace(/%s/, textParts[1])
							+ '</span> <span class="action">'
							+ privActionText
							+ '</span>';
				case '/action':
					var actionText = textParts.slice(1).join(' ');
					actionText = this.replaceBBCode(actionText);
					actionText = this.replaceHyperLinks(actionText);
					actionText = this.replaceEmoticons(actionText);
					return	'<span class="action">'
							+ actionText
							+ '</span>';
				case '/invite':
					var inviteText = this.lang['invite']
										.replace(/%s/, textParts[1])
										.replace(
											/%s/,
											'<a href="javascript:ajaxChat.switchChannelWrapper(\''
											+ this.encodeText(this.addSlashes(this.decodeSpecialChars(textParts[2])))
											+ '\');" title="'
											+ this.lang['joinChannel'].replace(/%s/, textParts[2])
											+ '">'
											+ textParts[2]
											+ '</a>'
										);
					return	'<span class="chatBotMessage">'
							+ inviteText
							+ '</span>';
				case '/inviteto':
					var inviteText = this.lang['inviteto']
										.replace(/%s/, textParts[1])
										.replace(/%s/, textParts[2]);
					return	'<span class="chatBotMessage">'
							+ inviteText
							+ '</span>';
				case '/uninvite':
					var uninviteText = this.lang['uninvite']
										.replace(/%s/, textParts[1])
										.replace(/%s/, textParts[2]);
					return	'<span class="chatBotMessage">'
							+ uninviteText
							+ '</span>';
				case '/uninviteto':
					var uninviteText = this.lang['uninviteto']
										.replace(/%s/, textParts[1])
										.replace(/%s/, textParts[2]);
					return	'<span class="chatBotMessage">'
							+ uninviteText
							+ '</span>';
				case '/queryOpen':
					return	'<span class="chatBotMessage">'
							+ this.lang['queryOpen'].replace(/%s/, textParts[1])
							+ '</span>';
				case '/queryClose':
					return	'<span class="chatBotMessage">'
							+ this.lang['queryClose'].replace(/%s/, textParts[1])
							+ '</span>';
				case '/ignoreAdded':
					return	'<span class="chatBotMessage">'
							+ this.lang['ignoreAdded'].replace(/%s/, textParts[1])
							+ '</span>';					
				case '/ignoreRemoved':
					return	'<span class="chatBotMessage">'
							+ this.lang['ignoreRemoved'].replace(/%s/, textParts[1])
							+ '</span>';					
				case '/ignoreList':
					return	'<span class="chatBotMessage">'
							+ this.lang['ignoreList'] + ' '
							+ textParts.slice(1).join(', ')
							+ '</span>';					
				case '/ignoreListEmpty':
					return	'<span class="chatBotMessage">'
							+ this.lang['ignoreListEmpty']
							+ '</span>';					
				case '/kick':
					return	'<span class="chatBotMessage">'
							+ this.lang['logoutKicked'].replace(/%s/, textParts[1])
							+ '</span>';
				case '/who':
					var users = textParts.slice(1);
					var listUsers = new Array();
					for(var user in users) {
						listUsers.push(
							'<a href="javascript:ajaxChat.privateMessageWrapper(\''
							+ this.encodeText(this.addSlashes(this.decodeSpecialChars(users[user])))
							+ '\');" title="'
							+ this.lang['sendPrivateMessage'].replace(/%s/, users[user])
							+ '">'
							+ users[user]
							+ '</a>'
						);
					}					
					return	'<span class="chatBotMessage">'
							+ this.lang['who'] + ' '
							+ listUsers.join(', ')
							+ '</span>';
				case '/whoEmpty':
					return	'<span class="chatBotMessage">'
							+ this.lang['whoEmpty']
							+ '</span>';
				case '/list':
					var channels = textParts.slice(1);
					var listChannels = new Array();
					for(var channel in channels) {
						listChannels.push(
							'<a href="javascript:ajaxChat.switchChannelWrapper(\''
							+ this.encodeText(this.addSlashes(this.decodeSpecialChars(channels[channel])))
							+ '\');" title="'
							+ this.lang['joinChannel'].replace(/%s/, channels[channel])
							+ '">'
							+ channels[channel]
							+ '</a>'
						);
					}
					return	'<span class="chatBotMessage">'
							+ this.lang['list'] + ' '
							+ listChannels.join(', ')
							+ '</span>';
				case '/bans':
					return	'<span class="chatBotMessage">'
							+ this.lang['bans'] + ' '
							+ textParts.slice(1).join(', ')
							+ '</span>';
				case '/bansEmpty':
					return	'<span class="chatBotMessage">'
							+ this.lang['bansEmpty']
							+ '</span>';
				case '/unban':
					return	'<span class="chatBotMessage">'
							+ this.lang['unban'].replace(/%s/, textParts[1])
							+ '</span>';
				case '/whois':
					return	'<span class="chatBotMessage">'
							+ this.lang['whois'].replace(/%s/, textParts[1]) + ' '
							+ textParts[2]
							+ '</span>';
				case '/roll':
					var rollText = this.lang['roll'].replace(/%s/, textParts[1]);
					rollText = rollText.replace(/%s/, textParts[2]);
					rollText = rollText.replace(/%s/, textParts[3]);
					return	'<span class="chatBotMessage">'
							+ rollText
							+ '</span>';
				case '/error':
					var errorMessage;
					if(textParts.length > 2)
						errorMessage = this.lang['error' + textParts[1]].replace(/%s/, textParts.slice(2).join(' ')) + ' ';
					else
						errorMessage = this.lang['error' + textParts[1]];
					return	'<span class="chatBotErrorMessage">'
							+ errorMessage
							+ '</span>';
				default:
					return this.replaceCustomCommands(text, textParts);
			}
		} catch(e) {
			//alert(e);
		}
		return text;
	},

	containsUnclosedTags: function(str) {
		if (!arguments.callee.regExpOpenTags || !arguments.callee.regExpCloseTags) {
			arguments.callee.regExpOpenTags		= new RegExp('<[^>\\/]+?>', 'gm');
			arguments.callee.regExpCloseTags	= new RegExp('<\\/[^>]+?>', 'gm');
		}	
		var openTags	= str.match(arguments.callee.regExpOpenTags);
		var closeTags	= str.match(arguments.callee.regExpCloseTags);
		// Return true if the number of tags doesn't match:
		if((!openTags && closeTags) || (openTags && !closeTags) || (openTags && closeTags && (openTags.length != closeTags.length)))
			return true;
		return false;
	},
		
	breakLongWords: function(text) {
		if(!this.settings['wordWrap'])
			return text;
		var newText = '';
		var charCounter = 0;
		var currentChar, withinTag, withinEntity;
		
		for(var i=0; i<text.length; i++) {
			currentChar = text.charAt(i);
			
			// Check if we are within a tag or entity:
			if(currentChar == '<') {
				withinTag = true;
				// Reset the charCounter after newline tags (<br/>):
				if(i>5 && text.substr(i-5,4) == '<br/')
					charCounter = 0;				
			} else if(withinTag && i>0 && text.charAt(i-1) == '>') {
				withinTag = false;
				// Reset the charCounter after newline tags (<br/>):
				if(i>4 && text.substr(i-5,4) == '<br/')
					charCounter = 0;
			} else if(currentChar == '&') {
				withinEntity = true;
			} else if(withinEntity && i>0 && text.charAt(i-1) == ';') {
				withinEntity = false;
				// We only increase the charCounter once for the whole entiy:
				charCounter++;
			}
				
			if(!withinTag && !withinEntity) {
				// Reset the charCounter if we encounter a word boundary:
				if(currentChar == ' ' || currentChar == '\n' || currentChar == '\t') {
					charCounter = 0;
				} else {
					// We are not within a tag or entity, increase the charCounter:
					charCounter++;
				}
				if(charCounter > this.settings['maxWordLength']) {
					// maxWordLength has been reached, break here and reset the charCounter:
					newText += this.settings['breakString'];
					charCounter = 0;
				}
			}		
			// Add the current char to the text:
			newText += currentChar;
		}
		
		return newText;
	},
	
	replaceBBCode: function(text) {
		if(!this.settings['bbCode'])
			return text;

		if (!arguments.callee.regExp) {
			arguments.callee.regExp = new RegExp(
				'\\[(\\w+)(?:=([^<>]*?))?\\](.+?)\\[\\/\\1\\]',
				'gm'
			);
		}
			
		return text.replace(
			arguments.callee.regExp,
			this.replaceBBCodeCallback
		);
	},
	
	replaceBBCodeCallback: function(str, p1, p2, p3) {
		
		// Only replace predefined BBCode tags:
		if(!ajaxChat.inArray(ajaxChat.bbCodeTags, p1))
			return str;
		
		// Avoid invalid XHTML (unclosed tags):
		if(ajaxChat.containsUnclosedTags(p3))
			return str;
			
		switch(p1) {			

			case 'url':
				var url;
				if(p2)
					url = p2.replace(/\s/gm, ajaxChat.encodeText(' '));
				else
					url = ajaxChat.stripBBCodeTags(p3.replace(/\s/gm, ajaxChat.encodeText(' ')));
				if (!arguments.callee.regExpUrl) {
					arguments.callee.regExpUrl = new RegExp(
						'^((http)|(https)|(ftp)|(irc)):\\/\\/',
						''
					);
				}
				if(!url || !url.match(arguments.callee.regExpUrl))
					return str;
				return '<a href="' + url + '" onclick="window.open(this.href); return false;">' + ajaxChat.replaceBBCode(p3) + '</a>';

			case 'color':
				if(!p2)
					return str;					
				// Only allow predefined color codes:
				if(!ajaxChat.inArray(ajaxChat.colorCodes, p2))
					return str;				
				return '<span style="color:' + p2 + ';">' +	ajaxChat.replaceBBCode(p3) + '</span>';

			case 'quote':
				if(p2)
					return '<span class="quote"><cite>' + ajaxChat.lang['cite'].replace(/%s/, p2) + '</cite><q>' + ajaxChat.replaceBBCode(p3) + '</q></span>';
				return '<span class="quote"><q>' + ajaxChat.replaceBBCode(p3) + '</q></span>';

			case 'code':
				// Replace vertical tabs and multiple spaces with two non-breaking space characters:
				return '<code>' + ajaxChat.replaceBBCode(p3.replace(/\t|(?:  )/gm, '&#160;&#160;')) + '</code>';		
									
			case 'u':
				return '<span style="text-decoration:underline;">' + ajaxChat.replaceBBCode(p3) + '</span>';

			default:
				return '<' + p1 + '>' + ajaxChat.replaceBBCode(p3) + '</' + p1 + '>';

		}	
	},
	
	replaceHyperLinks: function(text) {
		if(!this.settings['hyperLinks'])
			return text;
		
		if (!arguments.callee.regExp) {
			arguments.callee.regExp = new RegExp(
				'(^|\\s|>)(((http)|(https)|(ftp)|(irc)):\\/\\/[^\\s<>]+)(?!<\\/a>)',
				'gm'
			);
		}
			
		return text.replace(
			arguments.callee.regExp,
			// Specifying an anonymous function as second parameter:
			function(str, p1, p2) {
				return p1 + '<a href="' + p2 + '" onclick="window.open(this.href); return false;">' + p2 + '</a>';
			}
		);
	},

	replaceLineBreaks: function(text) {
		if (!arguments.callee.regExp) {
			arguments.callee.regExp = new RegExp('\\n',	'g');
		}
		if(!this.settings['lineBreaks'])
			return text.replace(arguments.callee.regExp, ' ');
		else
			return text.replace(arguments.callee.regExp, '<br/>');
	},

	replaceEmoticons: function(text) {
		if(!this.settings['emoticons'])
			return text;
		
		if (!arguments.callee.regExp) {
			var regExpStr = '^(.*)(';
			for(var i=0; i<this.emoticonCodes.length; i++) {
				if(i!=0)
					regExpStr += '|';
				regExpStr += '(?:' + this.escapeRegExp(this.emoticonCodes[i]) + ')';
			}
			regExpStr += ')(.*)$';

			// arguments.callee inside a function always refers to the function itself,
			// so we can store our static regular expression as property of this function:
			arguments.callee.regExp = new RegExp(regExpStr, 'gm');
		}
		
		return text.replace(
			arguments.callee.regExp,			
			this.replaceEmoticonsCallback
		);
	},
	
	replaceEmoticonsCallback: function(str, p1, p2, p3) {

		if (!arguments.callee.regExp) {
			arguments.callee.regExp = new RegExp('(="[^"]*$)|(&[^;]*$)', '');
		}
		
		// Avoid replacing emoticons in tag attributes or XHTML entities:
		if(p1.match(arguments.callee.regExp))
			return str;
			
		if(p2) {
			// Get the index of the found emoticon:
			var index = ajaxChat.arraySearch(p2, ajaxChat.emoticonCodes);
							
			return 	ajaxChat.replaceEmoticons(p1)
				+	'<img src="'
				+	ajaxChat.emoticonPath
				+	ajaxChat.emoticonFiles[index]
				+	'" alt="'
				+	p2
				+	'" />'
				+ 	ajaxChat.replaceEmoticons(p3);
		}
		
		// No emoticon found, just return:
		return str;
	},

	getActiveStyle: function() {
		var cookie = this.readCookie(this.sessionName + '_style');
		var style = cookie ? cookie : this.getPreferredStyleSheet();
		return style;		
	},

	initStyle: function() {
		this.setActiveStyleSheet(this.getActiveStyle());
	},
	
	persistStyle: function() {
		this.createCookie(this.sessionName + '_style', this.getActiveStyleSheet(), this.cookieExpiration);
	},
	
	setSelectedStyle: function() {
		var style = this.getActiveStyle();
		var styleOptions = this.styleSelection.getElementsByTagName('option');
		for(var i=0; i<styleOptions.length; i++) {
			if(styleOptions[i].value == style) {
				styleOptions[i].selected = true;
				break;
			}
		}				
	},
	
	getSelectedStyle: function() {
		var styleOptions = this.styleSelection.getElementsByTagName('option');
		if(this.styleSelection.selectedIndex == -1)
			return styleOptions[0].value;
		else
			return styleOptions[this.styleSelection.selectedIndex].value;
	},
	
	setActiveStyleSheet: function(title) {
		var i, a, main;
		var titleFound = false;
		for(i=0; (a = document.getElementsByTagName('link')[i]); i++) {
			if(a.getAttribute('rel').indexOf('style') != -1 && a.getAttribute('title')) {
				a.disabled = true;
				if(a.getAttribute('title') == title) {
	                a.disabled = false;
	                titleFound = true;
				}
			}
		}
		if(!titleFound && title != null)
		   this.setActiveStyleSheet(this.getPreferredStyleSheet());
	},
	
	getActiveStyleSheet: function() {
		var i, a;
		for(i=0; (a = document.getElementsByTagName('link')[i]); i++) {
			if(a.getAttribute('rel').indexOf('style') != -1 && a.getAttribute('title') && !a.disabled) return a.getAttribute('title');
		}
		return null;
	},
	
	getPreferredStyleSheet: function() {
		var i, a;
		for(i=0; (a = document.getElementsByTagName('link')[i]); i++) {
			if(a.getAttribute('rel').indexOf('style') != -1
				&& a.getAttribute('rel').indexOf('alt') == -1
				&& a.getAttribute('title')
				) return a.getAttribute('title');
		}
		return null;
	},
	
	createCookie: function(name,value,days) {
		if (days) {
			var date = new Date();
			date.setTime(date.getTime()+(days*24*60*60*1000));
			var expires = '; expires='+date.toGMTString();
		}
		else expires = '';
		document.cookie = name+'='+value+expires+'; path=/';
	},
	
	readCookie: function(name) {
		if(!document.cookie)
		   return null;
		var nameEQ = name + '=';
		var ca = document.cookie.split(';');
		for(var i=0;i < ca.length;i++) {
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1,c.length);
			if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
		}
		return null;
	},

	isCookieEnabled: function() {
		this.createCookie(this.sessionName + '_cookie_test', true, 1);
		var cookie = this.readCookie(this.sessionName + '_cookie_test');
		if(cookie) {
			// Unset the test cookie:
			this.createCookie(this.sessionName + '_cookie_test', true, -1);
			// Cookie test successfull, return true:
			return true;
		}
		return false;
	},
	
	finalize: function() {
		this.persistSettings();
		this.persistStyle();
		
		this.customFinalize();
	},

	// Override to handle custom info messages
	handleCustomInfoMessage: function(infoType, infoData) {
	},

	// Override to add custom initialization code
	// This method is called on page load
	customInitialize: function() {		
	},

	// Override to add custom finalization code
	// This method is called on page unload
	customFinalize: function() {	
	},
	
	// Override to replace custom commands:
	// Return replaced text for custom commands
	// text contains the whole message, textParts the message split up as words array
	replaceCustomCommands: function(text, textParts) {
		return text;
	},
	
	// Override to perform custom actions on new messages:
	// Return true if message is to be added to the chatList, else false
	onNewMessage: function(dateObject, userID, userName, userRoleClass, messageID, messageText, ip) {
		return true;
	}

}