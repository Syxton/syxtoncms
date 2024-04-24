delete_galleries||
  DELETE
  FROM pics_galleries
  WHERE pageid = '||pageid||'
  AND pollid = '||featureid||'
||delete_galleries

delete_pics_features||
  DELETE
  FROM pics_features
  WHERE pageid = '||pageid||'
  AND pollid = '||featureid||'
||delete_pics_features

delete_pics||
  DELETE
  FROM pics
  WHERE pageid = '||pageid||'
  AND pollid = '||featureid||'
||delete_pics