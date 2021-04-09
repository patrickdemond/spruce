DROP PROCEDURE IF EXISTS patch_question;
DELIMITER //
CREATE PROCEDURE patch_question()
  BEGIN

    SELECT "Replacing dkna_refuse column with dkna_allowed and refuse_allowed columns" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "dkna_refuse";

    IF @test = 1 THEN
      ALTER TABLE question
      ADD COLUMN dkna_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER dkna_refuse,
      ADD COLUMN refuse_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER dkna_allowed;

      UPDATE question SET dkna_allowed = dkna_refuse, refuse_allowed = dkna_refuse;

      ALTER TABLE question DROP COLUMN dkna_refuse;
    END IF;

  END //
DELIMITER ;

CALL patch_question();
DROP PROCEDURE IF EXISTS patch_question;
