DROP PROCEDURE IF EXISTS patch_question_option_description;
DELIMITER //
CREATE PROCEDURE patch_question_option_description()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new question_option_description table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS question_option_description ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "question_option_id INT UNSIGNED NOT NULL, ",
        "language_id INT UNSIGNED NOT NULL, ",
        "type ENUM('prompt', 'popup') NOT NULL, ",
        "value TEXT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_question_option_id (question_option_id ASC), ",
        "INDEX fk_language_id (language_id ASC), ",
        "UNIQUE INDEX uq_question_option_id_language_id_type (question_option_id ASC, language_id ASC, type ASC), ",
        "CONSTRAINT fk_question_option_description_question_option_id ",
          "FOREIGN KEY (question_option_id) ",
          "REFERENCES question_option (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_question_option_description_language_id ",
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

CALL patch_question_option_description();
DROP PROCEDURE IF EXISTS patch_question_option_description;
