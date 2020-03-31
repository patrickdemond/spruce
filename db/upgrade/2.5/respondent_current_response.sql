SELECT "Creating new response table" AS "";

CREATE TABLE IF NOT EXISTS respondent_current_response (
  respondent_id INT UNSIGNED NOT NULL,
  response_id INT UNSIGNED NULL,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  INDEX fk_response_id (response_id ASC),
  PRIMARY KEY (respondent_id),
  CONSTRAINT fk_respondent_current_response_respondent_id
    FOREIGN KEY (respondent_id)
    REFERENCES respondent (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_respondent_current_response_response_id
    FOREIGN KEY (response_id)
    REFERENCES response (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;
