DROP PROCEDURE IF EXISTS patch_question;
DELIMITER //
CREATE PROCEDURE patch_question()
  BEGIN

    SELECT "Adding new value to type enum column in question table" AS "";

    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT LOCATE( "signature", column_type )
    INTO @signature
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "type";

    IF @signature = 0 THEN
      ALTER TABLE question
      MODIFY COLUMN type ENUM(
        'audio', 'boolean', 'comment', 'date', 'device', 'equipment', 'list', 'lookup', 'number',
        'number with unit', 'signature', 'string', 'text', 'time'
      ) NOT NULL;
    END IF;

  END //
DELIMITER ;

CALL patch_question();
DROP PROCEDURE IF EXISTS patch_question;
