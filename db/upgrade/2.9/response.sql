DROP PROCEDURE IF EXISTS patch_response;
DELIMITER //
CREATE PROCEDURE patch_response()
  BEGIN
    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Adding missing foreign keys to response table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE constraint_schema = DATABASE()
    AND table_name = "response"
    AND constraint_name = "fk_respondent_respondent_id";

    IF 1 = @test THEN
      ALTER TABLE response DROP CONSTRAINT fk_respondent_respondent_id;
      ALTER TABLE response
      ADD CONSTRAINT fk_response_respondent_id FOREIGN KEY (respondent_id) REFERENCES respondent (id)
      ON DELETE CASCADE ON UPDATE NO ACTION;
    END IF;

    SELECT COUNT(*) INTO @test
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE constraint_schema = DATABASE()
    AND table_name = "response"
    AND constraint_name = "fk_response_page_id";

    IF 0 = @test THEN
      ALTER TABLE response
      ADD KEY fk_page_id (page_id),
      ADD CONSTRAINT fk_response_page_id FOREIGN KEY (page_id) REFERENCES page (id)
      ON DELETE SET NULL ON UPDATE NO ACTION;
    END IF;

    SELECT COUNT(*) INTO @test
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE constraint_schema = DATABASE()
    AND table_name = "response"
    AND constraint_name = "fk_response_language_id";

    IF 0 = @test THEN
      SET @sql = CONCAT(
        "ALTER TABLE response ",
        "ADD KEY fk_language_id (language_id), ",
        "ADD CONSTRAINT fk_response_language_id FOREIGN KEY (language_id) REFERENCES ", @cenozo, ".language (id) ",
        "ON DELETE NO ACTION ON UPDATE NO ACTION"
      );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;
    END IF;
  END //
DELIMITER ;

CALL patch_response();
DROP PROCEDURE IF EXISTS patch_response;


SELECT "Updating triggers in the response table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS response_AFTER_DELETE;
CREATE DEFINER = CURRENT_USER TRIGGER response_AFTER_DELETE AFTER DELETE ON response FOR EACH ROW
BEGIN
  CALL update_respondent_current_response( OLD.respondent_id );
  SELECT COUNT(*) INTO @test FROM response WHERE respondent_id = OLD.respondent_id;
  IF 0 = @test THEN
    UPDATE respondent SET end_datetime = NULL WHERE id = OLD.respondent_id;
  END IF;
END$$

DELIMITER ;
