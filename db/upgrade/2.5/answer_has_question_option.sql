SELECT "Creating new answer_has_question_option table" AS "";

CREATE TABLE IF NOT EXISTS answer_has_question_option (
  answer_id INT UNSIGNED NOT NULL,
  question_option_id INT UNSIGNED NOT NULL,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  PRIMARY KEY (answer_id, question_option_id),
  INDEX fk_question_option_id (question_option_id ASC),
  INDEX fk_answer_id (answer_id ASC),
  CONSTRAINT fk_answer_has_question_option_answer_id
    FOREIGN KEY (answer_id)
    REFERENCES answer (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_answer_has_question_option_question_option_id
    FOREIGN KEY (question_option_id)
    REFERENCES question_option (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS answer_has_question_option_AFTER_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER answer_has_question_option_AFTER_INSERT AFTER INSERT ON answer_has_question_option FOR EACH ROW
BEGIN
  -- make sure to remove the dkna and refuse bits when adding any option
  UPDATE answer SET dkna = false, refuse = false WHERE id = NEW.answer_id;
  -- unset any answer_extra data if the selected option is exclusive
  SELECT exclusive INTO @exclusive FROM question_option WHERE id = NEW.question_option_id;
  IF @exclusive THEN
    DELETE FROM answer_extra WHERE answer_id = NEW.answer_id;
  END IF;
END$$

DELIMITER ;
