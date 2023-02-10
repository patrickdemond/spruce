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
        "'alternate_consent_type', 'device_data', 'embedded_file', 'indicator', 'lookup', 'lookup_item', ",
        "'notation', 'proxy', 'proxy_type', 'qnaire_alternate_consent_type_trigger', ",
        "'qnaire_participant_trigger', 'qnaire_proxy_type_trigger', 'qnaire_report', 'qnaire_report_data', ",
        "'response_device' "
      ") ",
      "AND service.restricted = 1"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    -- machine (used for cypress, not interviewing instances)
    SET @sql = CONCAT(
      "DELETE FROM role_has_service ",
      "WHERE role_id = ( SELECT id FROM ", @cenozo, ".role WHERE name = 'machine' )"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    SET @sql = CONCAT(
      "INSERT IGNORE INTO role_has_service( role_id, service_id ) ",
      "SELECT role.id, service.id ",
      "FROM ", @cenozo, ".role, service ",
      "WHERE role.name = 'machine' ",
      "AND service.subject IN( 'response_device' ) ",
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
      "AND service.subject IN( 'indicator', 'lookup', 'lookup_item', 'proxy', 'response_device' ) ",
      "AND service.restricted = 1"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

    -- readonly
    SET @sql = CONCAT(
      "INSERT IGNORE INTO role_has_service( role_id, service_id ) ",
      "SELECT role.id, service.id ",
      "FROM ", @cenozo, ".role, service ",
      "WHERE role.name = 'readonly' ",
      "AND service.subject IN( 'address', 'participant' ) ",
      "AND service.method = 'GET' ",
      "AND service.resource = 1 ",
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
      "AND service.restricted = 1 ",
      "AND ( ",
        "( service.subject = 'response' AND service.method = 'DELETE' AND service.resource = 1 ) OR ",
        "( service.subject IN ( 'indicator', 'lookup', 'lookup_item', 'response_device' ) ) ",
      ")"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_role_has_service();
DROP PROCEDURE IF EXISTS patch_role_has_service;
