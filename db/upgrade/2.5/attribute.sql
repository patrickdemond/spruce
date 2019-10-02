SELECT "Creating new attribute table" AS "";

CREATE TABLE IF NOT EXISTS attribute (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  qnaire_id INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  UNIQUE INDEX uq_qnaire_id_name (qnaire_id ASC, name ASC),
  CONSTRAINT fk_attribute_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS attribute_BEFORE_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER attribute_BEFORE_INSERT BEFORE INSERT ON attribute FOR EACH ROW
BEGIN
  -- make sure name is valid
  SELECT NEW.name RLIKE "[a-z_][a-z0-9_]*" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE [a-z_][a-z0-9_]",
    MYSQL_ERRNO = 1300;
  END IF;
END$$

DROP TRIGGER IF EXISTS attribute_BEFORE_UPDATE $$
CREATE DEFINER = CURRENT_USER TRIGGER attribute_BEFORE_UPDATE BEFORE UPDATE ON attribute FOR EACH ROW
BEGIN
  -- make sure name is valid
  SELECT NEW.name RLIKE "[a-z_][a-z0-9_]*" INTO @test;
  IF( @test = 0 ) THEN
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE [a-z_][a-z0-9_]",
    MYSQL_ERRNO = 1300;
  END IF;
END$$

DELIMITER ;
