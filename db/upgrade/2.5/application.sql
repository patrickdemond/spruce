DROP PROCEDURE IF EXISTS patch_application;
DELIMITER //
CREATE PROCEDURE patch_application()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Adding application entry into application table" AS "";

    SET @sql = CONCAT(
      "INSERT IGNORE INTO ", @cenozo, ".application ",
      "( name, title, application_type_id, url, ",
        "version, cenozo, release_based, release_event_type_id, country, timezone ) ",
      "SELECT 'pine', 'Pine', application_type.id, REPLACE( app.url, 'mastodon', 'pine' ), ",
             "'2.5', app.cenozo, 0, NULL, 'Canada', 'Canada/Eastern' ",
      "FROM ", @cenozo, ".application_type, ",
               @cenozo, ".application AS app ",
      "WHERE application_type.name = 'pine' ",
      "AND app.name = 'mastodon'"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_application();
DROP PROCEDURE IF EXISTS patch_application;
