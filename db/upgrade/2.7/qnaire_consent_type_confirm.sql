DROP PROCEDURE IF EXISTS patch_qnarie_consent_type_confirm;
DELIMITER //
CREATE PROCEDURE patch_qnarie_consent_type_confirm()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new qnarie_consent_type_confirm table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS qnaire_consent_type_confirm ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "qnaire_id INT(10) UNSIGNED NOT NULL, ",
        "consent_type_id INT(10) UNSIGNED NOT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_qnaire_id (qnaire_id ASC), ",
        "INDEX fk_consent_type_id (consent_type_id ASC), ",
        "UNIQUE INDEX uq_qnaire_id_consent_type_id (qnaire_id ASC, consent_type_id ASC), ",
        "CONSTRAINT fk_qnaire_consent_type_confirm_qnaire_id ",
          "FOREIGN KEY (qnaire_id) ",
          "REFERENCES qnaire (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_consent_type_confirm_consent_type_id ",
          "FOREIGN KEY (consent_type_id) ",
          "REFERENCES ", @cenozo, ".consent_type (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_qnarie_consent_type_confirm();
DROP PROCEDURE IF EXISTS patch_qnarie_consent_type_confirm;
