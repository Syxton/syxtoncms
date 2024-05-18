is_siteadmin||
	SELECT *
	FROM roles_assignment
	WHERE roleid = '||adminroleid||'
	AND userid = '||userid||'
	AND pageid = '||siteid||'
	AND confirm = 0
||is_siteadmin

get_roles||
	SELECT *
	FROM roles
	ORDER BY roleid
||get_roles

confirm_role_assignment||
	UPDATE roles_assignment
	SET confirm = 0
	WHERE assignmentid = ||assignmentid||
||confirm_role_assignment

remove_role_assignment||
	DELETE
	FROM roles_assignment
	WHERE assignmentid = ||assignmentid||
||remove_role_assignment

remove_page_role_assignments||
	DELETE
	FROM roles_assignment
	WHERE pageid = ||pageid||
||remove_page_role_assignments

remove_page_roles_ability_perfeature||
	DELETE
	FROM roles_ability_perfeature
	WHERE pageid = ||pageid||
||remove_page_roles_ability_perfeature

remove_page_roles_ability_perfeature_pergroup||
	DELETE
	FROM roles_ability_perfeature_pergroup
	WHERE pageid = ||pageid||
||remove_page_roles_ability_perfeature_pergroup

remove_page_roles_ability_perfeature_peruser||
	DELETE
	FROM roles_ability_perfeature_peruser
	WHERE pageid = ||pageid||
||remove_page_roles_ability_perfeature_peruser

remove_page_roles_ability_pergroup||
	DELETE
	FROM roles_ability_pergroup
	WHERE pageid = ||pageid||
||remove_page_roles_ability_pergroup

remove_page_roles_ability_perpage||
	DELETE
	FROM roles_ability_perpage
	WHERE pageid = ||pageid||
||remove_page_roles_ability_perpage

remove_page_roles_ability_peruser||
	DELETE
	FROM roles_ability_peruser
	WHERE pageid = ||pageid||;
||remove_page_roles_ability_peruser

remove_user_roles_assignment||
    DELETE
    FROM roles_assignment
    WHERE userid = ||userid||
remove_user_roles_assignment||

remove_user_roles_ability_peruser||
    DELETE
    FROM roles_ability_peruser
    WHERE userid = ||userid||
remove_user_roles_ability_peruser||

remove_user_roles_ability_perfeature_peruser||
    DELETE
    FROM roles_ability_perfeature_peruser
    WHERE userid = ||userid||
||remove_user_roles_ability_perfeature_peruser

remove_user_role_assignment||
  DELETE
    FROM roles_assignment
   WHERE pageid = "||pageid||"
     AND userid = "||userid||"
||remove_user_role_assignment

insert_role_assignment||
	INSERT INTO roles_assignment (userid, roleid, pageid)
		VALUES(||userid||, ||roleid||, ||pageid||)
||insert_role_assignment

check_for_role_assignment||
	SELECT *
	FROM roles_assignment
	WHERE pageid = "||pageid||"
	AND userid = "||userid||"
	AND confirm = 0
||check_for_role_assignment

insert_abilities||
  INSERT INTO abilities (section, section_display, ability, ability_display, power)
       VALUES(||section||, ||displayname||, ||ability||, ||desc||, ||power||)
||insert_abilities

