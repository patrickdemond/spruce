DROP PROCEDURE IF EXISTS patch_application_has_site;
DELIMITER //
CREATE PROCEDURE patch_application_has_site()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );

    SET @sql = CONCAT(
      "SELECT COUNT(*) INTO @total ",
      "FROM ", @cenozo, ".application_has_site ",
      "JOIN ", @cenozo, ".application ON application_has_site.application_id = application.id ",
      "WHERE application.name = 'pine'"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    IF @total = 0 THEN
      SELECT "Adding beartooth and sabretooth sites to the new pine application" AS "";

      SET @sql = CONCAT(
        "INSERT IGNORE INTO ", @cenozo, ".application_has_site( application_id, site_id ) ",
        "SELECT DISTINCT application.id, site.id ",
        "FROM ", @cenozo, ".application, ", @cenozo, ".site ",
        "JOIN ", @cenozo, ".application_has_site ON site.id = application_has_site.site_id ",
        "JOIN ", @cenozo, ".application AS btapp ON application_has_site.application_id = btapp.id ",
        "JOIN ", @cenozo, ".application_type ON btapp.application_type_id = application_type.id ",
        "WHERE application.name = 'pine' ",
        "AND application_type.name IN( 'beartooth', 'sabretooth' )"
      );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;
    END IF;

  END //
DELIMITER ;

CALL patch_application_has_site();
DROP PROCEDURE IF EXISTS patch_application_has_site;
