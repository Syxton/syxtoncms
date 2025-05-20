get_bloglocker||
    SELECT *
    FROM pages_features pf
    ||*getblogs{{
        INNER JOIN html h ON h.htmlid = pf.featureid
    }}getblogs||
    WHERE pf.pageid = ||pageid||
    AND pf.feature = 'html'
    AND pf.area = 'locker'
    ||*getblogs{{
        ORDER BY h.dateposted DESC
    }}getblogs||
||get_bloglocker