/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author SkyKnight
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// Ajax Chat language Object:
var ajaxChatLang = {
	
	login: '%s входит в чат.',
	logout: '%s выходит из чата.',
	logoutTimeout: '%s вышел из чата по таймоуту.',
	logoutIP: '%s вышел из чата (неверный IP адрес).',
	logoutKicked: '%s был вышвырнут из чата (Kicked).',
	channelEnter: '%s присоединяется к каналу.',
	channelLeave: '%s покидает канал.',
	privmsg: '(приватное сообщение)',
	privmsgto: '(приватное сообщение %s)',
	invite: '%s приглашает Вас присоединиться к %s.',
	inviteto: 'Ваше приглашение %s присоедениться к каналу %s было успешно отправлено.',
	uninvite: '%s отзывает Ваше приглашение из канала %s.',
	uninviteto: 'Вы отозвали приглашение пользователю %s для канала %s.',
	queryOpen: 'Приватный канал открыт к %s.',
	queryClose: 'Приватный канал к %s закрыт.',
	ignoreAdded: '%s добавлен в игнорлист.',
	ignoreRemoved: '%s удален из игнорлиста.',
	ignoreList: 'Игнорируемые пользователи:',
	ignoreListEmpty: 'Игнорируемых пользователей не найдено.',
	who: 'Пользователи:',
	whoEmpty: 'На данном канале нет пользователей.',
	list: 'Доступные каналы:',
	bans: 'Забаненные пользователи:',
	bansEmpty: 'Нет забаненных пользователей.',
	unban: 'Пользователь %s разбанен.',
	whois: 'Пользователь %s - IP адрес:',
	roll: '%s кинул кубики %s. Результат %s.',
	sendPrivateMessage: 'Послать приватное сообщение %s',
	joinChannel: ' %s присоединился к каналу',
	cite: '%s сказал:',
	urlDialog: 'Пожалуйста введети адрес (URL) Web-страницы:',
	errorCookiesRequired: 'Cookies должны быть разрешены.',
	errorUserNameNotFound: 'Ошибка: Пользователь %s не найдет.',
	errorMissingText: 'Ошибка: Отсутствует текст сообщения.',
	errorMissingUserName: 'Ошибка: Отсутствует имя.',
	errorMissingChannelName: 'Ошибка: Отсутствует имя канала.',
	errorInvalidChannelName: 'Ошибка: Не верное имя канала: %s',
	errorPrivateMessageNotAllowed: 'Ошибка: Приватные сообщения не разрешены.',
	errorInviteNotAllowed: 'Ошибка: У Вас нет прав приглашать кого-либо в этот канал.',
	errorUninviteNotAllowed: 'Ошибка: У Вас нет прав отозвать приглашение из этого канала.',
	errorNoOpenQuery: 'Ошибка: Приватный канал не открыт.',
	errorKickNotAllowed: 'Ошибка: У Вас нет прав забанить %s.',
	errorCommandNotAllowed: 'Ошибка: Команда недоступна: %s',
	errorUnknownCommand: 'Ошибка: Неизвестная команда: %s',
	errorConnectionTimeout: 'Ошибка: Соединение не установлено. Пожалуйста, попробуйте еще раз.',
	errorConnectionStatus: 'Ошибка: Статус соединения: %s'
	
}