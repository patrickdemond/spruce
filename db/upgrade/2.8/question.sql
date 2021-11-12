DROP PROCEDURE IF EXISTS patch_question;
DELIMITER //
CREATE PROCEDURE patch_question()
  BEGIN

    SELECT "Adding new audio question type to type column in question table" AS "";

    SELECT LOCATE( "audio", column_type ) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "type";

    IF @test = 0 THEN
      ALTER TABLE question
      MODIFY COLUMN type ENUM('audio', 'boolean', 'comment', 'date', 'device', 'list', 'number', 'string', 'text') NOT NULL;
    END IF;

  END //
DELIMITER ;

CALL patch_question();
DROP PROCEDURE IF EXISTS patch_question;
