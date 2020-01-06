DROP PROCEDURE IF EXISTS patch_user;
  DELIMITER //
  CREATE PROCEDURE patch_user()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );

    SELECT "Adding generic qnaire respondent user" AS "";

    SET @sql = CONCAT(
      "INSERT IGNORE INTO ", @cenozo, ".user( name, first_name, last_name, use_12hour_clock, email ) ",
      "VALUES ( 'pine', 'utility', 'account', 1, 'pine@clsa-elcv.ca' )" );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

-- now call the procedure and remove the procedure
CALL patch_user();
DROP PROCEDURE IF EXISTS patch_user;
