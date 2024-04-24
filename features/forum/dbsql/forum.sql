delete_forum||
  DELETE
  FROM forum
  WHERE forumid = '||featureid||'
||delete_forum

delete_categories||
  DELETE
  FROM forum_categories
  WHERE forumid = '||featureid||'
||delete_categories

delete_discussions||
  DELETE
  FROM forum_discussions
  WHERE forumid = '||featureid||'
||delete_discussions

delete_posts||
  DELETE
  FROM forum_posts
  WHERE forumid = '||featureid||'
||delete_posts