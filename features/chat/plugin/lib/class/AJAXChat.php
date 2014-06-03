<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Ajax Chat backend logic:
class AJAXChat {

	var $db;
	var $_config;
	var $_requestVars;
	var $_infoMessages;
	var $_channels;
	var $_allChannels;
	var $_view;
	var $_lang;
	var $_unInvitations;
	var $_customVars;
	var $_sessionNew;
	
	function AJAXChat() {
		$this->initialize();
	}

	function initialize() {
		// Initialize configuration settings:
		$this->initConfig();

		// Initialize the DataBase connection:
		$this->initDataBaseConnection();

		// Initialize request variables:
		$this->initRequestVars();
		
		// Initialize the chat session:
		$this->initSession();
		
		// Handle the browser request and send the response content:
		$this->handleRequest();
	}

	function initConfig() {
		$config = null;
		require(AJAX_CHAT_PATH.'lib/config.php');
		$this->_config = &$config;

		// Initialize custom configuration settings:
		$this->initCustomConfig();
	}
	
	function initRequestVars() {
		$this->_requestVars = array();
		$this->_requestVars['ajax']			= isset($_REQUEST['ajax'])			? true							: false;
		$this->_requestVars['userID']		= isset($_REQUEST['userID'])		? (int)$_REQUEST['userID']		: null;
		$this->_requestVars['userName']		= isset($_REQUEST['userName'])		? $_REQUEST['userName']			: null;
		$this->_requestVars['channelID']	= isset($_REQUEST['channelID'])		? (int)$_REQUEST['channelID']	: null;
		$this->_requestVars['channelName']	= isset($_REQUEST['channelName'])	? $_REQUEST['channelName']		: null;
		$this->_requestVars['text']			= isset($_REQUEST['text'])			? $_REQUEST['text']				: null;
		$this->_requestVars['lastID']		= isset($_REQUEST['lastID'])		? (int)$_REQUEST['lastID']		: 0;
		$this->_requestVars['login']		= isset($_REQUEST['login'])			? true							: false;
		$this->_requestVars['logout']		= isset($_REQUEST['logout'])		? true							: false;
		$this->_requestVars['password']		= isset($_REQUEST['password'])		? $_REQUEST['password']			: null;
		$this->_requestVars['view']			= isset($_REQUEST['view'])			? $_REQUEST['view']				: null;
		$this->_requestVars['command']		= isset($_REQUEST['command'])		? $_REQUEST['command']			: null;
		$this->_requestVars['year']			= isset($_REQUEST['year'])			? (int)$_REQUEST['year']		: null;
		$this->_requestVars['month']		= isset($_REQUEST['month'])			? (int)$_REQUEST['month']		: null;
		$this->_requestVars['day']			= isset($_REQUEST['day'])			? (int)$_REQUEST['day']			: null;
		$this->_requestVars['hour']			= isset($_REQUEST['hour'])			? (int)$_REQUEST['hour']		: null;
		$this->_requestVars['search']		= isset($_REQUEST['search'])		? $_REQUEST['search']			: null;
		$this->_requestVars['shoutbox']		= isset($_REQUEST['shoutbox'])		? true							: false;
		$this->_requestVars['getInfos']		= isset($_REQUEST['getInfos'])		? $_REQUEST['getInfos']			: null;
		
		// Initialize custom request variables:
		$this->initCustomRequestVars();
		
		// Remove slashes which have been added to user input strings if magic_quotes_gpc is On:
		if(get_magic_quotes_gpc()) {
			// It is safe to remove the slashes as we escape user data ourself
			array_walk(
				$this->_requestVars,
				create_function(
					'&$value, $key',
					'if(is_string($value)) $value = stripslashes($value);'
				)
			);
		}
	}
	
	function initDataBaseConnection() {
		// Create a new database object:
		$this->db = new AJAXChatDataBase(
			$this->_config['dbConnection']
		);
		// Connect to the database server, if no existing databse connection is given:
		if(!$this->_config['dbConnection']['link']) {
			$this->db->connect($this->_config['dbConnection']);
			if($this->db->error()) {
				echo $this->db->getError();
				die();
			}
		}
		// Select a database, if a database name is given:
		if($this->_config['dbConnection']['name']) {
			$this->db->select($this->_config['dbConnection']);
			if($this->db->error()) {
				echo $this->db->getError();
				die();
			}
		}
		// Unset the dbConnection array for safety purposes:
		unset($this->_config['dbConnection']);			
	}

	function initSession() {
		// Start the PHP session (if not already started):
		$this->startSession();
		
		// Logout if we get a logout request:
		if($this->getRequestVar('logout')) {
			$this->logout(true);
			return;
		}
		
		// Logout if the chat has been closed:
		if($this->isLoggedIn() && !$this->isChatOpen()) {
			$this->logout(true);
			return;
		}
		
		// Login:
		if(!$this->isLoggedIn() && (
				$this->getConfig('forceAutoLogin') || $this->getRequestVar('login') || $this->getRequestVar('userName') || $this->getRequestVar('shoutbox')
			)) {
			$this->login();
		}
		
		// Initialize the view:
		$this->initView();
		
		if($this->isLoggedIn()) {
			if(!$this->getView()) {
				if($this->getChannel() === null) {
					// Set channel, insert login messages and add to online list in chat view:
					$this->chatViewLogin();
				} else {
					$this->initChannel();
				}
			}
			
			// IP Security check:
			if($this->getConfig('ipCheck') && ($this->getSessionIP() === null || $this->getSessionIP() != $_SERVER['REMOTE_ADDR'])) {
				// Logout:
				$this->logout(true, ' IP');
			}
		}

		if(!$this->getRequestVar('ajax')) {
			// Set style cookie:
			$this->setStyle();
		}
	}

	function isChatOpen() {
		if($this->getUserRole() == AJAX_CHAT_ADMIN)
			return true;
		if($this->getConfig('chatClosed'))
			return false;
		$time = time();
		if($this->getConfig('timeZoneOffset') !== null) {
			// Subtract the server timezone offset and add the config timezone offset:
			$time -= date('Z', $time);
			$time += $this->getConfig('timeZoneOffset');
		}
		// Check the opening hours:
		if(($this->getConfig('openingHour') > date('G', $time)) || ($this->getConfig('closingHour') <= date('G', $time)))
			return false;
		// Check the opening weekdays:
		if(!in_array(date('w', $time), $this->getConfig('openingWeekDays')))
			return false;
		return true;	
	}

	function handleRequest() {
		if($this->getRequestVar('ajax')) {
			// Parse info requests (for current userName, etc.):
			$this->parseInfoRequests();

			// Parse message requests:
			$this->initMessageHandling();
			
			// Send chat messages and online user list in XML format:
			$this->sendXMLMessages();
		} else {
			// Display XHTML content for non-ajax requests:
			$this->sendXHTMLContent();
		}
	}

	function parseInfoRequests() {
		if($this->getRequestVar('getInfos')) {
			$infoRequests = explode(',', $this->getRequestVar('getInfos'));			
			foreach($infoRequests as $infoRequest) {
				$this->parseInfoRequest($infoRequest);
			}
		}
	}
	
	function parseInfoRequest($infoRequest) {
		switch($infoRequest) {
			case 'userID':
				$this->addInfoMessage($this->getUserID(), 'userID');
				break;
			case 'userName':
				$this->addInfoMessage($this->getUserName(), 'userName');
				break;
			default:
				$this->parseCustomInfoRequest($infoRequest);
		}
	}

	function sendXHTMLContent() {
		$httpHeader = new AJAXChatHTTPHeader($this->getConfig('contentEncoding'));

		$template = new AJAXChatTemplate($this, $this->getTemplateFileName(), $httpHeader->getContentType());

		// Send HTTP header:
		$httpHeader->send();		

		// Send parsed template content:
		echo $template->getParsedContent();
	}

	function getTemplateFileName() {
		if($this->isLoggedIn()) {
			switch($this->getView()) {
				case 'logs':
					return AJAX_CHAT_PATH.'lib/template/logs.html';
					break;
				default:
					return AJAX_CHAT_PATH.'lib/template/loggedIn.html';
			}
		} else {
			return AJAX_CHAT_PATH.'lib/template/loggedOut.html';
		}
	}

	function initView() {
		if($this->hasAccessTo($this->_requestVars['view'])) {
			$this->_view = $this->_requestVars['view'];
		}
	}

	function hasAccessTo($view) {
		switch($view) {
			case 'logs':
				if($this->getUserRole() == AJAX_CHAT_ADMIN)
					return true;
				else
					return false;
			default:
				return false;
		}
	}
	
