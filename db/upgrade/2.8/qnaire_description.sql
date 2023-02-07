DROP PROCEDURE IF EXISTS patch_qnaire_description;
DELIMITER //
CREATE PROCEDURE patch_qnaire_description()
  BEGIN

    SELECT "Adding new type to the qnaire_description table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire_description"
    AND column_name = "type"
    AND column_type LIKE "%'incompatible'%";

    IF @test = 0 THEN
      ALTER TABLE qnaire_description
      MODIFY COLUMN type ENUM('introduction', 'conclusion', 'closed', 'invitation subject', 'invitation body', 'incompatible') NOT NULL;

      INSERT IGNORE INTO qnaire_description( qnaire_id, language_id, type )
      SELECT qnaire_id, language_id, 'introduction' FROM qnaire_has_language;

      INSERT IGNORE INTO qnaire_description( qnaire_id, language_id, type )
      SELECT qnaire_id, language_id, 'conclusion' FROM qnaire_has_language;

      INSERT IGNORE INTO qnaire_description( qnaire_id, language_id, type )
      SELECT qnaire_id, language_id, 'closed' FROM qnaire_has_language;

      INSERT IGNORE INTO qnaire_description( qnaire_id, language_id, type )
      SELECT qnaire_id, language_id, 'invitation subject' FROM qnaire_has_language;

      INSERT IGNORE INTO qnaire_description( qnaire_id, language_id, type )
      SELECT qnaire_id, language_id, 'invitation body' FROM qnaire_has_language;

      INSERT IGNORE INTO qnaire_description( qnaire_id, language_id, type )
      SELECT qnaire_id, language_id, 'incompatible' FROM qnaire_has_language;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire_description();
DROP PROCEDURE IF EXISTS patch_qnaire_description;
