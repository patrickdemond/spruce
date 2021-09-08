SELECT "Adding new trigger to respondent table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS respondent_AFTER_INSERT$$

CREATE DEFINER = CURRENT_USER TRIGGER respondent_AFTER_INSERT AFTER INSERT ON respondent FOR EACH ROW
BEGIN
  CALL update_respondent_current_response( NEW.id );
END$$

DELIMITER ;
