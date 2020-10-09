DROP PROCEDURE IF EXISTS patch_respondent_mail;
DELIMITER //
CREATE PROCEDURE patch_respondent_mail()
  BEGIN

    SELECT "Removing type column from respondent_mail table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "respondent_mail"
    AND column_name = "reminder_id";

    IF @test = 0 THEN
      ALTER TABLE respondent_mail
      ADD COLUMN reminder_id INT UNSIGNED NULL DEFAULT NULL,
      ADD INDEX fk_reminder_id (reminder_id ASC),
      ADD CONSTRAINT fk_respondent_mail_reminder_id
        FOREIGN KEY (reminder_id)
        REFERENCES reminder (id)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;

      UPDATE respondent_mail
      JOIN respondent ON respondent_mail.respondent_id = respondent.id
      JOIN reminder USING( qnaire_id )
      SET respondent_mail.reminder_id = reminder.id
      WHERE respondent_mail.type = "reminder";

      -- drop the reminder settings from the respondent_mail table
      ALTER TABLE respondent_mail DROP INDEX uq_respondent_id_type_rank,
                                  DROP COLUMN type,
                                  ADD UNIQUE INDEX uq_respondent_id_reminder_id_rank (respondent_id ASC, reminder_id ASC, rank ASC);
    END IF;

  END //
DELIMITER ;

CALL patch_respondent_mail();
DROP PROCEDURE IF EXISTS patch_respondent_mail;


DELIMITER $$

DROP TRIGGER IF EXISTS respondent_mail_BEFORE_INSERT $$

CREATE DEFINER = CURRENT_USER TRIGGER respondent_mail_BEFORE_INSERT BEFORE INSERT ON respondent_mail FOR EACH ROW
BEGIN
  SET @test = (
    SELECT COUNT(*) FROM respondent_mail
    WHERE respondent_id <=> NEW.respondent_id
    AND reminder_id <=> NEW.reminder_id
    AND rank = NEW.rank
  );
  IF @test > 0 THEN
    -- trigger unique key conflict
    SET @sql = CONCAT(
      "Duplicate entry '",
      IFNULL( NEW.respondent_id, "NULL" ), "-", IFNULL( NEW.reminder_id, "NULL" ), "-", NEW.rank,
      "' for key 'uq_respondent_id_reminder_id_rank'"
    );
    SIGNAL SQLSTATE '23000' SET MESSAGE_TEXT = @sql, MYSQL_ERRNO = 1062;
  END IF;
END$$



DROP TRIGGER IF EXISTS respondent_mail_BEFORE_UPDATE $$

CREATE DEFINER = CURRENT_USER TRIGGER respondent_mail_BEFORE_UPDATE BEFORE UPDATE ON respondent_mail FOR EACH ROW
BEGIN
  SET @test = (
    SELECT COUNT(*) FROM respondent_mail
    WHERE respondent_id <=> NEW.respondent_id
    AND reminder_id <=> NEW.reminder_id
    AND rank = NEW.rank
    AND respondent_mail.id != NEW.id
  );
  IF @test > 0 THEN
    -- trigger unique key conflict
    SET @sql = CONCAT(
      "Duplicate entry '",
      IFNULL( NEW.respondent_id, "NULL" ), "-", IFNULL( NEW.reminder_id, "NULL" ), "-", NEW.rank,
      "' for key 'uq_respondent_id_reminder_id_rank'"
    );
    SIGNAL SQLSTATE '23000' SET MESSAGE_TEXT = @sql, MYSQL_ERRNO = 1062;
  END IF;
END$$

DELIMITER ;
