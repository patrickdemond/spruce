DROP PROCEDURE IF EXISTS patch_respondent;
DELIMITER //
CREATE PROCEDURE patch_respondent()
  BEGIN

    SELECT "Making participant_id in respondent table nullable" AS "";

    SELECT IS_NULLABLE INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "respondent"
    AND column_name = "participant_id";

    IF @test = "NO" THEN
      ALTER TABLE respondent modify participant_id INT(10) UNSIGNED NULL DEFAULT NULL;
    END IF;

  END //
DELIMITER ;

CALL patch_respondent();
DROP PROCEDURE IF EXISTS patch_respondent;
