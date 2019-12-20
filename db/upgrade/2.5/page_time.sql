SELECT "Creating new page_time table" AS "";

CREATE TABLE IF NOT EXISTS page_time (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  response_id INT UNSIGNED NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  datetime DATETIME NULL DEFAULT NULL,
  microtime DOUBLE UNSIGNED NULL DEFAULT NULL,
  time DOUBLE UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  INDEX fk_response_id (response_id ASC),
  INDEX fk_page_id (page_id ASC),
  UNIQUE INDEX uq_response_id_page_id (response_id ASC, page_id ASC),
  CONSTRAINT fk_page_time_response_id
    FOREIGN KEY (response_id)
    REFERENCES response (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_page_time_page_id
    FOREIGN KEY (page_id)
    REFERENCES page (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
