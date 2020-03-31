DROP PROCEDURE IF EXISTS patch_application_type;
DELIMITER //
CREATE PROCEDURE patch_application_type()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Adding pine as new application_type" AS "";

    SET @sql = CONCAT( "INSERT IGNORE INTO ", @cenozo, ".application_type SET name = 'pine'" );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_application_type();
DROP PROCEDURE IF EXISTS patch_application_type;
