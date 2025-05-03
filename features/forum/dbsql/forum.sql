insert_forum||
    INSERT INTO forum
    (pageid)
    VALUES(||pageid||)
||insert_forum

delete_forum||
    DELETE
    FROM forum
    WHERE forumid = ||forumid||
||delete_forum

delete_categories||
    DELETE
    FROM forum_categories
    WHERE forumid = ||forumid||
||delete_categories

get_forum_categories||
    SELECT *
    FROM forum_categories
    WHERE forumid = ||forumid||
    AND shoutbox = 0
    ORDER BY sort
||get_forum_categories

get_shoutbox||
    SELECT *
    FROM forum_discussions
    WHERE forumid = ||forumid||
    AND shoutbox = 1
||get_shoutbox

set_category_sort||
    UPDATE forum_categories
    SET sort = ||sort||
    WHERE catid = ||catid||
||set_category_sort

delete_category||
    DELETE FROM forum_categories
    WHERE catid = ||catid||
||delete_category

insert_shoutbox||
    INSERT INTO forum_categories
    (forumid, pageid, title, shoutbox)
    VALUES(||forumid||, ||pageid||, 'Shoutbox', 1)
||insert_shoutbox

insert_category||
    INSERT INTO forum_categories
    (forumid, pageid, title, sort, shoutbox)
    VALUES(||forumid||, ||pageid||, ||title||, ||sort||, ||shoutbox||)
||insert_category

update_category||
    UPDATE forum_categories
    SET title = ||title||
    WHERE catid = ||catid||
||update_category

get_category||
    SELECT *
    FROM forum_categories
    WHERE catid = ||catid||
||get_category

get_category_discussions||
    SELECT *
    FROM forum_discussions
    WHERE catid = ||catid||
    AND shoutbox = 0
    AND bulletin = ||bulletin||
    ORDER BY lastpost DESC
||get_category_discussions

delete_category_discussions||
    DELETE
    FROM forum_discussions
    WHERE catid = ||catid||
||delete_category_discussions

delete_category_posts||
    DELETE
    FROM forum_posts
    WHERE catid = ||catid||
||delete_category_posts

delete_discussions||
    DELETE
    FROM forum_discussions
    WHERE forumid = ||forumid||
||delete_discussions

delete_discussion||
    DELETE
    FROM forum_discussions
    WHERE discussionid = ||discussionid||
||delete_discussion

get_discussion_posts||
    SELECT *
    FROM forum_posts
    WHERE discussionid = ||discussionid||
||get_discussion_posts

delete_discussion_posts||
    DELETE
    FROM forum_posts
    WHERE discussionid = ||discussionid||
||delete_discussion_posts

get_discussion||
    SELECT *
    FROM forum_discussions
    WHERE discussionid = ||discussionid||
||get_discussion

pin_discussion||
    UPDATE forum_discussions
    SET bulletin = 1
    WHERE discussionid = ||discussionid||
||pin_discussion

unpin_discussion||
    UPDATE forum_discussions
    SET bulletin = 0
    WHERE discussionid = ||discussionid||
||unpin_discussion

lock_discussion||
    UPDATE forum_discussions
    SET locked = 1
    WHERE discussionid = ||discussionid||
||lock_discussion

unlock_discussion||
    UPDATE forum_discussions
    SET locked = 0
    WHERE discussionid = ||discussionid||
||unlock_discussion

update_discussion_lastpost||
    UPDATE forum_discussions
    SET lastpost = ||lastpost||
    WHERE discussionid = ||discussionid||
||update_discussion_lastpost

update_discussion_views||
    UPDATE forum_discussions
    SET views = views + 1
    WHERE discussionid = ||discussionid||
||update_discussion_views

update_discussion_title||
    UPDATE forum_discussions
    SET title = ||title||
    WHERE discussionid = ||discussionid||
||update_discussion_title

insert_discussion||
    INSERT INTO forum_discussions
    (catid, forumid, pageid, userid, title, shoutbox, lastpost)
    VALUES(||catid||, ||forumid||, ||pageid||, ||userid||, ||title||, ||shoutbox||, ||lastpost||)
||insert_discussion

delete_posts||
    DELETE
    FROM forum_posts
    WHERE forumid = ||forumid||
||delete_posts

delete_post||
    DELETE
    FROM forum_posts
    WHERE postid = ||postid||
||delete_post

update_post||
    UPDATE forum_posts
    SET message = ||message||, edited = ||edited||, editedby = ||editedby||
    WHERE postid = ||postid||
||update_post

insert_post||
    INSERT INTO forum_posts
    (discussionid, catid, forumid, pageid, userid, message, posted, alias)
    VALUES(||discussionid||, ||catid||, ||forumid||, ||pageid||, ||userid||, ||message||, ||posted||, ||alias||)
||insert_post

get_post||
    SELECT *
    FROM forum_posts
    WHERE postid = ||postid||
||get_post

get_new_posts_for_user||
    SELECT *
    FROM forum_posts f
    WHERE f.catid = ||catid||
    AND (
        discussionid IN (
            SELECT a.discussionid
            FROM forum_discussions a
            INNER JOIN forum_views b ON a.discussionid = b.discussionid
            WHERE b.userid = ||userid||
            AND a.lastpost > b.lastviewed
        )
        OR discussionid NOT IN (
            SELECT discussionid
            FROM forum_views
            WHERE catid = ||catid||
            AND userid = ||userid||
        )
    )
||get_new_posts_for_user

get_forum_views||
    SELECT *
    FROM forum_views
    WHERE userid=||userid||
    AND discussionid=||discussionid||
||get_forum_views

update_forum_views||
    UPDATE forum_views
    SET lastviewed = ||lastviewed||
    WHERE userid = ||userid||
    AND discussionid = ||discussionid||
||update_forum_views

insert_forum_views||
    INSERT INTO forum_views (userid, catid, discussionid, lastviewed)
    VALUES (||userid||, ||catid||, ||discussionid||, ||lastviewed||)
||insert_forum_views