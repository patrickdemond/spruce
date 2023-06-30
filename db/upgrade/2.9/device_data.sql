DROP PROCEDURE IF EXISTS patch_device_data;
DELIMITER //
CREATE PROCEDURE patch_device_data()
  BEGIN

    SELECT "Fixing non-unique key in device_data table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
    AND table_name = "device_data"
    AND index_name = "uq_device_id_name"
    AND NON_UNIQUE = 1;

    IF @test > 0 THEN
      ALTER TABLE device_data DROP KEY uq_device_id_name;
      ALTER TABLE device_data ADD UNIQUE KEY uq_device_id_name (device_id, name);
    END IF;

  END //
DELIMITER ;

CALL patch_device_data();
DROP PROCEDURE IF EXISTS patch_device_data;
