DROP PROCEDURE IF EXISTS patch_module;
DELIMITER //
CREATE PROCEDURE patch_module()
  BEGIN

    SELECT "Adding new stage_id column to module table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "module"
    AND column_name = "stage_id";

    IF @test = 0 THEN
      ALTER TABLE module ADD COLUMN stage_id INT UNSIGNED NULL DEFAULT NULL AFTER qnaire_id;
      ALTER TABLE module
      ADD INDEX fk_module_stage_id (stage_id ASC),
      ADD CONSTRAINT fk_module_stage_id
        FOREIGN KEY (stage_id)
        REFERENCES stage (id)
        ON DELETE SET NULL
        ON UPDATE NO ACTION;
    END IF;

  END //
DELIMITER ;

CALL patch_module();
DROP PROCEDURE IF EXISTS patch_module;
