<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author Smiley Barry
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

$lang = array();
$lang['title'] = 'AJAX Chat';
$lang['userName'] = 'שם משתמש';
$lang['password'] = 'סיסמה';
$lang['login'] = 'היכנס';
$lang['logout'] = 'צא';
$lang['channel'] = 'ערוץ';
$lang['style'] = 'סגנון';
$lang['messageSubmit'] = 'שלח';
$lang['registeredUsers'] = 'משתמשים רשומים';
$lang['onlineUsers'] = 'משתמשים מחוברים';
$lang['autoScroll'] = 'גלילת צאט אוטומטית';
$lang['showOnlineUsers'] = 'הראה רשימת משתמשים מחוברים';
$lang['showHelp'] = 'הראה עזרה';
$lang['persistFontColor'] = 'צבע גופן קבוע';
$lang['bbCodeLabelBold'] = 'b';
$lang['bbCodeLabelItalic'] = 'i';
$lang['bbCodeLabelUnderline'] = 'u';
$lang['bbCodeLabelQuote'] = 'צטט';
$lang['bbCodeLabelCode'] = 'קוד';
$lang['bbCodeLabelURL'] = 'כתובת אינטרנט';
$lang['bbCodeLabelColor'] = 'צבע גופן';
$lang['bbCodeTitleBold'] = 'טקסט מודגש: [b]טקסט[/b]';
$lang['bbCodeTitleItalic'] = 'טקסט נטוי: [i]טקסט[/i]';
$lang['bbCodeTitleUnderline'] = 'טקסט מודגש קו: [u]טקסט[/u]';
$lang['bbCodeTitleQuote'] = 'צטט טקסט: [quote]טקסט[/quote] או [quote=כותב מקורי]טקסט[/quote]';
$lang['bbCodeTitleCode'] = 'הראה קוד: [code]קוד[/code]';
$lang['bbCodeTitleURL'] = 'הכנס כתובת: [url]http://www.example.org[/url] או[url=http://www.example.org]טקסט[/url]';
$lang['bbCodeTitleColor'] = 'צבע גופן: [color=צבע]טקסט[/color]';
$lang['help'] = 'עזרה';
$lang['helpItemDescJoin'] = 'הצטרף לערוץ:';
$lang['helpItemCodeJoin'] = '/join שם ערוץ';
$lang['helpItemDescJoinCreate'] = 'צור חדר פרטי (משתמשים רשומים בלבד):';
$lang['helpItemCodeJoinCreate'] = '/join';
$lang['helpItemDescInvite'] = 'הזמן מישהו (דוגמא: חדר פרטי):';
$lang['helpItemCodeInvite'] = '/invite שם_משתמש';
$lang['helpItemDescUninvite'] = 'בטל הזמנה:';
$lang['helpItemCodeUninvite'] = '/uninvite שם_משתמש';
$lang['helpItemDescLogout'] = 'צא מהצאט:';
$lang['helpItemCodeLogout'] = '/quit';
$lang['helpItemDescPrivateMessage'] = 'הודעה פרטית:';
$lang['helpItemCodePrivateMessage'] = '/msg שם_משתמש טקסט';
$lang['helpItemDescQueryOpen'] = 'פתח ערוץ אישי:';
$lang['helpItemCodeQueryOpen'] = '/query שם_משתמש';
$lang['helpItemDescQueryClose'] = 'סגור ערוץ אישי:';
$lang['helpItemCodeQueryClose'] = '/query';
$lang['helpItemDescAction'] = 'תאר פעולה:';
$lang['helpItemCodeAction'] = '/action טקסט';
$lang['helpItemDescMe'] = 'תאר פעולה עצמית:';
$lang['helpItemCodeMe'] = '/me טקסט';
$lang['helpItemDescDescribe'] = 'תאר פעולה בהודעה פרטית:';
$lang['helpItemCodeDescribe'] = '/describe שם_משתמש טקסט';
$lang['helpItemDescIgnore'] = 'התעלם או קבל הודעות ממשתמש:';
$lang['helpItemCodeIgnore'] = '/ignore שם_משתמש';
$lang['helpItemDescIgnoreList'] = 'הראה רשימה של אנשים שאתה מתעלם מהם:';
$lang['helpItemCodeIgnoreList'] = '/ignore';
$lang['helpItemDescKick'] = 'העף משתמש (חברי צוות בלבד):';
$lang['helpItemCodeKick'] = '/kick שם_משתמש [מספר דקות]';
$lang['helpItemDescUnban'] = 'בטל חסימת משתמש (חברי צוות בלבד):';
$lang['helpItemCodeUnban'] = '/unban שם_משתמש';
$lang['helpItemDescBans'] = 'הראה רשימת משתמשים חסומים (חברי צוות בלבד):';
$lang['helpItemCodeBans'] = '/bans';
$lang['helpItemDescWhois'] = 'הראה כתובת מחשב של משתמש (חברי צוות בלבד):';
$lang['helpItemCodeWhois'] = '/whois שם_משתמש';
$lang['helpItemDescWho'] = 'הראה רשימת משתמשים מחוברים:';
$lang['helpItemCodeWho'] = '/who שם_ערוץ';
$lang['helpItemDescList'] = 'הראה רשימת ערוצים פתוחים:';
$lang['helpItemCodeList'] = '/list';
$lang['helpItemDescRoll'] = 'גלגל קוביה:';
$lang['helpItemCodeRoll'] = '/roll [מספר]d[צדדים]';
$lang['requiresJavaScript'] = 'JavaScript נצרך בשביל הצאט הזה.';
$lang['errorInvalidUser'] = 'שם משתמש לא חוקי.';
$lang['errorUserInUse'] = 'שם משתמש בשימוש.';
$lang['errorBanned'] = 'משתמש או כתובת מחשב חסומים.';
$lang['errorMaxUsersLoggedIn'] = 'הצאט כרגע מלא.';
$lang['errorChatClosed'] = 'הצאט כרגע סגור.';
$lang['logsTitle'] = 'תיעוד';
$lang['logsDate'] = 'תאריך';
$lang['logsTime'] = 'זמן';
$lang['logsSearch'] = 'חיפוש';
$lang['logsPrivateChannels'] = 'ערוצים פרטיים';
$lang['logsPrivateMessages'] = 'הודעות פרטיות';
?>