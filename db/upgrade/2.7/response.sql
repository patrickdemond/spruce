DROP PROCEDURE IF EXISTS patch_response;
DELIMITER //
CREATE PROCEDURE patch_response()
  BEGIN

    SELECT "Adding new qnaire_version column to response table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response"
    AND column_name = "qnaire_version";

    IF @test = 0 THEN
      ALTER TABLE response ADD COLUMN qnaire_version VARCHAR(45) NULL DEFAULT NULL AFTER rank;
    END IF;

  END //
DELIMITER ;

CALL patch_response();
DROP PROCEDURE IF EXISTS patch_response;


DELIMITER $$

DROP TRIGGER IF EXISTS response_AFTER_INSERT;

CREATE TRIGGER response_AFTER_INSERT AFTER INSERT ON response FOR EACH ROW
BEGIN
  CALL update_respondent_current_response( NEW.respondent_id );

  -- create all response stages if the qnaire requires them
  SELECT qnaire_id INTO @qnaire_id FROM respondent WHERE id = NEW.respondent_id;
  SELECT stages INTO @stages FROM qnaire WHERE id = @qnaire_id;

  IF @stages THEN
    INSERT INTO response_stage( response_id, stage_id, status )
    SELECT NEW.id, stage.id, 'not ready' FROM stage WHERE qnaire_id = @qnaire_id;
  END IF;
END$$

DELIMITER ;
