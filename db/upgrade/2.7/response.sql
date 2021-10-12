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

    SELECT "Adding new stage_selection column to response table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response"
    AND column_name = "stage_selection";

    IF @test = 0 THEN
      ALTER TABLE response ADD COLUMN stage_selection TINYINT(1) NOT NULL DEFAULT 0 AFTER page_id;
    END IF;

    SELECT "Adding new checked_in column to response table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response"
    AND column_name = "checked_in";

    IF @test = 0 THEN
      ALTER TABLE response ADD COLUMN checked_in TINYINT(1) NOT NULL DEFAULT 0 AFTER stage_selection;
    END IF;

    SELECT "Adding new comments column to response table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response"
    AND column_name = "comments";

    IF @test = 0 THEN
      ALTER TABLE response ADD COLUMN comments TEXT NULL DEFAULT NULL;
    END IF;

    SELECT "Adding new current_page_rank column to response table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "response"
    AND column_name = "current_page_rank";

    IF @test = 0 THEN
      ALTER TABLE response ADD COLUMN current_page_rank INT UNSIGNED NULL DEFAULT NULL AFTER page_id;

      -- fill in the new column
      CREATE TEMPORARY TABLE response_pages
      SELECT response.id, IF( module.id IS NULL, 0, COUNT(*) ) + response_page.rank AS total
      FROM response
      JOIN respondent ON response.respondent_id = respondent.id
      JOIN page AS response_page ON response.page_id = response_page.id
      JOIN module AS response_module ON response_page.module_id = response_module.id
      LEFT JOIN module AS module ON respondent.qnaire_id = module.qnaire_id and module.rank < response_module.rank
      LEFT JOIN page AS page ON module.id = page.module_id
      GROUP BY response.id;

      UPDATE response
      JOIN response_pages USING( id )
      SET response.current_page_rank = response_pages.total;
    END IF;

  END //
DELIMITER ;

CALL patch_response();
DROP PROCEDURE IF EXISTS patch_response;


DELIMITER $$

DROP TRIGGER IF EXISTS response_AFTER_INSERT$$
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

DROP TRIGGER IF EXISTS response_BEFORE_UPDATE$$
CREATE TRIGGER response_BEFORE_UPDATE BEFORE UPDATE ON response FOR EACH ROW
BEGIN
  -- if the page has changed then update the current page rank
  IF NEW.page_id <=> OLD.page_id THEN
    SELECT IF( module.id IS NULL, 0, COUNT(*) ) + response_page.rank INTO @pages
    FROM response
    JOIN respondent ON response.respondent_id = respondent.id
    JOIN page AS response_page ON response.page_id = response_page.id
    JOIN module AS response_module ON response_page.module_id = response_module.id
    LEFT JOIN module AS module ON respondent.qnaire_id = module.qnaire_id and module.rank < response_module.rank
    LEFT JOIN page AS page ON module.id = page.module_id
    WHERE response.id = NEW.id;

    SET NEW.current_page_rank = @pages;
  END IF;
END$$

DELIMITER ;
