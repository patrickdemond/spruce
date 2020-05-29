DROP PROCEDURE IF EXISTS patch_qnaire;
DELIMITER //
CREATE PROCEDURE patch_qnaire()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new qnaire table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS qnaire ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "base_language_id INT UNSIGNED NOT NULL, ",
        "name VARCHAR(255) NOT NULL, ",
        "variable_suffix VARCHAR(45) NULL DEFAULT NULL, ",
        "closed TINYINT(1) NOT NULL DEFAULT 0, ",
        "debug TINYINT(1) NOT NULL DEFAULT 0, ",
        "readonly TINYINT(1) NOT NULL DEFAULT 0, ",
        "repeated ENUM('hour', 'day', 'week', 'month') NULL DEFAULT NULL, ",
        "repeat_offset INT UNSIGNED NULL DEFAULT NULL, ",
        "max_responses INT UNSIGNED NULL DEFAULT NULL, ",
        "email_from_name VARCHAR(255) NULL DEFAULT NULL, ",
        "email_from_address VARCHAR(127) NULL DEFAULT NULL, ",
        "email_invitation TINYINT(1) NOT NULL DEFAULT 0, ",
        "email_reminder ENUM('hour', 'day', 'week', 'month') NULL DEFAULT NULL, ",
        "email_reminder_offset INT UNSIGNED NULL DEFAULT NULL, ",
        "description TEXT NULL, ",
        "note TEXT NULL, ",
        "PRIMARY KEY (id), ",
        "UNIQUE INDEX uq_name (name ASC), ",
        "INDEX fk_base_language_id (base_language_id ASC), ",
        "CONSTRAINT fk_qnaire_base_language_id ",
          "FOREIGN KEY (base_language_id) ",
          "REFERENCES ", @cenozo, ".language (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;


DELIMITER $$

DROP TRIGGER IF EXISTS qnaire_AFTER_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER qnaire_AFTER_INSERT AFTER INSERT ON qnaire FOR EACH ROW
BEGIN
  INSERT INTO qnaire_average_time SET qnaire_id = NEW.id;

  INSERT INTO qnaire_has_language SET qnaire_id = NEW.id, language_id = NEW.base_language_id;
END$$

DROP TRIGGER IF EXISTS qnaire_AFTER_UPDATE $$
CREATE DEFINER = CURRENT_USER TRIGGER qnaire_AFTER_UPDATE AFTER UPDATE ON qnaire FOR EACH ROW
BEGIN
  IF( OLD.base_language_id != NEW.base_language_id ) THEN
    INSERT IGNORE INTO qnaire_has_language SET qnaire_id = NEW.id, language_id = NEW.base_language_id;
  END IF;
END$$

DELIMITER ;
