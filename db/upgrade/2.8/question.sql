DROP PROCEDURE IF EXISTS patch_question;
DELIMITER //
CREATE PROCEDURE patch_question()
  BEGIN

    SELECT "Adding new audio question type to type column in question table" AS "";

    SELECT LOCATE( "audio", column_type ),
           LOCATE( "lookup", column_type )
    INTO @audio, @lookup
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "type";

    IF @audio = 0 OR @lookup = 0 THEN
      ALTER TABLE question
      MODIFY COLUMN type ENUM('audio', 'boolean', 'comment', 'date', 'device', 'list', 'lookup', 'number', 'string', 'text') NOT NULL;
    END IF;

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "export";

    IF @test = 0 THEN
      ALTER TABLE question ADD COLUMN export TINYINT(1) NOT NULL DEFAULT 1 AFTER type;
    END IF;

    SELECT "Changing constraint for device" AS "";

    SELECT delete_rule INTO @test
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE constraint_schema = DATABASE()
    AND table_name = "question"
    AND constraint_name = "fk_question_device_id";

    IF 'NO ACTION' = @test  THEN
      ALTER TABLE question DROP CONSTRAINT fk_question_device_id;
      ALTER TABLE question
      ADD CONSTRAINT fk_question_device_id FOREIGN KEY (device_id) REFERENCES device (id)
      ON DELETE SET NULL ON UPDATE NO ACTION;
    END IF;

    SELECT "Adding new lookup_id column to question table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "lookup_id";

    IF @test = 0 THEN
      ALTER TABLE question ADD COLUMN lookup_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER device_id;
      ALTER TABLE question
      ADD INDEX fk_lookup_id (lookup_id ASC),
      ADD CONSTRAINT fk_question_lookup_id
            FOREIGN KEY (lookup_id)
            REFERENCES lookup (id)
            ON DELETE SET NULL
            ON UPDATE NO ACTION;
    END IF;

  END //
DELIMITER ;

CALL patch_question();
DROP PROCEDURE IF EXISTS patch_question;
