
CREATE VIEW vw_user_groups AS
SELECT user_group.ID_GROUP, user_group.ID_USER, user_group.ID_RELATION, group_relations.NAME FROM user_group 
LEFT JOIN group_relations ON user_group.ID_RELATION = group_relations.ID_RELATION
WHERE ACTIVE=1
UNION 
SELECT ID_GROUP, ID_OWNER, 0 AS ID_RELATION, 'OWNER' AS NAME FROM groups;

CREATE VIEW vw_user_groups_detail AS
SELECT T1.ID_GROUP, T3.MAIN_COLOR, T2.NAME, T2.SHORTCUT, T2.GROUP_TYPE, T2.URL_ID, T1.ID_USER, T1.ID_RELATION, T2.ARCHIVED, T1.NAME AS RELATION_NAME
FROM vw_user_groups T1
JOIN groups T2 ON (T1.ID_GROUP = T2.ID_GROUP)
LEFT JOIN group_color_scheme T3 ON T2.COLOR_SCHEME=T3.ID_SCHEME;


CREATE OR REPLACE VIEW vw_user_detail AS
SELECT T1.NAME, T1.SURNAME, T1.USERNAME, T1.URL_ID, T1.ID_USER, T1.EMAIL, T1.SEX, T1.BIRTHDAY, T2.PATH AS PROFILE_PATH,T2.FILENAME AS PROFILE_FILENAME , T1.SECRET
FROM (`user` T1 LEFT JOIN file_list T2 ON ((T2.ID_FILE = T1.PROFILE_IMAGE)));

CREATE OR REPLACE VIEW vw_user_schools AS
select `T1`.`ID_USER` AS `ID_USER`,
`T1`.`USERNAME` AS `USERNAME`,
`T1`.`NAME` AS `NAME`,`T1`.`SURNAME` AS `SURNAME`,`T3`.`ID_SCHOOL` AS `ID_SCHOOL`,
`T3`.`NAME` AS `SCHOOL_NAME`,`T5`.`ID_CLASS` AS `ID_CLASS`,`T5`.`NAME` AS `CLASS_NAME`, `T5`.`GRADE` AS `CLASS_GRADE` 
from (((`user` `T1` left join `school_class_user` `T4` on((`T4`.`ID_USER` = `T1`.`ID_USER`))) left join `school_class` `T5` on((`T5`.`ID_CLASS` = `T4`.`ID_CLASS`))) left join `school` `T3` on((`T5`.`ID_SCHOOL` = `T3`.`ID_SCHOOL`)))

CREATE OR REPLACE VIEW vw_all_users AS
SELECT
id, sex, name, surname, profile_image, slug, 0 AS is_fictive, role
FROM user JOIN user_real using(id)
UNION
SELECT
id, sex, name, surname, null, slug, 1 AS is_fictive, 'student' AS role
FROM user JOIN user_fictive using(id);

CREATE OR REPLACE VIEW vw_classification AS
SELECT 
   T1.id,
   T1.classification_group_id,
   NULL AS clg,
   T1.user_id,
   T1.group_id,
   IF(T1.classification_group_id IS NULL, T1.name, T2.name) AS name,
   T1.grade,
   IF(T1.classification_date IS NULL, T1.created_when, T1.classification_date) AS classification_date,
   T1.period_id,
   T1.notice
FROM classification T1
LEFT JOIN classification_group T2 on T1.classification_group_id=T2.id
UNION all
SELECT 
   NULL,
   T2.id,
   T2.id AS clg,
   NULL,
   T2.group_id,
   T2.name,
   NULL,
   IF(T2.classification_date IS NULL, T2.created_when, T2.classification_date) AS classification_date,
   T1.period_id,
	T1.notice
FROM classification T1
RIGHT JOIN classification_group T2 on T1.classification_group_id=T2.id;
