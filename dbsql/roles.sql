is_siteadmin||
    SELECT *
    FROM `roles_assignment`
    WHERE roleid = ||adminroleid||
    AND userid = ||userid||
    AND pageid = ||siteid||
    AND confirm = 0
||is_siteadmin

get_roles||
    SELECT *
    FROM `roles`
    ORDER BY roleid
||get_roles

get_ability||
    SELECT ability
    FROM `abilities`
    WHERE section = ||section||
    AND ability = ||ability||
||get_ability

get_page_role_requests||
    SELECT *
    FROM `roles_assignment`
    WHERE pageid = ||pageid||
    AND confirm = 1
||get_page_role_requests

get_user_role_requests||
    SELECT *
    FROM `roles_assignment`
    WHERE userid = ||userid||
    AND confirm = 2
||get_user_role_requests

get_lower_roles||
    SELECT *
    FROM `roles`
    WHERE roleid > ||roleid||
    ORDER BY roleid
||get_lower_roles

confirm_role_assignment||
    UPDATE `roles_assignment`
    SET confirm = 0
    WHERE assignmentid = ||assignmentid||
||confirm_role_assignment

remove_role_assignment||
    DELETE FROM `roles_assignment`
    WHERE assignmentid = ||assignmentid||
||remove_role_assignment

remove_page_role_assignments||
    DELETE FROM `roles_assignment`
    WHERE pageid = ||pageid||
||remove_page_role_assignments

remove_page_roles_ability_perfeature||
    DELETE FROM `roles_ability_perfeature`
    WHERE pageid = ||pageid||
||remove_page_roles_ability_perfeature

remove_group_roles_ability_perfeature_pergroup||
    DELETE FROM `roles_ability_perfeature_pergroup`
    WHERE pageid = ||pageid||
    AND groupid = ||groupid||
||remove_group_roles_ability_perfeature_pergroup

remove_page_roles_ability_perfeature_pergroup||
    DELETE FROM `roles_ability_perfeature_pergroup`
    WHERE pageid = ||pageid||
||remove_page_roles_ability_perfeature_pergroup

remove_roles_ability_perfeature_pergroup_override||
    DELETE FROM `roles_ability_perfeature_pergroup`
    WHERE pageid = ||pageid||
    AND groupid = ||groupid||
    AND feature = ||feature||
    AND featureid = ||featureid||
    AND ability = ||ability||
||remove_roles_ability_perfeature_pergroup_override

remove_page_roles_ability_perfeature_peruser||
    DELETE FROM `roles_ability_perfeature_peruser`
    WHERE pageid = ||pageid||
||remove_page_roles_ability_perfeature_peruser

remove_roles_ability_perfeature_peruser_override||
    DELETE FROM `roles_ability_perfeature_peruser`
    WHERE pageid = ||pageid||
    AND userid = ||userid||
    AND feature = ||feature||
    AND featureid = ||featureid||
    AND ability = ||ability||
||remove_roles_ability_perfeature_peruser_override

update_roles_ability_perfeature_peruser_override||
    UPDATE `roles_ability_perfeature_peruser`
    SET allow = ||allow||
    WHERE pageid = ||pageid||
    AND userid = ||userid||
    AND feature = ||feature||
    AND featureid = ||featureid||
    AND ability = ||ability||
||update_roles_ability_perfeature_peruser_override

remove_group_roles_ability_pergroup||
    DELETE FROM `roles_ability_pergroup`
    WHERE pageid = ||pageid||
    AND groupid = ||groupid||
||remove_group_roles_ability_pergroup

remove_page_roles_ability_pergroup||
    DELETE FROM `roles_ability_pergroup`
    WHERE pageid = ||pageid||
||remove_page_roles_ability_pergroup

remove_roles_ability_pergroup_override||
    DELETE FROM `roles_ability_pergroup`
    WHERE pageid = ||pageid||
    AND groupid = ||groupid||
    AND ability = ||ability||
