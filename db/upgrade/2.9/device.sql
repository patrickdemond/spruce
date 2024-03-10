DROP PROCEDURE IF EXISTS patch_device;
DELIMITER //
CREATE PROCEDURE patch_device()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

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
