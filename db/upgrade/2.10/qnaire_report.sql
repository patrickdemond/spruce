DROP PROCEDURE IF EXISTS patch_qnaire_report;
DELIMITER //
CREATE PROCEDURE patch_qnaire_report()
  BEGIN

    SELECT "Adding new title column to qnaire_report table" AS "";

    -- determine the @cenozo database name
    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire_report"
    AND column_name = "title";

    IF @test = 0 THEN
      ALTER TABLE qnaire_report
      ADD COLUMN title VARCHAR(255) NOT NULL AFTER language_id;
      UPDATE qnaire_report SET title = "Participant Report";
    END IF;

    SELECT "Adding new title column to qnaire_report table" AS "";

    -- determine the @cenozo database name
    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire_report"
    AND column_name = "dpi";

    IF @test = 0 THEN
      ALTER TABLE qnaire_report
      ADD COLUMN dpi INT(10) UNSIGNED NOT NULL DEFAULT 72 AFTER title;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire_report();
DROP PROCEDURE IF EXISTS patch_qnaire_report;
