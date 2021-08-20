DROP PROCEDURE IF EXISTS patch_response_stage;
DELIMITER //
CREATE PROCEDURE patch_response_stage()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new response_stage table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS response_stage ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "response_id INT(10) UNSIGNED NOT NULL, ",
        "stage_id INT UNSIGNED NOT NULL, ",
        "page_id INT(10) UNSIGNED NULL DEFAULT NULL, ",
        "user_id INT(10) UNSIGNED NULL DEFAULT NULL, ",
        "status ENUM('not ready', 'ready', 'active', 'paused', 'skipped', 'completed') NOT NULL DEFAULT 'not ready', ",
        "deviation_type_id INT UNSIGNED NULL DEFAULT NULL, ",
        "comments TEXT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_response_id (response_id ASC), ",
        "INDEX fk_stage_id (stage_id ASC), ",
        "INDEX fk_deviation_type_id (deviation_type_id ASC), ",
        "INDEX fk_page_id (page_id ASC), ",
        "UNIQUE INDEX uq_response_id_stage_id (response_id ASC, stage_id ASC), ",
        "INDEX fk_user_id (user_id ASC), ",
        "CONSTRAINT fk_response_stage_response_id ",
          "FOREIGN KEY (response_id) ",
          "REFERENCES response (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_response_stage_stage_id ",
          "FOREIGN KEY (stage_id) ",
          "REFERENCES stage (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_response_stage_deviation_type_id ",
          "FOREIGN KEY (deviation_type_id) ",
          "REFERENCES deviation_type (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_response_stage_page_id ",
          "FOREIGN KEY (page_id) ",
          "REFERENCES page (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_response_stage_user_id ",
          "FOREIGN KEY (user_id) ",
          "REFERENCES ", @cenozo, ".user (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_response_stage();
DROP PROCEDURE IF EXISTS patch_response_stage;
