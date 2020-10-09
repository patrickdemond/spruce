SELECT "Creating new reminder table" AS "";

CREATE TABLE IF NOT EXISTS reminder (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  qnaire_id INT UNSIGNED NOT NULL,
  offset INT UNSIGNED NOT NULL,
  unit ENUM('hour', 'day', 'week', 'month') NOT NULL,
  PRIMARY KEY (id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  CONSTRAINT fk_reminder_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

DELIMITER $$

DROP TRIGGER IF EXISTS reminder_AFTER_INSERT $$

CREATE DEFINER = CURRENT_USER TRIGGER reminder_AFTER_INSERT AFTER INSERT ON reminder FOR EACH ROW
BEGIN
  INSERT INTO reminder_description( reminder_id, language_id, type )
  SELECT NEW.id, language_id, type.name
  FROM ( SELECT "subject" AS name UNION SELECT "body" AS name ) AS type, qnaire_has_language
  WHERE qnaire_has_language.qnaire_id = NEW.qnaire_id;
END$$

DELIMITER ;
