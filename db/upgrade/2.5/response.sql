SELECT "Creating new response table" AS "";

CREATE TABLE IF NOT EXISTS response (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  respondent_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  language_id INT UNSIGNED NOT NULL,
  page_id INT UNSIGNED NULL DEFAULT NULL,
  submitted TINYINT(1) NOT NULL DEFAULT 0,
  start_datetime DATETIME NULL DEFAULT NULL,
  last_datetime DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX fk_respondent_id (respondent_id ASC),
  UNIQUE INDEX uq_respondent_id_rank (respondent_id ASC, rank ASC),
  CONSTRAINT fk_respondent_respondent_id
    FOREIGN KEY (respondent_id)
    REFERENCES respondent (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS response_AFTER_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER response_AFTER_INSERT AFTER INSERT ON response FOR EACH ROW
BEGIN
  CALL update_respondent_current_response( NEW.respondent_id );
END$$


DROP TRIGGER IF EXISTS response_AFTER_DELETE $$
CREATE DEFINER = CURRENT_USER TRIGGER response_AFTER_DELETE AFTER DELETE ON response FOR EACH ROW
BEGIN
  CALL update_respondent_current_response( OLD.respondent_id );
END$$

DELIMITER ;
