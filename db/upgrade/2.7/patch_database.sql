-- Patch to upgrade database to version 2.7

SET AUTOCOMMIT=0;

SOURCE question.sql
SOURCE question_option.sql

SOURCE update_version_number.sql

COMMIT;
