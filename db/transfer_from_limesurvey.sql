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

INSERT IGNORE INTO patrick_linden.question( page_id, rank, name, description )
SELECT page.id, 1, title, question
FROM patrick_limesurvey.questions
JOIN patrick_limesurvey.groups USING ( gid, language )
JOIN patrick_linden.module ON group_order+1 = module.rank AND qnaire_id = 2
JOIN patrick_linden.page ON module.id = page.module_id AND page.name = title COLLATE utf8mb4_unicode_ci
WHERE questions.sid = 357653
AND parent_qid = 0
AND questions.language = "en"
ORDER BY group_order, question_order;

