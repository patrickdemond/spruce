SELECT "Creating new page_average_time table" AS "";

CREATE TABLE IF NOT EXISTS page_average_time (
  page_id INT UNSIGNED NOT NULL,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  time FLOAT NULL DEFAULT NULL,
  PRIMARY KEY (page_id),
  INDEX fk_page_id (page_id ASC),
  CONSTRAINT fk_page_average_time_page_id
    FOREIGN KEY (page_id)
    REFERENCES page (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

SELECT "Calculating average time for all pages" AS "";

INSERT IGNORE INTO page_average_time( page_id, time )
SELECT page.id, ROUND( AVG( time ) )
FROM page
LEFT JOIN page_time ON page.id = page_time.page_id AND IFNULL( page_time.time, 0 ) <= IFNULL( page.max_time, 0 )
GROUP BY page.id;
