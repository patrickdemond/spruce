DROP PROCEDURE IF EXISTS patch_answer;
DELIMITER //
CREATE PROCEDURE patch_answer()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );
    
    SELECT "Creating new answer table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS answer ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "response_id INT UNSIGNED NOT NULL, ",
        "question_id INT UNSIGNED NOT NULL, ",
        "language_id INT UNSIGNED NOT NULL, ",
        "value JSON NULL NOT NULL DEFAULT 'null', ",
        "PRIMARY KEY (id), ",
        "INDEX fk_response_id (response_id ASC), ",
        "INDEX fk_question_id (question_id ASC), ",
        "INDEX fk_language_id (language_id ASC), ",
        "UNIQUE INDEX uq_response_id_question_id (response_id ASC, question_id ASC), ",
        "CHECK( JSON_VALID( value ) ), ",
        "CONSTRAINT fk_answer_response_id ",
          "FOREIGN KEY (response_id) ",
          "REFERENCES response (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_answer_question_id ",
          "FOREIGN KEY (question_id) ",
          "REFERENCES question (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_answer_language_id ",
          "FOREIGN KEY (language_id) ",
          "REFERENCES ", @cenozo, ".language (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_answer();
DROP PROCEDURE IF EXISTS patch_answer;
