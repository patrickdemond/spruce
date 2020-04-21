DROP PROCEDURE IF EXISTS patch_report_restriction;
  DELIMITER //
  CREATE PROCEDURE patch_report_restriction()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Adding records to report_restriction table" AS "";

    SET @sql = CONCAT(
      "INSERT IGNORE INTO ", @cenozo, ".report_restriction ( ",
        "report_type_id, rank, name, title, mandatory, null_allowed, restriction_type, custom, subject, description ) ",
      "SELECT report_type.id, 1, 'qnaire', 'Questionnaire', 1, 0, 'table', 0, 'qnaire', 'Select a questionnaire.' ",
      "FROM ", @cenozo, ".report_type ",
      "WHERE report_type.name = 'respondent'"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    SET @sql = CONCAT(
      "INSERT IGNORE INTO ", @cenozo, ".report_restriction ( ",
        "report_type_id, rank, name, title, mandatory, null_allowed, restriction_type, custom, subject, description ) ",
      "SELECT report_type.id, 2, 'submitted', 'Submitted', 0, 0, 'boolean', 0, 'response.submitted', 'Submitted' ",
      "FROM ", @cenozo, ".report_type ",
      "WHERE report_type.name = 'respondent'"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    SET @sql = CONCAT(
      "INSERT IGNORE INTO ", @cenozo, ".report_restriction ( ",
        "report_type_id, rank, name, title, mandatory, null_allowed, restriction_type, custom, subject, operator, description ) ",
      "SELECT report_type.id, 3, 'start_datetime', 'Start Date & Time', 0, 0, 'datetime', 0, 'response.start_datetime', '>=', 'Responses started on or after the given date & time' ",
      "FROM ", @cenozo, ".report_type ",
      "WHERE report_type.name = 'respondent'"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    SET @sql = CONCAT(
      "INSERT IGNORE INTO ", @cenozo, ".report_restriction ( ",
        "report_type_id, rank, name, title, mandatory, null_allowed, restriction_type, custom, subject, operator, description ) ",
      "SELECT report_type.id, 4, 'last_datetime', 'Last Date & Time', 0, 0, 'datetime', 0, 'response.last_datetime', '<=', 'Responses last answered on or before the given date & time' ",
      "FROM ", @cenozo, ".report_type ",
      "WHERE report_type.name = 'respondent'"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

-- now call the procedure and remove the procedure
CALL patch_report_restriction();
DROP PROCEDURE IF EXISTS patch_report_restriction;
