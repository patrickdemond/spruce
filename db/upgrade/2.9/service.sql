SELECT "Adding new services" AS "";

DELETE FROM service WHERE subject = "response_device";

INSERT IGNORE INTO service ( subject, method, resource, restricted ) VALUES
( 'answer_device', 'GET', 0, 0 ),
( 'answer_device', 'GET', 1, 0 ),
( 'answer_device', 'PATCH', 1, 1 ),
( 'equipment', 'GET', 0, 0 ),
( 'equipment_type', 'GET', 0, 0 ),
( 'equipment_type', 'GET', 1, 0 ),
( 'qnaire_equipment_type_trigger', 'DELETE', 1, 1 ),
( 'qnaire_equipment_type_trigger', 'GET', 0, 1 ),
( 'qnaire_equipment_type_trigger', 'GET', 1, 1 ),
( 'qnaire_equipment_type_trigger', 'PATCH', 1, 1 ),
( 'qnaire_equipment_type_trigger', 'POST', 0, 1 ),
( 'response', 'POST', 0, 1 );
