get_user||
	SELECT *
	FROM users
	WHERE userid = ||userid||
||get_user

get_user_by_email||
	SELECT *
	FROM users
	WHERE email = ||email||
||get_user_by_email

get_active_user||
	SELECT *
	FROM users
	WHERE userid = ||userid||
	||*recentlyactive||
||get_active_user

used_email||
	SELECT *
	FROM users
	WHERE email = ||email||
	AND userid <> ||userid||
||used_email

delete_user||
	DELETE
	FROM users
	WHERE userid = ||userid||
||delete_user

delete_user_logs||
	DELETE
	FROM logfile
	WHERE userid = ||userid||
||delete_user_logs

update_password||
	UPDATE users
	SET alternate = '', password = ||password||
	WHERE userid = ||userid||
||update_password

create_user||
	INSERT INTO users (email, fname, lname, temp, password, userkey, joined)
	VALUES(||email||, ||fname||, ||lname||, ||temp||, ||password||, ||userkey||, ||time||)
||create_user

lookup_user_rss||
	SELECT *
	FROM rss_feeds
	WHERE pageid = ||pageid||
	AND type = ||type||
	AND featureid = ||featureid||
	AND rssid IN (
						SELECT rssid
						FROM rss
						WHERE userid = ||userid||
	)
||lookup_user_rss

get_rss||
	SELECT *
	FROM rss
	WHERE rssid = ||rssid||
||get_rss