||remove_roles_ability_pergroup_override

remove_page_roles_ability_perpage||
    DELETE FROM `roles_ability_perpage`
    WHERE pageid = ||pageid||
||remove_page_roles_ability_perpage

insert_roles_ability_peruser_override||
    INSERT INTO `roles_ability_peruser` (pageid, userid, ability, allow)
    VALUES (||pageid||, ||userid||, ||ability||, ||allow||)
||insert_roles_ability_peruser_override

remove_page_roles_ability_peruser||
    DELETE FROM `roles_ability_peruser`
    WHERE pageid = ||pageid||;
||remove_page_roles_ability_peruser

remove_roles_ability_peruser_override||
    DELETE FROM `roles_ability_peruser`
    WHERE pageid = ||pageid||
    AND userid = ||userid||
    AND ability = ||ability||
||remove_roles_ability_peruser_override

update_roles_ability_peruser_override||
    UPDATE `roles_ability_peruser`
    SET allow = ||allow||
    WHERE pageid = ||pageid||
    AND userid = ||userid||
    AND ability = ||ability||
||update_roles_ability_peruser_override

remove_user_roles_assignment||
    DELETE FROM `roles_assignment`
    WHERE userid = ||userid||
||remove_user_roles_assignment

remove_user_roles_ability_peruser||
    DELETE FROM `roles_ability_peruser`
    WHERE userid = ||userid||
||remove_user_roles_ability_peruser

remove_user_roles_ability_perfeature_peruser||
    DELETE FROM `roles_ability_perfeature_peruser`
    WHERE userid = ||userid||
||remove_user_roles_ability_perfeature_peruser

remove_user_role_assignment||
    DELETE FROM `roles_assignment`
    WHERE pageid = ||pageid||
    AND userid = ||userid||
||remove_user_role_assignment

insert_role_assignment||
    INSERT INTO `roles_assignment` (userid, roleid, pageid, confirm)
    VALUES (||userid||, ||roleid||, ||pageid||, ||confirm||)
||insert_role_assignment

remove_role_assignment_request||
    DELETE FROM `roles_assignment`
    WHERE pageid = ||pageid||
    AND userid = ||userid||
    AND confirm = ||confirm||
||remove_role_assignment_request

get_role_assignment||
    SELECT *
    FROM `roles_assignment`
    WHERE pageid = ||pageid||
    AND userid = ||userid||
    AND confirm = ||confirm||
||get_role_assignment

insert_abilities||
    INSERT INTO `abilities` (section, section_display, ability, ability_display, power)
    VALUES (||section||, ||section_display||, ||ability||, ||ability_display||, ||power||)
||insert_abilities

insert_roles_ability||
    INSERT INTO `roles_ability` (roleid, ability, allow, section)
    VALUES  (1, ||ability||, 1, ||section||),
            (2, ||ability||, ||creator||, ||section||),
            (3, ||ability||, ||editor||, ||section||),
            (4, ||ability||, ||guest||, ||section||),
            (5, ||ability||, ||visitor||, ||section||),
            (6, ||ability||, 0, ||section||)
||insert_roles_ability


