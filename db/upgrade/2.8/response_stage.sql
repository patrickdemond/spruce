DROP PROCEDURE IF EXISTS patch_response_stage;
DELIMITER //
CREATE PROCEDURE patch_response_stage()
  BEGIN

    SELECT "Adding new status to the response_stage table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response_stage"
    AND column_name = "status"
    AND column_type LIKE "%'not applicable'%";

    IF @test = 0 THEN
      ALTER TABLE response_stage
      MODIFY COLUMN status ENUM(
        'not ready', 'not applicable', 'ready', 'active', 'paused', 'skipped', 'parent skipped', 'completed'
      ) NOT NULL DEFAULT 'not ready';
    END IF;

  END //
DELIMITER ;

CALL patch_response_stage();
DROP PROCEDURE IF EXISTS patch_response_stage;
