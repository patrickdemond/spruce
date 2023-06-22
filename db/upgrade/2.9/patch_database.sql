-- Patch to upgrade database to version 2.9

SET AUTOCOMMIT=0;

SOURCE embedded_file.sql
SOURCE qnaire_report.sql
SOURCE response.sql
SOURCE qnaire.sql
SOURCE respondent.sql
SOURCE reminder.sql

SOURCE update_version_number.sql

COMMIT;
