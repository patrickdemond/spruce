DROP PROCEDURE IF EXISTS patch_question_description;
DELIMITER //
CREATE PROCEDURE patch_question_description()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );

    SELECT "Creating new question_description table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS question_description ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "question_id INT UNSIGNED NOT NULL, ",
        "language_id INT UNSIGNED NOT NULL, ",
        "value TEXT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_question_id (question_id ASC), ",
        "INDEX fk_language_id (language_id ASC), ",
        "UNIQUE INDEX uq_question_id_language_id (question_id ASC, language_id ASC), ",
        "CONSTRAINT fk_question_description_question_id ",
          "FOREIGN KEY (question_id) ",
          "REFERENCES question (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_question_description_language_id ",
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

CALL patch_question_description();
DROP PROCEDURE IF EXISTS patch_question_description;
