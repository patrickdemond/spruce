-- Patch to upgrade database to version 2.8

SET AUTOCOMMIT=0;

SOURCE table_character_sets.sql

SOURCE image.sql
SOURCE question.sql
SOURCE answer.sql
SOURCE qnaire_proxy_type_trigger.sql
SOURCE qnaire_alternate_consent_type_trigger.sql
SOURCE module.sql
SOURCE service.sql
SOURCE role_has_service.sql

SOURCE update_version_number.sql

COMMIT;
