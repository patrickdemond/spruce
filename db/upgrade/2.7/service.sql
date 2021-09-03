SELECT "Adding new services" AS "";

INSERT IGNORE INTO service ( subject, method, resource, restricted ) VALUES

( 'address', 'GET', 0, 1 ),
( 'address', 'PATCH', 1, 1 ),
( 'consent', 'GET', 0, 1 ),
( 'consent', 'PATCH', 1, 1 ),
( 'consent', 'POST', 0, 1 ),
( 'debug', 'POST', 0, 0 ),
( 'deviation_type', 'DELETE', 1, 1 ),
( 'deviation_type', 'GET', 0, 1 ),
( 'deviation_type', 'GET', 1, 1 ),
( 'deviation_type', 'PATCH', 1, 1 ),
( 'deviation_type', 'POST', 0, 1 ),
( 'participant', 'PATCH', 1, 1 ),
( 'qnaire_consent_type_confirm', 'DELETE', 1, 1 ),
( 'qnaire_consent_type_confirm', 'GET', 0, 1 ),
( 'qnaire_consent_type_confirm', 'GET', 1, 1 ),
( 'qnaire_consent_type_confirm', 'PATCH', 1, 1 ),
( 'qnaire_consent_type_confirm', 'POST', 0, 1 ),
( 'response', 'PATCH', 1, 1 ),
( 'response_stage', 'GET', 0, 1 ),
( 'response_stage', 'GET', 1, 1 ),
( 'response_stage', 'PATCH', 1, 1 ),
( 'stage', 'DELETE', 1, 1 ),
( 'stage', 'GET', 0, 1 ),
( 'stage', 'GET', 1, 1 ),
( 'stage', 'PATCH', 1, 1 ),
( 'stage', 'POST', 0, 1 );

SELECT "Renaming some services" AS "";

UPDATE service SET subject = "qnaire_consent_type_trigger" WHERE subject = "qnaire_consent_type";
