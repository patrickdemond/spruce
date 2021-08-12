DROP PROCEDURE IF EXISTS patch_qnaire;
DELIMITER //
CREATE PROCEDURE patch_qnaire()
  BEGIN

    SELECT "Adding new version column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "version";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN version VARCHAR(45) NULL DEFAULT NULL AFTER name;
    END IF;

    SELECT "Adding new stages column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "stages";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN stages TINYINT(1) NOT NULL DEFAULT 0 AFTER readonly;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;


SELECT "Adding new trigger to qnaire table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS qnaire_AFTER_UPDATE$$

CREATE TRIGGER qnaire_AFTER_UPDATE AFTER UPDATE ON qnaire FOR EACH ROW
BEGIN
  IF OLD.base_language_id != NEW.base_language_id THEN
    INSERT IGNORE INTO qnaire_has_language SET qnaire_id = NEW.id, language_id = NEW.base_language_id;
  END IF;

  IF OLD.stages && !NEW.stages THEN
    DELETE FROM deviation_type WHERE qnaire_id = NEW.id;
    DELETE FROM stage WHERE qnaire_id = NEW.id;
  ELSEIF !OLD.stages && NEW.stages THEN
    SELECT COUNT(*) INTO @total FROM module WHERE qnaire_id = NEW.id;
    IF 0 < @total THEN
      SELECT MIN( rank ), MAX( rank ) INTO @min_rank, @max_rank FROM module WHERE qnaire_id = NEW.id;

      INSERT INTO stage( qnaire_id, first_module_id, last_module_id, rank, name )
      SELECT NEW.id, first_module.id, last_module.id, 1, "default"
      FROM module AS first_module, module AS last_module
      WHERE first_module.qnaire_id = NEW.id
      AND first_module.rank = @min_rank
      AND last_module.qnaire_id = NEW.id
      AND last_module.rank = @max_rank;
    END IF;
  END IF;
END$$

DELIMITER ;
