SELECT "Adding new services" AS "";

INSERT IGNORE INTO service ( subject, method, resource, restricted ) VALUES
( 'country', 'GET', 0, 0 ),
( 'country', 'GET', 1, 0 ),
( 'image', 'DELETE', 1, 1 ),
( 'image', 'GET', 0, 0 ),
( 'image', 'GET', 1, 0 ),
( 'image', 'PATCH', 1, 1 ),
( 'image', 'POST', 0, 1 );
