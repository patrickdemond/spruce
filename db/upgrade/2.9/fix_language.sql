DROP PROCEDURE IF EXISTS fix_language;
DELIMITER //
CREATE PROCEDURE fix_language()
  BEGIN
    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Enabling French language" AS "";

    SET @sql = CONCAT("UPDATE ", @cenozo, ".language SET active = true WHERE code = 'fr'");
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL fix_language();
DROP PROCEDURE IF EXISTS fix_language;
