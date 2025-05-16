update_rss_name||
	UPDATE rss
	SET rssname = ||rssname||
	WHERE rssid = ||rssid||
||update_rss_name

create_rss||
	INSERT INTO rss (userid, rssname)
	VALUES (||userid||, ||rssname||)
||create_rss

create_feed||
	INSERT INTO rss_feeds (rssid, type, featureid, pageid)
	VALUES (||rssid||, ||type||, ||featureid||, ||pageid||)
||create_feed