insert_roles_ability||
	INSERT INTO roles_ability (roleid, ability, allow, section)
		VALUES(1, ||ability||, 1, ||section||),
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
    FROM roles_ability ra
   WHERE (1 IN (SELECT allow
                  FROM roles_ability_perfeature_peruser
                 WHERE pageid = '||pageid||'
                   AND userid = '||userid||'
                   AND ability = '||ability||'
                   AND feature = '||feature||'
                   AND featureid = '||featureid||'
                   AND allow = '1')
          OR
          (1 IN (SELECT allow
                   FROM roles_ability_peruser
                  WHERE pageid = '||pageid||'
                    AND userid = '||userid||'
                    AND ability = '||ability||'
                    AND allow = '1')
           OR
           ||featuregroupsql[0]|| ||groupsql[0]||
           (1 IN (SELECT allow
                    FROM roles_ability_perfeature
                   WHERE pageid = '||pageid||'
                     AND roleid = '||roleid||'
                     AND ability = '||ability||'
                     AND feature = '||feature||'
                     AND featureid = '||featureid||'
                     AND allow = '1')
            OR
            (1 IN (SELECT allow
                     FROM roles_ability_perpage
                    WHERE pageid = '||pageid||'
                      AND roleid = '||roleid||'
                      AND ability = '||ability||'
                      AND allow = '1')
             OR
             1 IN (SELECT allow
                     FROM roles_ability
                    WHERE roleid = '||roleid||'
                      AND ability = '||ability||'
                      AND allow = '1')
             AND
             0 NOT IN (SELECT allow
                         FROM roles_ability_perpage
                        WHERE pageid = '||pageid||'
                          AND roleid = '||roleid||'
                          AND ability = '||ability||'
                          AND allow = '0')
            )
            AND
            0 NOT IN (SELECT allow
                        FROM roles_ability_perfeature
                       WHERE pageid = '||pageid||'
                         AND roleid = '||roleid||'
                         AND ability = '||ability||'
                         AND feature = '||feature||'
                         AND featureid = '||featureid||'
                         AND allow = '0')
            ||featuregroupsql[1]|| ||groupsql[1]||
           )
            AND 0 NOT IN (SELECT allow
                            FROM roles_ability_peruser
                           WHERE pageid = '||pageid||'
                             AND userid = '||userid||'
                             AND ability = '||ability||'
                             AND allow = '0')
          )
          AND 0 NOT IN (SELECT allow
                          FROM roles_ability_perfeature_peruser
                         WHERE pageid = '||pageid||'
                           AND userid = '||userid||'
                           AND ability = '||ability||'
                           AND feature = '||feature||'
                           AND featureid = '||featureid||'
                           AND allow = '0')
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
     SELECT a.ability,
            (
            SELECT 1 as allowed FROM roles_ability ra WHERE
              (
                1 IN (SELECT allow FROM roles_ability_perfeature_peruser WHERE pageid = '||pageid||' AND userid = '||userid||' AND feature='||feature||' AND featureid = '||featureid||' AND ability = a.ability AND allow = '1')
                OR
                (
                  1 IN (SELECT allow FROM roles_ability_peruser WHERE userid = '||userid||' AND pageid = '||pageid||' AND ability = a.ability AND allow = '1')
                  OR
                  ||featuregroupsql[0]|| ||groupsql[0]||
                  (
                    1 IN (SELECT allow FROM roles_ability_perfeature WHERE pageid = '||pageid||' AND roleid = '||roleid||' AND feature='||feature||' AND featureid = '||featureid||' AND ability = a.ability AND allow = '1')
                    OR
                    (
                      1 IN (SELECT allow FROM roles_ability_perpage WHERE roleid = '||roleid||' AND pageid = '||pageid||' AND ability = a.ability AND allow = '1')
                      OR
                      (
                        1 IN (SELECT allow FROM roles_ability WHERE roleid = '||roleid||' AND ability = a.ability AND allow = '1')
                      )
                      AND 0 NOT IN (SELECT allow FROM roles_ability_perpage WHERE roleid = '||roleid||' AND pageid = '||pageid||' AND ability = a.ability AND allow = '0')
                  )
                  AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature WHERE pageid = '||pageid||' AND roleid = '||roleid||' AND feature='||feature||' AND featureid = '||featureid||' AND ability = a.ability AND allow = '0')
                  ||groupsql[1]|| ||featuregroupsql[1]||
                )
                AND 0 NOT IN (SELECT allow FROM roles_ability_peruser WHERE userid = '||userid||' AND pageid = '||pageid||' AND ability = a.ability AND allow = '0')
              )
              AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature_peruser WHERE pageid = '||pageid||' AND userid = '||userid||' AND feature='||feature||' AND featureid = '||featureid||' AND ability = a.ability AND allow = '0')
            )
          LIMIT 1) as allowed
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
     AND p.pageid NOT IN (SELECT pageid
                            FROM pages
                           WHERE siteviewable = 1)
   }}notsiteviewable||
   ||notmenuitems{{
     AND p.pageid NOT IN (SELECT pageid
                            FROM pages
                           WHERE menu_page = 1)
   }}notmenuitems||
