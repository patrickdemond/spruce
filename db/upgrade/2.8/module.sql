SELECT "Fixing bug in module_AFTER_INSERT trigger" AS "";

DROP TRIGGER IF EXISTS module_AFTER_INSERT;

DELIMITER $$

CREATE DEFINER=CURRENT_USER TRIGGER module_AFTER_INSERT AFTER INSERT ON module FOR EACH ROW
BEGIN
  INSERT INTO module_average_time SET module_id = NEW.id;

  INSERT INTO module_description( module_id, language_id, type )
  SELECT NEW.id, language_id, type.name
  FROM ( SELECT "prompt" AS name UNION SELECT "popup" AS name ) AS type, qnaire_has_language
  WHERE qnaire_id = NEW.qnaire_id;

  -- if the qnaire has stages then we may have to update them
  SELECT stages INTO @stages FROM qnaire WHERE id = NEW.qnaire_id;
  IF @stages THEN
    SELECT COUNT(*) INTO @total FROM stage WHERE qnaire_id = NEW.qnaire_id;
    IF 0 = @total THEN
      -- create a new stage for the module to belong to
      INSERT INTO stage
      SET qnaire_id = NEW.qnaire_id, first_module_id = NEW.id, last_module_id = NEW.id, rank = 1, name = "default";
    ELSEIF 1 = NEW.rank THEN
      -- when adding a first-rank module add it to the first stage
      SELECT id INTO @next_module_id FROM module WHERE qnaire_id = NEW.qnaire_id AND rank = 2;
      UPDATE stage SET first_module_id = NEW.id WHERE qnaire_id = NEW.qnaire_id AND first_module_id = @next_module_id;
    ELSE
      -- we're not the only, not the first, so set as the last if we're on a boundary
      SELECT stage.id INTO @stage_id
      FROM stage
      JOIN module ON stage.last_module_id = module.id
      WHERE stage.qnaire_id = NEW.qnaire_id
      AND module.rank = NEW.rank-1;
      IF @stage_id IS NOT NULL THEN
        UPDATE stage SET last_module_id = NEW.id WHERE qnaire_id = NEW.qnaire_id AND id = @stage_id;
      END IF;
    END IF;
  END IF;
END$$

DELIMITER ;
