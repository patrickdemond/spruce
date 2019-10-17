SELECT "Creating new module table" AS "";

CREATE TABLE IF NOT EXISTS module (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  qnaire_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  precondition TEXT NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  UNIQUE INDEX uq_qnaire_id_rank (qnaire_id ASC, rank ASC),
  UNIQUE INDEX uq_qnaire_id_name (qnaire_id ASC, name ASC),
  CONSTRAINT fk_module_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS module_AFTER_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER module_AFTER_INSERT AFTER INSERT ON module FOR EACH ROW
BEGIN
  INSERT INTO module_description( module_id, language_id )
  SELECT NEW.id, language_id
  FROM qnaire_has_language
  WHERE qnaire_id = NEW.qnaire_id;
END$$

DELIMITER ;
