DROP PROCEDURE IF EXISTS patch_response_stage;
DELIMITER //
CREATE PROCEDURE patch_response_stage()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Replacing user_id with username in response_stage table" AS "";

    -- determine the @cenozo database name
    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response_stage"
    AND column_name = "user_id";

    IF @test THEN
      ALTER TABLE response_stage
      ADD COLUMN username VARCHAR(45) NULL DEFAULT NULL AFTER page_id;

      SET @sql = CONCAT(
        "UPDATE response_stage ",
        "JOIN ", @cenozo, ".user ON response_stage.user_id = user.id ",
        "SET username = user.name"
      );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      ALTER TABLE response_stage
      DROP CONSTRAINT fk_response_stage_user_id,
      DROP INDEX fk_user_id,
      DROP COLUMN user_id;
    END IF;

  END //
DELIMITER ;

CALL patch_response_stage();
DROP PROCEDURE IF EXISTS patch_response_stage;
