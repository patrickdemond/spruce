SELECT "Adding new trigger to answer table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS answer_AFTER_INSERT$$
CREATE DEFINER = CURRENT_USER TRIGGER answer_AFTER_INSERT AFTER INSERT ON answer FOR EACH ROW
BEGIN
  SELECT device_id INTO @device_id FROM question WHERE question.id = NEW.question_id;
  IF @device_id IS NOT NULL THEN
    INSERT INTO answer_device SET create_timestamp = NULL, answer_id = NEW.id;
  END IF;
END$$

DELIMITER ;