//	This is a fully implemented roles structure for the system.  The following is the importance structure
//
//	Feature specific per individual user per page
//		|
//		Indivual user specific per page
//			|
//			Feature specific per group per page
//				|
//				Group specific per page
//					|
//					Feature specific per role per page
//						|
//						Role specific per page
//							|
//							Role specific per SITE LEVEL permissions
user_has_ability_in_page||
    SELECT 1 as allowed
    FROM `roles_ability` ra
    WHERE (
        1 IN (
            SELECT allow
            FROM `roles_ability_perfeature_peruser`
            WHERE pageid = ||pageid||
            AND userid = ||userid||
            AND ability = ||ability||
            AND feature = ||feature||
            AND featureid = ||featureid||
            AND allow = 1
        )
        OR (
            1 IN (
                SELECT allow
                FROM `roles_ability_peruser`
                WHERE pageid = ||pageid||
                AND userid = ||userid||
                AND ability = ||ability||
                AND allow = 1
            )
            OR
            ||featuregroupsql[0]|| ||groupsql[0]||
            (
                1 IN (
                    SELECT allow
                    FROM `roles_ability_perfeature`
                    WHERE pageid = ||pageid||
                    AND roleid = ||roleid||
                    AND ability = ||ability||
                    AND feature = ||feature||
                    AND featureid = ||featureid||
                    AND allow = 1
                )
                OR (
                    1 IN (
                        SELECT allow
                        FROM `roles_ability_perpage`
                        WHERE pageid = ||pageid||
                        AND roleid = ||roleid||
                        AND ability = ||ability||
                        AND allow = 1
                    )
                    OR
                    1 IN (
                        SELECT allow
                        FROM `roles_ability`
                        WHERE roleid = ||roleid||
                        AND ability = ||ability||
                        AND allow = 1
                    )
                    AND
                    0 NOT IN (
                        SELECT allow
                        FROM `roles_ability_perpage`
                        WHERE pageid = ||pageid||
                        AND roleid = ||roleid||
                        AND ability = ||ability||
                        AND allow = 0
                    )
                )
                AND
                0 NOT IN (
                    SELECT allow
                    FROM `roles_ability_perfeature`
                    WHERE pageid = ||pageid||
                    AND roleid = ||roleid||
                    AND ability = ||ability||
                    AND feature = ||feature||
                    AND featureid = ||featureid||
                    AND allow = 0
                )
                ||featuregroupsql[1]|| ||groupsql[1]||
            )
            AND 0 NOT IN (
                SELECT allow
                FROM `roles_ability_peruser`
                WHERE pageid = ||pageid||
                AND userid = ||userid||
                AND ability = ||ability||
                AND allow = 0
            )
        )
        AND 0 NOT IN (
            SELECT allow
            FROM `roles_ability_perfeature_peruser`
            WHERE pageid = ||pageid||
            AND userid = ||userid||
            AND ability = ||ability||
            AND feature = ||feature||
            AND featureid = ||featureid||
            AND allow = 0
        )
    )
    LIMIT 1
||user_has_ability_in_page

//	This is a fully implemented roles structure for the system.  The following is the importance structure
//
//	Feature specific per individual user per page
//		|
//		Indivual user specific per page
//			|
//			Feature specific per group per page
//				|
//				Group specific per page
//					|
//					Feature specific per role per page
//						|
//						Role specific per page
//							|
//							Role specific per SITE LEVEL permissions
get_user_abilities||
    SELECT a.ability, (
        SELECT 1 as allowed
        FROM roles_ability ra
        WHERE (
            1 IN (
                SELECT allow
                FROM roles_ability_perfeature_peruser
                WHERE pageid = ||pageid||
                AND userid = ||userid||
                AND feature = ||feature||
                AND featureid = ||featureid||
                AND ability = a.ability
                AND allow = 1
            )
            OR (
                1 IN (
                    SELECT allow
                    FROM roles_ability_peruser
                    WHERE userid = ||userid||
                    AND pageid = ||pageid||
                    AND ability = a.ability
                    AND allow = 1
                )
                OR
                ||featuregroupsql[0]|| ||groupsql[0]||
                (
                    1 IN (
                        SELECT allow
                        FROM roles_ability_perfeature
                        WHERE pageid = ||pageid||
                        AND roleid = ||roleid||
                        AND feature = ||feature||
                        AND featureid = ||featureid||
                        AND ability = a.ability
                        AND allow = 1
                    )
                    OR (
                        1 IN (
                            SELECT allow
                            FROM roles_ability_perpage
                            WHERE roleid = ||roleid||
                            AND pageid = ||pageid||
                            AND ability = a.ability
                            AND allow = 1
                        )
                        OR (
                            1 IN (
                                SELECT allow
                                FROM roles_ability
                                WHERE roleid = ||roleid||
                                AND ability = a.ability
                                AND allow = 1
                            )
                        )
                        AND 0 NOT IN (
                            SELECT allow
                            FROM roles_ability_perpage
                            WHERE roleid = ||roleid||
                            AND pageid = ||pageid||
                            AND ability = a.ability
                            AND allow = 0
                        )
                    )
                    AND 0 NOT IN (
                        SELECT allow
                        FROM roles_ability_perfeature
                        WHERE pageid = ||pageid||
                        AND roleid = ||roleid||
                        AND feature = ||feature||
                        AND featureid = ||featureid||
                        AND ability = a.ability
                        AND allow = 0
                    )
                    ||groupsql[1]|| ||featuregroupsql[1]||
                )
                AND 0 NOT IN (
                    SELECT allow
                    FROM roles_ability_peruser
                    WHERE userid = ||userid||
                    AND pageid = ||pageid||
                    AND ability = a.ability
                    AND allow = 0
                )
            )
            AND 0 NOT IN (
                SELECT allow
                FROM roles_ability_perfeature_peruser
                WHERE pageid = ||pageid||
                AND userid = ||userid||
                AND feature = ||feature||
                AND featureid = ||featureid||
                AND ability = a.ability
                AND allow = 0
            )
        )
        LIMIT 1
    ) as allowed
    FROM abilities a
    ||issection{{
    WHERE (||section||)
    }}issection||
    ORDER BY section
