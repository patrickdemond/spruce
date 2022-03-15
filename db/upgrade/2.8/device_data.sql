SELECT "Creating new device_data table" AS "";

CREATE TABLE IF NOT EXISTS device_data (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  device_id INT(10) UNSIGNED NOT NULL,
  name VARCHAR(45) NOT NULL,
  code VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  INDEX fk_device_id (device_id ASC),
  INDEX uq_device_id_name (device_id ASC, name ASC),
  CONSTRAINT fk_device_data_device_id
    FOREIGN KEY (device_id)
    REFERENCES device (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
