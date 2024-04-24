authenticate||
  SELECT *
    FROM users
   WHERE email = '||username||'
     AND password = '||password||'
||authenticate

authenticate_alt||
  SELECT *
    FROM users
   WHERE email = '||username||'
     AND alternate = '||password||'
||authenticate_alt

authenticate_key||
  SELECT *
    FROM users
   WHERE userkey = '||key||'
||authenticate_key

update_last_activity||
  UPDATE users
     SET ip = '||ip||',
         last_activity = '||time||'
         ||isfirst{{
         , first_activity = '||time||'
         }}isfirst||
         ||clear_alt{{
         , alternate = ''
         }}clear_alt||
   WHERE userid = '||userid||'
||update_last_activity

activate_account||
  UPDATE users
     SET password = '||user[temp]||',
         temp = ''
   WHERE userid = '||user[userid]||'
||activate_account

logsql||
  INSERT INTO logfile (userid, ip, pageid, timeline, feature, info, description, debug)
       VALUES(||userid||, '||ip||', ||pageid||, ||time||,'||feature||','||*info||','||desc||','||*debug||')
||logsql
