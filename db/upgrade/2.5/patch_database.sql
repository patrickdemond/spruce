-- Patch to upgrade database to version 2.5

SET AUTOCOMMIT=0;

SOURCE application_type.sql
SOURCE application_type_has_role.sql
SOURCE application.sql
SOURCE application_has_site.sql

SOURCE access.sql
SOURCE service.sql
SOURCE role_has_service.sql
SOURCE setting.sql
SOURCE writelog.sql

SOURCE survey.sql
SOURCE attribute.sql
SOURCE module.sql
SOURCE page.sql
SOURCE question.sql
SOURCE question_answer.sql
SOURCE requisite_group.sql
SOURCE requisite.sql
SOURCE response.sql
SOURCE response_attribute.sql
SOURCE answer.sql

SOURCE update_version_number.sql

COMMIT;