	function login() {
		// Retrieve valid login user data (from request variables or session data):
		$userData = $this->getValidLoginUserData();
		
		if(!$userData) {
			$this->addInfoMessage('errorInvalidUser');
			return false;
		}

		// If the chat is closed, only the admin may login:
		if(!$this->isChatOpen() && $userData['userRole'] != AJAX_CHAT_ADMIN) {
			$this->addInfoMessage('errorChatClosed');
			return false;
		}

		if(!$this->getConfig('allowGuestLogins') && $userData['userRole'] == AJAX_CHAT_GUEST) {
			return false;
		}

		// Check if userName/userID is already in use:
		if($this->isUserInUse($userData['userID'], $userData['userName'])) {
			if($userData['userRole'] == AJAX_CHAT_USER || $userData['userRole'] == AJAX_CHAT_MODERATOR || $userData['userRole'] == AJAX_CHAT_ADMIN) {
				// Set the registered user inactive and remove the inactive users so the user can be logged in again:
				$this->setInactive($userData['userID']);
				$this->removeInactive();
			} else {
				$this->addInfoMessage('errorUserInUse');
				return false;
			}
		}
		
		// Check if user is banned:
		if($this->isUserBanned($userData['userID'], $userData['userName'])) {
			$this->addInfoMessage('errorBanned');
			return false;
		}
		
		// Check if the max number of users is logged in (not affecting moderators or admins):
		if(!($userData['userRole'] == AJAX_CHAT_MODERATOR || $userData['userRole'] == AJAX_CHAT_ADMIN) && $this->isMaxUsersLoggedIn()) {
			$this->addInfoMessage('errorMaxUsersLoggedIn');
			return false;
		}

		// Use a new session id (if session has been started by the chat):
		$this->regenerateSessionID();

		// Log in:
		$this->setUserID($userData['userID']);
		$this->setUserName($userData['userName']);
		$this->setUserRole($userData['userRole']);
		$this->setLoggedIn(true);	
		$this->setLoginTimeStamp(time());

		// IP Security check variable:
		$this->setSessionIP($_SERVER['REMOTE_ADDR']);

		// Add userID and userName to info messages:
		$this->addInfoMessage($this->getUserID(), 'userID');
		$this->addInfoMessage($this->getUserName(), 'userName');

		// Purge logs:
		if($this->getConfig('logsPurgeLogs'))	
			$this->purgeLogs();

		return true;
	}
	
	function chatViewLogin() {
		$channelID = $this->_requestVars['channelID'];
		$channelName = $this->_requestVars['channelName'];
		
		// Check the given channelID, or get channelID from channelName:
		if($channelID === null) {
			if($channelName) {
				$channelID = $this->getChannelIDFromChannelName($channelName);
				// channelName might need encoding conversion:
				if($channelID === null) {
					$channelID = $this->getChannelIDFromChannelName(
									$this->trimChannelName($channelName, $this->getConfig('contentEncoding'))
								);
				}
			}
		}

		// Validate the resulting channelID:
		if(!$this->validateChannel($channelID)) {
			$channelID = $this->getConfig('defaultChannelID');
		}

		$this->setChannel($channelID);
		$this->addToOnlineList();
			
		// Login message:
		$text = '/login '.$this->getUserName();
		$this->insertChatBotMessage(
			$this->getChannel(),
			$text
		);	
	}

	function initChannel() {
		$channelID = $this->_requestVars['channelID'];
		$channelName = $this->_requestVars['channelName'];

		if($channelID !== null) {
			$this->switchChannel($this->getChannelNameFromChannelID($channelID));			
		} else if($channelName) {
			if($this->getChannelIDFromChannelName($channelName) === null) {
				// channelName might need encoding conversion:
				$channelName = $this->trimChannelName($channelName, $this->getConfig('contentEncoding'));
			}		
			$this->switchChannel($channelName);	
		}
	}
	
	function logout($insertLogoutMessage=true, $type='') {
		if($this->isLoggedIn()) {
			if(!$this->getView() && $this->getChannel() !== null) {
				$this->chatViewLogout($insertLogoutMessage, $type);
			}
			
			$this->setLoggedIn(false);
			
			$this->destroySession();
		}
	}
	
	function chatViewLogout($insertLogoutMessage, $type) {
		if($insertLogoutMessage) {
			// Logout message
			$text = '/logout '.$this->getUserName().$type;
			$this->insertChatBotMessage(
				$this->getChannel(),
				$text
			);
		}
		$this->removeFromOnlineList();		
	}
	
	function switchChannel($channelName) {
		if($channelName && $channelName[0] == '[') {
			// Joining a private channel:
			$userName = $this->subString($channelName, 1, $this->stringLength($channelName)-2);
			if($userName == $this->getUserName()) {
				$channelID = $this->getPrivateChannelID();
			} else {
				$userID = $this->getIDFromName($userName);
				if($userID === null) {
					$channelID = null;
				} else {
					$channelID = $this->getPrivateChannelID($userID);
				}
			}
		} else {
			// Joining a normal channel:
			$channelID = $this->getChannelIDFromChannelName($channelName);
		}

		if($this->getChannel() == $channelID) {
			// User is already in the given channel, return:
			return;
		}

		// Check if we have a valid channel:
		if(!$this->validateChannel($channelID)) {				
			// Invalid channel:
			$text = '/error InvalidChannelName '.$channelName;
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				$text
			);
			return;
		}
		
		// Channel leave message
		$text = '/channelLeave '.$this->getUserName();
		$this->insertChatBotMessage(
			$this->getChannel(),
			$text
		);
			
		$this->setChannel($channelID);
		$this->updateOnlineList();

		// Channel enter message
		$text = '/channelEnter '.$this->getUserName();
		$this->insertChatBotMessage(
			$this->getChannel(),
			$text
		);

