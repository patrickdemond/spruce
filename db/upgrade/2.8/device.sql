DROP PROCEDURE IF EXISTS patch_device;
DELIMITER //
CREATE PROCEDURE patch_device()
  BEGIN

    SELECT "Adding new emulate column to device table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "device"
    AND column_name = "emulate";

    IF @test = 0 THEN
      ALTER TABLE device ADD COLUMN emulate TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

  END //
DELIMITER ;

CALL patch_device();
DROP PROCEDURE IF EXISTS patch_device;
