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
