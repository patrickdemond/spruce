DROP PROCEDURE IF EXISTS patch_role;
  DELIMITER //
  CREATE PROCEDURE patch_role()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Adding new roles" AS "";

    SET @sql = CONCAT(
      "INSERT IGNORE INTO ", @cenozo, ".role( name, tier, all_sites ) ",
      "VALUES ( 'respondent', 1, 0 )" );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

-- now call the procedure and remove the procedure
CALL patch_role();
DROP PROCEDURE IF EXISTS patch_role;
