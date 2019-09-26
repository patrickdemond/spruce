SELECT "Creating new answer table" AS "";

CREATE TABLE IF NOT EXISTS answer (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  response_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  dkna TINYINT(1) NOT NULL DEFAULT 0,
  refuse TINYINT(1) NOT NULL DEFAULT 0,
  value_boolean TINYINT(1) NULL DEFAULT NULL,
  value_number FLOAT NULL DEFAULT NULL,
  value_string VARCHAR(255) NULL DEFAULT NULL,
  value_text TEXT NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX fk_response_id (response_id ASC),
  INDEX fk_question_id (question_id ASC),
  UNIQUE INDEX uq_response_id_question_id (response_id ASC, question_id ASC),
  CONSTRAINT fk_answer_response_id
    FOREIGN KEY (response_id)
    REFERENCES response (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_answer_question_id
    FOREIGN KEY (question_id)
    REFERENCES question (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS answer_BEFORE_UPDATE $$
CREATE DEFINER = CURRENT_USER TRIGGER answer_BEFORE_UPDATE BEFORE UPDATE ON answer FOR EACH ROW
BEGIN
  IF ( NEW.dkna || NEW.refuse ) THEN
    IF( OLD.dkna != NEW.dkna || OLD.refuse != NEW.refuse ) THEN
      SET NEW.value_boolean = NULL, NEW.value_number = NULL, NEW.value_string = NULL, NEW.value_text = NULL;
    ELSE
      SET NEW.dkna = 0, NEW.refuse = 0;
    END IF;
  END IF;
END$$

DELIMITER ;
