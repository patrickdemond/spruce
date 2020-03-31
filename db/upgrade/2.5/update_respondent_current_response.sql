SELECT "Creating new update_respondent_current_response procedure" AS "";

DROP procedure IF EXISTS update_respondent_current_response;

DELIMITER $$

CREATE PROCEDURE update_respondent_current_response (IN proc_respondent_id INT(10) UNSIGNED)
BEGIN
  REPLACE INTO respondent_current_response( respondent_id, response_id )
  SELECT respondent.id, response.id
  FROM respondent
  LEFT JOIN response ON respondent.id = response.respondent_id
  AND response.rank <=> (
    SELECT rank
    FROM response
    WHERE respondent.id = response.respondent_id
    GROUP BY response.respondent_id
    LIMIT 1
  )
  WHERE respondent.id = proc_respondent_id;
END$$

DELIMITER ;
