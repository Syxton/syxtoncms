/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author panas
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Ajax Chat language Object:
var ajaxChatLang = {
	
	login: '%s μπήκε στο Chat.',
	logout: '%s βγήκε από το Chat.',
	logoutTimeout: '%s βγήκε από το Chat (Ανενεργό).',
	logoutIP: '%s βγήκε από το Chat (Λανθασμένη IP).',
	logoutKicked: '%s βγήκε από το Chat (Kicked).',
	channelEnter: '%s μπήκε στο κανάλι.',
	channelLeave: '%s βγήκε από το κανάλι.',
	privmsg: '(ψιθυρίζει)',
	privmsgto: '( ψιθυρίζει σε %s)',
	invite: '%s σας καλεί να συμμετάσχετε στο %s.',
	inviteto: 'Η πρόσκληση σας σε %s να συμμετάσχει στο κανάλι %s έχει σταλεί.',
	uninvite: '%s τερματίζει την πρόσκληση σας για το κανάλι %s.',
	uninviteto: 'Η πρόσκληση σας σε %s για το κανάλι %s έχει σταλεί.',
	queryOpen: 'Άνοιξε πρίβε κανάλι σε %s.',
	queryClose: ' Το πρίβε κανάλι %s έκλεισε.',
	ignoreAdded: '%s προστέθηκε στη λίστα αγνόησης.',
	ignoreRemoved: ' %s αφαιρέθηκε  από τη λίστα αγνόησης .',
	ignoreList: 'Αγνοήμενοι χρήστες:',
	ignoreListEmpty: 'Δεν υπάρχουν αγνοημένοι χρήστες.',
	who: 'Χρήστες παρόν:',
	whoEmpty: 'Δεν υπάρχουν χρήστες στο συγκεκριμένο κανάλι.',
	list: 'Διαθέσιμα κανάλια:',
	bans: 'Αποκλεισμένοι χρήστες:',
	bansEmpty: 'Δεν υπάρχουν αποκλεισμένοι χρήστες.',
	unban: 'Ο αποκλεισμός %s αφαιρέθηκε.',
	whois: ' %s - IP διεύθυνση:',
	roll: '%s ρίχνει %s και φέρνει %s.',
	sendPrivateMessage: 'Αποστολή προσωπικού μηνύματος σε %s',
	joinChannel: 'Μπαίνει στο κανάλι %s',
	cite: '%s είπε:',
	urlDialog: 'παρακαλούμε εισάγετε την διεύθυνση (URL) της ιστοσελίδας:',
	errorCookiesRequired: 'Τα cookies είναι απαραίτητα για το chat.',
	errorUserNameNotFound: 'Σφάλμα: Ο χρήστης %s δεν βρέθηκε.',
	errorMissingText: 'Σφάλμα: Λείπει το μήνυμα.',
	errorMissingUserName: ': Λείπει ο χρήστης.',
	errorMissingChannelName: 'Σφάλμα: Λείπει το όνομα του καναλιού.',
	errorInvalidChannelName: 'Σφάλμα: Ακατάλληλο όνομα καναλιού: %s',
	errorPrivateMessageNotAllowed: 'Σφάλμα: Τα προσωπικά μηνύματα δεν επιτρέπονται.',
	errorInviteNotAllowed: 'Σφάλμα: Δεν σας επιτρέπετε να καλέσετε άλλούς στο κανάλι.',
	errorUninviteNotAllowed: 'Σφάλμα: Δεν σας επιτρέπετε να τερματίσετε την πρόσκληση άλλων από το κανάλι.',
	errorNoOpenQuery: ': Δεν ανοίχθηκε πρίβε κανάλι.',
	errorKickNotAllowed: 'Δεν σας επιτρέπετε να πετάξετε %s.',
	errorCommandNotAllowed: 'Σφάλμα: Δεν επιτρέπετε η εντολή: %s',
	errorUnknownCommand: 'Σφάλμα: Άγνωστη εντολή: %s',
	errorConnectionTimeout: 'Σφάλμα: Έληξε ο χρόνος σύνδεσης. Προσπαθήστε ξανά.',
	errorConnectionStatus: 'Σφάλμα: Κατάσταση σύνδεσης: %s'
	
}