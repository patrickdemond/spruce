SELECT "Modifying triggers in page table" AS "";

DELIMITER $$

DROP TRIGGER IF EXISTS page_AFTER_INSERT$$
CREATE TRIGGER page_AFTER_INSERT
AFTER INSERT ON page
FOR EACH ROW
BEGIN
  INSERT INTO page_average_time SET page_id = NEW.id;

  INSERT INTO page_description( page_id, language_id, type )
  SELECT NEW.id, language_id, type.name
  FROM ( SELECT "prompt" AS name UNION SELECT "popup" AS name ) AS type, qnaire_has_language
  JOIN module ON qnaire_has_language.qnaire_id = module.qnaire_id
  WHERE module.id = NEW.module_id;

  -- update the qnaire's total number of pages
  SELECT qnaire_id INTO @qnaire_id FROM module WHERE id = NEW.module_id;

  SELECT COUNT(*) INTO @pages
  FROM qnaire
  JOIN module ON qnaire.id = module.qnaire_id
  JOIN page ON module.id = page.module_id
  WHERE qnaire.id = @qnaire_id;

  UPDATE qnaire SET total_pages = @pages WHERE id = @qnaire_id;

END$$

DROP TRIGGER IF EXISTS page_AFTER_DELETE$$
CREATE TRIGGER page_AFTER_DELETE AFTER DELETE ON page FOR EACH ROW
BEGIN
  -- update the qnaire's total number of pages
  SELECT qnaire_id INTO @qnaire_id FROM module WHERE id = OLD.module_id;

  SELECT IF( page.id IS NULL, 0, COUNT(*) ) INTO @pages
  FROM qnaire
  LEFT JOIN module ON qnaire.id = module.qnaire_id
  LEFT JOIN page ON module.id = page.module_id
  WHERE qnaire.id = @qnaire_id;

  UPDATE qnaire SET total_pages = @pages WHERE id = @qnaire_id;
END$$

DELIMITER ;
