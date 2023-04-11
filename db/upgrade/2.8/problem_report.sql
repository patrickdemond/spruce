SELECT "Creating new problem_report table" AS "";

CREATE TABLE IF NOT EXISTS problem_report (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  response_id INT(10) UNSIGNED NOT NULL,
  show_hidden TINYINT(1) NOT NULL,
  page_name VARCHAR(255) NOT NULL,
  remote_address VARCHAR(45) NULL DEFAULT NULL,
  user_agent VARCHAR(255) NULL DEFAULT NULL,
  brand VARCHAR(127) NULL DEFAULT NULL,
  platform VARCHAR(127) NULL DEFAULT NULL,
  mobile VARCHAR(127) NULL DEFAULT NULL,
  datetime DATETIME NOT NULL,
  description VARCHAR(45) NOT NULL,
  PRIMARY KEY (id),
  INDEX fk_response_id (response_id ASC),
  CONSTRAINT fk_problem_report_response_id
    FOREIGN KEY (response_id)
    REFERENCES response (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
