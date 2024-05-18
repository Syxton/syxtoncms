get_page||
	SELECT *
	FROM pages
	WHERE pageid = ||pageid||
||get_page

create_page||
	INSERT INTO pages (name, short_name, description, keywords, default_role, opendoorpolicy, siteviewable, menu_page)
		VALUES(||name||, ||short_name||, ||description||, ||keywords||, ||default_role||, ||opendoorpolicy||, ||siteviewable||, ||menu_page||)
||create_page

edit_page||
	UPDATE pages
	SET description = ||description||,
		name = ||name||,
		short_name = ||shortname||,
		keywords = ||keywords||,
		siteviewable = ||siteviewable||,
		menu_page = ||menu_page||,
		default_role = ||defaultrole||,
		opendoorpolicy = ||opendoorpolicy||
	WHERE pageid = ||pageid||
||edit_page

delete_page||
	DELETE
	FROM `pages`
	WHERE pageid = ||pageid||
||delete_page

delete_page_menus||
	DELETE
	FROM `menus`
	WHERE pageid = ||pageid||
||delete_page_menus

delete_page_settings||
	DELETE
	FROM `settings`
	WHERE pageid = ||pageid||
||delete_page_settings

add_page_menu||
  INSERT INTO menus (pageid, text, link, sort, hidefromvisitors)
		VALUES(||pageid||, ||text||, ||link||, ||sort||, ||hidefromvisitors||)
||add_page_menu

update_page_menu||
	UPDATE menus
	SET hidefromvisitors = ||hidefromvisitors||, link = ||pageid||, text = ||text||
	WHERE pageid = ||pageid||
||update_page_menu

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

get_page_menu||
	SELECT *
	FROM menus
	WHERE pageid = ||pageid||
||get_page_menu

get_menu_for_users||
	SELECT *
	FROM menus
	WHERE parent IS NULL
	ORDER BY sort
||get_menu_for_users

get_menu_for_visitors||
	SELECT *
	FROM menus
	WHERE hidefromvisitors = 0
	AND parent IS NULL
	ORDER BY sort
||get_menu_for_visitors

get_menu_children||
	SELECT *
	FROM menus
	WHERE parent = '||menuid||'
	ORDER BY sort
||get_menu_children
