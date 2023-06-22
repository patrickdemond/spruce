DROP PROCEDURE IF EXISTS patch_reminder;
DELIMITER //
CREATE PROCEDURE patch_reminder()
  BEGIN
    SELECT "Renaming columns in reminder table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "reminder"
    AND column_name = "offset";

    IF 1 = @test THEN
      ALTER TABLE reminder CHANGE `offset` delay_offset INT(10) UNSIGNED NOT NULL;
    END IF;

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "reminder"
    AND column_name = "unit";

    IF 1 = @test THEN
      ALTER TABLE reminder CHANGE unit delay_unit ENUM('hour', 'day', 'week', 'month') NOT NULL;
    END IF;

  END //
DELIMITER ;

CALL patch_reminder();
DROP PROCEDURE IF EXISTS patch_reminder;
