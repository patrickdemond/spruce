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

    SELECT "Removing beartooth_url column from qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_url";

    IF @test = 1 THEN
      ALTER TABLE qnaire DROP COLUMN beartooth_url;
    END IF;

    SELECT "Removing beartooth_username column from qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_username";

    IF @test = 1 THEN
      ALTER TABLE qnaire DROP COLUMN beartooth_username;
    END IF;

    SELECT "Removing beartooth_password column from qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_password";

    IF @test = 1 THEN
      ALTER TABLE qnaire DROP COLUMN beartooth_password;
    END IF;

    SELECT "Adding beartooth column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN beartooth TINYINT(1) NOT NULL DEFAULT 0 AFTER email_invitation;
    END IF;

    SELECT "Adding appointment_type column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "appointment_type";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN appointment_type VARCHAR(45) NULL DEFAULT NULL AFTER beartooth;
    END IF;

    SELECT "Adding attributes_mandatory column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "attributes_mandatory";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN attributes_mandatory TINYINT(1) NOT NULL DEFAULT 0 AFTER problem_report;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;
