SELECT "Creating new response_stage_pause table" AS "";

CREATE TABLE IF NOT EXISTS response_stage_pause (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  response_stage_id INT(10) UNSIGNED NOT NULL,
  username VARCHAR(45) NOT NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX fk_response_stage_id (response_stage_id ASC),
  CONSTRAINT fk_response_stage_pause_response_stage_id
    FOREIGN KEY (response_stage_id)
    REFERENCES response_stage (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
  )
ENGINE = InnoDB;


DROP PROCEDURE IF EXISTS patch_response_stage_pause;
DELIMITER //
CREATE PROCEDURE patch_response_stage_pause()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Replacing user_id with username in response_stage_pause table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response_stage_pause"
    AND column_name = "user_id";

    IF @test THEN
      ALTER TABLE response_stage_pause
      ADD COLUMN username VARCHAR(45) NOT NULL AFTER response_stage_id;

      SET @sql = CONCAT(
        "UPDATE response_stage_pause ",
        "JOIN ", @cenozo, ".user ON response_stage_pause.user_id = user.id ",
        "SET username = user.name"
      );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      ALTER TABLE response_stage_pause
      DROP CONSTRAINT fk_response_pause_user_id,
      DROP INDEX fk_user_id,
      DROP COLUMN user_id;
    END IF;

  END //
DELIMITER ;

CALL patch_response_stage_pause();
DROP PROCEDURE IF EXISTS patch_response_stage_pause;
