DROP PROCEDURE IF EXISTS patch_role_has_service;
DELIMITER //
CREATE PROCEDURE patch_role_has_service()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );
    
    -- administrator
    SET @sql = CONCAT(
      "INSERT IGNORE INTO role_has_service( role_id, service_id ) ",
      "SELECT role.id, service.id ",
      "FROM ", @cenozo, ".role, service ",
      "WHERE role.name = 'administrator' ",
      "AND service.subject IN( ",
        "'address', 'consent', 'deviation_type', 'device', 'participant', ",
        "'qnaire_consent_type_confirm', 'response', 'response_stage', 'stage' ",
      ") ",
      "AND service.restricted = 1"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    -- interviewer
    SET @sql = CONCAT(
      "INSERT IGNORE INTO role_has_service( role_id, service_id ) ",
      "SELECT role.id, service.id ",
      "FROM ", @cenozo, ".role, service ",
      "WHERE role.name = 'interviewer' ",
      "AND service.subject IN( 'address', 'consent', 'participant', 'response_stage' ) ",
      "AND service.restricted = 1"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    SET @sql = CONCAT(
      "INSERT IGNORE INTO role_has_service( role_id, service_id ) ",
      "SELECT role.id, service.id ",
      "FROM ", @cenozo, ".role, service ",
      "WHERE role.name = 'interviewer' ",
      "AND service.subject IN( 'deviation_type' ) ",
      "AND service.method = 'GET' ",
      "AND service.restricted = 1"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    SET @sql = CONCAT(
      "INSERT IGNORE INTO role_has_service( role_id, service_id ) ",
      "SELECT role.id, service.id ",
      "FROM ", @cenozo, ".role, service ",
      "WHERE role.name = 'interviewer' ",
      "AND service.subject = 'response' ",
      "AND service.method = 'PATCH' ",
      "AND service.restricted = 1"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    -- respondent
    SET @sql = CONCAT(
      "INSERT IGNORE INTO role_has_service( role_id, service_id ) ",
      "SELECT role.id, service.id ",
      "FROM ", @cenozo, ".role, service ",
      "WHERE role.name = 'respondent' ",
      "AND service.subject IN( 'address', 'participant' ) ",
      "AND service.method = 'GET' ",
      "AND service.resource = true ",
      "AND service.restricted = 1"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_role_has_service();
DROP PROCEDURE IF EXISTS patch_role_has_service;
