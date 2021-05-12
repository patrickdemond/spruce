DROP PROCEDURE IF EXISTS patch_response;
DELIMITER //
CREATE PROCEDURE patch_response()
  BEGIN

    SELECT "Adding new qnaire_version column to response table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response"
    AND column_name = "qnaire_version";

    IF @test = 0 THEN
      ALTER TABLE response ADD COLUMN qnaire_version VARCHAR(45) NULL DEFAULT NULL AFTER rank;
    END IF;

  END //
DELIMITER ;

CALL patch_response();
DROP PROCEDURE IF EXISTS patch_response;
