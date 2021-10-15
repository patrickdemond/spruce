-- Patch to upgrade database to version 2.7

SET AUTOCOMMIT=0;

SOURCE application_type_has_role.sql
SOURCE stage.sql
SOURCE deviation_type.sql
SOURCE response_stage.sql
SOURCE module.sql
SOURCE device.sql
SOURCE question.sql
SOURCE question_option.sql
SOURCE qnaire.sql
SOURCE response.sql
SOURCE respondent.sql
SOURCE service.sql
SOURCE role_has_service.sql
SOURCE qnaire_consent_type_confirm.sql
SOURCE qnaire_consent_type_trigger.sql
SOURCE page.sql

SOURCE update_version_number.sql

COMMIT;
