DROP PROCEDURE IF EXISTS patch_qnaire_event_type_trigger;
DELIMITER //
CREATE PROCEDURE patch_qnaire_event_type_trigger()
  BEGIN

    SELECT "Creating new qnaire_equipment_type_trigger table" AS "";

    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS qnaire_event_type_trigger ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "qnaire_id INT(10) UNSIGNED NOT NULL, ",
        "event_type_id INT(10) UNSIGNED NOT NULL, ",
        "question_id INT(10) UNSIGNED NOT NULL, ",
        "answer_value VARCHAR(255) NOT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_qnaire_id (qnaire_id ASC), ",
        "INDEX fk_event_type_id (event_type_id ASC), ",
        "INDEX fk_question_id (question_id ASC), ",
        "UNIQUE INDEX uq_qnaire_id_event_type_id_question_id_answer_value (qnaire_id ASC, event_type_id ASC, question_id ASC, answer_value ASC), ",
        "CONSTRAINT fk_qnaire_event_type_trigger_qnaire_id ",
          "FOREIGN KEY (qnaire_id) ",
          "REFERENCES qnaire (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_event_type_trigger_event_type_id ",
          "FOREIGN KEY (event_type_id) ",
          "REFERENCES ", @cenozo, ".event_type (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_event_type_trigger_question_id ",
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

CALL patch_qnaire_event_type_trigger();
DROP PROCEDURE IF EXISTS patch_qnaire_event_type_trigger;
