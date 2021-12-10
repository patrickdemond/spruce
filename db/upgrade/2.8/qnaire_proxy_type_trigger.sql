DROP PROCEDURE IF EXISTS patch_qnaire_proxy_type;
DELIMITER //
CREATE PROCEDURE patch_qnaire_proxy_type()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new qnaire_proxy_type table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS qnaire_proxy_type_trigger ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "qnaire_id INT(10) UNSIGNED NOT NULL, ",
        "proxy_type_id INT(10) UNSIGNED NULL, ",
        "question_id INT(10) UNSIGNED NOT NULL, ",
        "answer_value VARCHAR(255) NOT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_qnaire_id (qnaire_id ASC), ",
        "INDEX fk_proxy_type_id (proxy_type_id ASC), ",
        "INDEX fk_question_id (question_id ASC), ",
        "UNIQUE INDEX uq_qnaire_id_proxy_type_id_question_id (qnaire_id ASC, proxy_type_id ASC, question_id ASC), ",
        "CONSTRAINT fk_qnaire_proxy_type_trigger_qnaire_id ",
          "FOREIGN KEY (qnaire_id) ",
          "REFERENCES qnaire (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_proxy_type_trigger_proxy_type_id ",
          "FOREIGN KEY (proxy_type_id) ",
          "REFERENCES ", @cenozo, ".proxy_type (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_proxy_type_trigger_question_id ",
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

CALL patch_qnaire_proxy_type();
DROP PROCEDURE IF EXISTS patch_qnaire_proxy_type;
