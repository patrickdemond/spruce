-- Patch to upgrade database to version 2.6

SET AUTOCOMMIT=0;

SOURCE role.sql
SOURCE application_type_has_role.sql
SOURCE service.sql
SOURCE role_has_service.sql
SOURCE response.sql
SOURCE qnaire_consent_type.sql
SOURCE reminder.sql
SOURCE reminder_description.sql
SOURCE qnaire_has_language.sql
SOURCE qnaire.sql
SOURCE qnaire_description.sql
SOURCE respondent_mail.sql
SOURCE question_option.sql

SOURCE update_version_number.sql

COMMIT;
