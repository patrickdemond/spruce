DROP PROCEDURE IF EXISTS patch_question;
DELIMITER //
CREATE PROCEDURE patch_question()
  BEGIN

    SELECT "Replacing dkna_refuse column with dkna_allowed and refuse_allowed columns in the question table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "dkna_refuse";

    IF @test = 1 THEN
      ALTER TABLE question
      ADD COLUMN dkna_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER dkna_refuse,
      ADD COLUMN refuse_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER dkna_allowed;

      UPDATE question SET dkna_allowed = dkna_refuse, refuse_allowed = dkna_refuse;

      ALTER TABLE question DROP COLUMN dkna_refuse;
    END IF;

    SELECT "Adding new device_id column to question table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "question"
    AND column_name = "device_id";

    IF @test = 0 THEN
      ALTER TABLE question
      ADD COLUMN device_id INT UNSIGNED NULL DEFAULT NULL AFTER refuse_allowed,
      ADD INDEX fk_device_id (device_id ASC),
      ADD CONSTRAINT fk_question_device_id
        FOREIGN KEY (device_id)
        REFERENCES device (id)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;
    END IF;

    SELECT "Adding the 'device' option to the type enum column in the question table" AS "";
    ALTER TABLE question
    MODIFY COLUMN type ENUM('boolean', 'comment', 'date', 'device', 'list', 'number', 'string', 'text') NOT NULL;

  END //
DELIMITER ;

CALL patch_question();
DROP PROCEDURE IF EXISTS patch_question;


SELECT "Updating before update trigger in question table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS question_BEFORE_UPDATE$$

CREATE TRIGGER question_BEFORE_UPDATE BEFORE UPDATE ON question FOR EACH ROW BEGIN
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
END$$

DELIMITER ;
