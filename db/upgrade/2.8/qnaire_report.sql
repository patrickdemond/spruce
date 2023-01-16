DROP PROCEDURE IF EXISTS patch_qnaire_report;
DELIMITER //
CREATE PROCEDURE patch_qnaire_report()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new qnaire_report table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS qnaire_report ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "qnaire_id INT(10) UNSIGNED NOT NULL, ",
        "language_id INT(10) UNSIGNED NOT NULL, ",
        "data MEDIUMTEXT NOT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_qnaire_report_qnaire_id (qnaire_id ASC), ",
        "INDEX fk_qnaire_report_language_id (language_id ASC), ",
        "UNIQUE INDEX uq_qnaire_id_language_id (qnaire_id ASC, language_id ASC), ",
        "CONSTRAINT fk_qnaire_report_qnaire_id ",
          "FOREIGN KEY (qnaire_id) ",
          "REFERENCES qnaire (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_qnaire_report_language_id ",
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

CALL patch_qnaire_report();
DROP PROCEDURE IF EXISTS patch_qnaire_report;
