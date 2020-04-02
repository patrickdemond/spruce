DROP PROCEDURE IF EXISTS patch_respondent;
DELIMITER //
CREATE PROCEDURE patch_respondent()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );
    
    SELECT "Creating new respondent table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS respondent_mail ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "respondent_id INT UNSIGNED NOT NULL, ",
        "mail_id INT UNSIGNED NOT NULL, ",
        "type ENUM('invitation', 'reminder') NOT NULL, ",
        "rank INT UNSIGNED NOT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_respondent_id (respondent_id ASC), ",
        "INDEX fk_mail_id (mail_id ASC), ",
        "UNIQUE INDEX uq_respondent_id_type_rank (respondent_id ASC, type ASC, rank ASC), ",
        "CONSTRAINT fk_respondent_mail_respondent_id ",
          "FOREIGN KEY (respondent_id) ",
          "REFERENCES respondent (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_respondent_mail_mail_id ",
          "FOREIGN KEY (mail_id) ",
          "REFERENCES ", @cenozo, ".mail (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_respondent();
DROP PROCEDURE IF EXISTS patch_respondent;
