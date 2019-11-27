SELECT "Creating new answer_extra table" AS "";

CREATE TABLE IF NOT EXISTS answer_extra (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  answer_id INT UNSIGNED NOT NULL,
  question_option_id INT UNSIGNED NOT NULL,
  value_boolean TINYINT(1) NULL DEFAULT NULL,
  value_number FLOAT NULL DEFAULT NULL,
  value_string VARCHAR(255) NULL DEFAULT NULL,
  value_text TEXT NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX fk_answer_id (answer_id ASC),
  INDEX fk_question_option_id (question_option_id ASC),
  CONSTRAINT fk_answer_extra_answer_id
    FOREIGN KEY (answer_id)
    REFERENCES answer (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_answer_extra_question_option_id
    FOREIGN KEY (question_option_id)
    REFERENCES question_option (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

DELIMITER $$

DROP TRIGGER IF EXISTS answer_extra_AFTER_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER answer_extra_AFTER_INSERT AFTER INSERT ON answer_extra FOR EACH ROW
BEGIN
  -- make sure to remove the dkna and refuse bits when adding any answer_extra
  UPDATE answer SET dkna = false, refuse = false WHERE id = NEW.answer_id;
  -- remove all exclusive options
  DELETE FROM answer_has_question_option 
  WHERE answer_id = NEW.answer_id
  AND answer_has_question_option.question_option_id != NEW.question_option_id
  AND ( SELECT exclusive FROM question_option WHERE id = question_option_id );
END$$

DROP TRIGGER IF EXISTS answer_extra_BEFORE_UPDATE $$
CREATE DEFINER = CURRENT_USER TRIGGER answer_extra_BEFORE_UPDATE BEFORE UPDATE ON answer_extra FOR EACH ROW
BEGIN
  -- make sure that only one value can be non-null
  IF( OLD.value_boolean IS NULL AND NEW.value_boolean IS NOT NULL ) THEN
    SET NEW.value_number = NULL, NEW.value_string = NULL, NEW.value_text = NULL;
  ELSEIF( OLD.value_number IS NULL AND NEW.value_number IS NOT NULL ) THEN
    SET NEW.value_boolean = NULL, NEW.value_string = NULL, NEW.value_text = NULL;
  ELSEIF( OLD.value_string IS NULL AND NEW.value_string IS NOT NULL ) THEN
    SET NEW.value_boolean = NULL, NEW.value_number = NULL, NEW.value_text = NULL;
  ELSEIF( OLD.value_text IS NULL AND NEW.value_text IS NOT NULL ) THEN
    SET NEW.value_boolean = NULL, NEW.value_number = NULL, NEW.value_string = NULL;
  END IF;
END$$

DELIMITER ;
