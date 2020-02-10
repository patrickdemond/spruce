SELECT "Creating new question_option table" AS "";

CREATE TABLE IF NOT EXISTS question_option (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  exclusive TINYINT(1) NOT NULL DEFAULT 0,
  extra ENUM('number', 'string', 'text') NULL DEFAULT NULL,
  multiple_answers TINYINT(1) NOT NULL DEFAULT 0,
  minimum FLOAT NULL DEFAULT NULL,
  maximum FLOAT NULL DEFAULT NULL,
  precondition TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_question_id (question_id ASC),
  UNIQUE INDEX uq_question_id_rank (question_id ASC, rank ASC),
  UNIQUE INDEX uq_question_id_name (question_id ASC, name ASC),
  CONSTRAINT fk_question_option_question_id
    FOREIGN KEY (question_id)
    REFERENCES question (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS question_option_BEFORE_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER question_option_BEFORE_INSERT BEFORE INSERT ON question_option FOR EACH ROW
BEGIN
  -- make sure name is valid
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  END IF;
END$$

DROP TRIGGER IF EXISTS question_option_AFTER_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER question_option_AFTER_INSERT AFTER INSERT ON question_option FOR EACH ROW
BEGIN
  INSERT INTO question_option_description( question_option_id, language_id, type )
  SELECT NEW.id, language_id, type.name
  FROM ( SELECT "prompt" AS name UNION SELECT "popup" AS name ) AS type, qnaire_has_language
  JOIN module ON qnaire_has_language.qnaire_id = module.qnaire_id
  JOIN page ON module.id = page.module_id
  JOIN question ON page.id = question.page_id
  WHERE question.id = NEW.question_id;
END$$

DROP TRIGGER IF EXISTS question_option_BEFORE_UPDATE $$
CREATE DEFINER = CURRENT_USER TRIGGER question_option_BEFORE_UPDATE BEFORE UPDATE ON question_option FOR EACH ROW
BEGIN
  -- make sure name is valid
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  END IF;

  -- remove minimum and maximum if extra is being changed from number
  IF( NOT( OLD.extra <=> NEW.extra ) AND "number" = OLD.extra ) THEN
    SET NEW.minimum = NULL;
    SET NEW.maximum = NULL;
  END IF;
END$$

DELIMITER ;
