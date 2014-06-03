<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */
if(!isset($CFG) && file_exists('../../../config.php')){ include('../../../config.php'); } 

// Define AJAX Chat user roles:
define('AJAX_CHAT_CHATBOT', 4);
define('AJAX_CHAT_ADMIN', 3);
define('AJAX_CHAT_MODERATOR',	2);
define('AJAX_CHAT_USER',	1);
define('AJAX_CHAT_GUEST', 0);

// AJAX Chat config parameters:
$config = array();

// Database connection values:
$config['dbConnection'] = array();
// Database hostname:
$config['dbConnection']['host'] = $CFG->dbhost;
// Database username:
$config['dbConnection']['user'] = $CFG->dbuser;
// Database password:
$config['dbConnection']['pass'] = $CFG->dbpass;
// Database name:
$config['dbConnection']['name'] = $CFG->dbname;
// Database type:
$config['dbConnection']['type'] = $CFG->dbtype;
// Database link:
$config['dbConnection']['link'] = false;

// Database table names:
$config['dbTableNames'] = array();
$config['dbTableNames']['online']		= 'ajax_chat_online';
$config['dbTableNames']['messages']		= 'ajax_chat_messages';
$config['dbTableNames']['bans']			= 'ajax_chat_bans';

// Available languages:
$config['langAvailable'] = array('ar','de','el','en','es','fi','he','it','nl','ro','ru','sv');
// Default language:
$config['langDefault'] = 'en';

// Available styles:
$config['styleAvailable'] = array('MyBB');
// Default style:
$config['styleDefault'] = 'MyBB';

// The encoding used for the XHTML content:
$config['contentEncoding'] = 'UTF-8';
// The encoding of the data source, like userNames and channelNames:
$config['sourceEncoding'] = 'UTF-8';

// Session handling:
$config['sessionName'] = 'ajax_chat';
$config['sessionValuePrefix'] = 'ajaxChat';

// Default channelName used together with the defaultChannelID if no channel with this ID exists:
$config['defaultChannelName'] = 'Public';
// ChannelID used when no channel is given:
$config['defaultChannelID'] = 0;
// Defines an array of channelIDs (e.g. array(0, 1)) to limit the number of available channels, will be ignored if set to null:
$config['limitChannelList'] = null;

// UserID plus this value are private channels (this is also the max userID and max channelID):
$config['privateChannelDiff'] = 500000000;
// UserID plus this value are used for private messages:
$config['privateMessageDiff'] = 1000000000;

// Enable/Disable private Channels:
$config['allowPrivateChannels'] = true;
// Enable/Disable private Messages:
$config['allowPrivateMessages'] = true;

// If enabled, users will be logged in automatically as guest users (if allowed), if not authenticated:
$config['forceAutoLogin'] = false;

// Defines if login/logout and channel enter/leave are displayed:
$config['showChannelMessages'] = true;

// If enabled, the chat will only be accessible for the admin:
$config['chatClosed'] = false;
// Defines the timezone offset in seconds (-12*60*60 to 12*60*60) - if null, the server timezone is used:

$config['timeZoneOffset'] = get_offset();
// Defines the hour of the day the chat is opened (0 - closingHour):
$config['openingHour'] = 0;
// Defines the hour of the day the chat is closed (openingHour - 24):
$config['closingHour'] = 24;
// Defines the weekdays the chat is opened (0=Sunday to 6=Saturday):
$config['openingWeekDays'] = array(0,1,2,3,4,5,6);

// Enable/Disable guest logins:
$config['allowGuestLogins'] = true;
// Enable/Disable write access for guest users - if disabled, guest users may not write messages:
$config['allowGuestWrite'] = true;
// Allow/Disallow guest users to choose their own userName:
$config['allowGuestUserName'] = true;
// Guest users should be distinguished by either a prefix or a suffix or both (no whitespace):
$config['guestUserPrefix'] = '(';
// Guest users should be distinguished by either a prefix or a suffix or both (no whitespace):
$config['guestUserSuffix'] = ')';
// Guest userIDs may not be lower than this value (and not higher than privateChannelDiff):
$config['minGuestUserID'] = 400000000;

// The userID used for ChatBot messages:
$config['chatBotID'] = 2147483647;
// The userName used for ChatBot messages
$config['chatBotName'] = 'Chat Note';

// Minutes until a user is declared inactive (closed browser window):
$config['inactiveTimeout'] = 15;
// Interval in minutes to check for inactive users (last online status):
$config['inactiveCheckInterval'] = 5;

// Defines if messages are shown which have been sent before the user entered the channel:
$config['requestMessagesPriorChannelEnter'] = true;
// Defines an array of channelIDs (e.g. array(0, 1)) for which the previous setting is always true (will be ignored if set to null):
$config['requestMessagesPriorChannelEnterList'] = null;
// Max time difference in hours for messages to display on each request:
$config['requestMessagesTimeDiff'] = 1;
// Max number of messages to display on each request:
$config['requestMessagesLimit'] = 10;

// Max users in chat (does not affect moderators or admins):
$config['maxUsersLoggedIn'] = 100;
// Max userName length:
$config['userNameMaxLength'] = 50;
// Max messageText length:
$config['messageTextMaxLength'] = 1040;

// Defines the max number of messages a user may send per minute before getting kicked and banned:
$config['maxMessageRate'] = 60;
// Defines the default time in minutes a user gets banned if kicked and banned from the chat system:
$config['defaultBanTime'] = 60;

// Argument that is given to the handleLogout JavaScript method:
$config['logoutData'] = './?logout=true';

// If true, checks if the user IP is the same when logged in:
$config['ipCheck'] = true;

// Defines the max time difference in hours for logs when no period or search condition is given:
$config['logsRequestMessagesTimeDiff'] = 1;
// Defines how many logs are returned on each logs request:
$config['logsRequestMessagesLimit'] = 10;

// Defines the earliest year used for the logs selection:
$config['logsFirstYear'] = 2007;

// Defines if old messages are purged from the database:
$config['logsPurgeLogs'] = false;
// Max time difference in days for old messages before they are purged from the database:
$config['logsPurgeTimeDiff'] = 365;
?>