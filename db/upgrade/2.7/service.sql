SELECT "Adding new services" AS "";

INSERT IGNORE INTO service ( subject, method, resource, restricted ) VALUES

( 'debug', 'POST', 0, 0 ),
( 'deviation_type', 'DELETE', 1, 1 ),
( 'deviation_type', 'GET', 0, 1 ),
( 'deviation_type', 'GET', 1, 1 ),
( 'deviation_type', 'PATCH', 1, 1 ),
( 'deviation_type', 'POST', 0, 1 ),
( 'response', 'PATCH', 1, 1 ),
( 'response_stage', 'GET', 0, 1 ),
( 'response_stage', 'GET', 1, 1 ),
( 'response_stage', 'PATCH', 1, 1 ),
( 'stage', 'DELETE', 1, 1 ),
( 'stage', 'GET', 0, 1 ),
( 'stage', 'GET', 1, 1 ),
( 'stage', 'PATCH', 1, 1 ),
( 'stage', 'POST', 0, 1 );
