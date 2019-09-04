CREATE TABLE IF NOT EXISTS module (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  survey_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  description TEXT NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_survey_id (survey_id ASC),
  UNIQUE INDEX uq_survey_id_rank (survey_id ASC, rank ASC),
  UNIQUE INDEX uq_survey_id_name (survey_id ASC, name ASC),
  CONSTRAINT fk_module_survey_id
    FOREIGN KEY (survey_id)
    REFERENCES survey (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