||admin_has_ability_in_pages

user_has_ability_in_pages||
  SELECT a.*, (SELECT name
                 FROM pages
                WHERE pageid = a.pageid) as name
    FROM (SELECT pu.pageid
            FROM roles_ability_peruser pu
           WHERE pu.userid = '||userid||'
             AND pu.ability = '||ability||'
             AND pu.allow = 1
             ||perrole||) a
        GROUP BY a.pageid
||user_has_ability_in_pages

user_has_ability_in_pages_perrole||
  UNION ALL SELECT p.pageid
              FROM pages p
             WHERE p.pageid IN (
                               SELECT ra.pageid
                                 FROM roles_assignment ra
                                WHERE ra.userid = '||userid||'
                                  AND ra.roleid = '||roleid||'
                                  AND ra.confirm = 0
                                  AND ra.roleid IN (
                                                   SELECT rab.roleid
                                                     FROM roles_ability rab
                                                    WHERE rab.ability = '||ability||'
                                                      AND rab.allow = 1
                                                   )
                               )
                OR p.pageid IN (
                               SELECT pp.pageid
                                 FROM roles_ability_perpage pp
                                WHERE pp.roleid = '||roleid||'
                                  AND pp.ability = '||ability||'
                                  AND pp.allow = 1
                               )
             ||notsiteviewable{{
               AND p.pageid NOT IN (SELECT pageid
                                      FROM pages
                                     WHERE siteviewable = 1)
             }}notsiteviewable||
             ||notmenuitems{{
               AND p.pageid NOT IN (SELECT pageid
                                      FROM pages
                                     WHERE menu_page = 1)
             }}notmenuitems||
||user_has_ability_in_pages_perrole

//	This is a fully implemented roles structure for the system.  The following is the importance structure
//
//			Feature specific per role for given page
//				|
//				Role specific per page
//					|
//					Role specific per SITE LEVEL permissions
get_role_abilities||
     SELECT a.ability,
        (
          SELECT 1 as allowed FROM roles_ability ra WHERE
          (
            1 IN (SELECT allow FROM roles_ability_perfeature WHERE pageid = '||pageid||' AND roleid = '||roleid||' AND feature = '||feature||' AND featureid = '||featureid||' AND ability = a.ability AND allow = 1)
            OR
            (
              1 IN (SELECT allow FROM roles_ability_perpage WHERE roleid = '||roleid||' AND pageid = '||pageid||' AND ability = a.ability AND allow = 1)
              OR
              1 IN (SELECT allow FROM roles_ability WHERE roleid = '||roleid||' AND ability = a.ability AND allow = 1)
              AND
              0 NOT IN (SELECT allow FROM roles_ability_perpage WHERE roleid = '||roleid||' AND pageid = '||pageid||' AND ability = a.ability AND allow = 0)
            )
            AND
            0 NOT IN (SELECT allow FROM roles_ability_perfeature WHERE pageid = '||pageid||' AND roleid = '||roleid||' AND feature = '||feature||' AND featureid = '||featureid||' AND ability = a.ability AND allow = 0)
        )
      LIMIT 1) as allowed
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
  SELECT 1 as allowed FROM roles_ability ra WHERE
    (
    1 IN (SELECT allow FROM roles_ability_perfeature WHERE pageid = '||pageid||' AND roleid = '||roleid||' AND feature = '||feature||' AND featureid = '||featureid||' AND ability = '||ability||' AND allow = '1')
    OR
      (
      1 IN (SELECT allow FROM roles_ability_perpage WHERE roleid = '||roleid||' AND pageid = '||pageid||' AND ability = '||ability||' AND allow = '1')
      OR
        (
        1 IN (SELECT allow FROM roles_ability WHERE roleid = '||roleid||' AND ability = '||ability||' AND allow = '1')
        )
      AND 0 NOT IN (SELECT allow FROM roles_ability_perpage WHERE roleid = '||roleid||' AND pageid = '||pageid||' AND ability = '||ability||' AND allow = '0')
      )
    AND 0 NOT IN (SELECT allow FROM roles_ability_perfeature WHERE pageid = '||pageid||' AND roleid = '||roleid||' AND feature = '||feature||' AND featureid = '||featureid||' AND ability = '||ability||' AND allow = '0')
    )
  LIMIT 1
