addable_features||
  SELECT *
  FROM features f
  WHERE f.allowed = '1'
  AND (
        (f.feature NOT IN (SELECT pf.feature
                           FROM pages_features pf
                           WHERE pf.pageid = '||pageid||'
                          )
         AND f.feature != 'addfeature'
        )
      ||issite{{
        OR f.site_multiples_allowed = 1
      //OR//
        OR f.multiples_allowed = 1
      }}issite||
      )
  ORDER BY f.feature_title
||addable_features

get_feature||
    SELECT *
    FROM pages_features
    WHERE pageid = ||pageid||
    AND feature = ||feature||
    AND featureid = ||featureid||
||get_feature

update_feature_sort||
    UPDATE pages_features
    SET sort = ||sort||, area = ||area||
    WHERE id = ||id||
||update_feature_sort

delete_feature||
    DELETE
    FROM pages_features
    WHERE feature = ||feature||
    AND pageid = ||pageid||
    AND featureid = ||featureid||
||delete_feature

delete_feature_settings||
    DELETE
    FROM settings
    WHERE type = '||feature||'
    AND pageid = '||pageid||'
    AND featureid = '||featureid||'
||delete_feature_settings

delete_page_features||
    DELETE
    FROM pages_features
    WHERE pageid = ||pageid||
||delete_page_features