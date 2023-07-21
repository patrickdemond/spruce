SELECT "Adding new services" AS "";

DELETE FROM service WHERE subject = "response_device";

INSERT IGNORE INTO service ( subject, method, resource, restricted ) VALUES
( 'answer_device', 'GET', 0, 0 ),
( 'answer_device', 'GET', 1, 0 ),
( 'answer_device', 'PATCH', 1, 1 ),
( 'response', 'POST', 0, 1 );
