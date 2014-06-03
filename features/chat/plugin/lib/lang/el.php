<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author panas
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

$lang = array();
$lang['title'] = 'AJAX Chat';
$lang['userName'] = 'Όνομα χρήστη';
$lang['password'] = 'Κωδικός';
$lang['login'] = 'Σύνδεση';
$lang['logout'] = 'Αποσύνδεση';
$lang['channel'] = 'Κανάλι';
$lang['style'] = 'Στυλ';
$lang['messageSubmit'] = 'Υποβολή';
$lang['registeredUsers'] = 'Εγγεγραμμένοι χρήστες';
$lang['onlineUsers'] = 'Συνδεδεμένοι χρήστες';
$lang['autoScroll'] = 'Αυτόματη κύλιση';
$lang['showOnlineUsers'] = 'Εμφάνιση συνδεδεμένων χρηστών';
$lang['showHelp'] = 'Εμφάνιση της βοήθειας';
$lang['persistFontColor'] = 'Έντονο χρώμα φόντου';
$lang['bbCodeLabelBold'] = 'b';
$lang['bbCodeLabelItalic'] = 'i';
$lang['bbCodeLabelUnderline'] = 'u';
$lang['bbCodeLabelQuote'] = 'Παράθεση';
$lang['bbCodeLabelCode'] = 'Κώδικας';
$lang['bbCodeLabelURL'] = 'URL';
$lang['bbCodeLabelColor'] = 'Χρώμα φόντου';
$lang['bbCodeTitleBold'] = 'Έντονο κείμενο: [b]κείμενο[/b]';
$lang['bbCodeTitleItalic'] = 'Πλάγια γραφή: [i]κείμενο[/i]';
$lang['bbCodeTitleUnderline'] = 'Υπογραμμισμένο κείμενο: [u]κείμενο[/u]';
$lang['bbCodeTitleQuote'] = 'Κείμενο σε παράθεση: [quote]κείμενο[/quote] ή [quote=author]κείμενο[/quote]';
$lang['bbCodeTitleCode'] = 'Εμφάνιση κώδικα: [code]code[/code]';
$lang['bbCodeTitleURL'] = 'Εισαγωγή URL: [url]http://example.org[/url] ή [url=http://example.org]κείμενο[/url]';
$lang['bbCodeTitleColor'] = 'Χρώμα φόντου: [color=red]κείμενο[/color]';
$lang['help'] = 'Βοήθεια';
$lang['helpItemDescJoin'] = 'Είσοδος σε ένα κανάλι:';
$lang['helpItemCodeJoin'] = '/join όνομα_καναλιού';
$lang['helpItemDescJoinCreate'] = 'Δημιουργία πριβέ καναλιού(μόνο για εγγεγραμμένους):';
$lang['helpItemCodeJoinCreate'] = '/join';
$lang['helpItemDescInvite'] = 'Για μα προκσαλέστε (π.χ. σε ένα πριβέ κανάλι):';
$lang['helpItemCodeInvite'] = '/invite όνομα';
$lang['helpItemDescUninvite'] = 'Ακύρωση πρόσκλησης:';
$lang['helpItemCodeUninvite'] = '/uninvite όνομα';
$lang['helpItemDescLogout'] = 'Αποσύνδεση από το Chat:';
$lang['helpItemCodeLogout'] = '/quit';
$lang['helpItemDescPrivateMessage'] = 'Προσωπικό μήνυμα:';
$lang['helpItemCodePrivateMessage'] = '/msg όνομα_χρήστη κείμενο';
$lang['helpItemDescQueryOpen'] = 'Άνοιγμα πριβέ καναλιού:';
$lang['helpItemCodeQueryOpen'] = '/query όνομα χρήστη';
$lang['helpItemDescQueryClose'] = 'Κλεισιμό ενώς πριβέ καναλιού:';
$lang['helpItemCodeQueryClose'] = '/query';
$lang['helpItemDescAction'] = 'Περιγραφή ενέργειας:';
$lang['helpItemCodeAction'] = '/action κείμενο';
$lang['helpItemDescMe'] = 'Περιγραφή δικιάς σας ενέργειας:';
$lang['helpItemCodeMe'] = '/me κείμενο';
$lang['helpItemDescDescribe'] = 'Περιγραφή ενέργειας με προσωπικό μήνυμα:';
$lang['helpItemCodeDescribe'] = '/describe όνομα_χρήστη κείμενο';
$lang['helpItemDescIgnore'] = 'Αγνόηση/αποδοχή μηνυμάτων από χρήστη:';
$lang['helpItemCodeIgnore'] = '/ignore όνομα_χρήστη';
$lang['helpItemDescIgnoreList'] = 'Εμφάνιση λίστας αγνοημένων:';
$lang['helpItemCodeIgnoreList'] = '/ignore';
$lang['helpItemDescKick'] = 'Kick έναν χρήστη (Μόνον για συντονιστές):';
$lang['helpItemCodeKick'] = '/kick όνομα χρήστη [λεπτά αποκλεισμού]';
$lang['helpItemDescUnban'] = 'Αφαίρεση αποκλεισμού από χρήστη (Μόνον για συντονιστές):';
$lang['helpItemCodeUnban'] = '/unban όνομα_χρήστη';
$lang['helpItemDescBans'] = 'Λίστα αποκλεισμένων χρηστών (Μόνον  για συντονιστές):';
$lang['helpItemCodeBans'] = '/bans';
$lang['helpItemDescWhois'] = 'Εμφανίζει την IP του χρήστη (Μόνον  για συντονιστές):';
$lang['helpItemCodeWhois'] = '/whois όνομα χρήστη';
$lang['helpItemDescWho'] = 'Λίστα συνδεδεμένων χρηστών:';
$lang['helpItemCodeWho'] = '/who [όνομακαναλιού]';
$lang['helpItemDescList'] = 'Εμφάνιση διαθέσιμων καναλιών:';
$lang['helpItemCodeList'] = '/list';
$lang['helpItemDescRoll'] = 'Κύλιση ζαριών:';
$lang['helpItemCodeRoll'] = '/roll [αριθμός]d[πλευρές]';
$lang['requiresJavaScript'] = 'Χρειάζεται JavaScript για το Chat.';
$lang['errorInvalidUser'] = 'Ακατάλληλο όνομα χρήστη.';
$lang['errorUserInUse'] = 'Το όνομα υπάρχει ήδη.';
$lang['errorBanned'] = 'Το όνομα χρήστη ή IP είναι αποκλεισμένα.';
$lang['errorMaxUsersLoggedIn'] = 'Το chat έχει φτάσει το μέγιστο αριθμό χρηστών.';
$lang['errorChatClosed'] = 'Το chat είναι κλειστό προς το παρόν.';
$lang['logsTitle'] = 'AJAX Chat - Καταγραφές';
$lang['logsDate'] = 'Ημερομηνία';
$lang['logsTime'] = 'Ώρα';
$lang['logsSearch'] = 'Αναζήτηση';
$lang['logsPrivateChannels'] = 'Πριβέ κανάλια';
$lang['logsPrivateMessages'] = 'προσωπικά μηνύματα';
?>