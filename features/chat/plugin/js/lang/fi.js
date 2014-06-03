/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author zazu
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Ajax Chat language Object:
var ajaxChatLang = {
	
	login: '%s kirjautui sisään.',
	logout: '%s kirjautui ulos.',
	logoutTimeout: '%s kirjautui ulos (Aikakatkaisu).',
	logoutIP: '%s kirjautui ulos (virheellinen IP-osoite).',
	logoutKicked: '%s  kirjautui ulos (Potkut).',
	channelEnter: '%s liitty kanavalle.',
	channelLeave: '%s poistuu kanavalta.',
	privmsg: '(kuiskaa)',
	privmsgto: '(kuiskaa käyttäjälle %s)',
	invite: '%s kutsuu sinut liittymään %s.',
	inviteto: 'Sinun kutsusi käyttäjälle %s, liittymisestä kanavalle %s, on lähetetty.',
	uninvite: '%s peruu kutsun kanavalle %s.',
	uninviteto: 'Sinun kutsun perumnen käyttäjälle %s kanavaa %s varten, on lähetetty.',	
	queryOpen: 'Yksityinen kanava käyttäjälle %s on avattu.',
	queryClose: 'Yksityinen kanava käyttäjälle %s on suljettu.',
	ignoreAdded: 'Käyttäjä %s on lisätty huomiotta jätettäviin.',
	ignoreRemoved: 'Käyttäjä %s on poistettu huomiotta jätettävistä.',
	ignoreList: 'Huomiotta jätettävät käyttäjät:',
	ignoreListEmpty: 'Ei huomiotta jätettäviä käyttäjiä.',
	who: 'Paikallaolijat:',
	whoEmpty: 'Ei käyttäjiä annetulla kanavalla.',
	list: 'Käytettävät kanavat:',
	bans: 'Potkitut käyttäjät:',
	bansEmpty: 'Ei potkittuja käyttäjiä.',
	unban: 'Käyttäjän %s potkut on poistettu.',
	whois: 'Käyttäjän %s IP osoite:',
	roll: '%s heittä %s kertaa ja saa %s.',
	sendPrivateMessage: 'Lähetä yksityinen viesit käyttäjälle %s',
	joinChannel: 'Liity kanavalle %s',
	cite: '%s sanoi:',
	urlDialog: 'Lisää nettisivujen osoite(URL):',
	errorCookiesRequired: 'Evästeiden pitää olla sallituja käyttääksesi tätä chattia.',
	errorUserNameNotFound: 'Virhe: Käyttäjää %s ei löydetty.',
	errorMissingText: 'Virhe: Puuttuva viestin teksti.',
	errorMissingUserName: 'Virhe: Puuttuva käyttäjänimi.',
	errorMissingChannelName: 'Virhe: Puuttuva kanavan nimi.',
	errorInvalidChannelName: 'Virhe: Virheellinen kanavan nimi: %s',
	errorPrivateMessageNotAllowed: 'Virhe: Yksityisviestit eivät ole sallittuja.',
	errorInviteNotAllowed: 'Virhe: Sinulla ei ole oikeutta kutsua ketään kanavalle.',
	errorUninviteNotAllowed: 'Virhe: Sinulla ei ole oikeutta perua kutsua tälle kanavalle.',
	errorNoOpenQuery: 'Virhe: Ei yksityistä kanavaa auki.',
	errorKickNotAllowed: 'Virhe: Sinulla ei ole oikeutta potkia käyttäjää %s.',
	errorCommandNotAllowed: 'Virhe: Komento ei ole sallittu: %s',
	errorUnknownCommand: 'Virhe: Tuntematon komento: %s',
	errorConnectionTimeout: 'Virhe: Yhteyden aikakatkaisu, olkaa hyvä ja yrittäkää uudelleen.',
	errorConnectionStatus: 'Virhe: Yhteyden tila: %s'

}