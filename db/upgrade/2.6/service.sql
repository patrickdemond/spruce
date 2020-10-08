SELECT "Adding additional services" AS "";

INSERT IGNORE INTO service ( subject, method, resource, restricted ) VALUES

-- framework services
( 'address', 'GET', 1, 1 ),
( 'consent', 'GET', 1, 1 ),
( 'consent_type', 'DELETE', 1, 1 ),
( 'consent_type', 'PATCH', 1, 1 ),
( 'consent_type', 'POST', 0, 1 ),
( 'event', 'GET', 1, 1 ),
( 'hold', 'GET', 1, 1 ),
( 'phone', 'GET', 1, 1 ),
( 'proxy', 'GET', 1, 1 ),
( 'trace', 'GET', 1, 1 ),
( 'search_result', 'GET', 1, 1 );
