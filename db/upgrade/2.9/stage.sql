DROP PROCEDURE IF EXISTS patch_stage;
DELIMITER //
CREATE PROCEDURE patch_stage()
  BEGIN

    SELECT "Adding token_check_precondition column to stage table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "stage"
    AND column_name = "token_check_precondition";

    IF @test = 0 THEN
      ALTER TABLE stage ADD COLUMN token_check_precondition TEXT NULL DEFAULT NULL AFTER precondition;
    END IF;

  END //
DELIMITER ;

CALL patch_stage();
DROP PROCEDURE IF EXISTS patch_stage;
