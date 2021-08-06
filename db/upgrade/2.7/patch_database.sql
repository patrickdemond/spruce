-- Patch to upgrade database to version 2.7

SET AUTOCOMMIT=0;

SOURCE stage.sql
SOURCE deviation_type.sql
SOURCE response_stage.sql
SOURCE question.sql
SOURCE question_option.sql
SOURCE qnaire.sql
SOURCE response.sql

SOURCE update_version_number.sql

COMMIT;
