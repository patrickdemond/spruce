SELECT "Creating new lookup_item table" AS "";

CREATE TABLE IF NOT EXISTS lookup_item (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  lookup_id INT UNSIGNED NOT NULL,
  identifier VARCHAR(45) NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_lookup_id (lookup_id ASC),
  UNIQUE INDEX uq_lookup_id_identifier (lookup_id ASC, identifier ASC),
  CONSTRAINT fk_lookup_item_lookup_id
    FOREIGN KEY (lookup_id)
    REFERENCES lookup (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
