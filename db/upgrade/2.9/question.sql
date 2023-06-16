SELECT "Adding new trigger to question table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS question_AFTER_UPDATE$$
CREATE DEFINER = CURRENT_USER TRIGGER question_AFTER_UPDATE AFTER UPDATE ON question FOR EACH ROW
BEGIN
  IF NEW.device_id IS NULL AND OLD.device_id IS NOT NULL THEN
    DELETE FROM answer_device
    WHERE answer_id IN ( SELECT answer.id FROM answer WHERE question_id = NEW.id );
  END IF;

  IF NEW.device_id IS NOT NULL AND OLD.device_id IS NULL THEN
    INSERT IGNORE INTO answer_device( answer_id )
    SELECT id FROM answer WHERE question_id = NEW.id;
  END IF;
END$$

DELIMITER ;
