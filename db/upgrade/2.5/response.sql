DROP PROCEDURE IF EXISTS patch_access;
DELIMITER //
CREATE PROCEDURE patch_access()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );
    
    SELECT "Creating new access table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS response ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "qnaire_id INT UNSIGNED NOT NULL, ",
        "participant_id INT UNSIGNED NOT NULL, ",
        "rank INT UNSIGNED NOT NULL, ",
        "language_id INT UNSIGNED NOT NULL, ",
        "page_id INT UNSIGNED NULL DEFAULT NULL, ",
        "token CHAR(19) NOT NULL, ",
        "submitted TINYINT(1) NOT NULL DEFAULT 0, ",
        "start_datetime DATETIME NULL DEFAULT NULL, ",
        "last_datetime DATETIME NULL DEFAULT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_qnaire_id (qnaire_id ASC), ",
        "INDEX fk_participant_id (participant_id ASC), ",
        "INDEX fk_language_id (language_id ASC), ",
        "INDEX fk_page_id (page_id ASC), ",
        "UNIQUE INDEX uq_qnaire_id_participant_id_rank (qnaire_id ASC, participant_id ASC, rank ASC), ",
        "UNIQUE INDEX uq_token (token ASC), ",
        "CONSTRAINT fk_response_qnaire_id ",
          "FOREIGN KEY (qnaire_id) ",
          "REFERENCES qnaire (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_response_participant_id ",
          "FOREIGN KEY (participant_id) ",
          "REFERENCES ", @cenozo, ".participant (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_response_language_id ",
          "FOREIGN KEY (language_id) ",
          "REFERENCES ", @cenozo, ".language (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_response_page_id ",
          "FOREIGN KEY (page_id) ",
          "REFERENCES page (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_access();
DROP PROCEDURE IF EXISTS patch_access;


DELIMITER $$

DROP TRIGGER IF EXISTS response_BEFORE_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER response_BEFORE_INSERT BEFORE INSERT ON response FOR EACH ROW
BEGIN
  -- check for duplicates in non-repeated qnaires
  SELECT repeated INTO @repeated FROM qnaire WHERE id = NEW.qnaire_id;
  IF NOT @repeated THEN
    SELECT COUNT(*) INTO @existing
    FROM response
    WHERE qnaire_id = NEW.qnaire_id
    AND participant_id = NEW.participant_id;

    IF 0 < @existing THEN
      -- trigger unique key conflict
      SET @sql = CONCAT(
        "Duplicate entry '",
        NEW.qnaire_id, "-", NEW.participant_id,
        "' for key 'uq_qnaire_id_participant_id'"
      );
      SIGNAL SQLSTATE '23000' SET MESSAGE_TEXT = @sql, MYSQL_ERRNO = 1062;
    END IF;
  END IF;
END$$


DROP TRIGGER IF EXISTS response_BEFORE_UPDATE $$
CREATE DEFINER = CURRENT_USER TRIGGER response_BEFORE_UPDATE BEFORE UPDATE ON response FOR EACH ROW
BEGIN
  -- if changing the participant or qnaire check for duplicates in non-repeated qnaires
  IF NEW.participant_id != OLD.participant_id OR NEW.qnaire_id != OLD.qnaire_id THEN
    SELECT repeated INTO @repeated FROM qnaire WHERE id = NEW.qnaire_id;
    IF NOT @repeated THEN
      SELECT COUNT(*) INTO @existing
      FROM response
      WHERE qnaire_id = NEW.qnaire_id
      AND participant_id = NEW.participant_id
      AND id != NEW.id;

      IF 0 < @existing THEN
        -- trigger unique key conflict
        SET @sql = CONCAT(
          "Duplicate entry '",
          NEW.qnaire_id, "-", NEW.participant_id,
          "' for key 'uq_qnaire_id_participant_id'"
        );
        SIGNAL SQLSTATE '23000' SET MESSAGE_TEXT = @sql, MYSQL_ERRNO = 1062;
      END IF;
    END IF;
  END IF;
END$$

DELIMITER ;
