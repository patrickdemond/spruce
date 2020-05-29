SELECT "Creating new module_average_time table" AS "";

CREATE TABLE IF NOT EXISTS module_average_time (
  module_id INT UNSIGNED NOT NULL,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  time FLOAT NULL DEFAULT NULL,
  PRIMARY KEY (module_id),
  INDEX fk_module_id (module_id ASC),
  CONSTRAINT fk_module_average_time_module_id
    FOREIGN KEY (module_id)
    REFERENCES module (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

SELECT "Calculating average time for all modules" AS "";

INSERT IGNORE INTO module_average_time( module_id, time )
SELECT module.id, ROUND( SUM( time ) / COUNT( DISTINCT page_time.response_id ) )
FROM patrick_pine.module
LEFT JOIN patrick_pine.page ON module.id = page.module_id
LEFT JOIN patrick_pine.page_time ON page.id = page_time.page_id
WHERE IFNULL( page_time.time, 0 ) <= IFNULL( page.max_time, 0 )
GROUP BY module.id;
