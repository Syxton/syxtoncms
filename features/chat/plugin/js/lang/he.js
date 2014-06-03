/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author Smiley Barry
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Ajax Chat language Object:
var ajaxChatLang = {
	
	login: '%s נכנס לתוך הצאט.',
	logout: '%s יוצא מהצאט.',
	logoutTimeout: '%s הוצא מהצאט (היה לא זמין).',
	logoutIP: '%s הוצא מהצאט (כתובת מחשב בלתי חוקית).',
	logoutKicked: '%s הוצא מהצאט (הועף/נבעט).',
	channelEnter: '%s נכנס לתוך הערוץ.',
	channelLeave: '%s יוצא מהערוץ.',
	privmsg: '(לוחש)',
	privmsgto: '(לוחש ל%s)',
	invite: '%s מזמין אותך להצטרף לערוץ %s.',
	inviteto: 'ההזמנה שלך עבור %s להצטרף לערוץ %s נשלחה.',
	uninvite: '%s ביטל את הזמנתו לערוץ %s.',
	uninviteto: 'ביטול ההזמנה שלך עבור %s להצטרף לערוץ %s נשלח.',
	queryOpen: 'ערוץ פרטי עבור %s נפתח.',
	queryClose: 'ערוץ פרטי עבור %s נסגר.',
	ignoreAdded: 'המשתמש %s נוסף לרשימת ההתעלמות.',
	ignoreRemoved: 'המשתמש %s נמחק מרשימת ההתעלמות.',
	ignoreList: 'משתמשים אשר אתה מתעלם מהם:',
	ignoreListEmpty: 'אין משתמשים ברשימה.',
	who: 'משתמשים מחוברים:',
	whoEmpty: 'אין משתמשים מחוברים בערוץ.',
	list: 'ערוצים פתוחים:',
	bans: 'משתמשים חסומים:',
	bansEmpty: 'אין משתמשים חסומים.',
	unban: 'בוטלה החסימה נגד המשתמש %s.',
	whois: 'כתובת המחשב של המשתמש %s:',
	roll: '%s מגלגל %s ומקבל %s.',
	sendPrivateMessage: 'שלח הודעה פרטית ל%s',
	joinChannel: 'הצטרף לערוץ %s',
	cite: '%s אמר:',
	urlDialog: 'אנא הכנס את כתובת האינטרנט (URL) של הדף:',
	errorCookiesRequired: 'הצאט מבקש עוגיות כדי לפעול. אנא רד לחנות לקנות.',
	errorUserNameNotFound: 'שגיאה: המשתמש %s לא נמצא.',
	errorMissingText: 'שגיאה: חסר טקסט בהודעה.',
	errorMissingUserName: 'שגיאה: חסר שם משתמש.',
	errorMissingChannelName: 'שגיאה: חסר שם ערוץ.',
	errorInvalidChannelName: 'שגיאה: שם ערוץ לא חוקי: %s',
	errorPrivateMessageNotAllowed: 'שגיאה: הודעות פרטיות אסורות לשימוש.',
	errorInviteNotAllowed: 'שגיאה: אסור לך להזמין אנשים לערוץ זה.',
	errorUninviteNotAllowed: 'שגיאה: אסור לך לבטל הזמנות של אנשים לערוץ זה.',
	errorNoOpenQuery: 'שגיאה: ערוץ פרטי לא פתוח.',
	errorKickNotAllowed: 'שגיאה: אסור לך להעיף את %s.',
	errorCommandNotAllowed: 'שגיאה: פקודה אסורה: %s',
	errorUnknownCommand: 'שגיאה: פקודה לא ידועה: %s',
	errorConnectionTimeout: 'שגיאה: זמן חיבור פג. אנא נסה שנית.',
	errorConnectionStatus: 'שגיאת חיבור: %s'
	
}