DROP PROCEDURE IF EXISTS patch_question_option;
DELIMITER //
CREATE PROCEDURE patch_question_option()
  BEGIN

    SELECT "Adding new question types to extra column in question_option table" AS "";

    SELECT LOCATE( "audio", column_type ),
           LOCATE( "lookup", column_type ),
           LOCATE( "number with unit", column_type )
    INTO @audio, @lookup, @number_with_unit
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question_option"
    AND column_name = "extra";

    IF @audio = 0 OR @lookup = 0 OR @number_with_unit = 0 THEN
      ALTER TABLE question_option
      MODIFY COLUMN extra ENUM('date', 'number', 'number with unit', 'string', 'text') NULL DEFAULT NULL;
    END IF;

    SELECT "Adding new unit_list column to question_option table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question_option"
    AND column_name = "unit_list";

    IF @test = 0 THEN
      ALTER TABLE question_option
      ADD COLUMN unit_list VARCHAR(1023) NULL DEFAULT NULL
      CHECK ( JSON_VALID( unit_list ) )
      AFTER multiple_answers;
    END IF;

  END //
DELIMITER ;

CALL patch_question_option();
DROP PROCEDURE IF EXISTS patch_question_option;


SELECT "Modifying question_option table triggers" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS question_option_BEFORE_INSERT$$
CREATE DEFINER=CURRENT_USER TRIGGER question_option_BEFORE_INSERT BEFORE INSERT ON question_option FOR EACH ROW
BEGIN
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  ELSE
    IF( "number with unit" = NEW.extra AND NEW.unit_list IS NULL ) THEN
      SET NEW.unit_list = "[]";
    ELSE
      IF( "number with unit" != NEW.extra AND NEW.unit_list IS NOT NULL ) THEN
        SET NEW.unit_list = NULL;
      END IF;
    END IF;
  END IF;
END$$

DROP TRIGGER IF EXISTS question_option_BEFORE_UPDATE$$
CREATE DEFINER=CURRENT_USER TRIGGER question_option_BEFORE_UPDATE BEFORE UPDATE ON question_option FOR EACH ROW
BEGIN
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  ELSE
    IF( NOT( OLD.extra <=> NEW.extra ) ) THEN
      IF( NEW.extra IS NULL ) THEN
        SET NEW.multiple_answers = false;
      END IF;

      IF( NEW.extra IS NULL OR ( "date" != NEW.extra AND "number" != NEW.extra ) ) THEN
        SET NEW.minimum = NULL;
        SET NEW.maximum = NULL;
      END IF;
    END IF;

    IF( "number with unit" = NEW.extra AND NEW.unit_list IS NULL ) THEN
      SET NEW.unit_list = "[]";
    ELSE
      IF( "number with unit" != NEW.extra AND NEW.unit_list IS NOT NULL ) THEN
        SET NEW.unit_list = NULL;
      END IF;
    END IF;
  END IF;
END$$

DELIMITER ;
