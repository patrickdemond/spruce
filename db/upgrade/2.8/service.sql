SELECT "Adding new services" AS "";

INSERT IGNORE INTO service ( subject, method, resource, restricted ) VALUES
( 'country', 'GET', 0, 0 ),
( 'country', 'GET', 1, 0 ),
( 'image', 'DELETE', 1, 1 ),
( 'image', 'GET', 0, 0 ),
( 'image', 'GET', 1, 0 ),
( 'image', 'PATCH', 1, 1 ),
( 'image', 'POST', 0, 1 ),
( 'proxy', 'GET', 0, 1 ),
( 'proxy', 'GET', 1, 1 ),
( 'proxy', 'PATCH', 1, 1 ),
( 'proxy', 'POST', 0, 1 ),
( 'proxy_type', 'DELETE', 1, 1 ),
( 'proxy_type', 'GET', 0, 0 ),
( 'proxy_type', 'GET', 1, 0 ),
( 'proxy_type', 'PATCH', 1, 1 ),
( 'proxy_type', 'POST', 0, 1 ),
( 'qnaire_proxy_type_trigger', 'DELETE', 1, 1 ),
( 'qnaire_proxy_type_trigger', 'GET', 0, 1 ),
( 'qnaire_proxy_type_trigger', 'GET', 1, 1 ),
( 'qnaire_proxy_type_trigger', 'PATCH', 1, 1 ),
( 'qnaire_proxy_type_trigger', 'POST', 0, 1 );
