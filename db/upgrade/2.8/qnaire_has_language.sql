SELECT "Modifying qnaire_has_language table triggers" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS qnaire_has_language_AFTER_INSERT$$
CREATE DEFINER=CURRENT_USER TRIGGER qnaire_has_language_AFTER_INSERT AFTER INSERT ON qnaire_has_language FOR EACH ROW
BEGIN
  INSERT IGNORE INTO qnaire_description( qnaire_id, language_id, type ) VALUES
  ( NEW.qnaire_id, NEW.language_id, 'introduction' ),
  ( NEW.qnaire_id, NEW.language_id, 'conclusion' ),
  ( NEW.qnaire_id, NEW.language_id, 'closed' ),
  ( NEW.qnaire_id, NEW.language_id, 'invitation subject' ),
  ( NEW.qnaire_id, NEW.language_id, 'invitation body' ),
  ( NEW.qnaire_id, NEW.language_id, 'incompatible' );

  INSERT IGNORE INTO reminder_description( reminder_id, language_id, type )
  SELECT reminder.id, NEW.language_id, type.name
  FROM ( SELECT "subject" AS name UNION SELECT "body" AS name ) AS type, reminder
  WHERE reminder.qnaire_id = NEW.qnaire_id;

  INSERT IGNORE INTO module_description( module_id, language_id, type )
  SELECT module.id, NEW.language_id, type.name
  FROM ( SELECT "prompt" AS name UNION SELECT "popup" AS name ) AS type, module
  WHERE module.qnaire_id = NEW.qnaire_id;

  INSERT IGNORE INTO page_description( page_id, language_id, type )
  SELECT page.id, NEW.language_id, type.name
  FROM ( SELECT "prompt" AS name UNION SELECT "popup" AS name ) AS type, page
  JOIN module ON page.module_id = module.id
  WHERE module.qnaire_id = NEW.qnaire_id;

  INSERT IGNORE INTO question_description( question_id, language_id, type )
  SELECT question.id, NEW.language_id, type.name
  FROM ( SELECT "prompt" AS name UNION SELECT "popup" AS name ) AS type, question
  JOIN page ON question.page_id = page.id
  JOIN module ON page.module_id = module.id
  WHERE module.qnaire_id = NEW.qnaire_id;

  INSERT IGNORE INTO question_option_description( question_option_id, language_id, type )
  SELECT question_option.id, NEW.language_id, type.name
  FROM ( SELECT "prompt" AS name UNION SELECT "popup" AS name ) AS type, question_option
  JOIN question ON question_option.question_id = question.id
  JOIN page ON question.page_id = page.id
  JOIN module ON page.module_id = module.id
  WHERE module.qnaire_id = NEW.qnaire_id;
END$$

DELIMITER ;
