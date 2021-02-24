DROP PROCEDURE IF EXISTS patch_qnaire;
DELIMITER //
CREATE PROCEDURE patch_qnaire()
  BEGIN

    SELECT "Removing email_reminder and email_reminder_offset columns from qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "email_reminder";

    IF @test = 1 THEN
      -- move reminders from the qnaire table to the reminder table
      INSERT INTO reminder( qnaire_id, offset, unit )
      SELECT id, email_reminder_offset, email_reminder
      FROM qnaire
      WHERE email_reminder IS NOT NULL;

      -- drop the reminder settings from the qnaire table
      ALTER TABLE qnaire DROP COLUMN email_reminder,
                         DROP COLUMN email_reminder_offset;
    END IF;

    SELECT "Adding new beartooth_password column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_password";

    IF @test = 0 THEN
      ALTER TABLE qnaire
      ADD COLUMN beartooth_password VARCHAR(255) NULL DEFAULT NULL AFTER email_invitation;
    END IF;

    SELECT "Adding new beartooth_username column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_username";

    IF @test = 0 THEN
      ALTER TABLE qnaire
      ADD COLUMN beartooth_username VARCHAR(255) NULL DEFAULT NULL AFTER email_invitation;
    END IF;

    SELECT "Adding new beartooth_url column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_url";

    IF @test = 0 THEN
      ALTER TABLE qnaire
      ADD COLUMN beartooth_url VARCHAR(255) NULL DEFAULT NULL AFTER email_invitation;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;
