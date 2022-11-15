SELECT "Creating new indicator table" AS "";

CREATE TABLE IF NOT EXISTS indicator (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  lookup_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  INDEX fk_lookup_id (lookup_id ASC),
  UNIQUE INDEX uq_lookup_id_name (lookup_id ASC, name ASC),
  CONSTRAINT fk_lookup_id2
    FOREIGN KEY (lookup_id)
    REFERENCES lookup (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
