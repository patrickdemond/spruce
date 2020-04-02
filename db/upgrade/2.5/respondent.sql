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
      "CREATE TABLE IF NOT EXISTS respondent ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "qnaire_id INT UNSIGNED NOT NULL, ",
        "participant_id INT UNSIGNED NOT NULL, ",
        "invitation_mail_id INT UNSIGNED NULL DEFAULT NULL, ",
        "reminder_mail_id INT UNSIGNED NULL DEFAULT NULL, ",
        "token CHAR(19) NOT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_qnaire_id (qnaire_id ASC), ",
        "INDEX fk_participant_id (participant_id ASC), ",
        "INDEX fk_introduction_mail_id (invitation_mail_id ASC), ",
        "INDEX fk_reminder_mail_id (reminder_mail_id ASC), ",
        "UNIQUE INDEX uq_token (token ASC), ",
        "CONSTRAINT fk_respondent_qnaire_id ",
          "FOREIGN KEY (qnaire_id) ",
          "REFERENCES qnaire (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_respondent_participant_id ",
          "FOREIGN KEY (participant_id) ",
          "REFERENCES ", @cenozo, ".participant (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_respondent_introduction_mail_id ",
          "FOREIGN KEY (invitation_mail_id) ",
          "REFERENCES ", @cenozo, ".mail (id) ",
          "ON DELETE SET NULL ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_respondent_reminder_mail_id ",
          "FOREIGN KEY (reminder_mail_id) ",
          "REFERENCES ", @cenozo, ".mail (id) ",
          "ON DELETE SET NULL ",
          "ON UPDATE NO ACTION) "
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_respondent();
DROP PROCEDURE IF EXISTS patch_respondent;
