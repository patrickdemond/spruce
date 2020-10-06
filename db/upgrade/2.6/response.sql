DROP PROCEDURE IF EXISTS patch_response;
DELIMITER //
CREATE PROCEDURE patch_response()
  BEGIN

    SELECT "Adding new show_hidden column to response table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response"
    AND column_name = "show_hidden";

    IF @test = 0 THEN
      ALTER TABLE response ADD COLUMN show_hidden TINYINT(1) NOT NULL DEFAULT 0 AFTER submitted;
    END IF;

  END //
DELIMITER ;

CALL patch_response();
DROP PROCEDURE IF EXISTS patch_response;
