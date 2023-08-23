DROP PROCEDURE IF EXISTS patch_qnaire;
DELIMITER //
CREATE PROCEDURE patch_qnaire()
  BEGIN

    SELECT "Adding anonymous column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "anonymous";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER readonly;
    END IF;

    SELECT "Adding beartooth_appointment_type column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_appointment_type";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN beartooth_appointment_type VARCHAR(45) NULL DEFAULT NULL AFTER beartooth_url;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;
