DROP PROCEDURE IF EXISTS patch_qnaire_description;
DELIMITER //
CREATE PROCEDURE patch_qnaire_description()
  BEGIN

    SELECT "Removing enum options from type column in qnaire_description table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire_description"
    AND column_name = "type"
    AND column_type LIKE "%'reminder%";

    IF @test = 1 THEN
      -- move descriptions to the reminder_description table
      REPLACE INTO reminder_description( reminder_id, language_id, type, value )
      SELECT reminder.id, language_id, IF( 'reminder subject' = type, 'subject', 'body' ), value
      FROM qnaire_description
      JOIN reminder USING( qnaire_id )
      WHERE type IN( 'reminder subject', 'reminder body' );

      DELETE FROM qnaire_description WHERE type IN( 'reminder subject', 'reminder body' );

      -- redefine the type enum values
      ALTER TABLE qnaire_description
      MODIFY type ENUM('introduction', 'conclusion', 'closed', 'invitation subject', 'invitation body') NOT NULL;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire_description();
DROP PROCEDURE IF EXISTS patch_qnaire_description;
