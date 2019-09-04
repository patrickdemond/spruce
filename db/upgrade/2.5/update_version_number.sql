DROP PROCEDURE IF EXISTS upgrade_application_number;
DELIMITER //
CREATE PROCEDURE upgrade_application_number()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "linden", "cenozo" ) );
    
    SELECT "Upgrading application version number" AS "";

    SET @sql = CONCAT(
      "UPDATE ", @cenozo, ".application ",
      "SET version = '2.5' ",
      "WHERE '", DATABASE(), "' LIKE CONCAT( '%_', name )"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL upgrade_application_number();
DROP PROCEDURE IF EXISTS upgrade_application_number;
