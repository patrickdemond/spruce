DROP PROCEDURE IF EXISTS patch_page;
DELIMITER //
CREATE PROCEDURE patch_page()
  BEGIN

    SELECT "Adding new tabulate column in page table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "page"
    AND column_name = "tabulate";

    IF @test = 0 THEN
      ALTER TABLE page ADD COLUMN tabulate TINYINT(1) NOT NULL DEFAULT 0 AFTER precondition;
    END IF;

  END //
DELIMITER ;

CALL patch_page();
DROP PROCEDURE IF EXISTS patch_page;