||get_user_abilities

admin_has_ability_in_pages||
    SELECT p.*
    FROM pages p
    WHERE p.pageid > 0
    ||notsiteviewable{{
    AND p.pageid NOT IN (
        SELECT pageid
        FROM pages
        WHERE siteviewable = 1
    )
    }}notsiteviewable||
    ||notmenuitems{{
    AND p.pageid NOT IN (
        SELECT pageid
        FROM pages
        WHERE menu_page = 1
    )
    }}notmenuitems||
||admin_has_ability_in_pages

user_has_ability_in_pages||
    SELECT a.*, (
        SELECT name
        FROM pages
        WHERE pageid = a.pageid
    ) as name
    FROM (
        SELECT pu.pageid
        FROM roles_ability_peruser pu
        WHERE pu.userid = ||userid||
        AND pu.ability = ||ability||
        AND pu.allow = 1
        ||rolesql||
    ) a
    GROUP BY a.pageid
||user_has_ability_in_pages

user_has_ability_in_pages_perrole||
    UNION ALL
    SELECT p.pageid
    FROM pages p
    WHERE p.pageid IN (
        SELECT ra.pageid
        FROM roles_assignment ra
        WHERE ra.userid = ||userid||
        AND ra.roleid = ||roleid||
        AND ra.confirm = 0
        AND ra.roleid IN (
            SELECT rab.roleid
            FROM roles_ability rab
            WHERE rab.ability = ||ability||
            AND rab.allow = 1
        )
    )
    OR p.pageid IN (
        SELECT pp.pageid
        FROM roles_ability_perpage pp
        WHERE pp.roleid = ||roleid||
        AND pp.ability = ||ability||
        AND pp.allow = 1
    )
    ||notsiteviewable{{
    AND p.pageid NOT IN (
        SELECT pageid
        FROM pages
        WHERE siteviewable = 1
    )
    }}notsiteviewable||
    ||notmenuitems{{
    AND p.pageid NOT IN (
        SELECT pageid
        FROM pages
        WHERE menu_page = 1
    )
    }}notmenuitems||
||user_has_ability_in_pages_perrole

