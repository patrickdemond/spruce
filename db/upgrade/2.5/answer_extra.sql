SELECT "Creating new answer_extra table" AS "";

CREATE TABLE IF NOT EXISTS answer_extra (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  answer_id INT UNSIGNED NOT NULL,
  value VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  INDEX fk_answer_id (answer_id ASC),
  CONSTRAINT fk_answer_extra_answer_id
    FOREIGN KEY (answer_id)
    REFERENCES answer (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
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
  AND ( SELECT exclusive FROM question_option WHERE id = question_option_id );
END$$

DELIMITER ;
