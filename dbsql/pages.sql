my_pagelist||
    SELECT p.*
      FROM pages p
INNER JOIN roles_ability ry ON ry.roleid = '||roleid||'
                           AND ry.ability = 'viewpages'
                           AND allow = '1'
     WHERE (p.pageid IN (SELECT ra.pageid
                           FROM roles_assignment ra
                          WHERE ra.userid = '||userid||'
                            AND ra.pageid = p.pageid
                            AND confirm = 0)
            OR
            p.pageid IN (SELECT rau.pageid
                           FROM roles_ability_peruser rau
                          WHERE rau.userid = '||userid||'
                            AND rau.ability = 'viewpages'
                            AND allow = '1'))
       AND p.pageid NOT IN (SELECT rau.pageid
                              FROM roles_ability_peruser rau
                             WHERE rau.userid = '||userid||'
                               AND rau.ability = 'viewpages'
                               AND allow = '0')
       AND p.pageid != ||siteid||
       AND p.menu_page != '1'
  ORDER BY p.name
||my_pagelist

admin_pagelinks||
    SELECT pl.*
      FROM pages_links pl
     WHERE pl.hostpageid = ||pageid||
       AND pl.linkpageid != ||siteid||
       AND pl.linkpageid != ||pageid||
  ORDER BY pl.sort
||admin_pagelinks

user_pagelinks||
    SELECT pl.*
      FROM pages_links pl
     WHERE (
                pl.hostpageid = ||pageid||
            AND pl.linkpageid != ||siteid||
            AND pl.linkpageid != ||pageid||
           )
       AND (
               (
                  pl.linkpageid IN (SELECT ras.pageid
                                      FROM roles_assignment ras
                                     WHERE ras.userid = '||userid||'
                                       AND ras.confirm = '0'
                                       AND ras.roleid IN (SELECT ra.roleid
                                                            FROM roles_ability ra
                                                           WHERE ra.ability = 'viewpages'
                                                             AND ra.allow = '1'))
              AND pl.linkpageid NOT IN (SELECT rap.pageid
                                          FROM roles_ability_perpage rap
                                         WHERE rap.pageid = pl.linkpageid
                                           AND rap.allow = 0
                                           AND rap.roleid IN (SELECT ras.roleid
                                                                FROM roles_assignment ras
                                                               WHERE ras.userid = '||userid||'
                                                                 AND ras.pageid = pl.linkpageid
                                                                 AND ras.confirm = 0))
              AND pl.linkpageid NOT IN (SELECT rau.pageid
                                          FROM roles_ability_peruser rau
                                         WHERE rau.userid = '||userid||'
                                           AND rau.ability = 'viewpages'
                                           AND rau.allow = '0')
              )
              OR
              (
                   pl.linkpageid IN (SELECT p.pageid
                                       FROM pages p
                                      WHERE (p.siteviewable = 1
                                         OR p.opendoorpolicy = 1))
               AND pl.linkpageid NOT IN (SELECT rap.pageid
                                           FROM roles_ability_perpage rap
                                          WHERE rap.pageid = pl.linkpageid
                                            AND rap.allow = 0
                                            AND rap.roleid IN (SELECT ras.roleid
                                                                 FROM roles_assignment ras
                                                                WHERE ras.userid = '||userid||'
                                                                  AND ras.pageid = rap.pageid
                                                                  AND ras.confirm = 0))
               AND pl.linkpageid NOT IN (SELECT rau.pageid
                                           FROM roles_ability_peruser rau
                                          WHERE rau.userid = '||userid||'
                                            AND rau.ability = 'viewpages'
                                            AND rau.allow = '0')
              )
              OR pl.linkpageid IN (SELECT rau.pageid
                                     FROM roles_ability_peruser rau
                                    WHERE rau.userid = '||userid||'
                                      AND rau.ability = 'viewpages'
                                      AND rau.allow = '1')
           )
  ORDER BY pl.sort
||user_pagelinks

default_pagelinks||
    SELECT pl.*
      FROM pages_links pl
     WHERE pl.linkpageid IN (SELECT p.pageid
                               FROM pages p
                              WHERE p.pageid = pl.linkpageid
                                AND siteviewable = 1)
       AND pl.hostpageid = ||pageid||
       AND pl.linkpageid != ||siteid||
       AND pl.linkpageid != ||pageid||
    ORDER BY pl.sort
||default_pagelinks
