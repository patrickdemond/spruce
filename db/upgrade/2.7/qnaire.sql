DROP PROCEDURE IF EXISTS patch_qnaire;
DELIMITER //
CREATE PROCEDURE patch_qnaire()
  BEGIN

    SELECT "Adding new version column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "version";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN version VARCHAR(45) NULL DEFAULT NULL AFTER name;
    END IF;

    SELECT "Adding new stages column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "stages";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN stages TINYINT(1) NOT NULL DEFAULT 0 AFTER readonly;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;
