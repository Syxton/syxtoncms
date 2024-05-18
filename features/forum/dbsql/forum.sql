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

delete_category||
	DELETE FROM forum_categories
	WHERE catid = ||catid||
||delete_category

delete_category_discussions||
	DELETE
	FROM forum_discussions
	WHERE catid = '||catid||'
||delete_category_discussions

delete_category_posts||
	DELETE
	FROM forum_posts
	WHERE catid = '||catid||'
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

delete_discussion_posts||
	DELETE
	FROM forum_posts
	WHERE discussionid = ||discussionid||
||delete_discussion_posts

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

update_discussion_title||
	UPDATE forum_discussions
	SET title = ||title||
	WHERE discussionid = ||discussionid||
||update_discussion_title

insert_discussion||
	INSERT INTO forum_discussions 
	(catid, forumid, pageid, userid, title, lastpost)
	VALUES(||catid||, ||forumid||, ||pageid||, ||userid||, ||title||, ||lastpost||)
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