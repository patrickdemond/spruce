SELECT "Creating new response_device table" AS "";

CREATE TABLE IF NOT EXISTS response_device (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  response_id INT(10) UNSIGNED NOT NULL,
  device_id INT(10) UNSIGNED NOT NULL,
  uuid VARCHAR(45) NOT NULL,
  status ENUM('in progress', 'failed', 'completed') NOT NULL DEFAULT 'in progress',
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX fk_response_id (response_id ASC),
  INDEX fk_device_id (device_id ASC),
  UNIQUE INDEX uq_uuid (uuid ASC),
  UNIQUE INDEX uq_response_id_device_id (response_id ASC, device_id ASC),
  CONSTRAINT fk_response_device_response_id
    FOREIGN KEY (response_id)
    REFERENCES response (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_response_device_device_id
    FOREIGN KEY (device_id)
    REFERENCES device (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
