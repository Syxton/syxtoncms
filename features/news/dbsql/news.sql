delete_news_features||
  DELETE
  FROM news_features
  WHERE pageid = ||pageid||
  AND featureid = ||featureid||
||delete_news_features

delete_all_news_items||
  DELETE
  FROM news
  WHERE pageid = ||pageid||
  AND featureid = ||featureid||
||delete_all_news_items

delete_news_item||
  DELETE
  FROM news
  WHERE newsid = ||newsid||
||delete_news_item

insert_news_feature||
    INSERT INTO news_features (pageid, lastupdate)
    VALUES(||pageid||, ||lastupdate||)
||insert_news_feature