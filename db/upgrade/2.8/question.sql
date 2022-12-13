DROP PROCEDURE IF EXISTS patch_question;
DELIMITER //
CREATE PROCEDURE patch_question()
  BEGIN

    SELECT "Adding new question types to type column in question table" AS "";

    SELECT LOCATE( "audio", column_type ),
           LOCATE( "lookup", column_type ),
           LOCATE( "number with unit", column_type )
    INTO @audio, @lookup, @number_with_unit
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "type";

    IF @audio = 0 OR @lookup = 0 OR @number_with_unit = 0 THEN
      ALTER TABLE question
      MODIFY COLUMN type ENUM(
        'audio', 'boolean', 'comment', 'date', 'device', 'list', 'lookup',
        'number', 'number with unit', 'string', 'text'
      ) NOT NULL;
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

    SELECT "Adding new unit_list column to question table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "unit_list";

    IF @test = 0 THEN
      ALTER TABLE question
      ADD COLUMN unit_list TEXT NULL DEFAULT NULL
      CHECK ( JSON_VALID( unit_list ) )
      AFTER lookup_id;
    END IF;

  END //
DELIMITER ;

CALL patch_question();
DROP PROCEDURE IF EXISTS patch_question;


SELECT "Modifying question table triggers" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS question_BEFORE_INSERT$$
CREATE DEFINER=CURRENT_USER TRIGGER question_BEFORE_INSERT BEFORE INSERT ON question FOR EACH ROW
BEGIN
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  ELSE
    SELECT qnaire_id INTO @qnaire_id
    FROM page
    JOIN module ON page.module_id = module.id
    WHERE page.id = NEW.page_id;

    SELECT COUNT(*) INTO @test
    FROM question
    JOIN page ON question.page_id = page.id
    JOIN module ON page.module_id = module.id
    WHERE question.name = NEW.name
    AND module.qnaire_id = @qnaire_id;
    IF( @test > 0 ) THEN
      SET @sql = CONCAT(
        "Duplicate entry '",
        @qnaire_id, "-", NEW.name,
        "' for key 'uq_qnaire_id_name'"
      );
      SIGNAL SQLSTATE '23000' SET MESSAGE_TEXT = @sql, MYSQL_ERRNO = 1062;
    END IF;

    IF( "number with unit" = NEW.type AND NEW.unit_list IS NULL ) THEN
      SET NEW.unit_list = "[]";
    ELSE
      IF( "number with unit" != NEW.type AND NEW.unit_list IS NOT NULL ) THEN
        SET NEW.unit_list = NULL;
      END IF;
    END IF;
  END IF;
END$$


DROP TRIGGER IF EXISTS question_BEFORE_UPDATE$$
CREATE DEFINER=CURRENT_USER TRIGGER question_BEFORE_UPDATE BEFORE UPDATE ON question FOR EACH ROW
BEGIN
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  ELSE
    SELECT qnaire_id INTO @qnaire_id
    FROM page
    JOIN module ON page.module_id = module.id
    WHERE page.id = NEW.page_id;

    SELECT COUNT(*) INTO @test
    FROM question
    JOIN page ON question.page_id = page.id
    JOIN module ON page.module_id = module.id
    WHERE question.name = NEW.name
    AND module.qnaire_id = @qnaire_id
    AND question.id != NEW.id;
    IF( @test > 0 ) THEN
      SET @sql = CONCAT(
        "Duplicate entry '",
        @qnaire_id, "-", NEW.name,
        "' for key 'uq_qnaire_id_name'"
      );
      SIGNAL SQLSTATE '23000' SET MESSAGE_TEXT = @sql, MYSQL_ERRNO = 1062;
    END IF;
  END IF;

  IF( OLD.type != NEW.type AND "device" = OLD.type ) THEN
    SET NEW.device_id = NULL;
  END IF;

  IF( OLD.type != NEW.type AND "number" = OLD.type ) THEN
    SET NEW.minimum = NULL;
    SET NEW.maximum = NULL;
  END IF;

  IF( "number with unit" = NEW.type AND NEW.unit_list IS NULL ) THEN
    SET NEW.unit_list = "[]";
  ELSE
    IF( "number with unit" != NEW.type AND NEW.unit_list IS NOT NULL ) THEN
      SET NEW.unit_list = NULL;
    END IF;
  END IF;
END$$

DELIMITER ;
