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

get_galleries||
  SELECT DISTINCT galleryid, galleryid, gallery_title
  FROM pics p
  WHERE (
        p.pageid='||pageid||'
        AND
        p.featureid='||featureid||'
        )
  ||siteviewable{{
    OR (p.siteviewable = 1)
  }}siteviewable||
  ORDER BY p.galleryid
||get_galleries

get_page_galleries||
  SELECT *
  FROM pics_galleries
  WHERE galleryid IN  (
                      SELECT galleryid
                      FROM pics
                      WHERE pageid='||pageid||'
                      )
||get_page_galleries

insert_pic||
    INSERT INTO pics (pageid, featureid, galleryid, gallery_title, imagename, siteviewable, caption, alttext, dateadded)
    VALUES(||pageid||, ||featureid||, ||galleryid||, ||gallery_title||, ||imagename||, ||siteviewable||, ||caption||, ||alttext||, ||dateadded||)
||insert_pic

insert_gallery||
    INSERT INTO pics_galleries (pageid, featureid, name)
    VALUES(||pageid||, ||featureid||, ||gallery_name||)
||insert_gallery
