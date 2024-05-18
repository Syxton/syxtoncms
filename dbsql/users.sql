get_user||
	SELECT *
	FROM users
	WHERE userid = ||userid||
||get_user

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

create_user||
	INSERT INTO users (email, fname, lname, temp, password, userkey, joined)
		VALUES(||email||, ||fname||, ||lname||, ||temp||, ||password||, ||userkey||, ||time||)
||create_user