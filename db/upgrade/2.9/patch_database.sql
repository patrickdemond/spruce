-- Patch to upgrade database to version 2.9

SET AUTOCOMMIT=0;

SOURCE custom_report.sql
SOURCE role_has_custom_report.sql
SOURCE embedded_file.sql
SOURCE qnaire_report.sql
SOURCE qnaire_document.sql
SOURCE response.sql
SOURCE response_stage.sql
SOURCE response_stage_pause.sql
SOURCE qnaire.sql
SOURCE respondent.sql
SOURCE answer_device.sql
SOURCE response_device.sql
SOURCE answer.sql
SOURCE question.sql
SOURCE stage.sql
SOURCE service.sql
SOURCE role_has_service.sql
SOURCE reminder.sql
SOURCE device.sql
SOURCE device_data.sql
SOURCE qnaire_participant_trigger.sql
SOURCE qnaire_collection_trigger.sql
SOURCE qnaire_equipment_type_trigger.sql
SOURCE qnaire_event_type_trigger.sql
SOURCE timestamps.sql

SOURCE update_version_number.sql

COMMIT;
