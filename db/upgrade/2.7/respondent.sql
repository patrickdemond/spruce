DROP PROCEDURE IF EXISTS patch_respondent;
DELIMITER //
CREATE PROCEDURE patch_respondent()
  BEGIN

    SELECT "Adding new export_datetime column to respondent table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "respondent"
    AND column_name = "export_datetime";

    IF @test = 0 THEN
      ALTER TABLE respondent ADD COLUMN export_datetime DATETIME NULL DEFAULT NULL;
    END IF;

  END //
DELIMITER ;

CALL patch_respondent();
DROP PROCEDURE IF EXISTS patch_respondent;


SELECT "Adding new trigger to respondent table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS respondent_AFTER_INSERT$$

CREATE DEFINER = CURRENT_USER TRIGGER respondent_AFTER_INSERT AFTER INSERT ON respondent FOR EACH ROW
BEGIN
  CALL update_respondent_current_response( NEW.id );
END$$

DELIMITER ;
