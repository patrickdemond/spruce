SELECT "Creating new question table" AS "";

CREATE TABLE IF NOT EXISTS question (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  type ENUM('boolean', 'list', 'number', 'string', 'text', 'comment') NOT NULL,
  mandatory TINYINT(1) NOT NULL DEFAULT 1,
  dkna_refuse TINYINT(1) NOT NULL DEFAULT 1,
  minimum FLOAT NULL DEFAULT NULL,
  maximum FLOAT NULL DEFAULT NULL,
  precondition TEXT NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_page_id (page_id ASC),
  UNIQUE INDEX uq_page_id_rank (page_id ASC, rank ASC),
  UNIQUE INDEX uq_page_id_name (page_id ASC, name ASC),
  CONSTRAINT fk_question_page_id
    FOREIGN KEY (page_id)
    REFERENCES page (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS question_BEFORE_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER question_BEFORE_INSERT BEFORE INSERT ON question FOR EACH ROW
BEGIN
  -- make sure name is valid
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  ELSE
    -- make sure that question names are unique in qnaire
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
  END IF;
END$$

DROP TRIGGER IF EXISTS question_AFTER_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER question_AFTER_INSERT AFTER INSERT ON question FOR EACH ROW
BEGIN
  INSERT INTO question_description( question_id, language_id, type )
  SELECT NEW.id, language_id, type.name
  FROM ( SELECT "prompt" AS name UNION SELECT "popup" AS name ) AS type, qnaire_has_language
  JOIN module ON qnaire_has_language.qnaire_id = module.qnaire_id
  JOIN page ON module.id = page.module_id
  WHERE page.id = NEW.page_id;
END$$

DROP TRIGGER IF EXISTS question_BEFORE_UPDATE $$
CREATE DEFINER = CURRENT_USER TRIGGER question_BEFORE_UPDATE BEFORE UPDATE ON question FOR EACH ROW
BEGIN
  -- make sure name is valid
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  ELSE
    -- make sure that question names are unique in qnaire
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

  -- remove minimum and maximum if type is being changed from number
  IF( OLD.type != NEW.type AND "number" = OLD.type ) THEN
    SET NEW.minimum = NULL;
    SET NEW.maximum = NULL;
  END IF;
END$$

DELIMITER ;
