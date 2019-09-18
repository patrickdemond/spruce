SELECT "Creating new answer_option table" AS "";

CREATE TABLE IF NOT EXISTS answer_option (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  answer_id INT UNSIGNED NOT NULL,
  question_option_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  INDEX fk_answer_option_answer_id (answer_id ASC),
  INDEX fk_question_option_id (question_option_id ASC),
  CONSTRAINT fk_answer_option_answer_id
    FOREIGN KEY (answer_id)
    REFERENCES answer (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_answer_option_question_option_id
    FOREIGN KEY (question_option_id)
    REFERENCES question_option (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
