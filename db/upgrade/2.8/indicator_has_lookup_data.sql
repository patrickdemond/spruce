SELECT "Creating new indicator_has_lookup_data table" AS "";

CREATE TABLE IF NOT EXISTS indicator_has_lookup_data (
  indicator_id INT UNSIGNED NOT NULL,
  lookup_data_id INT UNSIGNED NOT NULL,
  update_timestamp VARCHAR(45) NULL,
  create_timestamp VARCHAR(45) NULL,
  PRIMARY KEY (indicator_id, lookup_data_id),
  INDEX fk_lookup_data_id (lookup_data_id ASC),
  INDEX fk_indicator_id (indicator_id ASC),
  CONSTRAINT fk_indicator_has_lookup_data_indicator_id
    FOREIGN KEY (indicator_id)
    REFERENCES indicator (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_indicator_has_lookup_data_lookup_data_id
    FOREIGN KEY (lookup_data_id)
    REFERENCES lookup_data (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
