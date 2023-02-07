-- Patch to upgrade database to version 2.8

SET AUTOCOMMIT=0;

SOURCE table_character_sets.sql

SOURCE embedded_file.sql
SOURCE image.sql
SOURCE lookup.sql
SOURCE question.sql
SOURCE question_option.sql
SOURCE answer.sql
SOURCE qnaire_description.sql
SOURCE qnaire_has_language.sql
SOURCE qnaire_consent_type_trigger.sql
SOURCE qnaire_proxy_type_trigger.sql
SOURCE qnaire_alternate_consent_type_trigger.sql
SOURCE qnaire_participant_trigger.sql
SOURCE module.sql
SOURCE service.sql
SOURCE role_has_service.sql
SOURCE device_data.sql
SOURCE qnaire.sql
SOURCE lookup_item.sql
SOURCE indicator.sql
SOURCE indicator_has_lookup_item.sql
SOURCE page.sql
SOURCE report_type.sql
SOURCE qnaire_report.sql
SOURCE qnaire_report_data.sql

SOURCE update_version_number.sql

COMMIT;