//	This is a fully implemented roles structure for the system. The following is the importance structure
//
//			Feature specific per role for given page
//				|
//				Role specific per page
//					|
//					Role specific per SITE LEVEL permissions
get_role_abilities||
    SELECT a.ability, (
        SELECT 1 as allowed
        FROM roles_ability ra
        WHERE (
            1 IN (
                SELECT allow
                FROM roles_ability_perfeature
                WHERE pageid = ||pageid||
                AND roleid = ||roleid||
                AND feature = ||feature||
                AND featureid = ||featureid||
                AND ability = a.ability
                AND allow = 1
            )
            OR (
                1 IN (
                    SELECT allow
                    FROM roles_ability_perpage
                    WHERE roleid = ||roleid||
                    AND pageid = ||pageid||
                    AND ability = a.ability
                    AND allow = 1
                )
                OR 1 IN (
                    SELECT allow
                    FROM roles_ability
                    WHERE roleid = ||roleid||
                    AND ability = a.ability
                    AND allow = 1
                )
                AND 0 NOT IN (
                    SELECT allow
                    FROM roles_ability_perpage
                    WHERE roleid = ||roleid||
                    AND pageid = ||pageid||
                    AND ability = a.ability
                    AND allow = 0
                )
            )
            AND 0 NOT IN (
                SELECT allow
                FROM roles_ability_perfeature
                WHERE pageid = ||pageid||
                AND roleid = ||roleid||
                AND feature = ||feature||
                AND featureid = ||featureid||
                AND ability = a.ability
                AND allow = 0
            )
        )
        LIMIT 1
    ) as allowed
    FROM abilities a
    ||issection{{
    WHERE (||section||)
    }}issection||
    ORDER BY section
||get_role_abilities

//	This is a fully implemented roles structure for the system.  The following is the importance structure
//
//			Feature specific per role for given page
//				|
//				Role specific per page
//					|
//					Role specific per SITE LEVEL permissions
role_has_ability_in_page||
    SELECT 1 as allowed
    FROM roles_ability ra
    WHERE (
        1 IN (
            SELECT allow
            FROM roles_ability_perfeature
            WHERE pageid = ||pageid||
            AND roleid = ||roleid||
            AND feature = ||feature||
            AND featureid = ||featureid||
            AND ability = ||ability||
            AND allow = 1
        )
        OR
        (
            1 IN (
                SELECT allow
                FROM roles_ability_perpage
                WHERE roleid = ||roleid||
                AND pageid = ||pageid||
                AND ability = ||ability||
                AND allow = 1
            )
            OR
            1 IN (
                SELECT allow
                FROM roles_ability
                WHERE roleid = ||roleid||
                AND ability = ||ability||
                AND allow = 1
            )
            AND 0 NOT IN (
                SELECT allow
                FROM roles_ability_perpage
                WHERE roleid = ||roleid||
                AND pageid = ||pageid||
                AND ability = ||ability||
                AND allow = 0
            )
        )
        AND 0 NOT IN (
            SELECT allow
            FROM roles_ability_perfeature
            WHERE pageid = ||pageid||
            AND roleid = ||roleid||
            AND feature = ||feature||
            AND featureid = ||featureid||
            AND ability = ||ability||
            AND allow = 0
        )
    )
    LIMIT 1
||role_has_ability_in_page

get_user_role||
    SELECT *
    FROM `roles_assignment`
    WHERE userid = ||userid||
    AND pageid = ||pageid||
    AND confirm = 0
    LIMIT 1
||get_user_role

users_that_can_have_abilities_modified||
    SELECT u.*
    FROM `users` u
    WHERE u.userid IN (
        SELECT ra.userid
        FROM `roles_assignment` ra
        WHERE ra.pageid = ||pageid||
    )
    AND u.userid NOT IN (
        SELECT ra.userid
        FROM `roles_assignment` ra
        WHERE ra.pageid = ||siteid||
        AND ra.roleid = ||adminrole||
    )
    AND u.userid NOT IN (
        SELECT ra.userid
        FROM `roles_assignment` ra
        WHERE ra.pageid = ||pageid||
        AND ra.roleid <= ||myrole||
    )
    AND u.userid <> ||userid||
    ORDER BY u.lname
||users_that_can_have_abilities_modified

