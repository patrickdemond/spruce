-- create the tracking F2 qnaire
INSERT IGNORE INTO patrick_spruce.qnaire( name ) VALUES ( "Tracking F2 Main" );

-- add all modules to the qnaire
INSERT IGNORE INTO patrick_spruce.module( qnaire_id, rank, name, description )
SELECT qnaire.id, group_order+1, group_name, groups.description
FROM patrick_spruce.qnaire, patrick_limesurvey.groups
WHERE qnaire.name = "Tracking F2 Main"
AND sid = 357653
AND language = "en"
ORDER BY group_order;

-- add all pages to the modules
INSERT IGNORE INTO patrick_spruce.page( module_id, rank, name )
SELECT module.id, question_order+1, title
FROM patrick_limesurvey.questions
JOIN patrick_limesurvey.groups USING ( gid, language )
JOIN patrick_spruce.module ON group_order+1 = module.rank
JOIN patrick_spruce.qnaire ON module.qnaire_id = qnaire.id
WHERE qnaire.name = "Tracking F2 Main"
AND questions.sid = 357653
AND parent_qid = 0
AND questions.language = "en"
ORDER BY group_order, question_order;

-- temporarily add a qid column to the question table
ALTER TABLE patrick_spruce.question
ADD COLUMN qid INT NULL DEFAULT NULL,
ADD INDEX dk_qid ( qid );

-- add all questions to the pages
INSERT IGNORE INTO patrick_spruce.question( qid, page_id, rank, name, description, type, multiple )
SELECT qid, page.id, 1, title, question,
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
JOIN patrick_spruce.module ON group_order+1 = module.rank
JOIN patrick_spruce.qnaire ON module.qnaire_id = qnaire.id
JOIN patrick_spruce.page ON module.id = page.module_id AND page.name = title COLLATE utf8mb4_unicode_ci
LEFT JOIN patrick_limesurvey.answers USING( qid, language )
WHERE qnaire.name = "Tracking F2 Main"
AND questions.sid = 357653
AND parent_qid = 0 
AND questions.language = "en"
GROUP BY questions.qid
ORDER BY group_order, question_order;

-- add all question_options to the questions
INSERT IGNORE INTO question_option( question_id, rank, name, value, exclusive, extra )
SELECT question.id, sortorder, answer, code, 1, IF( answer = "Other" OR code = "OTHER", "string", NULL ) 
FROM patrick_spruce.question
JOIN patrick_limesurvey.answers USING( qid )
WHERE answers.language = "en"
AND code NOT IN( "DK_NA", "REFUSED" )
ORDER BY qid, sortorder;

INSERT IGNORE INTO question_option( question_id, rank, name, value, extra )
SELECT question.id, question_order, question, title,
       IF( question = "Other" OR ( title LIKE "%\_OT\_%" AND title NOT LIKE 'CCT_%' ), "string", NULL )
FROM patrick_spruce.question
JOIN patrick_limesurvey.questions subquestions ON question.qid = subquestions.parent_qid
WHERE subquestions.language = "en"
AND title NOT LIKE "%DK_NA%"
AND title NOT LIKE "%REFUSED%"
ORDER BY parent_qid, question_order;

-- make certain question_options exclusive
UPDATE patrick_spruce.question_option
JOIN patrick_spruce.question ON question_option.question_id = question.id
JOIN patrick_limesurvey.question_attributes USING( qid )
SET exclusive = 1
WHERE attribute = "exclude_all_others"
AND question_attributes.value LIKE CONCAT( "%", question_option.value COLLATE utf8mb4_unicode_ci, "%" );

-- 

-- remove the temporary qid column now that we no longer need it
-- ALTER TABLE question DROP INDEX dk_qid, DROP COLUMN qid;