		$this->addInfoMessage($channelName, 'channelSwitch');
		$this->_requestVars['lastID'] = 0;
	}
	
	function addToOnlineList() {
		$sql = 'INSERT INTO '.$this->getConfig('dbTableNames','online').'(
					userID,
					userName,
					userRole,
					channel,
					dateTime,
					ip
				)
				VALUES (
					'.$this->db->makeSafe($this->getUserID()).',
					'.$this->db->makeSafe($this->getUserName()).',
					'.$this->db->makeSafe($this->getUserRole()).',
					'.$this->db->makeSafe($this->getChannel()).',
					NOW(),
					INET_ATON(\''.$_SERVER['REMOTE_ADDR'].'\')
				);';	
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function removeFromOnlineList() {
		$sql = 'DELETE FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					userID = '.$this->db->makeSafe($this->getUserID()).';';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function updateOnlineList() {
		$sql = 'UPDATE
					'.$this->getConfig('dbTableNames','online').'
				SET
					channel 	= '.$this->db->makeSafe($this->getChannel()).',
					dateTime 	= NOW(),
					ip			= INET_ATON(\''.$_SERVER['REMOTE_ADDR'].'\')
				WHERE
					userID = '.$this->db->makeSafe($this->getUserID()).';';
					
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function initMessageHandling() {
		// Don't handle messages if we are not logged in or not in chat view:
		if(!$this->isLoggedIn() || $this->getView())
			return false;
					
		if($this->_requestVars['text'] !== null)
			$this->insertMessage($this->_requestVars['text']);
	}
	
	function insertParsedMessage($text) {

		// If a queryUserName is set, sent all messages as private messages to this userName:
		if($this->getQueryUserName() && strpos($text, '/') !== 0)
			$text = '/msg '.$this->getQueryUserName().' '.$text;
		
		// Parse IRC-style commands:
		if(strpos($text, '/') === 0) {
			$textParts = explode(' ', $text);

			switch($textParts[0]) {
				
				// Channel switch:
				case '/join':
					if(count($textParts) == 1) {
						// join with no arguments is the own private channel, if allowed:
						if($this->isAllowedToCreatePrivateChannel()) {
							// Private channels are identified by square brackets:
							$this->switchChannel('['.$this->getUserName().']');
						} else {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/error MissingChannelName'
							);
						}
					} else {
						$this->switchChannel($textParts[1]);
					}
					break;
					
				// Logout:
				case '/quit':
				case '/leave':
				case '/part':
				case '/logout':
					$this->logout();
					break;
					
				// Private message:
				case '/msg':
				case '/privmsg':
				case '/describe':
					if($this->isAllowedToSendPrivateMessage()) {
						if(count($textParts) < 3) {
							if(count($textParts) == 2) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error MissingText'
								);
							} else {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error MissingUserName'
								);
							}
						} else {
							// Get UserID from UserName:
							$toUserID = $this->getIDFromName($textParts[1]);
							if($toUserID === null) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error UserNameNotFound '.$textParts[1]
								);
							} else {
								// Insert /privaction command if /describe is used:
								$command = ($textParts[0] == '/describe') ? '/privaction' : '/privmsg';							
								// Copy of private message to current User:
								$this->insertCustomMessage(
									$this->getUserID(),
									$this->getUserName(),
									$this->getUserRole(),
									$this->getPrivateMessageID(),
									$command.'to '.$textParts[1].' '.implode(' ', array_slice($textParts, 2))
								);								
								// Private message to requested User:
								$this->insertCustomMessage(
									$this->getUserID(),
									$this->getUserName(),
									$this->getUserRole(),
									$this->getPrivateMessageID($toUserID),
									$command.' '.implode(' ', array_slice($textParts, 2))
								);
							}
						}
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error PrivateMessageNotAllowed'
						);
					}
					break;
				
				// Invitation:
				case '/invite':
					if($this->getChannel() == $this->getPrivateChannelID() || in_array($this->getChannel(), $this->getChannels())) {
						if(count($textParts) == 1) {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/error MissingUserName'
							);
						} else {
							$toUserID = $this->getIDFromName($textParts[1]);
							if($toUserID === null) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error UserNameNotFound '.$textParts[1]
								);
							} else {						
								$invitationChannelName = $this->getChannelNameFromChannelID($this->getChannel());
								if($invitationChannelName === null) {
									// User is not in a normal channel - Invitation to private Channel:
									$invitationChannelName = '['.$this->getUserName().']';
								}
								// Copy of invitation to current User:
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/inviteto '.$textParts[1].' '.$invitationChannelName
								);							
								// Invitation to requested User:
								$this->insertChatBotMessage(
									$this->getPrivateMessageID($toUserID),
									'/invite '.$this->getUserName().' '.$invitationChannelName
								);
							}
						}						
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error InviteNotAllowed'
						);
					}
					break;

				// Uninvitation:
				case '/uninvite':		
					if($this->getChannel() == $this->getPrivateChannelID() || in_array($this->getChannel(), $this->getChannels())) {
						if(count($textParts) == 1) {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/error MissingUserName'
							);
						} else {
							$toUserID = $this->getIDFromName($textParts[1]);
							if($toUserID === null) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error UserNameNotFound '.$textParts[1]
								);
							} else {						
								$invitationChannelName = $this->getChannelNameFromChannelID($this->getChannel());
								if($invitationChannelName === null) {
									// User is not in a normal channel - uninvite from private Channel:
									$invitationChannelName = '['.$this->getUserName().']';
								}
								// Copy of uninvitation to current User:
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/uninviteto '.$textParts[1].' '.$invitationChannelName
								);			
								// Uninvitation to requested User:
								$this->insertChatBotMessage(
									$this->getPrivateMessageID($toUserID),
									'/uninvite '.$this->getUserName().' '.$invitationChannelName
								);
							}
						}						
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error UninviteNotAllowed'
						);						
					}
					break;

				// Private messaging:
				case '/query':
					if($this->isAllowedToSendPrivateMessage()) {
						if(count($textParts) == 1) {
							if($this->getQueryUserName()) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/queryClose '.$this->getQueryUserName()
								);							
								// Close the current query:
								$this->setQueryUserName(null);
							} else {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error NoOpenQuery'
								);
							}
						} else {
							if($this->getIDFromName($textParts[1]) === null) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error UserNameNotFound '.$textParts[1]
								);
							} else {
								// Open a query to the requested user:
								$this->setQueryUserName($textParts[1]);
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/queryOpen '.$textParts[1]
								);
							}
						}
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error PrivateMessageNotAllowed'
						);
					}
					break;
				
				// Adding/removing users to/from an ignore list:
				case '/ignore':
					$ignoredUserNames = $this->getIgnoredUserNames();
					if(count($textParts) == 1) {
						// Display a list of ignored users:
						if($ignoredUserNames && count($ignoredUserNames) > 0) {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/ignoreList '.join(' ', $ignoredUserNames)
							);
						} else {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/ignoreListEmpty -'
							);
						}
					} else {
						if($ignoredUserNames && in_array($textParts[1], $ignoredUserNames)) {
							$this->removeIgnoredUserName($textParts[1]);
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/ignoreRemoved '.$textParts[1]
							);
						} else {
							// Never add the chatBotName or the own userName:
							if($textParts[1] == $this->getConfig('chatBotName') || $textParts[1] == $this->getUserName()) {
								// Just do as we wanted to display the list:
								$this->insertMessage('/ignore');
							} else {
								$this->addIgnoredUserName($textParts[1]);
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/ignoreAdded '.$textParts[1]
								);	
							}
						}
					}
					break;
				
				// Kicking offending users from the chat:
				case '/kick':
					// Only moderators/admins may kick users:
					if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
						if(count($textParts) == 1) {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/error MissingUserName'
							);
						} else {
							// Get UserID from UserName:
							$kickUserID = $this->getIDFromName($textParts[1]);
							if($kickUserID === null) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error UserNameNotFound '.$textParts[1]
								);
							} else {
								// Check the role of the user to kick:
								$kickUserRole = $this->getRoleFromID($kickUserID);
								if($kickUserRole == AJAX_CHAT_ADMIN || ($kickUserRole == AJAX_CHAT_MODERATOR && $this->getUserRole() != AJAX_CHAT_ADMIN)) {
									// Admins and moderators may not be kicked:
									$this->insertChatBotMessage(
										$this->getPrivateMessageID(),
										'/error KickNotAllowed '.$textParts[1]
									);
								} else {
									// Kick user and insert message:
									$channel = $this->getChannelFromID($kickUserID);
									$banMinutes = (count($textParts) > 2) ? $textParts[2] : null;
									$this->kickUser($kickUserID, $textParts[1], $banMinutes);
									// If no channel found, user logged out before he could be kicked
									if($channel !== null) {
										$this->insertChatBotMessage(
											$channel,
											'/kick '.$textParts[1]
										);
									}
								}
							}
						}
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error CommandNotAllowed '.$textParts[0]
						);
					}
					break;
				
				// Listing banned users:
				case '/bans':
					// Only moderators/admins may ban users:
					if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
						$this->removeExpiredBans();
						$bannedUsers = $this->getBannedUsers();
						if(count($bannedUsers) > 0) {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/bans '.implode(' ', $bannedUsers)
							);
						} else {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/bansEmpty -'
							);
						}
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error CommandNotAllowed '.$textParts[0]
						);
					}				
					break;
				
				// Unban user (remove from ban list):
				case '/unban':
					// Only moderators/admins may unban users:
					if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
						$this->removeExpiredBans();
						if(count($textParts) == 1) {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/error MissingUserName'
							);
						} else {
							if(!in_array($textParts[1], $this->getBannedUsers())) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error UserNameNotFound '.$textParts[1]
								);
							} else {
								// Unban user and insert message:
								$this->unbanUser($textParts[1]);
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/unban '.$textParts[1]
								);	
							}
						}
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error CommandNotAllowed '.$textParts[0]
						);
					}
					break;
				
				// Describing actions:
				case '/action':
					if(count($textParts) == 1) {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error MissingText'
						);
					} else {
						if($this->getQueryUserName()) {
							// If we are in query mode, sent the action to the query user:
							$this->insertMessage('/describe '.$this->getQueryUserName().' '.implode(' ', array_slice($textParts, 1)));
						} else {
							$this->insertCustomMessage(
								$this->getUserID(),
								$this->getUserName(),
								$this->getUserRole(),
								$this->getChannel(),
								$text
							);
						}
					}
					break;
				
				// Describing own actions:
				case '/me':
					if(count($textParts) == 1) {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error MissingText'
						);
					} else {
						if($this->getQueryUserName()) {
							// If we are in query mode, sent the action to the query user:
							$this->insertMessage('/describe '.$this->getQueryUserName().' '.$this->getUserName().' '.implode(' ', array_slice($textParts, 1)));
						} else {
							$this->insertCustomMessage(
								$this->getUserID(),
								$this->getUserName(),
								$this->getUserRole(),
								$this->getChannel(),
								'/action '.$this->getUserName().' '.implode(' ', array_slice($textParts, 1))
							);
						}
					}
					break;
				
				// Listing online Users:
				case '/who':	
					if(count($textParts) == 1) {
						if($this->isAllowedToListHiddenUsers()) {
							// List online users from any channel:
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/who '.implode(' ', $this->getOnlineUsers())
							);
						} else {
							// Get online users for all accessible channels:
							$channels = $this->getChannels();
							// Make sure the current channel is added as well (might be a private or restricted channel):
							if(!in_array($this->getChannel(), $channels)) {
								array_push($channels, $this->getChannel());
							}
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/who '.implode(' ', $this->getOnlineUsers($channels))
							);
						}
					} else {
						$channelName = $textParts[1];					
						if($channelName && $channelName[0] == '[') {
							// Listing the users of a private channel:
							$privateChannelUserID = $this->getIDFromName($this->subString($channelName, 1, $this->stringLength($channelName)-2));
							if($privateChannelUserID === null) {
								$channelID = null;
							} else {
								$channelID = $this->getPrivateChannelID($privateChannelUserID);
							}
						} else {
							// Listing the users of a normal channel:
							$channelID = $this->getChannelIDFromChannelName($channelName);	
						}
						if(!$this->validateChannel($channelID)) {
							// Invalid channel:
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/error InvalidChannelName '.$channelName
							);
						} else {
							// Get online users for the given channel:
							$onlineUsers = $this->getOnlineUsers(array($channelID));
							if(count($onlineUsers) > 0) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/who '.implode(' ', $onlineUsers)
								);
							} else {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/whoEmpty -'
								);
							}
						}
					}
					break;
				
				// Listing available channels:
				case '/list':	
					// Get the names of all accessible channels:
					$channelNames = $this->getChannelNames();
					// Add the own private channel, if allowed:
					if($this->isAllowedToCreatePrivateChannel()) {
						array_push($channelNames, '['.$this->getUserName().']');
					}
					// Add the invitation channels:
					$invitations = $this->getInvitations();
					if($invitations) {
						foreach($invitations as $channelName) {
							if(!in_array($channelName, $channelNames)) {
								array_push($channelNames, $channelName);
							}
						}
					}
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/list '.implode(' ', $channelNames)
					);
					break;
				
				// Listing information about a User:
				case '/whois':
					// Only moderators/admins:
					if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
						if(count($textParts) == 1) {
							$this->insertChatBotMessage(
								$this->getPrivateMessageID(),
								'/error MissingUserName'
							);
						} else {
							// Get UserID from UserName:
							$whoisUserID = $this->getIDFromName($textParts[1]);
							if($whoisUserID === null) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/error UserNameNotFound '.$textParts[1]
								);
							} else {
								// List user information:
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/whois '.$textParts[1].' '.$this->getIPFromID($whoisUserID)
								);
							}
						}
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error CommandNotAllowed '.$textParts[0]
						);
					}
					break;
				
				// Rolling dice:
				case '/roll':				
					if(count($textParts) == 1) {
						// default is one d6:
						$text = '/roll '.$this->getUserName().' 1d6 '.$this->rollDice(6);
					} else {
						$diceParts = explode('d', $textParts[1]);
						if(count($diceParts) == 2) {
							$number = (int)$diceParts[0];
							$sides = (int)$diceParts[1];
							
							// Dice number must be an integer between 1 and 100, else roll only one:
							$number = ($number > 0 && $number <= 100) ?  $number : 1;
							
							// Sides must be an integer between 1 and 100, else take 6:
							$sides = ($sides > 0 && $sides <= 100) ?  $sides : 6;
							
							$text = '/roll '.$this->getUserName().' '.$number.'d'.$sides.' ';
							for($i=0; $i<$number; $i++) {
								if($i != 0)
									$text .= ',';
								$text .= $this->rollDice($sides);
							}
						} else {
							// if dice syntax is invalid, roll one d6:
							$text = '/roll '.$this->getUserName().' 1d6 '.$this->rollDice(6);
						}
					}
					$this->insertChatBotMessage(
						$this->getChannel(),
						$text
					);
					break;					
				
				// Custom or unknown command:
				default:
					if(!$this->parseCustomCommands($text, $textParts)) {				
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error UnknownCommand '.$textParts[0]
						);
					}
			}

		} else {
			// No command found, just insert the plain message:
			$this->insertCustomMessage(
				$this->getUserID(),
				$this->getUserName(),
				$this->getUserRole(),
				$this->getChannel(),
				$text
			);
		}
	}
	
	function insertMessage($text) {
		if(!$this->isAllowedToWriteMessage())
			return;

		if(!$this->floodControl())
			return;

		$text = $this->trimMessageText($text);	
		if($text == '')
			return;
		
		if(!$this->onNewMessage($text))
			return;
		
		$this->insertParsedMessage($text);
	}
	
	function floodControl() {
		// Moderators and Admins need no flood control:
		if($this->getUserRole() == AJAX_CHAT_MODERATOR || $this->getUserRole() == AJAX_CHAT_ADMIN)
			return true;

		$time = time();
		// Check the time of the last inserted message:
		if($this->getLastInsertedMessageTimeStamp()+60 < $time) {
			$this->setLastInsertedMessageTimeStamp($time);
			$this->setInsertedMessagesRate(1);
		} else {
			$rate = $this->getInsertedMessagesRate()+1;
			// Check if message rate is too high:
			if($rate > $this->getConfig('maxMessageRate')) {
				// Kick and ban user:
				$this->kickUser(
					$this->getUserID(),
					$this->getUserName(),
					$this->getConfig('defaultBanTime')
				);
				$this->insertChatBotMessage(
					$this->getChannel(),
					'/kick '.$this->getUserName()
				);
				// Return false so the message is not inserted:
				return false;
			} else {
				// Increase the inserted messages rate:
				$this->setInsertedMessagesRate($rate);
			}
		}
		
		return true;
	}
	
	function isAllowedToWriteMessage() {
		if($this->getUserRole() != AJAX_CHAT_GUEST)
			return true;
		if($this->getConfig('allowGuestWrite'))
			return true;
		return false;
	}

	function insertChatBotMessage($channelID, $messageText) {
		$this->insertCustomMessage(
			$this->getConfig('chatBotID'),
			$this->getConfig('chatBotName'),
			AJAX_CHAT_CHATBOT,
			$channelID,
			$messageText
		);
	}
	
	function insertCustomMessage($userID, $userName, $userRole, $channel, $text) {
		$sql = 'INSERT INTO '.$this->getConfig('dbTableNames','messages').'(
								userID,
								userName,
								userRole,
								channel,
								dateTime,
								ip,
								text
							)
				VALUES (
					'.$this->db->makeSafe($userID).',
					'.$this->db->makeSafe($userName).',
					'.$this->db->makeSafe($userRole).',
					'.$this->db->makeSafe($channel).',
					NOW(),
					INET_ATON(\''.$_SERVER['REMOTE_ADDR'].'\'),
					'.$this->db->makeSafe($text).'
				);';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function rollDice($sides) {
		// seed with microseconds since last "whole" second:
		mt_srand((double)microtime()*1000000);
		
		return mt_rand(1, $sides);
	}
	
	function kickUser($userID, $userName=null, $banMinutes=null) {
		// Ban User for the given time in minutes:
		if($banMinutes) {
			$this->banUser($userID, $userName, $banMinutes);
		}

		// Remove given User from online list:
		$sql = 'DELETE FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					userID = '.$this->db->makeSafe($userID).';';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function getBannedUsers() {
		$sql = 'SELECT
					userName
				FROM
					'.$this->getConfig('dbTableNames','bans').';';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}

		$bannedUsers = array();
		while($row = $result->fetch()) {
			array_push($bannedUsers, $row['userName']);
		}
		
		$result->free();
		
		return $bannedUsers;
	}
	
	function banUser($userID, $userName, $banMinutes) {
		// Remove expired bans:
		$this->removeExpiredBans();
		
		$banMinutes = (int)$banMinutes;
		if(!$banMinutes)
			return;
		
		$ip = $this->getIPFromID($userID);
		if($ip) {
			$sql = 'INSERT INTO '.$this->getConfig('dbTableNames','bans').'(
						userID,
						userName,
						dateTime,
						ip
					)
					VALUES (
						'.$this->db->makeSafe($userID).',
						'.$this->db->makeSafe($userName).',
						DATE_ADD(NOW(), interval '.$this->db->makeSafe($banMinutes).' MINUTE),
						INET_ATON(\''.$ip.'\')
					);';	
			
			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);
			
			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();
				die();
			}
		}
	}
	
	function unbanUser($userName) {
		$sql = 'DELETE FROM
					'.$this->getConfig('dbTableNames','bans').'
				WHERE
					userName = '.$this->db->makeSafe($userName).';';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function removeExpiredBans() {
		$sql = 'DELETE FROM
					'.$this->getConfig('dbTableNames','bans').'
				WHERE
					dateTime < NOW();';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function setInactive($userID) {
		$sql = 'UPDATE
					'.$this->getConfig('dbTableNames','online').'
				SET
					dateTime = DATE_SUB(NOW(), interval '.(intval($this->getConfig('inactiveTimeout'))+1).' MINUTE)
				WHERE
					userID = '.$this->db->makeSafe($userID).';';
					
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function removeInactive() {
		$sql = 'SELECT
					userID,
					userName,
					channel
				FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					NOW() > DATE_ADD(dateTime, interval '.$this->getConfig('inactiveTimeout').' MINUTE);';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
		
		if($result->numRows() > 0) {
			$condition = '';
			while($row = $result->fetch()) {
				if(!empty($condition))
					$condition .= ' OR ';
				// Add userID to condition for removal:
				$condition .= 'userID='.$this->db->makeSafe($row['userID']);
				
				// Insert logout timeout message:
				$text = '/logout '.$row['userName'].' Timeout';
				$this->insertChatBotMessage(
					$row['channel'],
					$text
				);
			}
			
			$result->free();
			
			$sql = 'DELETE FROM
						'.$this->getConfig('dbTableNames','online').'
					WHERE
						'.$condition.';';
			
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
			
			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();
				die();
			}
		} else {
			$result->free();
		}
	}

	function updateOnlineStatus() {
		if($this->isLoggedIn()) {		
			// Update online status within the timeout span (timeout - 1 minute):
			if(!$this->getStatusUpdateTimeStamp() || ((time() - $this->getStatusUpdateTimeStamp()) > ($this->getConfig('inactiveTimeout')-1)*60)) {
				$this->updateOnlineList();
				$this->setStatusUpdateTimeStamp(time());
			}
		}
	}
	
	function checkAndRemoveInactive() {
		// Remove inactive users if logged out and every inactiveCheckInterval:
		if(!$this->getInactiveCheckTimeStamp() || ((time() - $this->getInactiveCheckTimeStamp()) > $this->getConfig('inactiveCheckInterval')*60)) {
			$this->removeInactive();
			$this->setInactiveCheckTimeStamp(time());
		}
	}
	
	function sendXMLMessages() {		
		$httpHeader = new AJAXChatHTTPHeader('UTF-8', 'text/xml');

		// Send HTTP header:
		$httpHeader->send();
		
		// Output XML messages:
		echo $this->getXMLMessages();
	}

	function getXMLMessages() {
		switch($this->getView()) {
			case 'logs':
				return $this->getLogsViewXMLMessages();
			default:
				return $this->getChatViewXMLMessages();
		}
	}

	function getMessageCondition() {
		$condition = 	'id > '.$this->db->makeSafe($this->_requestVars['lastID']).'
						AND (
							channel = '.$this->db->makeSafe($this->getChannel()).'
							OR
							channel = '.$this->db->makeSafe(($this->getPrivateMessageID())).'
						)
						AND
						';
		if($this->getConfig('requestMessagesPriorChannelEnter') ||
			($this->getConfig('requestMessagesPriorChannelEnterList') && in_array($this->getChannel(), $this->getConfig('requestMessagesPriorChannelEnterList')))) {
			$condition .= 'NOW() < DATE_ADD(dateTime, interval '.$this->getConfig('requestMessagesTimeDiff').' HOUR)';
		} else {
			$condition .= 'dateTime >= \''.date('Y-m-d H:i:s', $this->getChannelEnterTimeStamp()).'\'';	
		}
		return $condition;
	}
	
	function getMessageFilter() {
			$ignoreUsers = '';
			if($this->getIgnoredUserNames()) {
				foreach($this->getIgnoredUserNames() as $userName) {
					$ignoreUsers .= ' AND NOT (userName = '.$this->db->makeSafe($userName).')'."\n";
					// Do not show /roll messages from ignored users (/roll messages are chatBot messages):
					$ignoreUsers .= ' AND NOT (text LIKE ('.$this->db->makeSafe('/roll '.$userName.' %').'))'."\n";
					// Do not show /invite messages from ignored users (/invite messages are chatBot messages):
					$ignoreUsers .= ' AND NOT (text LIKE ('.$this->db->makeSafe('/invite '.$userName.' %').'))'."\n";
				}
			}

			$filterChannelMessages = '';
			if(!$this->getConfig('showChannelMessages') || $this->getRequestVar('shoutbox')) {
				$filterChannelMessages = '	AND NOT (
											text LIKE (\'/login%\')
											OR
											text LIKE (\'/logout%\')
											OR
											text LIKE (\'/channelEnter%\')
											OR
											text LIKE (\'/channelLeave%\')
											OR
											text LIKE (\'/kick%\')
										)';
			}
			
			return $ignoreUsers.$filterChannelMessages;		
	}
	
	function getChatViewXMLMessages() {
		$this->updateOnlineStatus();
		$this->checkAndRemoveInactive();
				
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<root>';
		
		if($this->isLoggedIn()) {	

			// Helper var to check if current user is still listed online:
			$isOnline = false;
			
			// Get the online users which are on the same channel:
			$sql = 'SELECT
						userID,
						userName,
						userRole
					FROM
						'.$this->getConfig('dbTableNames','online').'
					WHERE
						channel = '.$this->db->makeSafe($this->getChannel()).';';
			
			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);
			
			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();
				die();
			}
			
			$users = '<users>';
			$messages = '';
			while($row = $result->fetch()) {

				// Check if current user is still listed online:
				if($row['userID'] == $this->getUserID())
					$isOnline = true;
	
				$users .= '<user';
				$users .= ' userID="'.$row['userID'].'"';
				$users .= ' userRole="'.$row['userRole'].'"';
				$users .= '>';
				$users .= '<![CDATA['.$this->encodeSpecialChars($row['userName']).']]>';
				$users .= '</user>';
			}
			$users .= '</users>';
	
			$result->free();
			
			// Logout if current user is not listed online:
			if(!$isOnline)
				$this->logout(false);
		}
		
		if($this->isLoggedIn()) {

			// Go through the info messages:
			foreach($this->getInfoMessages() as $type=>$infoArray) {
				foreach($infoArray as $info) {
					$xml .= '<info type="'.$type.'">';
					$xml .= '<![CDATA['.$this->encodeSpecialChars($info).']]>';
					$xml .= '</info>';
				}
			}

			$xml .= $users;
			
			// Get the last messages in descending order (this optimises the LIMIT usage):
			$sql = 'SELECT
						id,
						userID,
						userName,
						userRole,
						UNIX_TIMESTAMP(dateTime) AS timeStamp,
						text
					FROM
						'.$this->getConfig('dbTableNames','messages').'
					WHERE
						'.$this->getMessageCondition().'
						'.$this->getMessageFilter().'
					ORDER BY
						id
						DESC
					LIMIT '.$this->getConfig('requestMessagesLimit').';';

			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);
			
			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();
				die();
			}
			
			$messages = '';
			
			// Add the messages in reverse order so it is ascending again:
			while($row = $result->fetch()) {
				
				if(!$this->parseChatViewMessageContent($row)) {
					continue;
				}
				
				$message = '';
				$message .= '<message';
				$message .= ' id="'.$row['id'].'"';
				$message .= ' dateTime="'.date('r', $row['timeStamp']).'"';
				$message .= ' userID="'.$row['userID'].'"';
				$message .= ' userRole="'.$row['userRole'].'"';
				$message .= '>';
				$message .= '<username><![CDATA['.$this->encodeSpecialChars($row['userName']).']]></username>';
				$message .= '<text><![CDATA['.$this->encodeSpecialChars($row['text']).']]></text>';
				$message .= '</message>';
				$messages = $message.$messages;
			}

			// Check if we have been uninvited from a private or restricted channel:
			if(!$this->validateChannel($this->getChannel())) {
				// Switch to the default channel:
				$this->switchChannel($this->getChannelNameFromChannelID($this->getConfig('defaultChannelID')));
				// Recursive method call:
				return $this->getChatViewXMLMessages();
			}			
			
			$messages = '<messages>'.$messages.'</messages>';

			$xml .= $messages;
			
			$result->free();
		} else {
			$xml .= '<info type="logout">';
			$xml .= '<![CDATA['.$this->encodeSpecialChars($this->getConfig('logoutData')).']]>';
			$xml .= '</info>';
		}
		
		$xml .= '</root>';
		
		return $xml;
	}

	function parseChatViewMessageContent($message) {
		// Check message text for invitations and uninvitations:
		if(strpos($message['text'], '/invite ') === 0) {
			$textParts = explode(' ', $message['text']);
			$userName = $textParts[1];
			$channelName = $textParts[2];
			
			// Add channelName to the invitation list, if not already on the uninvitation list:
			if(!$this->getUnInvitations() || !in_array($channelName, $this->getUnInvitations())) {
				// Add channel name to the invitations list:
				$this->addInvitation($channelName);
			}
		} else if(strpos($message['text'], '/uninvite ') === 0) {
			$textParts = explode(' ', $message['text']);
			$userName = $textParts[1];
			$channelName = $textParts[2];
			
			// Remove given channel name from invitation list:
			$this->removeInvitation($channelName);
			
			// Add channel name to the uninvitation list:
			$this->addUnInvitation($channelName);
			
			// Do not show the uninvitation message from ignored users:
			if($this->getIgnoredUserNames()) {
				if(in_array($userName, $this->getIgnoredUserNames())) {
					return false;
				}
			}
		}
		
		return true;	
	}
	
	function getLogsViewCondition() {
		$condition = 'id > '.$this->db->makeSafe($this->_requestVars['lastID']);
		
		// Check the channel condition:
		switch($this->_requestVars['channelID']) {
			case '-3':
				// Just display messages from all channels
				break;
			case '-2':
				$condition .= ' AND channel > '.($this->getConfig('privateMessageDiff')-1);
				break;
			case '-1':
				$condition .= ' AND (channel > '.($this->getConfig('privateChannelDiff')-1).' AND channel < '.($this->getConfig('privateMessageDiff')).')';
				break;
			default:
				if($this->validateChannel($this->_requestVars['channelID'])) {
					$condition .= ' AND channel = '.$this->db->makeSafe($this->_requestVars['channelID']);
				}
		}
		
		// Check the period condition:
		$hour	= ($this->_requestVars['hour'] === null || $this->_requestVars['hour'] > 23 || $this->_requestVars['hour'] < 0) ? null : $this->_requestVars['hour'];
		$day	= ($this->_requestVars['day'] === null || $this->_requestVars['day'] > 31 || $this->_requestVars['day'] < 1) ? null : $this->_requestVars['day'];
		$month	= ($this->_requestVars['month'] === null || $this->_requestVars['month'] > 12 || $this->_requestVars['month'] < 1) ? null : $this->_requestVars['month'];
		$year	= ($this->_requestVars['year'] === null || $this->_requestVars['year'] > date('Y') || $this->_requestVars['year'] < $this->getConfig('logsFirstYear')) ? null : $this->_requestVars['year'];

		// If a time (hour) is given but no date (year, month, day), use the current date:
		if($hour !== null) {
			if($day === null)
				$day = date('j');
			if($month === null)
				$month = date('n');
			if($year === null)
				$year = date('Y');
		}
		
		if($year === null) {
			// No year given, so no period condition
		} else if($month === null) {
			// Define the given year as period:
			$periodStart = mktime(0, 0, 0, 1, 1, $year);
			// The last day in a month can be expressed by using 0 for the day of the next month:
			$periodEnd = mktime(23, 59, 59, 13, 0, $year);
		} else if($day === null) {
			// Define the given month as period:
			$periodStart = mktime(0, 0, 0, $month, 1, $year);
			// The last day in a month can be expressed by using 0 for the day of the next month:
			$periodEnd = mktime(23, 59, 59, $month+1, 0, $year);
		} else if($hour === null){
			// Define the given day as period:
			$periodStart = mktime(0, 0, 0, $month, $day, $year);
			$periodEnd = mktime(23, 59, 59, $month, $day, $year);
		} else {
			// Define the given hour as period:
			$periodStart = mktime($hour, 0, 0, $month, $day, $year);
			$periodEnd = mktime($hour, 59, 59, $month, $day, $year);
		}
		
		if(isset($periodStart))
			$condition .= ' AND dateTime > \''.date('Y-m-d H:i:s', $periodStart).'\' AND dateTime <= \''.date('Y-m-d H:i:s', $periodEnd).'\'';
		
		// Check the search condition:
		if($this->_requestVars['search']) {
			// Use the search value as regular expression on message text and username:
			$condition .= ' AND (userName REGEXP '.$this->db->makeSafe($this->_requestVars['search']).' OR text REGEXP '.$this->db->makeSafe($this->_requestVars['search']).')';
		}
		
		// If no period or search condition is given, just monitor the last messages on the given channel:
		if(!isset($periodStart) && !$this->_requestVars['search']) {
			$condition .= ' AND NOW() < DATE_ADD(dateTime, interval '.$this->getConfig('logsRequestMessagesTimeDiff').' HOUR)';
		}

		return $condition;
	}
	
	function getLogsViewXMLMessages() {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<root>';

		if($this->isLoggedIn()) {

			// Go through the info messages:
			foreach($this->getInfoMessages() as $type=>$infoArray) {
				foreach($infoArray as $info) {
					$xml .= '<info type="'.$type.'">';
					$xml .= '<![CDATA['.$this->encodeSpecialChars($info).']]>';
					$xml .= '</info>';
				}
			}
		
			$sql = 'SELECT
						id,
						userID,
						userName,
						userRole,
						channel,
						UNIX_TIMESTAMP(dateTime) AS timeStamp,
						INET_NTOA(ip) AS ip,
						text
					FROM
						'.$this->getConfig('dbTableNames','messages').'
					WHERE
						'.$this->getLogsViewCondition().'
					ORDER BY
						id
					LIMIT '.$this->getConfig('logsRequestMessagesLimit').';';
						
			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);
			
			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();
				die();
			}
	
			$xml .= '<messages>';
			while($row = $result->fetch()) {
				$xml .= '<message';
				$xml .= ' id="'.$row['id'].'"';
				$xml .= ' dateTime="'.date('r', $row['timeStamp']).'"';
				$xml .= ' userID="'.$row['userID'].'"';
				$xml .= ' userRole="'.$row['userRole'].'"';
				$xml .= ' channel="'.$row['channel'].'"';
				$xml .= ' ip="'.$row['ip'].'"';
				$xml .= '>';
				$xml .= '<username><![CDATA['.$this->encodeSpecialChars($row['userName']).']]></username>';
				$xml .= '<text><![CDATA['.$this->encodeSpecialChars($row['text']).']]></text>';
				$xml .= '</message>';
			}
			$result->free();
	
			$xml .= '</messages>';
		} else {
			$xml .= '<info type="logout">';
			$xml .= '<![CDATA['.$this->encodeSpecialChars($this->getConfig('logoutData')).']]>';
			$xml .= '</info>';
		}
		
		$xml .= '</root>';
		
		return $xml;
	}
		
	function purgeLogs() {
		$sql = 'DELETE FROM
					'.$this->getConfig('dbTableNames','messages').'
				WHERE
					dateTime < DATE_SUB(NOW(), interval '.$this->getConfig('logsPurgeTimeDiff').' DAY);';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
	}
	
	function getInfoMessages($type=null) {
		if(!isset($this->_infoMessages)) {
			$this->_infoMessages = array();
		}
		if($type) {
			if(!isset($this->_infoMessages[$type])) {
				$this->_infoMessages[$type] = array();
			}
			return $this->_infoMessages[$type];
		} else {
			return $this->_infoMessages;
		}
	}
	
	function addInfoMessage($info, $type='error') {
		if(!isset($this->_infoMessages)) {
			$this->_infoMessages = array();
		}
		if(!isset($this->_infoMessages[$type])) {
			$this->_infoMessages[$type] = array();
		}	
		if(!in_array($info, $this->_infoMessages[$type])) {
			array_push($this->_infoMessages[$type], $info);
		}
	}
	
	function getRequestVars() {
		return $this->_requestVars;
	}
	
	function getRequestVar($key) {
		if(isset($this->_requestVars[$key]))
			return $this->_requestVars[$key];
		else
			return null;
	}
	
	function setRequestVar($key, $value) {
		$this->_requestVars[$key] = $value;
	}

	function getOnlineUsers($channelIDs=null) {
		if($channelIDs) {
			// Make the channelIDs safe for database usage:
			$safeChannelIDs = array();
			foreach($channelIDs as $channelID) {
				array_push($safeChannelIDs, $this->db->makeSafe($channelID));
			}
			$condition = 'WHERE channel IN ('.implode(', ', $safeChannelIDs).')';
		} else
			$condition = '';
		
		$sql = 'SELECT
					userName
				FROM
					'.$this->getConfig('dbTableNames','online').'
				'.$condition.';';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
		
		$users = array();
		while($row = $result->fetch()) {
			array_push($users, $row['userName']);
		}
		
		$result->free();
		
		return $users;
	}

	function getOnlineUsersData($channelIDs=null) {
		if($channelIDs) {
			// Make the channelIDs safe for database usage:
			$safeChannelIDs = array();
			foreach($channelIDs as $channelID) {
				array_push($safeChannelIDs, $this->db->makeSafe($channelID));
			}
			$condition = 'WHERE channel IN ('.implode(', ', $safeChannelIDs).')';
		} else
			$condition = '';
		
		$sql = 'SELECT
					userID,
					userName,
					userRole,
					channel,
					INET_NTOA(ip) AS ip
				FROM
					'.$this->getConfig('dbTableNames','online').'
				'.$condition.';';
		
		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
		
		$usersData = array();
		while($row = $result->fetch()) {
			array_push($usersData, $row);
		}
		
		$result->free();
		
		return $usersData;
	}

	function startSession() {
		if(!session_id()) {
			// Set the session name:
			session_name($this->getConfig('sessionName'));

			// Start the session:
			session_start();
			
			// We started a new session:
			$this->_sessionNew = true;
		}
	}
	
	function destroySession() {
		if($this->_sessionNew) {	
			// Delete all session variables:
			$_SESSION = array();
			
			// Delete the session cookie:
			if (isset($_COOKIE[session_name()])) {
				setcookie(session_name(), '', time()-42000, '/');
			}
		
			// Destroy the session:
			session_destroy();
		} else {
			// Unset all session variables starting with the sessionValuePrefix:
			foreach($_SESSION as $key=>$value) {
				if(strpos($key, $this->getConfig('sessionValuePrefix')) === 0) {
					unset($_SESSION[$key]);
				}
			}
		}
	}

	function regenerateSessionID() {
		if($this->_sessionNew) {
			// Regenerate session id:
			@session_regenerate_id(true);
		}
	}

	function getSessionVar($key, $prefix=null) {
		if($prefix === null)
			$prefix = $this->getConfig('sessionValuePrefix');

		// Return the session value if existing:
		if(isset($_SESSION[$prefix.$key]))
			return $_SESSION[$prefix.$key];
		else
			return null;
	}
	
	function setSessionVar($key, $value, $prefix=null) {
		if($prefix === null)
			$prefix = $this->getConfig('sessionValuePrefix');
		
		// Set the session value:
		$_SESSION[$prefix.$key] = $value;
	}
	
	function getSessionIP() {
		return $this->getSessionVar('IP');
	}
	
	function setSessionIP($ip) {
		$this->setSessionVar('IP', $ip);
	}
	
	function getQueryUserName() {
		return $this->getSessionVar('QueryUserName');
	}
	
	function setQueryUserName($userName) {
		$this->setSessionVar('QueryUserName', $userName);
	}
	
	function getIgnoredUserNames() {
		return $this->getSessionVar('IgnoredUserNames');
	}
	
	function addIgnoredUserName($userName) {
		$ignoredUserNames = $this->getIgnoredUserNames();
		if($ignoredUserNames === null)
			$ignoredUserNames = array();		
		// Only add if not already present:
		if(!in_array($userName, $ignoredUserNames))
			array_push($ignoredUserNames, $userName);
		// Save the ignoredUserNames array in the session:
		$this->setSessionVar('IgnoredUserNames', $ignoredUserNames);
	}
	
	function removeIgnoredUserName($userName) {
		if($this->getIgnoredUserNames()) {
			// Anonymous function to filter out the given UserName:
			$filterFunction = create_function(
				'$value',
				'return $value !== "'.$userName.'";'
			);			
			$ignoredUserNames = array_filter($this->getIgnoredUserNames(), $filterFunction);
			// Save the ignoredUserNames array in the session:
			$this->setSessionVar('IgnoredUserNames', $ignoredUserNames);
		}
	}
	
	function getInvitations() {
		return $this->getSessionVar('Invitations');
	}

	function addInvitation($invitation) {
		$invitations = $this->getInvitations();
		if($invitations === null)
			$invitations = array();
		// Only add if not already present:
		if(!in_array($invitation, $invitations))
			array_push($invitations, $invitation);
		// Save the invitations array in the session:
		$this->setSessionVar('Invitations', $invitations);
	}

	function removeInvitation($invitation) {
		if($this->getInvitations()) {
			// Anonymous function to filter out the given Invitation:
			$filterFunction = create_function(
				'$value',
				'return $value !== "'.$invitation.'";'
			);			
			$invitations = array_filter($this->getInvitations(), $filterFunction);
			// Save the invitations array in the session:
			$this->setSessionVar('Invitations', $invitations);
		}
	}

	function getUnInvitations() {
		return $this->_unInvitations;
	}

	function addUnInvitation($unInvitation) {
		if($this->_unInvitations === null)
			$this->_unInvitations = array();
		if(!in_array($unInvitation, $this->_unInvitations))
			array_push($this->_unInvitations, $unInvitation);
	}

	function removeUnInvitation($unInvitation) {
		if($this->getUnInvitations()) {
			// Anonymous function to filter out the given UnInvitation:
			$filterFunction = create_function(
				'$value',
				'return $value !== "'.$unInvitation.'";'
			);			
			$unInvitations = array_filter($this->getUnInvitations(), $filterFunction);
			$this->_unInvitations = $unInvitations;
		}
	}
	
	function getUserID() {
		return $this->getSessionVar('UserID');
	}
	
	function setUserID($id) {
		$this->setSessionVar('UserID', $id);
	}

	function getUserName() {
		return $this->getSessionVar('UserName');
	}
	
	function setUserName($name) {
		$this->setSessionVar('UserName', $name);
	}

	function getUserRole() {		
		$userRole = $this->getSessionVar('UserRole');
		if($userRole === null)
			return AJAX_CHAT_GUEST;
		return $userRole;
	}
	
	function setUserRole($role) {
		$this->setSessionVar('UserRole', $role);
	}

	function getChannel() {
		return $this->getSessionVar('Channel');
	}
	
	function setChannel($channel) {
		$this->setSessionVar('Channel', $channel);
		// Save the channel enter timestamp:
		$this->setChannelEnterTimeStamp(time());
	}

	function isLoggedIn() {
		return (bool)$this->getSessionVar('LoggedIn');
	}
	
	function setLoggedIn($bool) {
		$this->setSessionVar('LoggedIn', $bool);
	}
	
	function getLoginTimeStamp() {
		return $this->getSessionVar('LoginTimeStamp');
	}

	function setLoginTimeStamp($time) {
		$this->setSessionVar('LoginTimeStamp', $time);
	}

	function getChannelEnterTimeStamp() {
		return $this->getSessionVar('ChannelEnterTimeStamp');
	}

	function setChannelEnterTimeStamp($time) {
		$this->setSessionVar('ChannelEnterTimeStamp', $time);
	}

	function getStatusUpdateTimeStamp() {
		return $this->getSessionVar('StatusUpdateTimeStamp');
	}

	function setStatusUpdateTimeStamp($time) {
		$this->setSessionVar('StatusUpdateTimeStamp', $time);
	}

	function getInactiveCheckTimeStamp() {
		return $this->getSessionVar('InactiveCheckTimeStamp');
	}

	function setInactiveCheckTimeStamp($time) {
		$this->setSessionVar('InactiveCheckTimeStamp', $time);
	}

	function getLastInsertedMessageTimeStamp() {
		return $this->getSessionVar('LastInsertedMessageTimeStamp');
	}

	function setLastInsertedMessageTimeStamp($time) {
		$this->setSessionVar('LastInsertedMessageTimeStamp', $time);
	}
	
	function getInsertedMessagesRate() {
		return $this->getSessionVar('InsertedMessagesRate');
	}
	
	function setInsertedMessagesRate($rate) {
		$this->setSessionVar('InsertedMessagesRate', $rate);
	}

	function getLangCode() {
		$langCode = $this->getSessionVar('LangCode');
		// Check if the langCode is valid:
		if(!in_array($langCode, $this->getConfig('langAvailable'))) {
			// Determine the user language:
			$language = new AJAXChatLanguage($this->getConfig('langAvailable'), $this->getConfig('langDefault'));
			$langCode = $language->getLangCode();
			// Save the new langCode:	
			$this->setLangCode($langCode);
		}
		return $langCode;
	}

	function setLangCode($langCode) {
		$this->setSessionVar('LangCode', $langCode);
	}

	function removeUnsafeCharacters($str) {
		// Remove NO-WS-CTL, non-whitespace control characters (RFC 2822), decimal 1–8, 11–12, 14–31, and 127:
		return AJAXChatEncoding::removeUnsafeCharacters($str);
	}

	function subString($str, $start=0, $length=null, $encoding='UTF-8') {
		return AJAXChatString::subString($str, $start, $length, $encoding);
	}
	
	function stringLength($str, $encoding='UTF-8') {
		return AJAXChatString::stringLength($str, $encoding);
	}

	function trimMessageText($text) {
		return $this->trimString($text, 'UTF-8', $this->getConfig('messageTextMaxLength'));
	}

	function trimUserName($userName) {
		return $this->trimString($userName, null, $this->getConfig('userNameMaxLength'), true, true);
	}
	
	function trimChannelName($channelName) {		
		return $this->trimString($channelName, null, null, true, true);
	}

	function trimString($str, $sourceEncoding=null, $maxLength=null, $replaceWhitespace=false, $decodeEntities=false, $htmlEntitiesMap=null) {
		// Make sure the string contains valid unicode:
		$str = $this->convertToUnicode($str, $sourceEncoding);
		
		// Make sure the string contains no unsafe characters:
		$str = $this->removeUnsafeCharacters($str);
		
		// Strip whitespace from the beginning and end of the string:
		$str = trim($str);

		if($replaceWhitespace) {
			// Replace any whitespace in the userName with the underscore "_":
			$str = preg_replace('/\s/', '_', $str);	
		}

		if($decodeEntities) {
			// Decode entities:
			$str = $this->decodeEntities($str, 'UTF-8', $htmlEntitiesMap);	
		}
		
		if($maxLength) {
			// Cut the string to the allowed length:
			$str = $this->subString($str, 0, $maxLength);
		}
		
		return $str;
	}
	
	function convertToUnicode($str, $sourceEncoding=null) {
		if($sourceEncoding === null) {
			$sourceEncoding = $this->getConfig('sourceEncoding');
		}
		return $this->convertEncoding($str, $sourceEncoding, 'UTF-8');
	}
	
	function convertFromUnicode($str, $contentEncoding=null) {
		if($contentEncoding === null) {
			$contentEncoding = $this->getConfig('contentEncoding');
		}
		return $this->convertEncoding($str, 'UTF-8', $contentEncoding);
	}

	function convertEncoding($str, $charsetFrom, $charsetTo) {
		return AJAXChatEncoding::convertEncoding($str, $charsetFrom, $charsetTo);
	}

	function encodeEntities($str, $encoding='UTF-8', $convmap=null) {
		return AJAXChatEncoding::encodeEntities($str, $encoding, $convmap);
	}

	function decodeEntities($str, $encoding='UTF-8', $htmlEntitiesMap=null) {
		return AJAXChatEncoding::decodeEntities($str, $encoding, $htmlEntitiesMap);
	}
	
	function htmlEncode($str) {
		return AJAXChatEncoding::htmlEncode($str, $this->getConfig('contentEncoding'));
	}
	
	function encodeSpecialChars($str) {
		return AJAXChatEncoding::encodeSpecialChars($str);
	}

	function decodeSpecialChars($str) {
		return AJAXChatEncoding::decodeSpecialChars($str);
	}
	
	function getConfig($key, $subkey=null) {
		if($subkey)
			return $this->_config[$key][$subkey];
		else
			return $this->_config[$key];
	}

	function setConfig($key, $subkey, $value) {
		if($subkey) {
			if(!isset($this->_config[$key])) {
				$this->_config[$key] = array();
			}
			$this->_config[$key][$subkey] = $value;
		} else {
			$this->_config[$key] = $value;
		}
	}
	
	function getLang($key=null) {
		if(!$this->_lang) {
			// Include the language file:
			$lang = null;
			require(AJAX_CHAT_PATH.'lib/lang/'.$this->getLangCode().'.php');
			$this->_lang = &$lang;
		}
		if($key === null)
			return $this->_lang;
		if(isset($this->_lang[$key]))
			return $this->_lang[$key];
		return null;
	}

	function getView() {
		return $this->_view;
	}

	function getChatURL() {
		if(defined('AJAX_CHAT_URL')) {
			return AJAX_CHAT_URL;
		}
		
		return
			(isset($_SERVER['HTTPS']) ? 'https://' : 'http://').
			(isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'].'@' : '').
			$_SERVER['SERVER_NAME'].
			(isset($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] == 443 || $_SERVER['SERVER_PORT'] == 80 ? '' : ':'.$_SERVER['SERVER_PORT']).
			substr($_SERVER['SCRIPT_NAME'],0, strrpos($_SERVER['SCRIPT_NAME'], '/')+1);
	}

	function getIDFromName($userName) {
		$sql = 'SELECT
					userID
				FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					userName = '.$this->db->makeSafe($userName).';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}

		if($result->numRows() == 1) {
			// Get the content:
			$row = $result->fetch();
			$result->free();
			// Return the User ID:
			return $row['userID'];
		} else {
			$result->free();
		}
		return null;
	}

	function getNameFromID($userID) {
		$sql = 'SELECT
					userName
				FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					userID = '.$this->db->makeSafe($userID).';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}

		if($result->numRows() == 1) {
			// Get the content:
			$row = $result->fetch();
			$result->free();
			// Return the User ID:
			return $row['userName'];
		} else {
			$result->free();
		}
		return null;
	}

	function getChannelFromID($userID) {
		$sql = 'SELECT
					channel
				FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					userID = '.$this->db->makeSafe($userID).';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}

		if($result->numRows() == 1) {
			// Get the content:
			$row = $result->fetch();
			$result->free();
			// Return the channel:
			return $row['channel'];
		} else {
			$result->free();
		}
		return null;
	}

	function getIPFromID($userID) {
		$sql = 'SELECT
					INET_NTOA(ip) AS ip
				FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					userID = '.$this->db->makeSafe($userID).';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}

		if($result->numRows() == 1) {
			// Get the content:
			$row = $result->fetch();
			$result->free();
			// Return the User ID:
			return $row['ip'];
		} else {
			$result->free();
		}
		return null;
	}
	
	function getRoleFromID($userID) {
		$sql = 'SELECT
					userRole
				FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					userID = '.$this->db->makeSafe($userID).';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}

		if($result->numRows() == 1) {
			// Get the content:
			$row = $result->fetch();
			$result->free();
			// Return the userRole:
			return $row['userRole'];
		} else {
			$result->free();
		}
		return null;
	}
	
	function getChannelNames() {
		return array_flip($this->getChannels());
	}
	
	function getChannelIDFromChannelName($channelName) {
		if(!$channelName)
			return null;
		$channels = $this->getAllChannels();
		if(array_key_exists($channelName,$channels))
			return $channels[$channelName];
		return null;
	}
	
	function getChannelNameFromChannelID($channelID) {
		foreach($this->getAllChannels() as $key=>$value) {
			if($value == $channelID)
				return $key;
		}
		return null;
	}
	
	function getChannelName() {
		$channelName = $this->getChannelNameFromChannelID($this->getChannel());
		// If channelName is null, it is probably a private room:
		if($channelName === null) {
			$userName = $this->getNameFromID($this->getChannel()-$this->getConfig('privateChannelDiff'));
			if($userName !== null) {
				$channelName = '['.$userName.']';				
			}
		}
		return $channelName;
	}

	function getPrivateChannelID($userID=null) {
		if($userID === null) {
			$userID = $this->getUserID();
		}
		return $userID + $this->getConfig('privateChannelDiff');
	}
	
	function getPrivateMessageID($userID=null) {
		if($userID === null) {
			$userID = $this->getUserID();
		}
		return $userID + $this->getConfig('privateMessageDiff');
	}

	function isAllowedToSendPrivateMessage() {
		if($this->getConfig('allowPrivateMessages'))
			return true;
		return false;
	}
	
	function isAllowedToCreatePrivateChannel() {
		if($this->getConfig('allowPrivateChannels')) {
			switch($this->getUserRole()) {
				case AJAX_CHAT_USER:
					return true;
				case AJAX_CHAT_MODERATOR:
					return true;
				case AJAX_CHAT_ADMIN:
					return true;
				default:
					return false;
			}
		}
		return false;
	}
	
	function isAllowedToListHiddenUsers() {
		// Hidden users are users within private or restricted channels:
		switch($this->getUserRole()) {
			case AJAX_CHAT_MODERATOR:
				return true;
			case AJAX_CHAT_ADMIN:
				return true;
			default:
				return false;
		}
	}

	function isUserInUse($userID, $userName) {
		// Check if userID / userName already in use:			
		$sql = 'SELECT
					COUNT(userID) AS numRows
				FROM
					'.$this->getConfig('dbTableNames','online').'
				WHERE
					userID = '.$this->db->makeSafe($userID).'
				OR
					userName = '.$this->db->makeSafe($userName).';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
		// Return false if found:
		$row = $result->fetch();
		$result->free();
		if($row['numRows'] != 0)
			return true;
		return false;
	}
	
	function isUserBanned($userID, $userName) {
		// Check if userID, userName or ip is banned:			
		$sql = 'SELECT
					COUNT(userID) AS numRows
				FROM
					'.$this->getConfig('dbTableNames','bans').'
				WHERE
				(
					userID = '.$this->db->makeSafe($userID).'
				OR
					userName = '.$this->db->makeSafe($userName).'
				OR
					ip = INET_ATON(\''.$_SERVER['REMOTE_ADDR'].'\')
				)
				AND
					NOW() < dateTime;';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
		// Return false if found:
		$row = $result->fetch();
		$result->free();
		if($row['numRows'] != 0)
			return true;
		return false;
	}
	
	function isMaxUsersLoggedIn() {
		// Get the number of online users:			
		$sql = 'SELECT
					COUNT(userID) AS numRows
				FROM
					'.$this->getConfig('dbTableNames','online').';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);
		
		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();
			die();
		}
		// Return true if the user count is equal or bigger than the allowed maximum:
		$row = $result->fetch();
		$result->free();
		if($row['numRows'] >= $this->getConfig('maxUsersLoggedIn'))
			return true;
		return false;
	}
			
	function validateChannel($channelID) {
		if($channelID === null)
			return false;
		// Return true for normal channels the user has acces to:
		if(in_array($channelID, $this->getChannels(), true))
			return true;
		// Return true if the user is allowed to join his own private channel:
		if($channelID == $this->getPrivateChannelID() && $this->isAllowedToCreatePrivateChannel())
			return true;
		// Return true if the user has been invited to a restricted or private channel:
		$invitations = $this->getInvitations();
		if($invitations) {
			foreach($invitations as $channelName) {
				if($channelName && $channelName[0] == '[') {
					// Joining a private channel:
					$userID = $this->getIDFromName($this->subString($channelName, 1, $this->stringLength($channelName)-2));
					if($userID !== null && $channelID == $this->getPrivateChannelID($userID)) {
						return true;
					}
				} else if($channelID == $this->getChannelIDFromChannelName($channelName)) {
					// Joining a restricted channel:
					return true;
				}
			}	
		}
		// No valid channel, return false:
		return false;
	}
	
	function createGuestUserName() {
		$maxLength =	$this->getConfig('userNameMaxLength')
						- $this->stringLength($this->getConfig('guestUserPrefix'))
						- $this->stringLength($this->getConfig('guestUserSuffix'));

		// seed with microseconds since last "whole" second:
		mt_srand((double)microtime()*1000000);

		// Create a random userName using numbers between 100000 and 999999:
		$userName = substr(mt_rand(100000, 999999), 0, $maxLength);

		return $this->getConfig('guestUserPrefix').$userName.$this->getConfig('guestUserSuffix');
	}
	
	// Guest userIDs must not interfere with existing userIDs and must be lower than privateChannelDiff:
	function createGuestUserID() {
		// seed with microseconds since last "whole" second:
		mt_srand((double)microtime()*1000000);
		
		return mt_rand($this->getConfig('minGuestUserID'), $this->getConfig('privateChannelDiff')-1);
	}

	function getGuestUser() {
		if(!$this->getConfig('allowGuestLogins'))
			return null;

		if($this->getConfig('allowGuestUserName')) {
			$maxLength =	$this->getConfig('userNameMaxLength')
							- $this->stringLength($this->getConfig('guestUserPrefix'))
							- $this->stringLength($this->getConfig('guestUserSuffix'));

			// Trim userName using the contentEncoding as source encoding:
			$userName = $this->trimUserName($this->getRequestVar('userName'), $this->getConfig('contentEncoding'), $maxLength);

			// If given userName is invalid, create one:
			if(!$userName) {
				$userName = $this->createGuestUserName();
			} else {
				// Add the guest users prefix and suffix to the given userName:
				$userName = $this->getConfig('guestUserPrefix').$userName.$this->getConfig('guestUserSuffix');	
			}
		} else {
			$userName = $this->createGuestUserName();
		}

		$userData = array();
		$userData['userID'] = $this->createGuestUserID();
		$userData['userName'] = $userName;
		$userData['userRole'] = AJAX_CHAT_GUEST;
		return $userData;		
	}

	function getCustomVar($key) {
		if(!isset($this->_customVars))
			$this->_customVars = array();
		if(!isset($this->_customVars[$key]))
			return null;
		return $this->_customVars[$key];
	}
	
	function setCustomVar($key, $value) {
		if(!isset($this->_customVars))
			$this->_customVars = array();
		$this->_customVars[$key] = $value;
	}

	// Override to replace custom template tags:
	// Return the replacement for the given tag (and given tagContent)	
	function replaceCustomTemplateTags($tag, $tagContent) {
		return null;
	}

	// Override to initialize custom configuration settings:
	function initCustomConfig() {
	}

	// Override, to parse custom info requests:
	// $infoRequest contains the current info request
	// Add info responses using the method addInfoMessage($info, $type)
	function parseCustomInfoRequest($infoRequest) {
	}

	// Override to add custom request variables:
	// Add values to the request variables array: $this->_requestVars['customVariable'] = null;
	function initCustomRequestVars() {
	}

	// Override to add custom commands:
	// Return true if a custom command has been successfully parsed, else false
	// $text contains the whole message, $textParts the message split up as words array
	function parseCustomCommands($text, $textParts) {
		return false;
	}

	// Override to perform custom actions on new messages:
	// Return true if message may be inserted, else false
	function onNewMessage($text) {
		return true;
	}

	// Override to perform custom actions on new messages:
	// Method to set the style cookie depending on user data
	function setStyle() {
	}
	
	// Override:
	// Returns an associative array containing userName, userID and userRole
	// Returns null if login is invalid
	function getValidLoginUserData() {
		// Check if we have a valid registered user:
		if(false) {
			// Here is the place to check user authentication
		} else {
			// Guest users:
			return $this->getGuestUser();
		}
	}

	// Override:
	// Store the channels the current user has access to
	// Make sure channel names don't contain any whitespace
	function &getChannels() {
		if($this->_channels === null) {
			$this->_channels = $this->getAllChannels();
		}
		return $this->_channels;
	}

	// Override:
	// Store all existing channels
	// Make sure channel names don't contain any whitespace
	function &getAllChannels() {
		if($this->_allChannels === null) {
			$this->_allChannels = array();
			
			// Default channel, public to everyone:
			$this->_allChannels[$this->trimChannelName($this->getConfig('defaultChannelName'))] = $this->getConfig('defaultChannelID');
		}
		return $this->_allChannels;
	}

}
?>