users_that_have_ability_in_page||
    SELECT *
    FROM users u
    WHERE (
        (
            userid NOT IN (
                SELECT userid
                FROM `roles_assignment`
                WHERE pageid = ||pageid||
                AND confirm = 0
                AND (
                    roleid IN (
                        SELECT roleid
                        FROM `roles_ability`
                        WHERE ability = ||ability||
                        AND allow = ||siteoropen{{0//OR//1}}siteoropen||
                    )
                    AND roleid NOT IN (
                        SELECT roleid
                        FROM `roles_ability_perpage`
                        WHERE ability = ||ability||
                        AND pageid = ||pageid||
                        AND allow = ||siteoropen{{1//OR//0}}siteoropen||
                    )
                )
                OR roleid IN (
                    SELECT roleid
                    FROM `roles_ability_perpage`
                    WHERE ability = ||ability||
                    AND pageid = ||pageid||
                    AND allow = ||siteoropen{{0//OR//1}}siteoropen||
                )
            )
            AND userid NOT IN (
                SELECT pu.userid
                FROM `roles_ability_peruser` pu
                WHERE pu.pageid = ||pageid||
                AND pu.ability = ||ability||
                AND pu.allow = 0
            )
        )
        OR userid IN (
            SELECT pu.userid
            FROM `roles_ability_peruser` pu
            WHERE pu.pageid = ||pageid||
            AND pu.ability = ||ability||
            AND pu.allow = 1
        )
    )
    OR userid IN (
        SELECT userid
        FROM `roles_assignment`
        WHERE roleid = 1
        AND pageid = ||siteid||
    )
||users_that_have_ability_in_page

get_groups_hierarchy||
    SELECT g.*
    FROM `groups` g
    JOIN `groups_users` u ON g.groupid = u.groupid
    WHERE g.parent = ||parent||
    AND u.pageid = ||pageid||
    AND u.userid = ||userid||
||get_groups_hierarchy

get_group||
    SELECT *
      FROM `groups`
     WHERE groupid = ||groupid||
||get_group

delete_group||
    DELETE FROM `groups`
    WHERE groupid = ||groupid||
    AND pageid = ||pageid||
||delete_group

get_subgroups||
    SELECT *
    FROM `groups`
    WHERE pageid = ||pageid||
    AND parent = ||parent||
    ORDER BY name
||get_subgroups

insert_group_user||
    INSERT INTO `groups_users` (userid, pageid, groupid)
    VALUES(||userid||, ||pageid||, ||groupid||)
||insert_group_user

delete_group_users||
    DELETE FROM `groups_users`
    AND pageid = ||pageid||
    AND groupid = ||groupid||
||delete_group_users

delete_group_user||
    DELETE FROM `groups_users`
    WHERE userid = ||userid||
    AND pageid = ||pageid||
    AND groupid = ||groupid||
||delete_group_user

get_group_users||
    SELECT *
    FROM `groups_users`
    WHERE groupid = ||groupid||
||get_group_users

get_group_users_by_role||
    SELECT u.*
    FROM `users` u
    WHERE u.userid NOT IN (
        SELECT ra.userid
        FROM `roles_assignment` ra
        WHERE ra.pageid = ||pageid||
        AND ra.roleid <= ||roleid||
    )
    AND u.userid IN (
        SELECT userid
        FROM `groups_users`
        WHERE groupid = ||groupid||
    )
    ORDER BY u.lname
||get_group_users_by_role

get_nongroup_users||
    SELECT u.*
    FROM `users` u
    WHERE ||searchstring||
    AND (
        ||pageid|| = ||siteid||
        OR u.userid IN (
            SELECT ra.userid
            FROM `roles_assignment` ra
            WHERE ra.pageid = ||pageid||
        )
    )
    AND u.userid NOT IN (
        SELECT ra.userid
        FROM `roles_assignment` ra
        WHERE ra.pageid = ||siteid||
        AND ra.roleid = ||adminrole||
    )
    AND u.userid NOT IN (
        SELECT ra.userid
        FROM `roles_assignment` ra
        WHERE ra.pageid = ||pageid||
        AND ra.roleid <= ||roleid||
    )
    AND u.userid != ||userid||
    AND u.userid NOT IN (
        SELECT userid
        FROM `groups_users`
        WHERE groupid = ||groupid||
    )
    ORDER BY u.lname
||get_nongroup_users

get_group_by_member||
    SELECT *
    FROM `groups`
    WHERE groupid IN (
        SELECT groupid
        FROM `groups_users`
        WHERE userid = ||userid||
        AND pageid = ||pageid||
    )
||get_group_by_member

get_users_in_group||
    SELECT u.*
    FROM `users` u
    WHERE u.userid IN (
        SELECT userid
        FROM `groups_users`
        WHERE pageid = ||pageid||
        AND groupid = ||groupid||
    )
    ORDER BY u.lname
||get_users_in_group

get_page_users_in_groups||
    SELECT u.*
    FROM `users` u
    WHERE (
        ||pageid|| = ||siteid||
        OR u.userid IN (
            SELECT ra.userid
            FROM roles_assignment ra
            WHERE ra.pageid = ||pageid||
        )
    )
    AND u.userid IN (
        SELECT userid
        FROM groups_users
        WHERE pageid = ||pageid||
        AND groupid = ||groupid||
    )
    ORDER BY u.lname
||get_page_users_in_groups

save_group||
    ||is_editing{{
    UPDATE `groups`
    SET name = ||name||, parent = ||parent||
    WHERE groupid = ||groupid||
    AND pageid = ||pageid||

    //OR//

    INSERT INTO `groups` (name, parent, pageid)
    VALUES (||name||, ||parent||, ||pageid||)
    }}is_editing||
||save_group

print_abilities_sql||
    SELECT *
    FROM `abilities`
    ||is_feature{{
        WHERE section = ||feature||
        OR ability IN (
            'editfeaturesettings',
            'removefeatures',
            'movefeatures',
            'edit_feature_abilities',
            'edit_feature_group_abilities',
            'edit_feature_user_abilities'
        )
    }}is_feature||
    ORDER BY section, ability
||print_abilities_sql

remove_role_override||
    DELETE
    FROM `roles_ability`
    WHERE roleid = ||roleid||
    AND ability = ||ability||
||remove_role_override

insert_role_override||
    INSERT INTO `roles_ability` (roleid, section, ability, allow)
    VALUES (||roleid||, ||section||, ||ability||, ||allow||)
||insert_role_override

get_page_role_override||
    SELECT *
    FROM `roles_ability_perpage`
    WHERE pageid = ||pageid||
    AND roleid = ||roleid||
    AND ability = ||ability||
||get_page_role_override

remove_page_role_override||
    DELETE
    FROM `roles_ability_perpage`
    WHERE pageid = ||pageid||
    AND roleid = ||roleid||
    AND ability = ||ability||
||remove_page_role_override

update_page_role_override||
    UPDATE `roles_ability_perpage`
    SET allow = ||allow||
    WHERE pageid = ||pageid||
    AND roleid = ||roleid||
    AND ability = ||ability||
||update_page_role_override

insert_page_role_override||
    INSERT INTO `roles_ability_perpage` (pageid, roleid, ability, allow)
    VALUES (||pageid||, ||roleid||, ||ability||, ||allow||)
||insert_page_role_override

get_page_group_feature_override||
    SELECT *
    FROM `roles_ability_perfeature_pergroup`
    WHERE pageid = ||pageid||
    AND feature = ||feature||
    AND featureid = ||featureid||
    AND groupid = ||groupid||
    AND ability = ||ability||
||get_page_group_feature_override

update_page_group_feature_override||
    UPDATE `roles_ability_perfeature_pergroup`
    SET allow = ||allow||
    WHERE pageid = ||pageid||
    AND feature = ||feature||
    AND featureid = ||featureid||
    AND groupid = ||groupid||
    AND ability = ||ability||
||update_page_group_feature_override

insert_page_group_feature_override||
    INSERT INTO `roles_ability_perfeature_pergroup` (pageid, feature, featureid, groupid, ability, allow)
    VALUES (||pageid||, ||feature||, ||featureid||, ||groupid||, ||ability||, ||allow||)
||insert_page_group_feature_override

insert_roles_ability_perfeature_peruser_override||
    INSERT INTO `roles_ability_perfeature_peruser` (pageid, feature, featureid, userid, ability, allow)
    VALUES (||pageid||, ||feature||, ||featureid||, ||userid||, ||ability||, ||allow||)
||insert_roles_ability_perfeature_peruser_override

get_page_group_override||
    SELECT *
    FROM `roles_ability_pergroup`
    WHERE pageid = ||pageid||
    AND groupid = ||groupid||
    AND ability = ||ability||
||get_page_group_override

update_page_group_override||
    UPDATE `roles_ability_pergroup`
    SET allow = ||allow||
    WHERE pageid = ||pageid||
    AND groupid = ||groupid||
    AND ability = ||ability||
||update_page_group_override

insert_page_group_override||
    INSERT INTO `roles_ability_pergroup` (pageid, groupid, ability, allow)
    VALUES (||pageid||, ||groupid||, ||ability||, ||allow||)
||insert_page_group_override

get_page_feature_user_override||
    SELECT *
    FROM `roles_ability_perfeature_peruser`
    WHERE pageid = ||pageid||
    AND feature = ||feature||
    AND featureid = ||featureid||
    AND userid = ||userid||
    AND ability = ||ability||
||get_page_feature_user_override

get_page_user_override||
    SELECT *
    FROM `roles_ability_peruser`
    WHERE pageid = ||pageid||
    AND userid = ||userid||
    AND ability = ||ability||
||get_page_user_override

get_page_role_feature_override||
    SELECT *
    FROM `roles_ability_perfeature`
    WHERE feature = ||feature||
    AND featureid = ||featureid||
    AND pageid = ||pageid||
    AND roleid = ||roleid||
    AND ability = ||ability||
||get_page_role_feature_override

remove_page_role_feature_override||
    DELETE
    FROM `roles_ability_perfeature`
    WHERE feature = ||feature||
    AND featureid = ||featureid||
    AND pageid = ||pageid||
    AND roleid = ||roleid||
    AND ability = ||ability||
||remove_page_role_feature_override

update_page_role_feature_override||
    UPDATE `roles_ability_perfeature`
    SET allow = ||allow||
    WHERE feature = ||feature||
    AND featureid = ||featureid||
    AND pageid = ||pageid||
    AND roleid = ||roleid||
    AND ability = ||ability||
||update_page_role_feature_override

insert_page_role_feature_override||
    INSERT INTO `roles_ability_perfeature` (feature, featureid, pageid, roleid, ability, allow)
    VALUES (||feature||, ||featureid||, ||pageid||, ||roleid||, ||ability||, ||allow||)
||insert_page_role_feature_override

user_search_all||
    SELECT u.userid, u.fname, u.lname, u.email
    FROM `users` u
    WHERE ||search||
    ORDER BY u.lname
||user_search_all

user_search_higher_role||
    SELECT u.userid, u.fname, u.lname, u.email
    FROM `users` u
    WHERE ||searchstring||
    AND u.userid IN (
        SELECT ra.userid
        FROM roles_assignment ra
        WHERE ra.pageid = ||pageid||
        AND ra.roleid > ||myroleid||
    )
    ORDER BY u.lname
||user_search_higher_role

user_search_lower_role||
    SELECT u.userid, u.fname, u.lname, u.email
    FROM `users` u
    WHERE ||searchstring||
    AND u.userid IN (
        SELECT ra.userid
        FROM roles_assignment ra
        WHERE ra.pageid = ||pageid||
        AND ra.roleid <= ||myroleid||
    )
    ORDER BY u.lname
||user_search_lower_role