DROP PROCEDURE IF EXISTS patch_qnaire;
DELIMITER //
CREATE PROCEDURE patch_qnaire()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );

    SELECT "Creating new qnaire table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS qnaire ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "base_language_id INT UNSIGNED NOT NULL, ",
        "name VARCHAR(45) NOT NULL, ",
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
