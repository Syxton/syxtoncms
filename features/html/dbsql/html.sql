delete_html||
  DELETE
  FROM html
  WHERE pageid = '||pageid||'
  AND htmlid = '||featureid||'
||delete_html

get_comment_info||
    SELECT c.*, h.pageid
    FROM html_comments c
    JOIN html h ON h.htmlid = c.htmlid
    WHERE c.commentid = '||commentid||'
||get_comment_info