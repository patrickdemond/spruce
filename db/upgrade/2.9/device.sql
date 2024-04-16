DROP PROCEDURE IF EXISTS patch_device;
DELIMITER //
CREATE PROCEDURE patch_device()
  BEGIN

    SELECT "Adding equipment_type columns to device table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "device"
    AND column_name = "form";

    IF @test = 0 THEN
      ALTER TABLE device ADD COLUMN form TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

  END //
DELIMITER ;

CALL patch_device();
DROP PROCEDURE IF EXISTS patch_device;
