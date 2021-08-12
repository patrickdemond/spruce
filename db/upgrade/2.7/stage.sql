SELECT "Creating new stage table" AS "";

CREATE TABLE IF NOT EXISTS stage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  qnaire_id INT(10) UNSIGNED NOT NULL,
  first_module_id INT(10) UNSIGNED NOT NULL,
  last_module_id INT(10) UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  precondition TEXT NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  UNIQUE INDEX uq_qnaire_id_rank (qnaire_id ASC, rank ASC),
  UNIQUE INDEX uq_qnaire_id_name (qnaire_id ASC, name ASC),
  INDEX fk_first_module_id (first_module_id ASC),
  INDEX fk_last_module_id (last_module_id ASC),
  CONSTRAINT fk_stage_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_stage_first_module_id
    FOREIGN KEY (first_module_id)
    REFERENCES module (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT fk_stage_last_module_id
    FOREIGN KEY (last_module_id)
    REFERENCES module (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS stage_BEFORE_INSERT;

CREATE TRIGGER stage_BEFORE_INSERT BEFORE INSERT ON stage FOR EACH ROW
BEGIN
  SELECT rank INTO @first_rank FROM module WHERE id = NEW.first_module_id;
  SELECT rank INTO @last_rank FROM module WHERE id = NEW.last_module_id;
  IF @first_rank > @last_rank THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = "Rank of first module cannot be greater than rank of last module.";
  END IF;
END$$

DELIMITER ;
