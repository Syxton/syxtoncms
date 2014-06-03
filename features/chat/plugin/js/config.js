/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Ajax Chat config parameters:
var ajaxChatConfig = {
	
	// The URL to the XML chat messages file:
	url: './?ajax=true',

	
	// The ID of the chat messages list:
	chatListID: 'chatList',
	// The ID of the online users list:
	onlineListID: 'onlineList',
	// The ID of the message text input field
	inputFieldID: 'messageText',
	// The ID of the channel selection:
	channelSelectionID: 'channelSelection',
	// The ID of the style selection:
	styleSelectionID: 'styleSelection',
	// The ID of the emoticons container:
	emoticonsContainerID: 'emoticonsContainer',
	// The ID of the color codes container:
	colorCodesContainerID: 'colorCodesContainer',

	
	// Defines if BBCode tags are replaced with the associated HTML code tags:
	bbCode: true,
	// Defines if hyperlinks are made clickable:
	hyperLinks: true,
	// Defines if line breaks are enabled:
	lineBreaks: true,
	// Defines if emoticon codes are replaced with their associated images:
	emoticons: true,

	// Defines if the focus is automatically set to the input field on chat load or channel switch:
	autoFocus: true,
	// Defines if the chat list scrolls automatically to display the latest messages:
	autoScroll: true,	
	// The maximum count of messages displayed in the chat list:
	maxMessages: 100,

	// Defines if long words are wrapped to avoid vertical scrolling:
	wordWrap: true,
	// Defines the maximum length before a word gets wrapped: 
	maxWordLength: 32,
	// Defines the character that is used to wrap long words:
	breakString: '&#8203;',
	
	// Defines the format of the date and time displayed for each chat message:
	dateFormat: '(%H:%i:%s)',
	
	// Defines if font colors persist without the need to assign them to each message:
	persistFontColor: false,

	
	// Defines a list of settings which are not to be stored in a session cookie:
	nonPersistentSettings: new Array(
		'bbCode',
		'hyperLinks',
		'lineBreaks',
		'emoticons',
		'autoFocus',
		'maxMessages',
		'wordWrap',
		'maxWordLength',
		'breakString',
		'dateFormat'
	),

	
	// The time in ms between update calls to retrieve new chat messages:
	timerRate: 2000,

	// The time in days until the style and setting cookies expire:
	cookieExpiration: 365,
	
	// The path to the emoticon images:
	emoticonPath: 'img/emoticons/',

	// Defines the list of allowed BBCodes:
	bbCodeTags: new Array(
		'b',
		'i',
		'u',
		'quote',
		'code',
		'color',
		'url'
	),
	
	// Defines the list of allowed color codes:
	colorCodes: new Array(
		'gray',
		'silver',
		'white',	
		'yellow',
		'orange',
		'red',
		'fuchsia',
		'purple',
		'navy',
		'blue',
		'aqua',
		'teal',
		'green',
		'lime',
		'olive',
		'maroon',
		'black'
	),
	
	// Defines the list of allowed emoticon codes:
	emoticonCodes: new Array(
		':)',
		':(',
		';)',
		':P',
		':D',
		':|',
		':O',
		':?',
		'8)',
		'8o',
		'B)',
		':-)',
		':-(',
		':-*',
		'O:-D',
		'>:-D',
		':o)',
		':idea:',
		':important:',
		':help:',
		':error:',
		':warning:',
		':favorite:'		
 	),
	
 	// Defines the list of emoticon files associated with the emoticon codes:
	emoticonFiles: new Array(
		'smile.png',
		'sad.png',
		'wink.png',
		'razz.png',
		'grin.png',
		'plain.png',
		'surprise.png',
		'confused.png',
		'glasses.png',
		'eek.png',
		'cool.png',
		'smile-big.png',
		'crying.png',
		'kiss.png',
		'angel.png',
		'devilish.png',
		'monkey.png',
		'idea.png',
		'important.png',
		'help.png',
		'error.png',
		'warning.png',
		'favorite.png'
	),

	
	// The following settings are usually overwritten by server-side values:
	
	// Session identification, used for style and setting cookies:
	sessionName: 'ajax_chat',
	
	// The name of the chat bot:
	chatBotName: 'ChatBot',
	// The userID of the chat bot:
	chatBotID: 2147483647,
	
	// The userName of the chat user:
	userName: null,
	// The userID of the chat user:
	userID: null	

}