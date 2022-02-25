SELECT "Creating new qnaire_participant_trigger table" AS "";

CREATE TABLE IF NOT EXISTS qnaire_participant_trigger (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  qnaire_id INT(10) UNSIGNED NOT NULL,
  question_id INT(10) UNSIGNED NOT NULL,
  answer_value VARCHAR(255) NOT NULL,
  column_name ENUM('override_stratum', 'mass_email', 'delink', 'withdraw_third_party', 'out_of_area', 'low_education') NOT NULL,
  value VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  INDEX fk_question_id (question_id ASC),
  UNIQUE INDEX uq_qnaire_id_question_id_answer_value_column_name (qnaire_id ASC, question_id ASC, answer_value ASC, column_name ASC),
  CONSTRAINT fk_qnaire_participant_trigger_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_qnaire_participant_trigger_question_id
    FOREIGN KEY (question_id)
    REFERENCES question (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
