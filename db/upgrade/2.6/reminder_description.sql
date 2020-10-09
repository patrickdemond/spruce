DROP PROCEDURE IF EXISTS patch_reminder_description;
DELIMITER //
CREATE PROCEDURE patch_reminder_description()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new reminder_description table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS reminder_description ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "reminder_id INT UNSIGNED NOT NULL, ",
        "language_id INT UNSIGNED NOT NULL, ",
        "type ENUM('subject', 'body') NOT NULL, ",
        "value TEXT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_reminder_id (reminder_id ASC), ",
        "INDEX fk_language_id (language_id ASC), ",
        "UNIQUE INDEX uq_reminder_id_language_id_type (reminder_id ASC, language_id ASC, type ASC), ",
        "CONSTRAINT fk_reminder_description_reminder_id ",
          "FOREIGN KEY (reminder_id) ",
          "REFERENCES reminder (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_reminder_description_language_id ",
          "FOREIGN KEY (language_id) ",
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

CALL patch_reminder_description();
DROP PROCEDURE IF EXISTS patch_reminder_description;
