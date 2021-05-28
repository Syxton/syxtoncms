addable_features||
  SELECT *
    FROM features f
   WHERE f.allowed = '1'
     AND (
            (f.feature NOT IN (SELECT pf.feature
                                 FROM pages_features pf
                                WHERE pf.pageid = '||pageid||')
          AND f.feature != 'addfeature')
    ||issite{{
      OR f.site_multiples_allowed = 1
    //OR//
      OR f.multiples_allowed = 1
    }}issite||)
ORDER BY f.feature_title
||addable_features