||role_has_ability_in_page

get_user_role||
  SELECT *
    FROM roles_assignment
   WHERE userid = '||userid||'
     AND pageid = '||pageid||'
     AND confirm = 0
   LIMIT 1
||get_user_role

users_that_have_ability_in_page||
  SELECT *
    FROM users u
   WHERE
         (
           (
             userid NOT IN (
                            SELECT userid
                              FROM roles_assignment
                             WHERE pageid = '||pageid||'
                               AND confirm = '0'
                               AND (
                                    roleid IN (
                                               SELECT roleid
                                                 FROM roles_ability
                                                WHERE ability = '||ability||'
                                                  AND allow = ||siteoropen{{ '0' //OR// '1'}}siteoropen||
                                              )
                                    AND
                                    roleid NOT IN (
                                                    SELECT roleid
                                                      FROM roles_ability_perpage
                                                     WHERE ability = '||ability||'
                                                       AND pageid = '||pageid||'
                                                       AND allow = ||siteoropen{{ '1' //OR// '0'}}siteoropen||
                                                  )
                                   )
                                OR roleid IN (
                                              SELECT roleid
                                                FROM roles_ability_perpage
                                               WHERE ability = '||ability||'
                                                 AND pageid = '||pageid||'
                                                 AND allow = ||siteoropen{{ '0' //OR// '1'}}siteoropen||
                                             )
                           )
             AND userid NOT IN (
                                SELECT pu.userid
                                  FROM roles_ability_peruser pu
                                 WHERE pu.pageid = '||pageid||'
                                   AND pu.ability = '||ability||'
                                   AND pu.allow = '0'
                               )
           )
           OR
           userid IN (
                      SELECT pu.userid
                        FROM roles_ability_peruser pu
                       WHERE pu.pageid = '||pageid||'
                         AND pu.ability = '||ability||'
                         AND pu.allow = '1'
                     )
         )
      OR userid IN (
                    SELECT userid
                      FROM roles_assignment
                     WHERE roleid = '1'
                       AND pageid = '||siteid||'
                   )
||users_that_have_ability_in_page

get_groups_hierarchy||
  SELECT *
    FROM `groups` g
   WHERE g.parent = '||parent||'
     AND g.groupid IN (
                       SELECT u.groupid
                         FROM groups_users u
                        WHERE u.pageid = '||pageid||'
                          AND u.userid = '||userid||'
                      )
||get_groups_hierarchy

get_group||
    SELECT *
      FROM `groups`
     WHERE groupid ='||groupid||'
||get_group

get_subgroups||
    SELECT *
      FROM `groups`
     WHERE pageid = '||pageid||'
       AND parent = '||parent||'
  ORDER BY name
||get_subgroups

get_group_users||
    SELECT *
      FROM `groups_users`
     WHERE groupid ='||groupid||'
||get_group_users

save_group||
  ||is_editing{{
    UPDATE `groups`
       SET name = '||name||', parent = '||parent||'
     WHERE groupid = '||groupid||'
       AND pageid = '||pageid||'

    //OR//

    INSERT INTO `groups` (name, parent, pageid)
         VALUES('||name||', '||parent||', '||pageid||')
  }}is_editing||
||save_group

print_abilities||
    SELECT *
      FROM abilities
  ||is_feature{{
     WHERE section = '||feature||'
        OR (
            ability = 'editfeaturesettings'
            OR
            ability = 'removefeatures'
            OR
            ability = 'movefeatures'
            OR
            ability = 'edit_feature_abilities'
            OR
            ability = 'edit_feature_group_abilities'
            OR
            ability = 'edit_feature_user_abilities'
          )
  }}is_feature||
  ORDER BY section, ability
||print_abilities

remove_role_override||
  DELETE
    FROM roles_ability
   WHERE roleid = '||roleid||'
     AND ability = '||ability||'
||remove_role_override

insert_role_override||
  INSERT INTO roles_ability
              (roleid, section, ability, allow)
       VALUES ('||roleid||', '||section||', '||ability||', '||setting||')
||insert_role_override

get_page_role_override||
  SELECT *
    FROM roles_ability_perpage
   WHERE pageid = '||pageid||'
     AND roleid = '||roleid||'
     AND ability = '||ability||'
||get_page_role_override

remove_page_role_override||
  DELETE
    FROM roles_ability_perpage
   WHERE pageid = '||pageid||'
     AND roleid = '||roleid||'
     AND ability = '||ability||'
||remove_page_role_override

update_page_role_override||
  UPDATE roles_ability_perpage
     SET allow = '||setting||'
   WHERE pageid = '||pageid||'
     AND roleid = '||roleid||'
     AND ability = '||ability||'
||update_page_role_override

insert_page_role_override||
  INSERT INTO roles_ability_perpage
              (pageid, roleid, ability, allow)
       VALUES ('||pageid||', '||roleid||', '||ability||', '||setting||')
||insert_page_role_override

get_page_group_feature_override||
  SELECT *
    FROM roles_ability_perfeature_pergroup
   WHERE pageid = '||pageid||'
     AND feature = '||feature||'
     AND featureid = '||featureid||'
     AND groupid = '||groupid||'
     AND ability = '||ability||'
||get_page_group_feature_override

get_page_group_override||
  SELECT *
    FROM roles_ability_pergroup
   WHERE pageid = '||pageid||'
     AND groupid = '||groupid||'
     AND ability = '||ability||'
||get_page_group_override

get_page_feature_user_override||
  SELECT *
    FROM roles_ability_perfeature_peruser
   WHERE pageid = '||pageid||'
     AND feature = '||feature||'
     AND featureid = '||featureid||'
     AND userid = '||userid||'
     AND ability = '||ability||'
||get_page_feature_user_override

get_page_user_override||
  SELECT *
    FROM roles_ability_peruser
   WHERE pageid = '||pageid||'
     AND userid = '||userid||'
     AND ability = '||ability||'
||get_page_user_override

get_page_role_feature_override||
  SELECT *
    FROM roles_ability_perfeature
   WHERE feature = '||feature||'
     AND featureid = '||featureid||'
     AND pageid = '||pageid||'
     AND roleid = '||roleid||'
     AND ability = '||ability||'
||get_page_role_feature_override

remove_page_role_feature_override||
  DELETE
    FROM roles_ability_perfeature
   WHERE feature = '||feature||'
     AND featureid = '||featureid||'
     AND pageid = '||pageid||'
     AND roleid = '||roleid||'
     AND ability = '||ability||'
||remove_roles_ability_perfeature

update_page_role_feature_override||
  UPDATE roles_ability_perfeature
     SET allow = '||setting||'
   WHERE feature = '||feature||'
     AND featureid = '||featureid||'
     AND pageid = '||pageid||'
     AND roleid = '||roleid||'
     AND ability = '||ability||'
||update_page_role_feature_override

insert_page_role_feature_override||
  INSERT INTO roles_ability_perfeature 
              (feature, featureid, pageid, roleid, ability, allow)
       VALUES ('||feature||', '||featureid||', '||pageid||', '||roleid||', '||ability||', '||setting||')
||insert_page_role_feature_override
