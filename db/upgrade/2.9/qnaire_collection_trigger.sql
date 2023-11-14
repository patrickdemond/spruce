DROP PROCEDURE IF EXISTS patch_qnaire_collection_trigger;
DELIMITER //
CREATE PROCEDURE patch_qnaire_collection_trigger()
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
      "CREATE TABLE IF NOT EXISTS qnaire_collection_trigger ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "qnaire_id INT(10) UNSIGNED NOT NULL, ",
        "collection_id INT(10) UNSIGNED NOT NULL, ",
        "question_id INT(10) UNSIGNED NOT NULL, ",
        "answer_value VARCHAR(255) NOT NULL, ",
        "add_to TINYINT(1) NOT NULL DEFAULT 1, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_qnaire_id (qnaire_id ASC), ",
        "INDEX fk_collection_id (collection_id ASC), ",
        "INDEX fk_question_id (question_id ASC), ",
        "UNIQUE INDEX uq_qnaire_id_collection_id_question_id_answer_value (qnaire_id ASC, collection_id ASC, question_id ASC, answer_value ASC), ",
        "CONSTRAINT fk_qnaire_collection_trigger_qnaire_id ",
          "FOREIGN KEY (qnaire_id) ",
          "REFERENCES qnaire (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_collection_trigger_collection_id ",
          "FOREIGN KEY (collection_id) ",
          "REFERENCES ", @cenozo, ".collection (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_collection_trigger_question_id ",
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

CALL patch_qnaire_collection_trigger();
DROP PROCEDURE IF EXISTS patch_qnaire_collection_trigger;
