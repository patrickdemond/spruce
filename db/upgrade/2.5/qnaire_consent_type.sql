DROP PROCEDURE IF EXISTS patch_qnaire_consent_type;
DELIMITER //
CREATE PROCEDURE patch_qnaire_consent_type()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new qnaire_consent_type table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS qnaire_consent_type ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "qnaire_id INT UNSIGNED NOT NULL, ",
        "consent_type_id INT UNSIGNED NOT NULL, ",
        "question_id INT UNSIGNED NOT NULL, ",
        "answer_value VARCHAR(255) NOT NULL, ",
        "accept TINYINT(1) NOT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_qnaire_id (qnaire_id ASC), ",
        "INDEX fk_consent_type_id (consent_type_id ASC), ",
        "INDEX fk_question_id (question_id ASC), ",
        "CONSTRAINT fk_qnaire_consent_type_qnaire_id ",
          "FOREIGN KEY (qnaire_id) ",
          "REFERENCES qnaire (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_consent_type_consent_type_id ",
          "FOREIGN KEY (consent_type_id) ",
          "REFERENCES ", @cenozo, ".consent_type (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_consent_type_question_id ",
          "FOREIGN KEY (question_id) ",
          "REFERENCES question (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_qnaire_consent_type();
DROP PROCEDURE IF EXISTS patch_qnaire_consent_type;
