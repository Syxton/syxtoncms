get_page||
    SELECT *
    FROM pages
    WHERE pageid = ||pageid||
||get_page

create_page||
    INSERT INTO pages (name, short_name, description, keywords, default_role, opendoorpolicy, siteviewable, menu_page)
    VALUES (||name||, ||short_name||, ||description||, ||keywords||, ||default_role||, ||opendoorpolicy||, ||siteviewable||, ||menu_page||)
||create_page

edit_page||
    UPDATE pages
    SET description = ||description||,
        name = ||name||,
        short_name = ||short_name||,
        keywords = ||keywords||,
        siteviewable = ||siteviewable||,
        menu_page = ||menu_page||,
        default_role = ||default_role||,
        opendoorpolicy = ||opendoorpolicy||
    WHERE pageid = ||pageid||
||edit_page

delete_page||
    DELETE
    FROM pages
    WHERE pageid = ||pageid||
||delete_page

delete_page_menus||
    DELETE
    FROM menus
    WHERE pageid = ||pageid||
||delete_page_menus

delete_page_settings||
    DELETE
    FROM settings
    WHERE pageid = ||pageid||
||delete_page_settings

add_page_menu||
    INSERT INTO menus (pageid, text, link, sort, hidefromvisitors)
    VALUES(||pageid||, ||text||, ||link||, ||sort||, ||hidefromvisitors||)
||add_page_menu

update_page_menu||
    UPDATE `menus`
    SET hidefromvisitors = ||hidefromvisitors||, link = ||pageid||, text = ||text||
    WHERE pageid = ||pageid||
||update_page_menu

user_search||
    SELECT *
    FROM `users`
    WHERE (
        ||searchstring||
    )
    ORDER BY `lname`
||user_search

user_search_part||
    (
        `fname` LIKE ||part||
        OR `lname` LIKE ||part||
        OR `email` LIKE ||part||
    )
||user_search_part

link_page_search||
    SELECT p.*, (
            SELECT pl.`linkid`
            FROM `pages_links` pl
            WHERE pl.`linkpageid` = p.pageid
            AND pl.`hostpageid` = ||pageid||
            LIMIT 1
        ) as alreadylinked
    FROM `pages` p
    WHERE p.`pageid` <> ||siteid||
    AND p.`pageid` <> ||pageid||
    AND (
        ||*searchstring||
    )
    ||*notloggedin{{
        AND p.`siteviewable` = 1
    }}notloggedin||
    ||*notadmin{{
        AND (
            p.`opendoorpolicy` = 1
            OR p.`siteviewable` = 1
        )
    }}notadmin||
    ORDER BY p.name
||link_page_search

link_page_search_part||
    (
        p.`name` LIKE ||part||
        OR p.`keywords` LIKE ||part||
        OR p.`description` LIKE ||part||
    )
||link_page_search_part

page_search||
    SELECT p.*||notadmin{{, ||checkrights||}}notadmin||
    FROM pages p
    WHERE p.pageid <> ||siteid||
    AND (
        ||searchstring||
    )
    ||viewablepages||
    ORDER BY p.name
||page_search

page_search_checkrights||
    IF (
        p.pageid IN (
            SELECT p.pageid
            FROM pages p
            INNER JOIN roles_ability ry
            ON ry.roleid = ||roleid||
            AND ry.ability = "viewpages"
            AND allow = 1
            WHERE (
                p.pageid IN (
                    SELECT ra.pageid
                    FROM roles_assignment ra
                    WHERE ra.userid = ||userid||
                    AND ra.pageid = p.pageid
                    AND ra.confirm = 0
                )
                OR p.pageid IN (
                    SELECT rau.pageid
                    FROM roles_ability_peruser rau
                    WHERE rau.userid = ||userid||
                    AND rau.ability = "viewpages"
                    AND allow = 1
                )
            )
            AND p.pageid NOT IN (
                SELECT rau.pageid
                FROM roles_ability_peruser rau
                WHERE rau.userid = ||userid||
                AND rau.ability = "viewpages"
                AND allow = 0
            )
            AND p.pageid <> ||siteid||
            AND p.menu_page <> 1
        ), 1, 0
    ) as added
||page_search_checkrights

my_pagelist||
    SELECT p.*
    FROM pages p
    INNER JOIN roles_ability ry
    ON ry.roleid = ||roleid||
    AND ry.ability = "viewpages"
    AND allow = 1
    WHERE (
        p.pageid IN (
            SELECT ra.pageid
            FROM roles_assignment ra
            WHERE ra.userid = ||userid||
            AND ra.pageid = p.pageid
            AND confirm = 0
        )
        OR
        p.pageid IN (
            SELECT rau.pageid
            FROM roles_ability_peruser rau
            WHERE rau.userid = ||userid||
            AND rau.ability = "viewpages"
            AND allow = 1
        )
    )
    AND p.pageid NOT IN (
        SELECT rau.pageid
        FROM roles_ability_peruser rau
        WHERE rau.userid = ||userid||
        AND rau.ability = "viewpages"
        AND allow = 0
    )
    AND p.pageid <> ||siteid||
    AND p.menu_page <> 1
    ORDER BY p.name
