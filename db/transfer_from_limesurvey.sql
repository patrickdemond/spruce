INSERT IGNORE INTO patrick_linden.module( qnaire_id, rank, name, description )
SELECT 2, group_order+1, group_name, description
FROM patrick_limesurvey.groups
WHERE sid = 357653
AND language = "en"
ORDER BY group_order;

INSERT IGNORE INTO patrick_linden.page( module_id, rank, name )
SELECT module.id, question_order+1, title
FROM patrick_limesurvey.questions
JOIN patrick_limesurvey.groups USING ( gid, language )
JOIN patrick_linden.module ON group_order+1 = module.rank AND qnaire_id = 2
WHERE questions.sid = 357653
AND parent_qid = 0
AND questions.language = "en"
ORDER BY group_order, question_order;

INSERT IGNORE INTO patrick_linden.question( page_id, rank, name, description, type, multiple )
SELECT page.id, 1, title, question,
  CASE type WHEN "L" THEN ( IF( "DK_NA,NO,REFUSED,YES" = GROUP_CONCAT( answers.code ORDER BY answers.code ), "boolean", "list"  ) )
            WHEN "M" THEN "list"
            WHEN "S" THEN "string"
            WHEN "Q" THEN "string" -- TODO: multiple strings -- need to make them separate questions
            WHEN "N" THEN "number"
            WHEN "T" THEN "text"
            WHEN "F" THEN "list" -- TODO: multiple lists -- need to make them separate questions
            WHEN "K" THEN "number" -- TODO: multiple numbers -- used to enter value in more than one unit
            WHEN "X" THEN "comment"
  END AS type,
  "M" = type AS multiple
FROM patrick_limesurvey.questions
JOIN patrick_limesurvey.groups USING ( gid, language )
JOIN patrick_linden.module ON group_order+1 = module.rank AND qnaire_id = 2 
JOIN patrick_linden.page ON module.id = page.module_id AND page.name = title COLLATE utf8mb4_unicode_ci
LEFT JOIN patrick_limesurvey.answers USING( qid, language )
WHERE questions.sid = 357653
AND parent_qid = 0 
AND questions.language = "en"
GROUP BY questions.qid
ORDER BY group_order, question_order;
