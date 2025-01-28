-- Patch to upgrade database to version 2.10

SET AUTOCOMMIT=0;

SOURCE question.sql
SOURCE qnaire_report.sql

SOURCE update_version_number.sql

COMMIT;
