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

    SELECT LOCATE( "equipment", column_type )
    INTO @equipment
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "type";

    IF @equipment = 0 THEN
      ALTER TABLE question
      MODIFY COLUMN type ENUM(
        'audio', 'boolean', 'comment', 'date', 'device', 'equipment', 'list', 'lookup', 'number',
        'number with unit', 'string', 'text', 'time'
      ) NOT NULL;
    END IF;

    SELECT "Adding equipment_type columns to question table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "equipment_type_id";

    IF @test = 0 THEN
      ALTER TABLE question
      ADD COLUMN new_equipment_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER refuse_allowed,
      ADD COLUMN equipment_sent TINYINT(1) NOT NULL DEFAULT 1 AFTER new_equipment_allowed,
      ADD COLUMN equipment_type_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER device_id;

      SET @sql = CONCAT(
        "ALTER TABLE question ",
        "ADD KEY fk_equipment_type_id (equipment_type_id), ",
        "ADD CONSTRAINT fk_question_equipment_type_id ",
          "FOREIGN KEY (equipment_type_id) ",
          "REFERENCES ", @cenozo, ".equipment_type (id) ",
          "ON DELETE NO ACTION ON UPDATE SET NULL"
      );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;
    END IF;

  END //
DELIMITER ;

CALL patch_question();
DROP PROCEDURE IF EXISTS patch_question;


SELECT "Updating triggers in question table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS question_AFTER_UPDATE$$
CREATE DEFINER = CURRENT_USER TRIGGER question_AFTER_UPDATE AFTER UPDATE ON question FOR EACH ROW
BEGIN
  IF NEW.device_id IS NULL AND OLD.device_id IS NOT NULL THEN
    DELETE FROM answer_device
    WHERE answer_id IN ( SELECT answer.id FROM answer WHERE question_id = NEW.id );
  END IF;

  IF NEW.device_id IS NOT NULL AND OLD.device_id IS NULL THEN
    INSERT IGNORE INTO answer_device( answer_id )
    SELECT id FROM answer WHERE question_id = NEW.id;
  END IF;
END$$


DROP TRIGGER question_BEFORE_UPDATE$$
CREATE DEFINER = CURRENT_USER TRIGGER question_BEFORE_UPDATE BEFORE UPDATE ON question FOR EACH ROW
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

  IF( OLD.type != NEW.type AND "equipment" = OLD.type ) THEN
    SET NEW.equipment_type_id = NULL;
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
