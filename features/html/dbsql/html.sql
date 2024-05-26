insert_html||
    INSERT INTO html (pageid, html, dateposted)
    VALUES(||pageid||, ||html||, ||dateposted||)
||insert_html

delete_html||
  DELETE
  FROM html
  WHERE pageid = ||pageid||
  AND htmlid = ||featureid||
||delete_html

get_comment_info||
    SELECT c.*, h.pageid
    FROM html_comments c
    JOIN html h ON h.htmlid = c.htmlid
    WHERE c.commentid = ||commentid||
||get_comment_info

update_comment||
    UPDATE html_comments
    SET comment = ||comment||, modified = ||modified||
    WHERE commentid = ||commentid||
||update_comment

delete_comment||
    DELETE FROM html_comments
    WHERE commentid = ||commentid||
||delete_comment

insert_reply||
    INSERT INTO html_comments (parentid, comment, userid, htmlid, created, modified)
        VALUES (||parentid||, ||comment||, ||userid||, ||htmlid||, ||created||, ||modified||)
||insert_reply

insert_comment||
    INSERT INTO html_comments (comment, userid, htmlid, created, modified)
        VALUES (||comment||, ||userid||, ||htmlid||, ||created||, ||modified||)
||insert_comment

html_edit_time||
    UPDATE html
    SET edit_user = ||userid||, edit_time = ||edit_time||
    WHERE htmlid = ||htmlid||
||html_edit_time

html_edit||
    UPDATE html
    SET html = ||html||, dateposted = ||dateposted||, edit_user = ||edit_user||, edit_time = ||edit_time||
    WHERE htmlid = ||htmlid||
||html_edit

html_set_firstedition||
    UPDATE html
    SET firstedition = ||firstedition||
    WHERE htmlid = ||htmlid||
||html_set_firstedition