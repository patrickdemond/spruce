-- Patch to upgrade database to version 2.5

SET AUTOCOMMIT=0;

SOURCE service.sql
SOURCE role_has_service.sql
SOURCE response.sql
SOURCE qnaire_consent_type.sql

SOURCE update_version_number.sql

COMMIT;
