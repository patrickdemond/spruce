DROP PROCEDURE IF EXISTS patch_application_type;
DELIMITER //
CREATE PROCEDURE patch_application_type()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );

    SELECT "Adding pine as new application_type" AS "";

    SET @sql = CONCAT( "INSERT IGNORE INTO ", @cenozo, ".application_type SET name = 'pine'" );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_application_type();
DROP PROCEDURE IF EXISTS patch_application_type;
