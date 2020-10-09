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

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;