||my_pagelist

admin_pagelinks||
    SELECT *
    FROM pages_links
    WHERE hostpageid = ||pageid||
    AND linkpageid <> ||siteid||
    AND linkpageid <> ||pageid||
    ORDER BY sort
||admin_pagelinks

user_pagelinks||
    SELECT pl.*
    FROM pages_links pl
    WHERE (
        pl.hostpageid = ||pageid||
        AND pl.linkpageid <> ||siteid||
        AND pl.linkpageid <> ||pageid||
    )
    AND (
        (
            pl.linkpageid IN (
                SELECT ras.pageid
                FROM roles_assignment ras
                WHERE ras.userid = ||userid||
                AND ras.confirm = 0
                AND ras.roleid IN (
                    SELECT ra.roleid
                    FROM roles_ability ra
                    WHERE ra.ability = "viewpages"
                    AND ra.allow = 1
                )
            )
            AND pl.linkpageid NOT IN (
                SELECT rap.pageid
                FROM roles_ability_perpage rap
                WHERE rap.pageid = pl.linkpageid
                AND rap.allow = 0
                AND rap.roleid IN (
                    SELECT ras.roleid
                    FROM roles_assignment ras
                    WHERE ras.userid = ||userid||
                    AND ras.pageid = pl.linkpageid
                    AND ras.confirm = 0
                )
            )
            AND pl.linkpageid NOT IN (
                SELECT rau.pageid
                FROM roles_ability_peruser rau
                WHERE rau.userid = ||userid||
                AND rau.ability = "viewpages"
                AND rau.allow = 0
            )
        )
        OR
        (
            pl.linkpageid IN (
                SELECT p.pageid
                FROM pages p
                WHERE p.siteviewable = 1
                OR p.opendoorpolicy = 1
            )
            AND pl.linkpageid NOT IN (
                SELECT rap.pageid
                FROM roles_ability_perpage rap
                WHERE rap.pageid = pl.linkpageid
                AND rap.allow = 0
                AND rap.roleid IN (
                    SELECT ras.roleid
                    FROM roles_assignment ras
                    WHERE ras.userid = ||userid||
                    AND ras.pageid = rap.pageid
                    AND ras.confirm = 0
                )
            )
            AND pl.linkpageid NOT IN (
                SELECT rau.pageid
                FROM roles_ability_peruser rau
                WHERE rau.userid = ||userid||
                AND rau.ability = "viewpages"
                AND rau.allow = 0
            )
        )
        OR pl.linkpageid IN (
            SELECT rau.pageid
            FROM roles_ability_peruser rau
            WHERE rau.userid = ||userid||
            AND rau.ability = "viewpages"
            AND rau.allow = 1
        )
    )
    ORDER BY pl.sort
||user_pagelinks

default_pagelinks||
    SELECT pl.*
    FROM `pages_links` pl
    WHERE pl.linkpageid IN (
        SELECT p.pageid
        FROM pages p
        WHERE p.pageid = pl.linkpageid
        AND siteviewable = 1
    )
    AND pl.hostpageid = ||pageid||
    AND pl.linkpageid <> ||siteid||
    AND pl.linkpageid <> ||pageid||
    ORDER BY pl.sort
||default_pagelinks

get_pagelinks||
    SELECT *
    FROM `pages_links`
    WHERE hostpageid = ||pageid||
    ORDER BY sort
||get_pagelinks

get_pagelink||
    SELECT *
    FROM `pages_links`
    WHERE linkid = ||linkid||
||get_pagelink

get_pagelink_in_position||
    SELECT *
    FROM `pages_links`
    WHERE hostpageid = ||hostpageid||
    AND sort = ||sort||
||get_pagelink_in_position

update_pagelink_sort||
    UPDATE `pages_links`
    SET sort = ||sort||
    WHERE linkid = ||linkid||
||update_pagelink_sort

update_pagelink_name||
    UPDATE `pages_links`
    SET linkdisplay = ||linkdisplay||
    WHERE linkid = ||linkid||
||update_pagelink_name

update_pagelink_sort_and_name||
    UPDATE `pages_links`
    SET sort = ||sort||, linkdisplay = ||linkdisplay||
    WHERE linkid = ||linkid||
||update_pagelink_sort_and_name

insert_pagelink||
    INSERT INTO `pages_links` (hostpageid, linkpageid, sort, linkdisplay)
    VALUES (||pageid||, ||linkpageid||, ||sort||, ||linkdisplay||)
||insert_pagelink

delete_pagelink||
    DELETE FROM `pages_links`
    WHERE hostpageid = ||pageid||
    AND linkpageid = ||linkpageid||
||delete_pagelink

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
    WHERE parent = ||menuid||
    ORDER BY sort
||get_menu_children
