SELECT "Creating new qnaire_average_time table" AS "";

CREATE TABLE IF NOT EXISTS qnaire_average_time (
  qnaire_id INT UNSIGNED NOT NULL,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  time FLOAT NULL DEFAULT NULL,
  PRIMARY KEY (qnaire_id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  CONSTRAINT fk_qnaire_average_time_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

SELECT "Calculating average time for all qnaires" AS "";

INSERT IGNORE INTO qnaire_average_time( qnaire_id, time )
SELECT qnaire.id, SUM( time ) / COUNT( DISTINCT response.id )
FROM qnaire
JOIN respondent ON qnaire.id = respondent.qnaire_id
JOIN response ON respondent.id = response.respondent_id
JOIN page_time ON response.id = page_time.response_id
JOIN page ON page_time.page_id = page.id
WHERE IFNULL( page_time.time, 0 ) <= page.max_time
AND response.submitted = true
GROUP BY qnaire.id;
